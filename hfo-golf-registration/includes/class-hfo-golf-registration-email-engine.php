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
	$event_location          = '';
	$event_contact_name      = '';
	$event_contact_phone     = '';
	$hotel_offer_title       = '';
	$hotel_offer_description = '';
	$hotel_offer_link        = '';
	$hotel_offer_link_label  = '';
	$hotel_offer_deadline    = '';
	$hotel_offer_image       = '';
	$registration_id         = absint( $order->get_meta( 'hfo_golf_registration_id', true ) );
	$team_name               = sanitize_text_field( $order->get_meta( 'hfo_golf_team_name', true ) );

	if ( '' === $team_name && $registration_id ) {
		$team_name = sanitize_text_field( get_post_meta( $registration_id, 'hfo_golf_team_name', true ) );
	}

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
		$event_location          = ! empty( $event_location_parts ) ? implode( ', ', $event_location_parts ) : sanitize_text_field( get_post_meta( $event_id, 'event_location', true ) );
		$event_contact_name      = sanitize_text_field( get_post_meta( $event_id, 'hfo_event_contact_name', true ) );
		$event_contact_phone     = sanitize_text_field( get_post_meta( $event_id, 'hfo_event_contact_phone', true ) );
		$hotel_offer_title       = sanitize_text_field( get_post_meta( $event_id, 'hfo_event_hotel_offer_title', true ) );
		$hotel_offer_description = wp_kses_post( get_post_meta( $event_id, 'hfo_event_hotel_offer_description', true ) );
		$hotel_offer_link        = esc_url_raw( get_post_meta( $event_id, 'hfo_event_hotel_offer_link', true ) );
		$hotel_offer_link_label  = sanitize_text_field( get_post_meta( $event_id, 'hfo_event_hotel_offer_link_label', true ) );
		$hotel_offer_deadline    = sanitize_text_field( get_post_meta( $event_id, 'hfo_event_hotel_offer_deadline', true ) );
		$hotel_offer_image       = esc_url_raw( get_post_meta( $event_id, 'hfo_event_hotel_offer_image', true ) );
	}

	$replacements = array(
		'{first_name}'              => method_exists( $order, 'get_billing_first_name' ) ? sanitize_text_field( $order->get_billing_first_name() ) : '',
		'{last_name}'               => method_exists( $order, 'get_billing_last_name' ) ? sanitize_text_field( $order->get_billing_last_name() ) : '',
		'{email}'                   => method_exists( $order, 'get_billing_email' ) ? sanitize_email( $order->get_billing_email() ) : '',
		'{event_name}'              => $event_id ? sanitize_text_field( get_the_title( $event_id ) ) : '',
		'{event_location}'          => $event_location,
		'{event_date}'              => $event_id ? sanitize_text_field( get_post_meta( $event_id, 'event_date', true ) ) : '',
		'{event_contact_name}'      => $event_contact_name,
		'{event_contact_phone}'     => $event_contact_phone,
		'{order_id}'                => method_exists( $order, 'get_id' ) ? (string) absint( $order->get_id() ) : '',
		'{team_name}'               => $team_name,
		'{hotel_offer_title}'       => $hotel_offer_title,
		'{hotel_offer_description}' => $hotel_offer_description,
		'{hotel_offer_link}'        => $hotel_offer_link,
		'{hotel_offer_link_label}'  => $hotel_offer_link_label,
		'{hotel_offer_deadline}'    => $hotel_offer_deadline,
		'{hotel_offer_image}'       => $hotel_offer_image,
	);

	return strtr( (string) $content, $replacements );
}


/**
 * Checks whether a post ID is a published Golf Event.
 *
 * @param int $event_id Event post ID.
 * @return bool
 */
function hfo_golf_is_valid_published_event( $event_id ) {
	$event_id = absint( $event_id );

	if ( ! $event_id || ! class_exists( 'HFO_Golf_Event_Post_Type' ) ) {
		return false;
	}

	$post = get_post( $event_id );

	return $post && HFO_Golf_Event_Post_Type::POST_TYPE === $post->post_type && 'publish' === $post->post_status;
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

	if ( hfo_golf_is_valid_published_event( $event_id ) ) {
		return $event_id;
	}

	$event_id = absint( get_post_meta( $registration_id, 'hfo_golf_event_id', true ) );

	return hfo_golf_is_valid_published_event( $event_id ) ? $event_id : 0;
}

