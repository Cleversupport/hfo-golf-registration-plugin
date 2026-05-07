<?php
/**
 * Plugin activation tasks.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
class HFO_Golf_Registration_Activator {

	/**
	 * Registers rewrite rules for custom post types and flushes rewrites.
	 *
	 * @return void
	 */
	public static function activate() {
		HFO_Golf_Event_Post_Type::register();
		HFO_Golf_Registration_Post_Type::register();

		flush_rewrite_rules();
	}
}
