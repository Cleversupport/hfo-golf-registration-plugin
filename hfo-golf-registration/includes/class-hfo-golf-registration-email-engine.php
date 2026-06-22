<?php
/**
 * Event-driven golf registration email engine.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces supported HFO golf email placeholders in dynamic email content.
 *
 * @param string   $content Email subject or body content.
 * @param WC_Order $order   WooCommerce order object.
 * @return string
 */
function replace_hfo_email_placeholders( $content, $order ) {
	if ( ! is_scalar( $content ) || ! $order || ! method_exists( $order, 'get_meta' ) ) {
		return is_scalar( $content ) ? (string) $content : '';
	}

	$event_id = absint( $order->get_meta( 'hfo_golf_event_id', true ) );
	$event_location = '';

	if ( $event_id ) {
		$event_location_parts = array_filter(
			array(
				sanitize_text_field( get_post_meta( $event_id, 'event_venue', true ) ),
				sanitize_text_field( get_post_meta( $event_id, 'event_address', true ) ),
				sanitize_text_field( get_post_meta( $event_id, 'event_city', true ) ),
				sanitize_text_field( get_post_meta( $event_id, 'event_state', true ) ),
			),
			'strlen'
		);
		$event_location = ! empty( $event_location_parts ) ? implode( ', ', $event_location_parts ) : sanitize_text_field( get_post_meta( $event_id, 'event_location', true ) );
	}

	$replacements = array(
		'{first_name}'     => method_exists( $order, 'get_billing_first_name' ) ? sanitize_text_field( $order->get_billing_first_name() ) : '',
		'{last_name}'      => method_exists( $order, 'get_billing_last_name' ) ? sanitize_text_field( $order->get_billing_last_name() ) : '',
		'{email}'          => method_exists( $order, 'get_billing_email' ) ? sanitize_email( $order->get_billing_email() ) : '',
		'{event_name}'     => $event_id ? sanitize_text_field( get_the_title( $event_id ) ) : '',
		'{event_location}' => $event_location,
		'{event_date}'     => $event_id ? sanitize_text_field( get_post_meta( $event_id, 'event_date', true ) ) : '',
		'{order_id}'       => method_exists( $order, 'get_id' ) ? (string) absint( $order->get_id() ) : '',
	);

	return strtr( (string) $content, $replacements );
}

/**
 * Sends the configured HFO golf event email for a WooCommerce order.
 *
 * @param int $order_id WooCommerce order ID.
 * @return bool Whether an email was sent successfully.
 */
function send_hfo_golf_event_email( $order_id ) {
	try {
		$order_id = absint( $order_id );

		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! method_exists( $order, 'get_meta' ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return false;
		}

		if ( 'sent' === (string) $order->get_meta( '_hfo_event_email_status', true ) ) {
			return true;
		}

		$event_id = absint( get_post_meta( $order_id, 'hfo_golf_event_id', true ) );

		if ( empty( $event_id ) ) {
			$order->update_meta_data( '_hfo_event_email_status', 'skipped_no_event' );
			$order->save_meta_data();
			return false;
		}

		$email = method_exists( $order, 'get_billing_email' ) ? sanitize_email( $order->get_billing_email() ) : '';

		if ( ! is_email( $email ) ) {
			throw new RuntimeException( __( 'Missing or invalid billing email.', 'hfo-golf-registration' ) );
		}

		if ( '1' !== (string) get_post_meta( $event_id, 'hfo_event_email_enabled', true ) ) {
			$order->update_meta_data( '_hfo_event_email_status', 'skipped_no_template' );
			$order->save_meta_data();
			return false;
		}

		$subject = (string) get_post_meta( $event_id, 'hfo_event_email_subject', true );
		$body    = (string) get_post_meta( $event_id, 'hfo_event_email_body', true );

		if ( '' === trim( $subject ) || '' === trim( wp_strip_all_tags( $body ) ) ) {
			$order->update_meta_data( '_hfo_event_email_status', 'skipped_no_template' );
			$order->save_meta_data();
			return false;
		}

		$subject = wp_specialchars_decode( replace_hfo_email_placeholders( wp_strip_all_tags( $subject ), $order ), ENT_QUOTES );
		$body    = wpautop( replace_hfo_email_placeholders( $body, $order ) );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = (bool) wp_mail( $email, $subject, $body, $headers );

		if ( ! $sent ) {
			throw new RuntimeException( __( 'wp_mail returned false.', 'hfo-golf-registration' ) );
		}

		$order->update_meta_data( '_hfo_event_email_status', 'sent' );
		$order->delete_meta_data( '_hfo_event_email_error' );
		$order->save_meta_data();

		return true;
	} catch ( Throwable $e ) {
		if ( isset( $order ) && $order && method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_hfo_event_email_status', 'failed' );
			$order->update_meta_data( '_hfo_event_email_error', sanitize_text_field( $e->getMessage() ) );
			$order->save_meta_data();
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'HFO golf event email failed: ' . $e->getMessage() );
		}

		return false;
	}
}

/**
 * Sends the configured HFO golf sponsor email for a WooCommerce order when applicable.
 *
 * @param int $order_id WooCommerce order ID.
 * @return bool Whether an email was sent successfully.
 */
function send_hfo_golf_sponsor_email( $order_id ) {
	try {
		$order_id = absint( $order_id );

		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! method_exists( $order, 'get_meta' ) ) {
			return false;
		}

		if ( ! hfo_golf_order_is_sponsor_type( $order ) ) {
			return false;
		}

		$email = method_exists( $order, 'get_billing_email' ) ? sanitize_email( $order->get_billing_email() ) : '';

		if ( ! is_email( $email ) ) {
			throw new RuntimeException( __( 'Missing or invalid billing email.', 'hfo-golf-registration' ) );
		}

		$subject = (string) get_option( 'hfo_sponsor_email_subject', '' );
		$body    = (string) get_option( 'hfo_sponsor_email_body', '' );

		if ( '' === trim( $subject ) || '' === trim( wp_strip_all_tags( $body ) ) ) {
			return false;
		}

		$subject = wp_specialchars_decode( replace_hfo_email_placeholders( wp_strip_all_tags( $subject ), $order ), ENT_QUOTES );
		$body    = wpautop( replace_hfo_email_placeholders( $body, $order ) );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return (bool) wp_mail( $email, $subject, $body, $headers );
	} catch ( Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'HFO golf sponsor email failed: ' . $e->getMessage() );
		}

		return false;
	}
}

/**
 * Safely sends all HFO golf checkout emails without interrupting checkout.
 *
 * @param int $order_id WooCommerce order ID.
 */
function send_hfo_golf_checkout_emails( $order_id ) {
	send_hfo_golf_event_email( $order_id );
	send_hfo_golf_sponsor_email( $order_id );
}

/**
 * Determines whether an order contains a sponsor checkout item.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return bool
 */
function hfo_golf_order_is_sponsor_type( $order ) {
	if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
		return false;
	}

	foreach ( $order->get_items() as $item ) {
		if ( ! method_exists( $item, 'get_meta' ) ) {
			continue;
		}

		$item_type              = sanitize_key( (string) $item->get_meta( 'hfo_golf_item_type', true ) );
		$registration_item_type = sanitize_key( (string) $item->get_meta( 'hfo_golf_registration_item_type', true ) );

		if ( false !== strpos( $item_type, 'sponsor' ) || false !== strpos( $registration_item_type, 'sponsor' ) ) {
			return true;
		}
	}

	return false;
}

add_action( 'woocommerce_checkout_order_processed', 'send_hfo_golf_checkout_emails', 20, 1 );