/**
 * Resolves a golf event ID for an order, falling back through registration and line items.
 *
 * @param int $order_id WooCommerce order ID.
 * @return int Resolved event post ID, or 0 when unavailable.
 */
function hfo_golf_resolve_event_id_for_order( $order_id ) {
	$order_id = absint( $order_id );

	if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
		return 0;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order || ! method_exists( $order, 'get_meta' ) ) {
		return 0;
	}

	$event_id = absint( $order->get_meta( 'hfo_golf_event_id', true ) );

	if ( hfo_golf_is_valid_published_event( $event_id ) ) {
		return $event_id;
	}

	$registration_id = absint( $order->get_meta( 'hfo_golf_registration_id', true ) );

	if ( $registration_id ) {
		$event_id = hfo_golf_get_event_id_from_registration( $registration_id );

		if ( hfo_golf_is_valid_published_event( $event_id ) ) {
			if ( method_exists( $order, 'update_meta_data' ) ) {
				$order->update_meta_data( 'hfo_golf_event_id', $event_id );

				if ( method_exists( $order, 'save_meta_data' ) ) {
					$order->save_meta_data();
				}
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'HFO event email: event_id resolved from registration_id.' );
			}

			return $event_id;
		}
	}

	if ( ! method_exists( $order, 'get_items' ) ) {
		return 0;
	}

	$line_item_event_meta_keys = array(
		'hfo_golf_event_id',
		'_hfo_golf_event_id',
		'hfo_golf_registration_event_id',
	);

	foreach ( $order->get_items() as $item ) {
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
			continue;
		}

		foreach ( $line_item_event_meta_keys as $meta_key ) {
			$event_id = absint( $item->get_meta( $meta_key, true ) );

			if ( ! hfo_golf_is_valid_published_event( $event_id ) ) {
				continue;
			}

			if ( method_exists( $order, 'update_meta_data' ) ) {
				$order->update_meta_data( 'hfo_golf_event_id', $event_id );
			}

			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( sprintf( __( 'Golf event ID resolved from order line item: %d.', 'hfo-golf-registration' ), $event_id ) );
			}

			if ( method_exists( $order, 'save_meta_data' ) ) {
				$order->save_meta_data();
			}

			return $event_id;
		}
	}

	return 0;
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

		if ( ! hfo_golf_is_valid_published_event( $event_id ) ) {
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

/** Returns a non-empty internal-email table row. */
function hfo_golf_internal_email_row( $label, $value, $multiline = false ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	$display = $multiline ? nl2br( esc_html( $value ) ) : esc_html( $value );
	return '<tr><th style="padding:7px 12px 7px 0;text-align:left;vertical-align:top;white-space:nowrap">' . esc_html( $label ) . '</th><td style="padding:7px 0">' . $display . '</td></tr>';
}

/** Wraps internal-email rows in an operational section. */
function hfo_golf_internal_email_section( $heading, $rows ) {
	return '' === $rows ? '' : '<h2 style="font-size:16px;margin:26px 0 8px;border-bottom:2px solid #1d4f35;padding-bottom:6px">' . esc_html( $heading ) . '</h2><table role="presentation" style="border-collapse:collapse;width:100%">' . $rows . '</table>';
}

/** Sends the event-configured, registration-type-aware internal notification. */
function send_hfo_golf_internal_organizer_email( $order_or_order_id ) {
	$order = null;
	try {
		$order_id = hfo_golf_normalize_order_id( $order_or_order_id );
		$order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		if ( ! $order || ! method_exists( $order, 'get_meta' ) || in_array( (string) $order->get_meta( '_hfo_internal_organizer_email_status', true ), array( 'sent', 'sending' ), true ) ) {
			return false;
		}
		$registration_id = absint( $order->get_meta( 'hfo_golf_registration_id', true ) );
		if ( ! $registration_id || HFO_Golf_Registration_Post_Type::POST_TYPE !== get_post_type( $registration_id ) ) {
			$order->update_meta_data( '_hfo_internal_organizer_email_status', 'skipped_no_registration' ); $order->save_meta_data(); return false;
		}
		if ( ! $order->is_paid() && ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			$order->update_meta_data( '_hfo_internal_organizer_email_status', 'skipped_unpaid' ); $order->save_meta_data(); return false;
		}
		$event_id = hfo_golf_resolve_event_id_for_order( $order_id );
		if ( '1' !== (string) get_post_meta( $event_id, 'hfo_event_internal_notification_enabled', true ) ) {
			$order->update_meta_data( '_hfo_internal_organizer_email_status', 'skipped_disabled' ); $order->save_meta_data(); return false;
		}
		$to = sanitize_email( get_post_meta( $event_id, 'hfo_event_internal_notification_to', true ) );
		if ( ! is_email( $to ) ) {
			$order->update_meta_data( '_hfo_internal_organizer_email_status', 'skipped_no_recipient' ); $order->save_meta_data(); return false;
		}
		$cc = array();
		foreach ( preg_split( '/[\s,;]+/', (string) get_post_meta( $event_id, 'hfo_event_internal_notification_cc', true ) ) as $candidate ) {
			$candidate = sanitize_email( $candidate );
			if ( is_email( $candidate ) ) { $cc[ strtolower( $candidate ) ] = $candidate; }
		}
		$type = sanitize_key( get_post_meta( $registration_id, 'registration_type', true ) );
		$labels = array( 'team' => 'Team Registration', 'individual' => 'Individual Player Registration', 'additional_guests' => 'Guest Meal Registration', 'sponsor_only' => 'Sponsor Registration' );
		if ( ! isset( $labels[ $type ] ) ) {
			$order->update_meta_data( '_hfo_internal_organizer_email_status', 'skipped_no_registration' ); $order->save_meta_data(); return false;
		}
		$get = static function ( $key ) use ( $registration_id ) { return get_post_meta( $registration_id, $key, true ); };
		$created = $order->get_date_created();
		$order_rows  = hfo_golf_internal_email_row( __( 'Order Number', 'hfo-golf-registration' ), '#' . $order->get_order_number() );
		$order_rows .= hfo_golf_internal_email_row( __( 'Registration Date', 'hfo-golf-registration' ), $created ? $created->date_i18n( get_option( 'date_format' ) ) : '' );
		$order_rows .= hfo_golf_internal_email_row( __( 'Registration Type', 'hfo-golf-registration' ), $labels[ $type ] );
		$order_rows .= hfo_golf_internal_email_row( __( 'Amount Paid', 'hfo-golf-registration' ), wp_strip_all_tags( $order->get_formatted_order_total() ) );
		$order_rows .= hfo_golf_internal_email_row( __( 'Transaction ID', 'hfo-golf-registration' ), sanitize_text_field( $order->get_transaction_id() ) );
		$full_name = trim( sanitize_text_field( $order->get_formatted_billing_full_name() ) );
		$full_name = $full_name ?: sanitize_text_field( $get( 'main_contact_name' ) );
		$address = implode( ', ', array_filter( array_map( 'sanitize_text_field', array( $order->get_billing_address_1(), $order->get_billing_city(), $order->get_billing_state(), $order->get_billing_postcode() ) ) ) );
		$purchaser  = hfo_golf_internal_email_row( __( 'Full Name', 'hfo-golf-registration' ), $full_name );
		$purchaser .= hfo_golf_internal_email_row( __( 'Email Address', 'hfo-golf-registration' ), $order->get_billing_email() ?: $get( 'main_contact_email' ) );
		$purchaser .= hfo_golf_internal_email_row( __( 'Phone Number', 'hfo-golf-registration' ), $order->get_billing_phone() ?: $get( 'main_contact_phone' ) );
		$purchaser .= hfo_golf_internal_email_row( __( 'Billing Address', 'hfo-golf-registration' ), $address );
		$specific = '';
		if ( 'team' === $type || 'individual' === $type ) {
			if ( 'team' === $type ) { $specific .= hfo_golf_internal_email_row( __( 'Team Name', 'hfo-golf-registration' ), $get( 'hfo_golf_team_name' ) ); }
			$players = 'team' === $type ? array( 'captain' => 'Team Captain', 'member_2' => 'Player 2', 'member_3' => 'Player 3', 'member_4' => 'Player 4' ) : array( 'captain' => 'Player' );
			foreach ( $players as $key => $label ) { $specific .= hfo_golf_internal_email_row( __( $label, 'hfo-golf-registration' ), $get( $key . '_name' ) ); $specific .= hfo_golf_internal_email_row( __( 'Handicap', 'hfo-golf-registration' ), $get( $key . '_handicap' ) ); }
			$specific = hfo_golf_internal_email_section( 'team' === $type ? __( 'GOLF TEAM INFORMATION', 'hfo-golf-registration' ) : __( 'PLAYER INFORMATION', 'hfo-golf-registration' ), $specific );
		} elseif ( 'sponsor_only' === $type ) {
			$specific .= hfo_golf_internal_email_row( __( 'Sponsor Name', 'hfo-golf-registration' ), $get( 'sponsor_program_name' ) );
			$specific .= hfo_golf_internal_email_row( __( 'Sponsor Level', 'hfo-golf-registration' ), $get( 'sponsorship_level' ) ? ucwords( $get( 'sponsorship_level' ) ) : '' );
			$specific .= hfo_golf_internal_email_row( __( 'Sponsor Contact Name', 'hfo-golf-registration' ), $get( 'sponsor_contact_name' ) );
			$specific .= hfo_golf_internal_email_row( __( 'Sponsor Email', 'hfo-golf-registration' ), $get( 'sponsor_email' ) );
			$specific .= hfo_golf_internal_email_row( __( 'Sponsor Phone', 'hfo-golf-registration' ), $get( 'sponsor_phone' ) );
			$specific .= hfo_golf_internal_email_row( __( 'Tee Sponsor', 'hfo-golf-registration' ), '1' === $get( 'tee_sponsor_selected' ) ? __( 'Yes', 'hfo-golf-registration' ) : '' );
			$specific = hfo_golf_internal_email_section( __( 'SPONSOR INFORMATION', 'hfo-golf-registration' ), $specific );
		}
		$lunch = absint( $get( 'additional_lunch_count' ) ); $dinner = absint( $get( 'additional_dinner_count' ) );
		$meals = '';
		if ( $lunch + $dinner > 0 ) { $meals .= hfo_golf_internal_email_row( __( 'Lunch Guests', 'hfo-golf-registration' ), $lunch ?: '' ); $meals .= hfo_golf_internal_email_row( __( 'Dinner Guests', 'hfo-golf-registration' ), $dinner ?: '' ); $meals .= hfo_golf_internal_email_row( __( 'Guest Name(s)', 'hfo-golf-registration' ), $get( 'hfo_golf_guest_names' ), true ); }
		$purchaser_heading = 'additional_guests' === $type ? __( 'PURCHASER / ATTENDEE INFORMATION', 'hfo-golf-registration' ) : __( 'PURCHASER INFORMATION', 'hfo-golf-registration' );
		$event_title = sanitize_text_field( get_the_title( $event_id ) );
		$body = '<div style="font-family:Arial,sans-serif;max-width:680px;color:#222"><h1 style="color:#1d4f35">' . esc_html( $event_title ) . '</h1><p><strong>' . esc_html( $labels[ $type ] ) . '</strong></p>' . hfo_golf_internal_email_section( __( 'ORDER INFORMATION', 'hfo-golf-registration' ), $order_rows ) . hfo_golf_internal_email_section( $purchaser_heading, $purchaser ) . $specific . hfo_golf_internal_email_section( __( 'GUEST MEALS', 'hfo-golf-registration' ), $meals ) . '</div>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' ); foreach ( $cc as $email ) { $headers[] = 'Cc: ' . $email; }
		$order->update_meta_data( '_hfo_internal_organizer_email_status', 'sending' ); $order->save_meta_data();
		if ( ! wp_mail( $to, sprintf( 'New %s Registration – %s', $event_title, $labels[ $type ] ), $body, $headers ) ) { throw new RuntimeException( 'wp_mail returned false.' ); }
		$order->update_meta_data( '_hfo_internal_organizer_email_status', 'sent' ); $order->update_meta_data( '_hfo_internal_organizer_email_sent_at', current_time( 'mysql', true ) );
		$order->add_order_note( sprintf( __( 'Internal organizer registration notification sent to %s.', 'hfo-golf-registration' ), $to ) ); $order->save_meta_data(); return true;
	} catch ( Throwable $e ) {
		if ( $order && method_exists( $order, 'update_meta_data' ) ) { $order->update_meta_data( '_hfo_internal_organizer_email_status', 'failed' ); $order->add_order_note( __( 'Internal organizer registration notification could not be sent.', 'hfo-golf-registration' ) ); $order->save_meta_data(); }
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( 'HFO internal organizer email failed: ' . sanitize_text_field( $e->getMessage() ) ); }
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
add_action( 'woocommerce_payment_complete', 'send_hfo_golf_internal_organizer_email', 30, 1 );
add_action( 'woocommerce_order_status_processing', 'send_hfo_golf_internal_organizer_email', 30, 1 );
add_action( 'woocommerce_order_status_completed', 'send_hfo_golf_internal_organizer_email', 30, 1 );
