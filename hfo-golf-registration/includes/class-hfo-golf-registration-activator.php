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

		self::sync_meal_coupon_role_capabilities();
	}

	/**
	 * Synchronizes the meal coupon capability with the configured additional roles.
	 *
	 * @param array|null $allowed_roles Optional sanitized role slugs. Defaults to the saved option.
	 * @return void
	 */
	public static function sync_meal_coupon_role_capabilities( $allowed_roles = null ) {
		$wp_roles = wp_roles();

		if ( ! $wp_roles ) {
			return;
		}

		if ( null === $allowed_roles ) {
			$allowed_roles = get_option( 'hfo_golf_meal_coupon_allowed_roles', array() );
		}

		if ( ! is_array( $allowed_roles ) ) {
			$allowed_roles = array();
		}

		$allowed_roles   = array_map( 'sanitize_key', $allowed_roles );
		$protected_roles = array( 'administrator', 'hfo_meal_coupon_manager' );
		$roles_to_allow  = array_unique( array_merge( $allowed_roles, $protected_roles ) );

		foreach ( $wp_roles->roles as $role_slug => $role_details ) {
			$role = get_role( $role_slug );

			if ( ! $role ) {
				continue;
			}

			if ( in_array( $role_slug, $roles_to_allow, true ) ) {
				$role->add_cap( 'manage_hfo_meal_coupons' );
				continue;
			}

			$role->remove_cap( 'manage_hfo_meal_coupons' );
		}
	}
}
