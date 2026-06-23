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
 * Normalizes supported WooCommerce hook payloads to an order ID.
 *
 * @param mixed $order_or_order_id Order ID, WC_Order object, or unexpected value.
 * @return int WooCommerce order ID, or 0 when unavailable.
 */
function hfo_golf_normalize_order_id( $order_or_order_id ) {
	if ( is_numeric( $order_or_order_id ) ) {
		return absint( $order_or_order_id );
	}

	if ( is_object( $order_or_order_id ) && method_exists( $order_or_order_id, 'get_id' ) ) {
		return absint( $order_or_order_id->get_id() );
	}

	return 0;
}

/**
 * Gets the related Golf Event ID for a registration using the checkout relationship logic.
 *
 * @param int $registration_id Registration post ID.
 * @return int Resolved event post ID, or 0 when unavailable.
 */
function hfo_golf_get_event_id_from_registration( $registration_id ) {
	$registration_id = absint( $registration_id );

	if ( ! $registration_id ) {
		return 0;
	}

	$event_id = absint( get_post_meta( $registration_id, 'related_event', true ) );

	if ( $event_id ) {
		return $event_id;
	}

	return absint( get_post_meta( $registration_id, 'hfo_golf_event_id', true ) );
}

/**
 * Resolves a golf event ID for an order, falling back through the linked registration.
 *
 * @param int $order_id WooCommerce order ID.
 * @return int Resolved event post ID, or 0 when unavailable.
 */
function hfo_golf_resolve_event_id_for_order( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id ) {
		return 0;
	}

	$event_id = absint( get_post_meta( $order_id, 'hfo_golf_event_id', true ) );

	if ( $event_id ) {
		return $event_id;
	}

	$registration_id = absint( get_post_meta( $order_id, 'hfo_golf_registration_id', true ) );

	if ( ! $registration_id ) {
		return 0;
	}

	$event_id = hfo_golf_get_event_id_from_registration( $registration_id );

	if ( $event_id ) {
		update_post_meta( $order_id, 'hfo_golf_event_id', $event_id );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'HFO event email: event_id resolved from registration_id.' );
		}
	}

	return $event_id;
}

/**
 * Sends the configured HFO golf event email for a WooCommerce order.
 *
 * @param int $order_id WooCommerce order ID.
 * @return bool Whether an email was sent successfully.
 */
