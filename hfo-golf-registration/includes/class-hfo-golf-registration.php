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

		$event_meta_boxes = new HFO_Golf_Event_Meta_Boxes();
		$event_meta_boxes->register_hooks();

		$registration_meta_boxes = new HFO_Golf_Registration_Meta_Boxes();
		$registration_meta_boxes->register_hooks();

		$settings = new HFO_Golf_Registration_Settings();
		$settings->register_hooks();

		$checkout_handler = new HFO_Golf_Registration_Checkout_Handler();
		$checkout_handler->register_hooks();

		$frontend = new HFO_Golf_Registration_Frontend();
		$frontend->register_hooks();

		$jetformbuilder = new HFO_Golf_Registration_JetFormBuilder();
		$jetformbuilder->register_hooks();
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
