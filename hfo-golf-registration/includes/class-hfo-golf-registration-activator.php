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
	 * Capability required to manage HFO meal coupons.
	 */
	const MEAL_COUPON_CAPABILITY = 'manage_hfo_meal_coupons';

	/**
	 * Role key for meal coupon managers.
	 */
	const MEAL_COUPON_MANAGER_ROLE = 'hfo_meal_coupon_manager';

	/**
	 * Registers plugin roles/capabilities, rewrite rules, and flushes rewrites.
	 *
	 * @return void
	 */
	public static function activate() {
		self::register_roles_and_capabilities();

		if ( defined( 'HFO_GOLF_REGISTRATION_VERSION' ) ) {
			update_option( 'hfo_golf_registration_installed_version', HFO_GOLF_REGISTRATION_VERSION );
		}

		HFO_Golf_Event_Post_Type::register();
		HFO_Golf_Registration_Post_Type::register();

		flush_rewrite_rules();
	}

	/**
	 * Registers plugin roles and capabilities.
	 *
	 * @return void
	 */
	public static function register_roles_and_capabilities() {
		$meal_coupon_manager = get_role( self::MEAL_COUPON_MANAGER_ROLE );

		if ( ! $meal_coupon_manager ) {
			add_role(
				self::MEAL_COUPON_MANAGER_ROLE,
				__( 'HFO Meal Coupon Manager', 'hfo-golf-registration' ),
				array(
					'read'                       => true,
					self::MEAL_COUPON_CAPABILITY => true,
				)
			);

			$meal_coupon_manager = get_role( self::MEAL_COUPON_MANAGER_ROLE );
		}

		if ( $meal_coupon_manager ) {
			$meal_coupon_manager->add_cap( 'read' );
			$meal_coupon_manager->add_cap( self::MEAL_COUPON_CAPABILITY );
		}

		self::add_meal_coupon_capability_to_administrators();
	}

	/**
	 * Allows administrators to manage HFO meal coupons.
	 *
	 * @return void
	 */
	private static function add_meal_coupon_capability_to_administrators() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( self::MEAL_COUPON_CAPABILITY );
		}
	}
}