function send_hfo_golf_event_email( $order_id ) {
	try {
		$order_id = hfo_golf_normalize_order_id( $order_id );

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

		$event_id = hfo_golf_resolve_event_id_for_order( $order_id );

		if ( empty( $event_id ) ) {
			$order->update_meta_data( '_hfo_event_email_status', 'skipped_no_event' );
			$order->update_meta_data( '_hfo_event_email_error', __( 'Could not resolve event ID from order or registration.', 'hfo-golf-registration' ) );

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( __( 'Event email skipped: could not resolve golf event from order or registration.', 'hfo-golf-registration' ) );
			}

			$order->save_meta_data();
			return false;
		}

		$order->update_meta_data( 'hfo_golf_event_id', $event_id );

		$email = method_exists( $order, 'get_billing_email' ) ? sanitize_email( $order->get_billing_email() ) : '';

		if ( ! is_email( $email ) ) {
			$order->update_meta_data( '_hfo_event_email_status', 'skipped_invalid_email' );
			$order->update_meta_data( '_hfo_event_email_error', __( 'Missing or invalid billing email.', 'hfo-golf-registration' ) );

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( __( 'Event email skipped: missing or invalid billing email.', 'hfo-golf-registration' ) );
			}

			$order->save_meta_data();
			return false;
		}

		if ( '1' !== (string) get_post_meta( $event_id, 'hfo_event_email_enabled', true ) ) {
			$order->update_meta_data( '_hfo_event_email_status', 'skipped_disabled' );
			$order->update_meta_data( '_hfo_event_email_error', __( 'Event email is disabled for this event.', 'hfo-golf-registration' ) );

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( __( 'Event email skipped: event email is disabled for this event.', 'hfo-golf-registration' ) );
			}

			$order->save_meta_data();
			return false;
		}

		$subject = (string) get_post_meta( $event_id, 'hfo_event_email_subject', true );
		$body    = (string) get_post_meta( $event_id, 'hfo_event_email_body', true );

		if ( '' === trim( $subject ) || '' === trim( wp_strip_all_tags( $body ) ) ) {
			$order->update_meta_data( '_hfo_event_email_status', 'skipped_no_template' );
			$order->update_meta_data( '_hfo_event_email_error', __( 'Event email subject or body is empty.', 'hfo-golf-registration' ) );

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( __( 'Event email skipped: subject or body is empty.', 'hfo-golf-registration' ) );
			}

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

		if ( method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note( sprintf( /* translators: %s: recipient email address. */ __( 'Event email sent to %s.', 'hfo-golf-registration' ), $email ) );
		}

		$order->save_meta_data();

		return true;
	} catch ( Throwable $e ) {
		if ( isset( $order ) && $order && method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_hfo_event_email_status', 'failed' );
			$order->update_meta_data( '_hfo_event_email_error', sanitize_text_field( $e->getMessage() ) );

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( sprintf( /* translators: %s: error message. */ __( 'Event email failed: %s', 'hfo-golf-registration' ), sanitize_text_field( $e->getMessage() ) ) );
			}

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
		$order_id = hfo_golf_normalize_order_id( $order_id );

		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! method_exists( $order, 'get_meta' ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return false;
		}

		if ( 'sent' === (string) $order->get_meta( '_hfo_sponsor_email_status', true ) ) {
			return true;
		}

		if ( ! hfo_golf_order_is_sponsor_type( $order ) ) {
			$order->update_meta_data( '_hfo_sponsor_email_status', 'skipped_not_sponsor' );
			$order->update_meta_data( '_hfo_sponsor_email_error', __( 'Order does not contain a sponsor checkout item.', 'hfo-golf-registration' ) );

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( __( 'Sponsor email skipped: order is not a sponsor type.', 'hfo-golf-registration' ) );
			}

			$order->save_meta_data();
			return false;
		}

		$email = method_exists( $order, 'get_billing_email' ) ? sanitize_email( $order->get_billing_email() ) : '';

		if ( ! is_email( $email ) ) {
			$order->update_meta_data( '_hfo_sponsor_email_status', 'failed' );
			$order->update_meta_data( '_hfo_sponsor_email_error', __( 'Missing or invalid billing email.', 'hfo-golf-registration' ) );

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( __( 'Sponsor email failed: missing or invalid billing email.', 'hfo-golf-registration' ) );
			}

			$order->save_meta_data();
			return false;
		}

		$subject = (string) get_option( 'hfo_sponsor_email_subject', '' );
		$body    = (string) get_option( 'hfo_sponsor_email_body', '' );

		if ( '' === trim( $subject ) || '' === trim( wp_strip_all_tags( $body ) ) ) {
			$order->update_meta_data( '_hfo_sponsor_email_status', 'skipped_no_template' );
			$order->update_meta_data( '_hfo_sponsor_email_error', __( 'Sponsor email subject or body is empty.', 'hfo-golf-registration' ) );

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( __( 'Sponsor email skipped: subject or body is empty.', 'hfo-golf-registration' ) );
			}

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

		$order->update_meta_data( '_hfo_sponsor_email_status', 'sent' );
		$order->delete_meta_data( '_hfo_sponsor_email_error' );

		if ( method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note( sprintf( /* translators: %s: recipient email address. */ __( 'Sponsor email sent to %s.', 'hfo-golf-registration' ), $email ) );
		}

		$order->save_meta_data();

		return true;
	} catch ( Throwable $e ) {
		if ( isset( $order ) && $order && method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_hfo_sponsor_email_status', 'failed' );
			$order->update_meta_data( '_hfo_sponsor_email_error', sanitize_text_field( $e->getMessage() ) );

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( sprintf( /* translators: %s: error message. */ __( 'Sponsor email failed: %s', 'hfo-golf-registration' ), sanitize_text_field( $e->getMessage() ) ) );
			}

			$order->save_meta_data();
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'HFO golf sponsor email failed: ' . $e->getMessage() );
		}

		return false;
	}
}

/**
 * Safely sends all HFO golf checkout emails without interrupting checkout.
 *
 * @param mixed $order_or_order_id Order ID, WC_Order object, or unexpected value.
 * @param mixed ...$unused Additional hook arguments intentionally ignored.
 */
function send_hfo_golf_checkout_emails( $order_or_order_id = null, ...$unused ) {
	$order_id = hfo_golf_normalize_order_id( $order_or_order_id );

	if ( ! $order_id ) {
		return;
	}

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

add_action( 'woocommerce_order_status_processing', 'send_hfo_golf_checkout_emails', 20, 1 );
add_action( 'woocommerce_checkout_order_processed', 'send_hfo_golf_checkout_emails', 20, 1 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'send_hfo_golf_checkout_emails', 20, 1 );
add_action( 'woocommerce_payment_complete', 'send_hfo_golf_checkout_emails', 20, 1 );
