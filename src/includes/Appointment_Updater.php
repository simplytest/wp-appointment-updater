<?php

namespace SimplyTest\AppointmentUpdater\includes;

use Requests_Auth_Basic;
use Requests_Exception;
use Requests_Exception_HTTP;
use Requests_Hooks;
use Requests_Session;
use SimplyTest\AppointmentUpdater\includes\Appointment_Updater_Helpers;
use SimplyTest\AppointmentUpdater\admin\Appointment_Updater_Admin;



class Appointment_Updater {

    /** Used for the transient name in the cache.*/
    private const CACHE_KEY = 'APPOINTMENTS_UPDATER_CACHE_QUERY_RESULTS';

    /** Refers to a single instance of this class. */
    private static $instance = null;

    /** Requests session handler for persistent requests and default parameters. */
    protected static $request_session = null;

    /** JavaScript handle for registering, enqueueing and localizing the script. */
    private const UPDATER_SCRIPT_HANDLE = 'appointment-updater-js-handle';

    /**
     * Creates or returns a singletone instance of this class.
     * @return Appointment_Updater A single instance of this class.
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new Appointment_Updater();
        }
        return self::$instance;
    }

    // No direct constructor call allowed.
    private function __construct() 
    {  
        if ( isset ( static::$instance ) ) {
            wp_die('This is a singleton class and you cannot create a second instance.');  // don't die?
        }
        self::init_session_handler();
    }

    public static function register() {
        if ( !shortcode_exists( 'show_training' ) )
            add_shortcode('show_training', array(self::$instance, 'run')); 
        add_action( 'clear_cache', array( self::$instance, 'clear_cache' ) );
    }
    


    /** Clears the cache containing next appointments, so they will be fetched again before expiration. */
    public static function clear_cache() {
        delete_transient( self::CACHE_KEY );
        self::init_session_handler();  // Re-initialize the session handler with new options, i.e. base_url .
    }

    private static function init_session_handler() {
        $base_url = Appointment_Updater_Admin::get_option_for( 'base_url' );
        $headers = [ 'Content-Type' => 'application/json' ];
        // We may use HTTP Basic Auth for Authentication over HTTPS
        $basic_auth = new Requests_Auth_Basic( [Appointment_Updater_Admin::get_option_for( 'consumer_key' ), Appointment_Updater_Admin::get_option_for( 'consumer_secret' )] );
        // We'll use the hook system to register basic authentication for multiple curl request and fsock.
        // Second is needed only if curl transport not available.
        $hooks = new Requests_Hooks();
        $basic_auth->register( $hooks );  // Register for a single request.

        // Register for multiple requests.
        // Set cURL options before adding the request to a cUrl multi handle.
        $hooks->register( 'curl.before_multi_add', function( &$curl_subhandle ) use( &$basic_auth ) {
            $basic_auth->curl_before_send( $curl_subhandle );
        });

        $options = [  
            'auth' => $basic_auth,
            'hooks' => $hooks,
            'connect_timeout' => 30,  // How long should we wait while trying to connect?
            'timeout' => 30,  // How long should we wait for a response?
        ];
        self::$request_session = new Requests_Session( 
            $base_url,  // Base URL
            $headers,  // Headers
            [],  // Empty Data for requests
            $options,  // Options of this session each request will share
        );
    }


    public static function run() {
        $next_appointments = get_transient( self::CACHE_KEY );
        if ( false === $next_appointments ) {
            // Not in the cache. Request data.
            $products = self::get_products( 18 );
            $variations = self::get_variations_of_all_products( $products );
            set_transient( self::CACHE_KEY, $variations, 2 * DAY_IN_SECONDS );
        }
        
        if ( empty( $next_appointments ) ) {
            return '';  // Empty content.
        } 
        else {
            $show_all = Appointment_Updater_Admin::get_option_for( 'appointments_dropdown' ) !== 'next-three-appointments' ;

            wp_enqueue_script( self::UPDATER_SCRIPT_HANDLE, plugins_url( '/../js/appointment-updater-helpers.js', __FILE__ ), array( 'jquery' ), '1.0.2' );
            wp_add_inline_script( self::UPDATER_SCRIPT_HANDLE, 
            'const SCRIPT_DATA = {' .
                 'VARIATIONS : ' . json_encode( $next_appointments ) . ',' .
                 'SHOW_ALL : ' . json_encode($show_all)
            . '}',
            'before' );
            
            $content = "<div id=\"next-appointments\"  class=\"wrap\">";
            $content .= "</div>";
            return $content;
        } 
    }

