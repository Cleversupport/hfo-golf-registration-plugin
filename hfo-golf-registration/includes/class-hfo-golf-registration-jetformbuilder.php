<?php
/**
 * JetFormBuilder registration intake.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HFO_Golf_Registration_JetFormBuilder {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'jet-form-builder/form-handler/before-send', array( $this, 'maybe_process_submission' ), 10, 1 );
	}

	/**
	 * Process a JetFormBuilder submission when flagged for this integration.
	 *
	 * @param mixed $form_handler JetFormBuilder form handler object.
	 * @return void
	 */
	public function maybe_process_submission( $form_handler ) {
		if ( ! class_exists( 'Jet_Form_Builder\Plugin' ) ) {
			return;
		}

		$request = isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();

		if ( ! isset( $request['hfo_golf_registration_form'] ) || '1' !== (string) $request['hfo_golf_registration_form'] ) {
			return;
		}

		$event_id = isset( $request['related_event'] ) ? absint( $request['related_event'] ) : 0;
		$event    = $this->get_valid_open_event( $event_id );

		if ( ! $event ) {
			throw new \Exception( esc_html__( 'Invalid event or registration is closed.', 'hfo-golf-registration' ) );
		}

		$main_contact_name  = $this->sanitize_text( $request, 'main_contact_name' );
		$main_contact_email = $this->sanitize_email( $request, 'main_contact_email' );
		$main_contact_phone = $this->sanitize_text( $request, 'main_contact_phone' );

		if ( '' === $main_contact_name || '' === $main_contact_phone || ! is_email( $main_contact_email ) ) {
			throw new \Exception( esc_html__( 'Please provide required main contact details.', 'hfo-golf-registration' ) );
		}

		$quantity_to_price = array(
			'golf_qty'             => 'golf_price',
			'lunch_qty'            => 'lunch_price',
			'dinner_qty'           => 'dinner_price',
			'platinum_sponsor_qty' => 'platinum_sponsor_price',
			'gold_sponsor_qty'     => 'gold_sponsor_price',
			'silver_sponsor_qty'   => 'silver_sponsor_price',
			'tee_sponsor_qty'      => 'tee_sponsor_price',
		);

		$subtotal = 0.0;
		$meta     = array(
			'related_event'             => (string) $event_id,
			'registration_type'         => 'team',
			'registration_status'       => 'submitted',
			'payment_status'            => 'unpaid',
			'main_contact_name'         => $main_contact_name,
			'main_contact_email'        => $main_contact_email,
			'main_contact_phone'        => $main_contact_phone,
			'main_contact_address'      => $this->sanitize_text( $request, 'main_contact_address' ),
			'main_contact_city'         => $this->sanitize_text( $request, 'main_contact_city' ),
			'main_contact_state'        => $this->sanitize_text( $request, 'main_contact_state' ),
			'main_contact_zip'          => $this->sanitize_text( $request, 'main_contact_zip' ),
			'captain_name'              => $this->sanitize_text( $request, 'captain_name' ),
			'captain_email'             => $this->sanitize_email( $request, 'captain_email' ),
			'captain_phone'             => $this->sanitize_text( $request, 'captain_phone' ),
			'captain_handicap'          => $this->sanitize_text( $request, 'captain_handicap' ),
			'member_2_name'             => $this->sanitize_text( $request, 'member_2_name' ),
			'member_2_email'            => $this->sanitize_email( $request, 'member_2_email' ),
			'member_2_phone'            => $this->sanitize_text( $request, 'member_2_phone' ),
			'member_2_handicap'         => $this->sanitize_text( $request, 'member_2_handicap' ),
			'member_3_name'             => $this->sanitize_text( $request, 'member_3_name' ),
			'member_3_email'            => $this->sanitize_email( $request, 'member_3_email' ),
			'member_3_phone'            => $this->sanitize_text( $request, 'member_3_phone' ),
			'member_3_handicap'         => $this->sanitize_text( $request, 'member_3_handicap' ),
			'member_4_name'             => $this->sanitize_text( $request, 'member_4_name' ),
			'member_4_email'            => $this->sanitize_email( $request, 'member_4_email' ),
			'member_4_phone'            => $this->sanitize_text( $request, 'member_4_phone' ),
			'member_4_handicap'         => $this->sanitize_text( $request, 'member_4_handicap' ),
			'additional_guests_details' => $this->sanitize_textarea( $request, 'additional_guests_details' ),
			'additional_lunch_count'    => (string) $this->sanitize_qty( $request, 'additional_lunch_count' ),
			'additional_dinner_count'   => (string) $this->sanitize_qty( $request, 'additional_dinner_count' ),
			'discount_code_used'        => $this->sanitize_text( $request, 'discount_code_used' ),
		);

		foreach ( $quantity_to_price as $qty_key => $price_key ) {
			$qty             = $this->sanitize_qty( $request, $qty_key );
			$meta[ $qty_key ] = (string) $qty;
			$price           = (float) get_post_meta( $event_id, $price_key, true );
			$subtotal       += (float) $qty * $price;
		}

		$discount_rate = $this->get_discount_rate( $event_id, $meta['discount_code_used'] );
		$discount      = $subtotal * $discount_rate;
		$grand_total   = $subtotal - $discount;

		$meta['subtotal']        = number_format( (float) $subtotal, 2, '.', '' );
		$meta['discount_amount'] = number_format( (float) $discount, 2, '.', '' );
		$meta['grand_total']     = number_format( (float) $grand_total, 2, '.', '' );

		$registration_id = wp_insert_post(
			array(
				'post_type'   => HFO_Golf_Registration_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Registration - %s', $main_contact_name ),
			),
			true
		);

		if ( is_wp_error( $registration_id ) || ! $registration_id ) {
			throw new \Exception( esc_html__( 'Unable to save registration.', 'hfo-golf-registration' ) );
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $registration_id, $key, $value );
		}

	}

	private function get_valid_open_event( $event_id ) {
		if ( ! $event_id ) {
			return false;
		}

		$event = get_post( $event_id );
		if ( ! $event || HFO_Golf_Event_Post_Type::POST_TYPE !== $event->post_type ) {
			return false;
		}

		$registration_status = sanitize_key( (string) get_post_meta( $event_id, 'registration_status', true ) );
		if ( 'open' !== $registration_status ) {
			return false;
		}

		return $event;
	}

	private function get_discount_rate( $event_id, $submitted_code ) {
		$submitted_code = strtolower( trim( (string) $submitted_code ) );
		if ( '' === $submitted_code ) {
			return 0.0;
		}

		$discount_15 = strtolower( trim( (string) get_post_meta( $event_id, 'discount_code_15', true ) ) );
		$discount_30 = strtolower( trim( (string) get_post_meta( $event_id, 'discount_code_30', true ) ) );

		if ( '' !== $discount_30 && $submitted_code === $discount_30 ) {
			return 0.30;
		}

		if ( '' !== $discount_15 && $submitted_code === $discount_15 ) {
			return 0.15;
		}

		return 0.0;
	}

	private function sanitize_email( $request, $key ) {
		return sanitize_email( isset( $request[ $key ] ) ? $request[ $key ] : '' );
	}

	private function sanitize_text( $request, $key ) {
		return sanitize_text_field( isset( $request[ $key ] ) ? $request[ $key ] : '' );
	}

	private function sanitize_textarea( $request, $key ) {
		return sanitize_textarea_field( isset( $request[ $key ] ) ? $request[ $key ] : '' );
	}

	private function sanitize_qty( $request, $key ) {
		return absint( isset( $request[ $key ] ) ? $request[ $key ] : 0 );
	}

}
