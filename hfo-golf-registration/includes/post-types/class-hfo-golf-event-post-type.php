<?php
/**
 * Golf Event custom post type registration.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the golf_event custom post type.
 */
class HFO_Golf_Event_Post_Type {

	/**
	 * Custom post type key.
	 *
	 * @var string
	 */
	const POST_TYPE = 'golf_event';

	/**
	 * Registers the custom post type with WordPress.
	 *
	 * @return void
	 */
	public static function register() {
		$post_type = sanitize_key( self::POST_TYPE );
		$slug      = sanitize_title( 'golf-events' );

		$labels = array(
			'name'                  => _x( 'Golf Events', 'Post type general name', 'hfo-golf-registration' ),
			'singular_name'         => _x( 'Golf Event', 'Post type singular name', 'hfo-golf-registration' ),
			'menu_name'             => _x( 'Golf Events', 'Admin menu text', 'hfo-golf-registration' ),
			'name_admin_bar'        => _x( 'Golf Event', 'Add new on toolbar', 'hfo-golf-registration' ),
			'add_new'               => __( 'Add New', 'hfo-golf-registration' ),
			'add_new_item'          => __( 'Add New Golf Event', 'hfo-golf-registration' ),
			'new_item'              => __( 'New Golf Event', 'hfo-golf-registration' ),
			'edit_item'             => __( 'Edit Golf Event', 'hfo-golf-registration' ),
			'view_item'             => __( 'View Golf Event', 'hfo-golf-registration' ),
			'all_items'             => __( 'All Golf Events', 'hfo-golf-registration' ),
			'search_items'          => __( 'Search Golf Events', 'hfo-golf-registration' ),
			'parent_item_colon'     => __( 'Parent Golf Events:', 'hfo-golf-registration' ),
			'not_found'             => __( 'No golf events found.', 'hfo-golf-registration' ),
			'not_found_in_trash'    => __( 'No golf events found in Trash.', 'hfo-golf-registration' ),
			'featured_image'        => _x( 'Golf Event Cover Image', 'Overrides the featured image phrase', 'hfo-golf-registration' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the set featured image phrase', 'hfo-golf-registration' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the remove featured image phrase', 'hfo-golf-registration' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the use featured image phrase', 'hfo-golf-registration' ),
			'archives'              => _x( 'Golf Event archives', 'The post type archive label', 'hfo-golf-registration' ),
			'insert_into_item'      => _x( 'Insert into golf event', 'Overrides the insert into item phrase', 'hfo-golf-registration' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this golf event', 'Overrides the uploaded to this item phrase', 'hfo-golf-registration' ),
			'filter_items_list'     => _x( 'Filter golf events list', 'Screen reader text for the filter links', 'hfo-golf-registration' ),
			'items_list_navigation' => _x( 'Golf events list navigation', 'Screen reader text for list navigation', 'hfo-golf-registration' ),
			'items_list'            => _x( 'Golf events list', 'Screen reader text for the items list', 'hfo-golf-registration' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => $slug ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-calendar-alt',
			'show_in_rest'       => true,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		);

		register_post_type( $post_type, $args );
	}
}