    /**
     * Retrieve $num_of_products products from a WooCommerce site.
     * @param int $num_of_products The number of products to retrieve. Maximum 100.
     * @return array An associative array with the products.
     */
    private static function get_products( int $num_of_products ) {
        try {
            $url = self::build_api_endpoint( 'products' );
            $query_params = '?per_page=' . $num_of_products;
            $response = self::$request_session->get( $url . $query_params );
            if ($response->status_code != 200) throw new Requests_Exception_HTTP("Status code != 200");
            return json_decode( $response->body, true );  // See phpdoc for JSON Tree depth if array is empty. Only works with UTF-8 strings
        }
        catch (Requests_Exception $ex) {
            DEBUG::LOG_MSG($ex->getMessage());
            return [];
        }
        catch (Requests_Exception_HTTP $ex) {
            DEBUG::LOG_MSG($ex->getMessage());
            return [];
        }
        
    }

    /**
     * Retrieve all variations for each product with a multiple request.
     * On each request completeness, the variations of each product are
     * cleaned and filtered, so this function already takes care of that.
     * Returns an associative array consisting of product id, training name and variations array.
     * @param array $products Associative array containing all products to retrieve variations from.
     * @return array An associative array of product id, training name and variations array.
     */
    private static function get_variations_of_all_products( $products ) { 
        if ( is_null($products) || empty( $products ) ) return [];

        $requests = [];
        foreach ( $products as $product ) {
            $variation_url_per_prod = self::build_api_endpoint('products/' . $product['id'] . '/variations');
            $requests [$product['id'] . '_x_' . $product['name']] =  [  // e.g. product_id=12345, product_name="BDD" => request_id will be 12345_x_BDD. See request_complete_callback
                'url' => $variation_url_per_prod,
            ];
        }

        try {
            $responses = self::$request_session->request_multiple( $requests, ['complete' => [self::$instance, 'request_complete_callback']]);
            return $responses;
            // Can contain empty arrays, as all variations are cleaned per request (and thus removed if they contain individual appointments)
            $responses = array_filter( $responses, function( $per_request_variation ) {
                return !empty( $per_request_variation );
            });

            // Flatten the responses
            $_res = [];
            foreach ( $responses as $variations ) {
                foreach ( $variations as $variation ) {
                    $_res[]=$variation;
                }
            }
            return $_res;

        } catch (Requests_Exception $e) {
            DEBUG::LOG_MSG( $e->getMessage() );
            return array();
        }
    }

    /** A callback for when a request from a multiple request is completed.
     * Must take two parameters, a \WpOrg\Requests\Response/ | \WpOrg\Requests\Exception reference 
     * and the ID from the requests array.
     * This callback converts the whole request (by reference) to an associative array of its body,
     * before it's further modified by Appointment_Updater_Helper methods.
     * See @method 
     * @method Appointment_Updater_Helper::clean_variation
     * @param mixed &$req The request reference.
     * @param mixed $id The ID from the requests array
     * @return void
     */
    public static function request_complete_callback( &$req, $request_id ) {
        if ($req instanceof Requests_Exception || $req->status_code != 200) {
            return [];
        }
        return $req->body;
        $split_request_id = explode( '_x_', $request_id );  // Now we can use the product id and the original product name, as product name is no longer present in the request.
        $product_id = $split_request_id[0];
        $product_name = $split_request_id[1];
        $product_variations = json_decode( $req->body, true );  // Array containing all variations of a product
        $req = Appointment_Updater_Helpers::clean_product_variations( $product_variations, $product_id, $product_name );
    }

    /** Builds a full API endpoint for the WooCommerce API without a base URL.
     * The base URL should be taken care of by the caller.
     * @param string $short_endpoint The short form of the endpoint, e.g. 'products'
     * @return string Full API Endpoint for WooCommerce API.
    */
    private static function build_api_endpoint( $short_endpoint ) {
        return trailingslashit( '/wp-json/wc/v3/' ) . trim( $short_endpoint );
    }
}
?>