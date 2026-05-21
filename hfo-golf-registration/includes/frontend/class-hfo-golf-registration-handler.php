<?php
/**
 * Frontend form submission handler.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles registration submission requests.
 */
class HFO_Golf_Registration_Handler {

	/**
	 * Registers form handling hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_nopriv_hfo_golf_registration_submit', array( $this, 'handle_submission' ) );
		add_action( 'admin_post_hfo_golf_registration_submit', array( $this, 'handle_submission' ) );
	}

	/**
	 * Processes form submissions.
	 *
	 * @return void
	 */
	public function handle_submission() {
		if ( ! isset( $_POST['hfo_golf_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hfo_golf_registration_nonce'] ) ), 'hfo_golf_registration_submit' ) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'hfo-golf-registration' ) );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
		if ( ! $event_id || 'golf_event' !== get_post_type( $event_id ) ) {
			wp_die( esc_html__( 'Invalid event selected.', 'hfo-golf-registration' ) );
		}

		if ( 'open' !== get_post_meta( $event_id, 'registration_status', true ) ) {
			wp_die( esc_html__( 'Registration is currently closed for this event.', 'hfo-golf-registration' ) );
		}

		$main_contact_name  = isset( $_POST['main_contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['main_contact_name'] ) ) : '';
		$main_contact_email = isset( $_POST['main_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['main_contact_email'] ) ) : '';
		$main_contact_phone = isset( $_POST['main_contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['main_contact_phone'] ) ) : '';

		if ( '' === $main_contact_name ) {
			wp_die( esc_html__( 'Main contact name is required.', 'hfo-golf-registration' ) );
		}

		if ( '' === $main_contact_email || ! is_email( $main_contact_email ) ) {
			wp_die( esc_html__( 'A valid main contact email is required.', 'hfo-golf-registration' ) );
		}

		if ( '' === $main_contact_phone ) {
			wp_die( esc_html__( 'Main contact phone is required.', 'hfo-golf-registration' ) );
		}

		$registration_id = wp_insert_post(
			array(
				'post_type'   => 'golf_registration',
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: %d: event ID. */
					__( 'Registration for Event #%d', 'hfo-golf-registration' ),
					$event_id
				),
			),
			true
		);

		if ( is_wp_error( $registration_id ) || ! $registration_id ) {
			wp_die( esc_html__( 'Unable to save registration. Please try again.', 'hfo-golf-registration' ) );
		}

		update_post_meta( $registration_id, 'event_id', $event_id );
		update_post_meta( $registration_id, 'main_contact_name', $main_contact_name );
		update_post_meta( $registration_id, 'main_contact_email', $main_contact_email );
		update_post_meta( $registration_id, 'main_contact_phone', $main_contact_phone );

		$submitted_redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		$redirect_to        = wp_validate_redirect( $submitted_redirect, home_url( '/' ) );
		$redirect_to        = add_query_arg( 'registration_success', '1', $redirect_to );

		wp_safe_redirect( $redirect_to );
		exit;
	}
}
