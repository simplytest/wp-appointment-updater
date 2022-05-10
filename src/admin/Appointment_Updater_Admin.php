<?php

namespace SimplyTest\AppointmentUpdater\admin;

use SimplyTest\AppointmentUpdater\includes\DEBUG;

class Appointment_Updater_Admin
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    public static $options;

    const SETTINGS_NAME = 'appointment_updater_options';
    const SETTING_SECTION_ID = 'appointment_updater_settings_section_id';
    const PAGE_TITLE = 'Appointment Updater Settings';    // Appears as tab title
    const MENU_TITLE = 'Appointment Updater';     // Appears on the sidebar of the admin dashboard
    const CAPABILITY = 'manage_options';
    const MENU_SLUG = 'appointment-updater-settings';     // Appears in the URL
    const OPTION_GROUP = 'appointment_updater_option_group';      // Option group name
   
    /** Keeps track of the admin singleton. */
    protected static $instance = null;

    /** Handle for AJAX Scripts. */
    private const AJAX_SCRIPT_HANDLE = "appointment-admin-ajax-handler";

    public static function get_instance() {
        self::$instance = self::$instance ?? new self;
        return self::$instance;
    }

    /** Constructor. Should not be called from outside directly. Use get_instance() instead. */
    private function __construct()
    {
        if ( isset ( static::$instance ) ) {
            wp_die('This is a singleton class and you cannot create a second instance.');  // don't die?
        }
        
    }

    public static function register() {
        add_action( 'admin_menu', array( self::$instance, 'add_settings_page' ) );
        add_action( 'admin_init', array( self::$instance, 'settings_init' ) );

        add_action('update_option_' . Appointment_Updater_Admin::SETTINGS_NAME, function ( $old_value,  $new_value ) {     
            $_diff = array_keys ( array_diff( $old_value, $new_value ) );
            // Consumer key, consumer secret or base URL have been changed. Clear cache and request data again.
            if ( in_array( 'consumer_key', $_diff ) || in_array( 'consumer_secret', $_diff ) || in_array( 'base_url', $_diff ) ) {
                DEBUG::LOG_MSG('sth changed');
                self::schedule_single_event( 'clear_cache', 15 );
            }
        
        }, 10, 2);

        $ajax_callback = 'clear_cache';
        add_action( 'wp_ajax_'.$ajax_callback, array(self::$instance, 'schedule_event_to_clear_cache') );

        add_action('admin_enqueue_scripts', function() use( &$ajax_callback ){
            wp_register_script(self::AJAX_SCRIPT_HANDLE, plugins_url( '/../js/appointment-updater-admin-ajax.js', __FILE__ ), array('jquery'), '1.0.2');
            $admin_ajaxObj = [
                'ajax_url' => admin_url('admin-ajax.php'), 
                'ajax_nonce' => wp_create_nonce('appointment_admin_ajax_validation'), 
                'ajax_callback' => $ajax_callback];
            wp_add_inline_script(self::AJAX_SCRIPT_HANDLE, 
            'const admin_ajaxObj = ' . json_encode($admin_ajaxObj),
            'before');
        });
    }

    public static function schedule_event_to_clear_cache() {
        check_ajax_referer( 'appointment_admin_ajax_validation', 'security' );
        $ret_val = self::schedule_single_event( 'clear_cache', 15 );
        if ( !$ret_val )
            wp_send_json_error( ['error' => 'Could not schedule event to clear cache. Try again later.'] );
        else 
            wp_send_json_success( $ret_val );
    }

    /**
     * Add options page
     */
    public static function add_settings_page()
    {
        // This page will be under "Settings"
        add_options_page(
            Appointment_Updater_Admin::PAGE_TITLE, 
            Appointment_Updater_Admin::MENU_TITLE, 
            Appointment_Updater_Admin::CAPABILITY, 
            Appointment_Updater_Admin::MENU_SLUG, 
            array( self::$instance, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public static function create_admin_page()
    {
        // Set class property
        Appointment_Updater_Admin::$options = get_option( Appointment_Updater_Admin::SETTINGS_NAME);
        $settings_page_renderer = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/Appointment_Updater_Settings_Renderer.php';
        include ( $settings_page_renderer );
    }

    /**
     * Register and add settings
     */
    public static function settings_init()
    {      
        register_setting(
            Appointment_Updater_Admin::OPTION_GROUP, // Option group
            Appointment_Updater_Admin::SETTINGS_NAME, // Option name
            array( self::$instance, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            Appointment_Updater_Admin::SETTING_SECTION_ID, // ID
            'Plugin Settings', // Title
            array( self::$instance, 'print_section_info' ), // Callback
            Appointment_Updater_Admin::MENU_SLUG // Page
        );  

        Appointment_Updater_Admin::add_field_in_settings_page( 'consumer_key', 'Consumer Key', 'consumer_key_callback' );
        Appointment_Updater_Admin::add_field_in_settings_page( 'consumer_secret', 'Consumer Secret', 'consumer_secret_callback' );
        Appointment_Updater_Admin::add_field_in_settings_page( 'base_url', 'Shop URL', 'base_url_callback' );
        Appointment_Updater_Admin::add_field_in_settings_page( 'appointments_dropdown', 'Number of appointments to show:', 'appointments_dropdown_callback' );

    }

    /**
     * Adds a field to the settings page with the specified parameters.
     * Helper function to avoid typing the same add_settings_field function from Wordpress API.
     * @param string $id Identifier for the field. Used in the 'id' attribute of tags.
     * @param string $title Title of the field shown in the page.
     * @param string $callback Callback function that fills a field with desired input.
     * @param string $page The slug-name of the settings page on which to show the section.
     * @param string $section The slug-name of the section of the settings page. 
     * @return void
     */
    private static function add_field_in_settings_page( $id, $title, $callback, $page=Appointment_Updater_Admin::MENU_SLUG, $section=Appointment_Updater_Admin::SETTING_SECTION_ID ) {
        add_settings_field( $id, $title, array( self::$instance, $callback ), $page, $section, $id );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public static function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['consumer_key'] ) )
            $new_input['consumer_key'] = sanitize_key( $input['consumer_key'] );

        if( isset( $input['consumer_secret'] ) )
            $new_input['consumer_secret'] = sanitize_key( $input['consumer_secret'] );

        if( isset( $input['base_url'] ) )
            $new_input['base_url'] = sanitize_url ( $input['base_url'] );

        if ( isset( $input['appointments_dropdown'] ) )
            $new_input['appointments_dropdown'] = $input['appointments_dropdown'];

        return $new_input;
    }

    public static function print_section_info()
    {
        echo '<div class="wrap" id="appointment-updater-plugin-settings-id"> ';
        echo '<p>Enter your settings below. Changes to credentials and URL will refetch requests to the shop.</p>';
        echo '<p>You can also click the button to request data again from the shop.</p>';
        echo '<div id="popup-id-on-clear" style="display:none;"></div>';
        submit_button('Refetch appointments', 'large', 'button-refetch-data-id');
        echo '</div>';
        wp_enqueue_script(self::AJAX_SCRIPT_HANDLE);
    }

    public static function consumer_key_callback( $id )
    {
        ?>
        <input
            type = "text"
            id = <?php echo $id?>
            name = <?php echo Appointment_Updater_Admin::SETTINGS_NAME . '[' . $id . ']';?>
            value = <?php echo esc_attr( Appointment_Updater_Admin::$options[$id] ?? '' ); ?>
        />
        <?php
        
    }

    public static function consumer_secret_callback( $id )
    {
        ?>
        <input
            type = "password"
            id = <?php echo $id?>
            name = <?php echo Appointment_Updater_Admin::SETTINGS_NAME . '[' . $id . ']';?>
            value = <?php echo esc_attr( Appointment_Updater_Admin::$options[$id]  ?? '');?> 
        />
        <?php
    }

    public static function base_url_callback( $id )
    {
        ?>
        <input
            type = "text"
            id = <?php echo $id;?>
            name = <?php echo  Appointment_Updater_Admin::SETTINGS_NAME . '[' . $id . ']';?>
            value = <?php echo esc_attr( Appointment_Updater_Admin::$options[$id]  ?? '');?>  
        />
        <?php
    }

    public static function appointments_dropdown_callback( $id ) {
        // If options[id] is set (denoted by ??) return it and compare it to next_three_appointments, if true, $selected = 'selected', otherwise ''
        $option_name = Appointment_Updater_Admin::SETTINGS_NAME . '[' . $id . ']';
        ?>
        <select name = <?php echo $option_name?> id = <?php echo $id?>>
            <?php $selected = (Appointment_Updater_Admin::$options[$id] ?? '') === 'next-three-appointments' ? 'selected' : '';?>
            <option value="next-three-appointments" <?php echo $selected; ?>>Next 3</option>

            <?php $selected = (Appointment_Updater_Admin::$options[$id] ?? '') === 'one-appointment-per-training' ? 'selected' : '';?>
            <option value="one-appointment-per-training" <?php echo $selected; ?>>One per training</option>
        </select>
        <?php
    }

    /**
     * Returns the option value set in the admin page of the plugin.
     * @param string $option_name The name for the option to retrieve.
     * @return string The value of the option.
     */
    public static function  get_option_for( $option_name ) {
        $_res = get_option(Appointment_Updater_Admin::SETTINGS_NAME);
        return $_res === false ? '' : $_res[trim ($option_name )];
    }

    /** Schedules a one-time event with the specified hook name. 
     * @param string $hook_name The registered hook name of an action to be scheduled.
     * @param int $schedule_time The time when the event should be scheduled from function call, in seconds.
     * @return bool True if event was scheduled successfully. False on failure.
    */
    public static function schedule_single_event( $hook_name, $schedule_time ) {
        // If an event is already planned, clear it. (This might happen if we're updating the admin page several times under $when_to_run seconds)
        if (wp_next_scheduled( $hook_name )) {
            wp_clear_scheduled_hook( $hook_name );
        }
        return wp_schedule_single_event( time() + $schedule_time, $hook_name );
    }
}
?>