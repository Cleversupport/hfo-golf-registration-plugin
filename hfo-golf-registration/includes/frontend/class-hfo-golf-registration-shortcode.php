<?php
/**
 * Frontend golf registration shortcode.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HFO_Golf_Registration_Shortcode {

	const SHORTCODE_TAG = 'hfo_golf_registration_form';
	const ACTION = 'hfo_golf_submit_registration';

	public function register_hooks() {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render_shortcode' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submission' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submission' ) );
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'event_id' => 0,
			),
			$atts,
			self::SHORTCODE_TAG
		);

		wp_enqueue_style( 'hfo-golf-registration-frontend', plugins_url( 'assets/css/frontend.css', HFO_GOLF_REGISTRATION_FILE ), array(), HFO_GOLF_REGISTRATION_VERSION );

		$event_id = absint( $atts['event_id'] );
		if ( ! $event_id || HFO_Golf_Event_Post_Type::POST_TYPE !== get_post_type( $event_id ) ) {
			return '<p>' . esc_html__( 'Invalid event selected.', 'hfo-golf-registration' ) . '</p>';
		}

		if ( isset( $_GET['registration_success'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['registration_success'] ) ) ) {
			$thank_you_message = get_post_meta( $event_id, 'thank_you_message', true );
			if ( '' === trim( (string) $thank_you_message ) ) {
				$thank_you_message = __( 'Thank you for your registration.', 'hfo-golf-registration' );
			}
			return '<div class="hfo-golf-registration-success">' . wp_kses_post( wpautop( $thank_you_message ) ) . '</div>';
		}

		ob_start();
		?>
		<form class="hfo-golf-registration-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( self::ACTION, 'hfo_golf_registration_nonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="related_event" value="<?php echo esc_attr( (string) $event_id ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( get_permalink( $event_id ) ); ?>" />
			<?php foreach ( $this->get_field_keys() as $key ) : ?>
				<p><label><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?><br/><input type="text" name="<?php echo esc_attr( $key ); ?>" /></label></p>
			<?php endforeach; ?>
			<p><button type="submit"><?php esc_html_e( 'Submit Registration', 'hfo-golf-registration' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public function handle_submission() {
		if ( ! isset( $_POST['hfo_golf_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hfo_golf_registration_nonce'] ) ), self::ACTION ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hfo-golf-registration' ) );
		}

		$related_event = isset( $_POST['related_event'] ) ? absint( wp_unslash( $_POST['related_event'] ) ) : 0;
		if ( ! $related_event || HFO_Golf_Event_Post_Type::POST_TYPE !== get_post_type( $related_event ) ) {
			wp_die( esc_html__( 'Invalid event.', 'hfo-golf-registration' ) );
		}

		if ( 'open' !== get_post_meta( $related_event, 'registration_status', true ) ) {
			wp_die( esc_html__( 'Registration is currently closed for this event.', 'hfo-golf-registration' ) );
		}

		$main_contact_name  = $this->sanitize_text_post_field( 'main_contact_name' );
		$main_contact_email = $this->sanitize_email_post_field( 'main_contact_email' );
		$main_contact_phone = $this->sanitize_text_post_field( 'main_contact_phone' );
		if ( '' === $main_contact_name || '' === $main_contact_email || '' === $main_contact_phone ) {
			wp_die( esc_html__( 'Missing required contact fields.', 'hfo-golf-registration' ) );
		}

		$meta = $this->get_sanitized_submission_data();
		$meta['related_event'] = $related_event;
		$totals = $this->calculate_totals( $related_event, $meta );
		$meta   = array_merge( $meta, $totals );

		$registration_id = wp_insert_post(
			array(
				'post_type'   => HFO_Golf_Registration_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Registration - %s', $main_contact_name ),
			)
		);
		if ( is_wp_error( $registration_id ) || ! $registration_id ) {
			wp_die( esc_html__( 'Unable to save registration.', 'hfo-golf-registration' ) );
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $registration_id, $key, $value );
		}

		$this->send_notification_emails( $related_event, $meta );

		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : get_permalink( $related_event );
		$redirect_to = wp_validate_redirect( $redirect_to, get_permalink( $related_event ) );
		wp_safe_redirect( add_query_arg( 'registration_success', '1', $redirect_to ) );
		exit;
	}

	private function get_sanitized_submission_data() {
		$data = array(
			'registration_type'         => 'team',
			'registration_status'       => 'submitted',
			'payment_status'            => 'unpaid',
			'main_contact_name'         => $this->sanitize_text_post_field( 'main_contact_name' ),
			'main_contact_email'        => $this->sanitize_email_post_field( 'main_contact_email' ),
			'main_contact_phone'        => $this->sanitize_text_post_field( 'main_contact_phone' ),
			'main_contact_address'      => $this->sanitize_text_post_field( 'main_contact_address' ),
			'main_contact_city'         => $this->sanitize_text_post_field( 'main_contact_city' ),
			'main_contact_state'        => $this->sanitize_text_post_field( 'main_contact_state' ),
			'main_contact_zip'          => $this->sanitize_text_post_field( 'main_contact_zip' ),
			'additional_guests_details' => $this->sanitize_textarea_post_field( 'additional_guests_details' ),
			'additional_lunch_count'    => 0,
			'additional_dinner_count'   => 0,
			'discount_code_used'        => $this->sanitize_text_post_field( 'discount_code_used' ),
		);
		foreach ( array( 'golf_qty', 'lunch_qty', 'dinner_qty', 'platinum_sponsor_qty', 'gold_sponsor_qty', 'silver_sponsor_qty', 'tee_sponsor_qty' ) as $qty_field ) {
			$data[ $qty_field ] = $this->sanitize_absint_post_field( $qty_field );
		}
		foreach ( array( 'captain', 'member_2', 'member_3', 'member_4' ) as $prefix ) {
			$data[ $prefix . '_name' ]     = $this->sanitize_text_post_field( $prefix . '_name' );
			$data[ $prefix . '_email' ]    = $this->sanitize_email_post_field( $prefix . '_email' );
			$data[ $prefix . '_phone' ]    = $this->sanitize_text_post_field( $prefix . '_phone' );
			$data[ $prefix . '_handicap' ] = $this->sanitize_text_post_field( $prefix . '_handicap' );
		}
		return $data;
	}

	private function calculate_totals( $event_id, $meta ) {
		$price_fields = array( 'golf_price' => 'golf_qty', 'lunch_price' => 'lunch_qty', 'dinner_price' => 'dinner_qty', 'platinum_sponsor_price' => 'platinum_sponsor_qty', 'gold_sponsor_price' => 'gold_sponsor_qty', 'silver_sponsor_price' => 'silver_sponsor_qty', 'tee_sponsor_price' => 'tee_sponsor_qty' );
		$subtotal = 0.0;
		foreach ( $price_fields as $price_key => $qty_key ) {
			$price = floatval( get_post_meta( $event_id, $price_key, true ) );
			$subtotal += $price * absint( $meta[ $qty_key ] );
		}
		$discount_rate = 0.0;
		if ( '' !== $meta['discount_code_used'] ) {
			if ( strtolower( $meta['discount_code_used'] ) === strtolower( (string) get_post_meta( $event_id, 'discount_code_15', true ) ) ) {
				$discount_rate = 0.15;
			} elseif ( strtolower( $meta['discount_code_used'] ) === strtolower( (string) get_post_meta( $event_id, 'discount_code_30', true ) ) ) {
				$discount_rate = 0.30;
			}
		}
		$discount_amount = round( $subtotal * $discount_rate, 2 );
		$grand_total     = round( $subtotal - $discount_amount, 2 );
		return array( 'subtotal' => $subtotal, 'discount_amount' => $discount_amount, 'grand_total' => $grand_total );
	}

	private function send_notification_emails( $event_id, $meta ) {
		$subject = sprintf( 'New Golf Registration: %s', get_the_title( $event_id ) );
		$message = "A new registration was submitted.\n\n";
		$message .= 'Main Contact: ' . sanitize_text_field( $meta['main_contact_name'] ) . "\n";
		$message .= 'Email: ' . sanitize_email( $meta['main_contact_email'] ) . "\n";
		$message .= 'Grand Total: ' . number_format_i18n( floatval( $meta['grand_total'] ), 2 );
		$admin_emails_raw = (string) get_post_meta( $event_id, 'notification_emails', true );
		$admin_emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $admin_emails_raw ) ) ) );
		if ( ! empty( $admin_emails ) ) {
			wp_mail( $admin_emails, sanitize_text_field( $subject ), sanitize_textarea_field( $message ) );
		}
		if ( '' !== $meta['main_contact_email'] ) {
			wp_mail( sanitize_email( $meta['main_contact_email'] ), sanitize_text_field( 'Golf Registration Confirmation' ), sanitize_textarea_field( $message ) );
		}
	}

	private function get_field_keys() {
		return array( 'main_contact_name', 'main_contact_email', 'main_contact_phone', 'main_contact_address', 'main_contact_city', 'main_contact_state', 'main_contact_zip', 'golf_qty', 'lunch_qty', 'dinner_qty', 'platinum_sponsor_qty', 'gold_sponsor_qty', 'silver_sponsor_qty', 'tee_sponsor_qty', 'discount_code_used', 'captain_name', 'captain_email', 'captain_phone', 'captain_handicap', 'member_2_name', 'member_2_email', 'member_2_phone', 'member_2_handicap', 'member_3_name', 'member_3_email', 'member_3_phone', 'member_3_handicap', 'member_4_name', 'member_4_email', 'member_4_phone', 'member_4_handicap', 'additional_guests_details' );
	}

	private function sanitize_text_post_field( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	private function sanitize_textarea_post_field( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		return sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
	}

	private function sanitize_email_post_field( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		return sanitize_email( wp_unslash( $_POST[ $key ] ) );
	}

	private function sanitize_absint_post_field( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return 0;
		}

		return absint( wp_unslash( $_POST[ $key ] ) );
	}
}
