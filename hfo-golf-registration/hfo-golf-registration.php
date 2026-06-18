<?php
/**
 * Plugin Name: HFO Golf Registration
 * Plugin URI:  https://github.com/Cleversupport/hfo-golf-registration-plugin
 * Description: Base plugin structure for HFO golf events and registrations.
 * Version:     0.1.29
 * Author:      HFO
 * Text Domain: hfo-golf-registration
 * Domain Path: /languages
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HFO_GOLF_REGISTRATION_VERSION', '0.1.29' );
define( 'HFO_GOLF_REGISTRATION_FILE', __FILE__ );
define( 'HFO_GOLF_REGISTRATION_PATH', plugin_dir_path( __FILE__ ) );

require_once HFO_GOLF_REGISTRATION_PATH . 'includes/post-types/class-hfo-golf-event-post-type.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/post-types/class-hfo-golf-registration-post-type.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/admin/class-hfo-golf-event-meta-boxes.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/admin/class-hfo-golf-registration-meta-boxes.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/admin/class-hfo-golf-registration-settings.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/woocommerce/class-hfo-golf-registration-checkout-handler.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/class-hfo-golf-registration-activator.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/class-hfo-golf-registration-deactivator.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/class-hfo-golf-registration-github-updater.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/class-hfo-golf-registration-frontend.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/frontend/class-hfo-golf-registration-form-shortcode.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/frontend/class-hfo-golf-meal-coupon-manager-shortcode.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/class-hfo-golf-registration-jetformbuilder.php';
require_once HFO_GOLF_REGISTRATION_PATH . 'includes/class-hfo-golf-registration.php';

register_activation_hook( __FILE__, array( 'HFO_Golf_Registration_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HFO_Golf_Registration_Deactivator', 'deactivate' ) );

/**
 * Starts the plugin.
 *
 * @return void
 */
function hfo_golf_registration_run() {
	$plugin = new HFO_Golf_Registration();
	$plugin->run();
}
hfo_golf_registration_run();
