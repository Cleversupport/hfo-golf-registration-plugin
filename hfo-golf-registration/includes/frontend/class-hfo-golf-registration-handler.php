<?php
/**
 * Frontend registration submission handler.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HFO_Golf_Registration_Handler {

	public function register_hooks() {
		add_action( 'admin_post_nopriv_hfo_golf_submit_registration', array( $this, 'handle_submission' ) );
		add_action( 'admin_post_hfo_golf_submit_registration', array( $this, 'handle_submission' ) );
	}

	public function handle_submission() {
		if ( ! isset( $_POST['hfo_golf_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hfo_golf_registration_nonce'] ) ), 'hfo_golf_submit_registration' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hfo-golf-registration' ) );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		if ( 0 === $event_id || HFO_Golf_Event_Post_Type::POST_TYPE !== get_post_type( $event_id ) ) {
			wp_die( esc_html__( 'Invalid event.', 'hfo-golf-registration' ) );
		}

		$data = $this->sanitize_input();
		$totals = $this->calculate_totals( $event_id, $data );

		$registration_id = wp_insert_post(
			array(
				'post_type'   => HFO_Golf_Registration_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Registration - %s - %s', get_the_title( $event_id ), $data['main_contact_name'] ),
			)
		);

		if ( is_wp_error( $registration_id ) || 0 === $registration_id ) {
			wp_die( esc_html__( 'Unable to save registration.', 'hfo-golf-registration' ) );
		}

		foreach ( $data as $key => $value ) {
			update_post_meta( $registration_id, $key, $value );
		}

		update_post_meta( $registration_id, 'related_event', (string) $event_id );
		update_post_meta( $registration_id, 'registration_type', 'team' );
		update_post_meta( $registration_id, 'registration_status', 'submitted' );
		update_post_meta( $registration_id, 'payment_status', 'unpaid' );
		update_post_meta( $registration_id, 'subtotal', number_format( (float) $totals['subtotal'], 2, '.', '' ) );
		update_post_meta( $registration_id, 'discount_amount', number_format( (float) $totals['discount_amount'], 2, '.', '' ) );
		update_post_meta( $registration_id, 'grand_total', number_format( (float) $totals['grand_total'], 2, '.', '' ) );

		$this->send_notifications( $event_id, $registration_id, $data, $totals );

		$redirect_url = get_permalink( $event_id );
		if ( ! $redirect_url ) {
			$thank_you_message = get_post_meta( $event_id, 'thank_you_message', true );
			echo wp_kses_post( $thank_you_message );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'registration_success', '1', $redirect_url ) );
		exit;
	}

	private function sanitize_input() {
		$text_fields = array(
			'main_contact_name','main_contact_phone','main_contact_address','main_contact_city','main_contact_state','main_contact_zip','discount_code_used',
			'captain_name','captain_phone','captain_handicap','member_2_name','member_2_phone','member_2_handicap','member_3_name','member_3_phone','member_3_handicap','member_4_name','member_4_phone','member_4_handicap',
		);
		$email_fields = array( 'main_contact_email', 'captain_email', 'member_2_email', 'member_3_email', 'member_4_email' );
		$count_fields = array( 'golf_qty','lunch_qty','dinner_qty','platinum_sponsor_qty','gold_sponsor_qty','silver_sponsor_qty','tee_sponsor_qty' );

		$data = array();
		foreach ( $text_fields as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		foreach ( $email_fields as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_email( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		foreach ( $count_fields as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? (string) absint( $_POST[ $field ] ) : '0';
		}
		$data['additional_guests_details'] = isset( $_POST['additional_guests_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['additional_guests_details'] ) ) : '';
		$data['additional_lunch_count'] = '0';
		$data['additional_dinner_count'] = '0';
		return $data;
	}

	private function calculate_totals( $event_id, $data ) {
		$prices = array(
			'golf_qty' => (float) get_post_meta( $event_id, 'golf_price', true ),
			'lunch_qty' => (float) get_post_meta( $event_id, 'lunch_price', true ),
			'dinner_qty' => (float) get_post_meta( $event_id, 'dinner_price', true ),
			'platinum_sponsor_qty' => (float) get_post_meta( $event_id, 'platinum_sponsor_price', true ),
			'gold_sponsor_qty' => (float) get_post_meta( $event_id, 'gold_sponsor_price', true ),
			'silver_sponsor_qty' => (float) get_post_meta( $event_id, 'silver_sponsor_price', true ),
			'tee_sponsor_qty' => (float) get_post_meta( $event_id, 'tee_sponsor_price', true ),
		);
		$subtotal = 0.0;
		foreach ( $prices as $qty_key => $unit_price ) {
			$subtotal += absint( $data[ $qty_key ] ) * max( 0, $unit_price );
		}

		$discount_percentage = 0;
		$submitted = strtolower( trim( $data['discount_code_used'] ) );
		if ( '' !== $submitted && strtolower( trim( (string) get_post_meta( $event_id, 'discount_code_15', true ) ) ) === $submitted ) {
			$discount_percentage = 15;
		} elseif ( '' !== $submitted && strtolower( trim( (string) get_post_meta( $event_id, 'discount_code_30', true ) ) ) === $submitted ) {
			$discount_percentage = 30;
		}

		$discount_amount = ( $subtotal * $discount_percentage ) / 100;
		$grand_total = $subtotal - $discount_amount;
		return array( 'subtotal' => $subtotal, 'discount_amount' => $discount_amount, 'grand_total' => $grand_total );
	}

	private function send_notifications( $event_id, $registration_id, $data, $totals ) {
		$notification_emails = sanitize_text_field( (string) get_post_meta( $event_id, 'notification_emails', true ) );
		$admin_recipients = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $notification_emails ) ) ) );
		$subject = sprintf( 'New Golf Registration #%d', absint( $registration_id ) );
		$message = sprintf( "Event: %s\nContact: %s\nEmail: %s\nGrand Total: %s", sanitize_text_field( get_the_title( $event_id ) ), $data['main_contact_name'], $data['main_contact_email'], number_format( (float) $totals['grand_total'], 2, '.', '' ) );
		if ( ! empty( $admin_recipients ) ) {
			wp_mail( $admin_recipients, sanitize_text_field( $subject ), sanitize_textarea_field( $message ) );
		}

		if ( ! empty( $data['main_contact_email'] ) ) {
			wp_mail(
				$data['main_contact_email'],
				esc_html__( 'Your golf registration was received', 'hfo-golf-registration' ),
				sanitize_textarea_field( sprintf( "Thank you, %s. Your registration has been submitted.", $data['main_contact_name'] ) )
			);
		}
	}
}
