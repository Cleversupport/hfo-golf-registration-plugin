<?php
/**
 * Frontend registration shortcode and submission handling.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles frontend form rendering and storage.
 */
class HFO_Golf_Registration_Frontend {

	/**
	 * Registers frontend hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'hfo_golf_registration', array( $this, 'render_shortcode' ) );
		add_shortcode( 'hfo_golf_registration_form', array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'maybe_handle_submission' ) );
	}

	/**
	 * Handles submitted form request.
	 *
	 * @return void
	 */
	public function maybe_handle_submission() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( ! isset( $_POST['hfo_golf_registration_submit'] ) ) {
			return;
		}

		$this->handle_submission();
	}

	/**
	 * Renders the registration form.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'event_id' => get_the_ID(),
			),
			$atts,
			'hfo_golf_registration'
		);

		$event_id = absint( $atts['event_id'] );

		if ( $event_id <= 0 || HFO_Golf_Event_Post_Type::POST_TYPE !== get_post_type( $event_id ) ) {
			return '<p>' . esc_html__( 'Event not found.', 'hfo-golf-registration' ) . '</p>';
		}

		wp_enqueue_style( 'hfo-golf-registration-frontend', plugins_url( 'assets/css/frontend.css', HFO_GOLF_REGISTRATION_FILE ), array(), HFO_GOLF_REGISTRATION_VERSION );

		if ( isset( $_GET['registration_success'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['registration_success'] ) ) ) {
			$thank_you_message = get_post_meta( $event_id, 'thank_you_message', true );
			if ( '' === trim( (string) $thank_you_message ) ) {
				$thank_you_message = __( 'Thank you for your registration.', 'hfo-golf-registration' );
			}

			return wp_kses_post( wpautop( $thank_you_message ) );
		}

		ob_start();
		?>
		<form method="post">
			<?php wp_nonce_field( 'hfo_golf_registration_frontend_submit', 'hfo_golf_registration_nonce' ); ?>
			<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>" />

			<p><label><?php esc_html_e( 'Main Contact Name', 'hfo-golf-registration' ); ?> <input type="text" name="main_contact_name" required /></label></p>
			<p><label><?php esc_html_e( 'Main Contact Email', 'hfo-golf-registration' ); ?> <input type="email" name="main_contact_email" required /></label></p>
			<p><label><?php esc_html_e( 'Main Contact Phone', 'hfo-golf-registration' ); ?> <input type="text" name="main_contact_phone" required /></label></p>
			<p><label><?php esc_html_e( 'Golf Quantity', 'hfo-golf-registration' ); ?> <input type="number" min="0" step="1" name="golf_qty" value="0" /></label></p>
			<p><label><?php esc_html_e( 'Lunch Quantity', 'hfo-golf-registration' ); ?> <input type="number" min="0" step="1" name="lunch_qty" value="0" /></label></p>
			<p><label><?php esc_html_e( 'Dinner Quantity', 'hfo-golf-registration' ); ?> <input type="number" min="0" step="1" name="dinner_qty" value="0" /></label></p>
			<p><label><?php esc_html_e( 'Platinum Sponsor Quantity', 'hfo-golf-registration' ); ?> <input type="number" min="0" step="1" name="platinum_sponsor_qty" value="0" /></label></p>
			<p><label><?php esc_html_e( 'Gold Sponsor Quantity', 'hfo-golf-registration' ); ?> <input type="number" min="0" step="1" name="gold_sponsor_qty" value="0" /></label></p>
			<p><label><?php esc_html_e( 'Silver Sponsor Quantity', 'hfo-golf-registration' ); ?> <input type="number" min="0" step="1" name="silver_sponsor_qty" value="0" /></label></p>
			<p><label><?php esc_html_e( 'Tee Sponsor Quantity', 'hfo-golf-registration' ); ?> <input type="number" min="0" step="1" name="tee_sponsor_qty" value="0" /></label></p>
			<p><label><?php esc_html_e( 'Discount Code', 'hfo-golf-registration' ); ?> <input type="text" name="discount_code_used" /></label></p>
			<p><label><?php esc_html_e( 'Additional Guests Details', 'hfo-golf-registration' ); ?> <textarea name="additional_guests_details"></textarea></label></p>
			<p><button type="submit" name="hfo_golf_registration_submit" value="1"><?php esc_html_e( 'Submit Registration', 'hfo-golf-registration' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Validates and saves a registration.
	 *
	 * @return void
	 */
	private function handle_submission() {
		if ( ! isset( $_POST['hfo_golf_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hfo_golf_registration_nonce'] ) ), 'hfo_golf_registration_frontend_submit' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'hfo-golf-registration' ) );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
		if ( $event_id <= 0 || HFO_Golf_Event_Post_Type::POST_TYPE !== get_post_type( $event_id ) ) {
			wp_die( esc_html__( 'Invalid event selected.', 'hfo-golf-registration' ) );
		}

		if ( 'open' !== get_post_meta( $event_id, 'registration_status', true ) ) {
			wp_die( esc_html__( 'Registration is currently closed for this event.', 'hfo-golf-registration' ) );
		}

		$main_contact_name  = isset( $_POST['main_contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['main_contact_name'] ) ) : '';
		$main_contact_email = isset( $_POST['main_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['main_contact_email'] ) ) : '';
		$main_contact_phone = isset( $_POST['main_contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['main_contact_phone'] ) ) : '';

		if ( '' === $main_contact_name || '' === $main_contact_phone || ! is_email( $main_contact_email ) ) {
			wp_die( esc_html__( 'Please provide a valid name, email, and phone.', 'hfo-golf-registration' ) );
		}

		$qty_fields = array( 'golf_qty', 'lunch_qty', 'dinner_qty', 'platinum_sponsor_qty', 'gold_sponsor_qty', 'silver_sponsor_qty', 'tee_sponsor_qty' );
		$qty_values = array();
		foreach ( $qty_fields as $qty_field ) {
			$qty_values[ $qty_field ] = isset( $_POST[ $qty_field ] ) ? max( 0, absint( wp_unslash( $_POST[ $qty_field ] ) ) ) : 0;
		}

		$price_map = array(
			'golf_qty'             => 'golf_price',
			'lunch_qty'            => 'lunch_price',
			'dinner_qty'           => 'dinner_price',
			'platinum_sponsor_qty' => 'platinum_sponsor_price',
			'gold_sponsor_qty'     => 'gold_sponsor_price',
			'silver_sponsor_qty'   => 'silver_sponsor_price',
			'tee_sponsor_qty'      => 'tee_sponsor_price',
		);

		$subtotal = 0.0;
		foreach ( $price_map as $qty_key => $price_key ) {
			$price_raw = get_post_meta( $event_id, $price_key, true );
			$price     = (float) $price_raw;
			$subtotal += $price * (float) $qty_values[ $qty_key ];
		}

		$discount_code_used = isset( $_POST['discount_code_used'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_code_used'] ) ) : '';
		$discount_code_used = trim( $discount_code_used );
		$discount_rate      = 0.0;
		$discount_15_code   = trim( (string) get_post_meta( $event_id, 'discount_code_15', true ) );
		$discount_30_code   = trim( (string) get_post_meta( $event_id, 'discount_code_30', true ) );

		if ( '' !== $discount_code_used && '' !== $discount_15_code && 0 === strcasecmp( $discount_code_used, $discount_15_code ) ) {
			$discount_rate = 0.15;
		} elseif ( '' !== $discount_code_used && '' !== $discount_30_code && 0 === strcasecmp( $discount_code_used, $discount_30_code ) ) {
			$discount_rate = 0.30;
		}

		$discount_amount = $subtotal * $discount_rate;
		$grand_total     = $subtotal - $discount_amount;

		$post_id = wp_insert_post(
			array(
				'post_type'   => HFO_Golf_Registration_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Registration - %s', $main_contact_name ),
			),
			true
		);

		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			wp_die( esc_html__( 'Unable to save registration.', 'hfo-golf-registration' ) );
		}

		update_post_meta( $post_id, 'main_contact_name', $main_contact_name );
		update_post_meta( $post_id, 'main_contact_email', $main_contact_email );
		update_post_meta( $post_id, 'main_contact_phone', $main_contact_phone );
		update_post_meta( $post_id, 'additional_guests_details', isset( $_POST['additional_guests_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['additional_guests_details'] ) ) : '' );
		foreach ( $qty_values as $qty_key => $qty_value ) {
			update_post_meta( $post_id, $qty_key, (string) $qty_value );
		}
		update_post_meta( $post_id, 'discount_code_used', $discount_code_used );
		update_post_meta( $post_id, 'subtotal', number_format( (float) $subtotal, 2, '.', '' ) );
		update_post_meta( $post_id, 'discount_amount', number_format( (float) $discount_amount, 2, '.', '' ) );
		update_post_meta( $post_id, 'grand_total', number_format( (float) $grand_total, 2, '.', '' ) );
		update_post_meta( $post_id, 'registration_type', 'team' );
		update_post_meta( $post_id, 'registration_status', 'submitted' );
		update_post_meta( $post_id, 'payment_status', 'unpaid' );
		update_post_meta( $post_id, 'related_event', (string) $event_id );

		wp_mail( $main_contact_email, __( 'Golf Registration Confirmation', 'hfo-golf-registration' ), __( 'Thank you for your registration submission.', 'hfo-golf-registration' ) );

		$notification_emails_raw = (string) get_post_meta( $event_id, 'notification_emails', true );
		$notification_emails     = array();
		foreach ( explode( ',', $notification_emails_raw ) as $email ) {
			$sanitized_email = sanitize_email( trim( $email ) );
			if ( is_email( $sanitized_email ) ) {
				$notification_emails[] = $sanitized_email;
			}
		}

		if ( ! empty( $notification_emails ) ) {
			wp_mail( $notification_emails, __( 'New Golf Registration Submitted', 'hfo-golf-registration' ), __( 'A new golf registration has been submitted.', 'hfo-golf-registration' ) );
		}

		$redirect_url = wp_get_referer();
		if ( ! $redirect_url ) {
			$redirect_url = get_permalink( $event_id );
		}
		$redirect_url = add_query_arg( 'registration_success', '1', $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
