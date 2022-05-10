<?php declare( strict_types=1 );
define ('APPOINTMENT_UPDATER_VERSION', '1.2');

/**
 * Plugin Name: Appointments Updater
 * Description: Retrieves appointments from a shop via WooCommerce API and displays them on a page using shortcode [show_training]. 
 * Author: Laurentiu Andries
 * Version: 1.2
 * Requires PHP: 7.3
 * Requires at least: 4.4
 * Author URI: https://github.com/0xbaddaddee
 */

// If this file is called directly, abort.
if ( ! defined ( 'ABSPATH' ) ) {
    wp_die('Do not call this directly!');
}

if ( is_readable( plugin_dir_path(__FILE__) . '/lib/autoload.php')) {
    require_once plugin_dir_path( __FILE__ ) . '/lib/autoload.php';
}
use SimplyTest\AppointmentUpdater\admin\Appointment_Updater_Admin;
use SimplyTest\AppointmentUpdater\includes\Appointment_Updater;

$appointment_updater = Appointment_Updater::get_instance();
$appointment_updater::register();

if ( is_admin() ) {
    $appointment_updater_admin = Appointment_Updater_Admin::get_instance();
    $appointment_updater_admin::register();
}



// Add Settings link directly below the plugin in the plugins page
add_filter('plugin_action_links_'.plugin_basename(__FILE__), function ( $links ) {
    $links []= '<a href="'.
        admin_url( 'options-general.php?page=' . Appointment_Updater_Admin::MENU_SLUG ) .
        '">' . __('Settings') .'</a>'; 
    return $links;
});




?>