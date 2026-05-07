<?php
/**
 * Main plugin bootstrap.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin hooks.
 */
class HFO_Golf_Registration {

	/**
	 * Registers WordPress hooks used by the plugin.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Registers the plugin custom post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		HFO_Golf_Event_Post_Type::register();
		HFO_Golf_Registration_Post_Type::register();
	}
}
