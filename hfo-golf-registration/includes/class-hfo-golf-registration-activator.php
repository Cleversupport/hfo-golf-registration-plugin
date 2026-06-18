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
		self::register_roles_and_capabilities();
		update_option( 'hfo_golf_registration_installed_version', HFO_GOLF_REGISTRATION_VERSION );

		flush_rewrite_rules();
	}

	/**
	 * Registers plugin roles and capabilities.
	 *
	 * Safe to run repeatedly so updates can provision capabilities for existing installs.
	 *
	 * @return void
	 */
	public static function register_roles_and_capabilities() {
		$role = get_role( 'hfo_meal_coupon_manager' );

		if ( ! $role ) {
			$role = add_role(
				'hfo_meal_coupon_manager',
				__( 'Meal Coupon Manager', 'hfo-golf-registration' ),
				array(
					'read'                    => true,
					'manage_hfo_meal_coupons' => true,
				)
			);
		}

		if ( $role ) {
			$role->add_cap( 'read' );
			$role->add_cap( 'manage_hfo_meal_coupons' );
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_hfo_meal_coupons' );
		}
	}
}
