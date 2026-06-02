<?php
/**
 * Golf Registration custom post type registration.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the golf_registration custom post type.
 */
class HFO_Golf_Registration_Post_Type {

	/**
	 * Custom post type key.
	 *
	 * @var string
	 */
	const POST_TYPE = 'golf_registration';

	/**
	 * Registers the custom post type with WordPress.
	 *
	 * @return void
	 */
	public static function register() {
		$post_type = sanitize_key( self::POST_TYPE );
		$slug      = sanitize_title( 'golf-registrations' );

		$labels = array(
			'name'                  => _x( 'Golf Registrations', 'Post type general name', 'hfo-golf-registration' ),
			'singular_name'         => _x( 'Golf Registration', 'Post type singular name', 'hfo-golf-registration' ),
			'menu_name'             => _x( 'Golf Registrations', 'Admin menu text', 'hfo-golf-registration' ),
			'name_admin_bar'        => _x( 'Golf Registration', 'Add new on toolbar', 'hfo-golf-registration' ),
			'add_new'               => __( 'Add New', 'hfo-golf-registration' ),
			'add_new_item'          => __( 'Add New Golf Registration', 'hfo-golf-registration' ),
			'new_item'              => __( 'New Golf Registration', 'hfo-golf-registration' ),
			'edit_item'             => __( 'Edit Golf Registration', 'hfo-golf-registration' ),
			'view_item'             => __( 'View Golf Registration', 'hfo-golf-registration' ),
			'all_items'             => __( 'All Golf Registrations', 'hfo-golf-registration' ),
			'search_items'          => __( 'Search Golf Registrations', 'hfo-golf-registration' ),
			'parent_item_colon'     => __( 'Parent Golf Registrations:', 'hfo-golf-registration' ),
			'not_found'             => __( 'No golf registrations found.', 'hfo-golf-registration' ),
			'not_found_in_trash'    => __( 'No golf registrations found in Trash.', 'hfo-golf-registration' ),
			'featured_image'        => _x( 'Golf Registration Image', 'Overrides the featured image phrase', 'hfo-golf-registration' ),
			'set_featured_image'    => _x( 'Set registration image', 'Overrides the set featured image phrase', 'hfo-golf-registration' ),
			'remove_featured_image' => _x( 'Remove registration image', 'Overrides the remove featured image phrase', 'hfo-golf-registration' ),
			'use_featured_image'    => _x( 'Use as registration image', 'Overrides the use featured image phrase', 'hfo-golf-registration' ),
			'archives'              => _x( 'Golf Registration archives', 'The post type archive label', 'hfo-golf-registration' ),
			'insert_into_item'      => _x( 'Insert into golf registration', 'Overrides the insert into item phrase', 'hfo-golf-registration' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this golf registration', 'Overrides the uploaded to this item phrase', 'hfo-golf-registration' ),
			'filter_items_list'     => _x( 'Filter golf registrations list', 'Screen reader text for the filter links', 'hfo-golf-registration' ),
			'items_list_navigation' => _x( 'Golf registrations list navigation', 'Screen reader text for list navigation', 'hfo-golf-registration' ),
			'items_list'            => _x( 'Golf registrations list', 'Screen reader text for the items list', 'hfo-golf-registration' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => $slug ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 21,
			'menu_icon'          => 'dashicons-clipboard',
			'show_in_rest'       => false,
			'supports'           => array( 'title', 'custom-fields' ),
		);

		register_post_type( $post_type, $args );
	}

	/**
	 * Appends the WooCommerce order ID to a registration title when available.
	 *
	 * @param int $registration_id Golf registration post ID.
	 * @param int $order_id        WooCommerce order ID.
	 * @return void
	 */
	public static function append_order_id_to_title( $registration_id, $order_id ) {
		$registration_id = absint( $registration_id );
		$order_id        = absint( $order_id );

		if ( ! $registration_id || ! $order_id ) {
			return;
		}

		$post = get_post( $registration_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		$current_title = (string) $post->post_title;
		$order_suffix  = ' - ' . $order_id;

		if ( substr( $current_title, -strlen( $order_suffix ) ) === $order_suffix ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => $registration_id,
				'post_title' => $current_title . $order_suffix,
			)
		);
	}
}
