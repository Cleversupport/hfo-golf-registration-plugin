<?php
/**
 * Frontend registration form handling.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HFO_Golf_Registration_Frontend {
	const NONCE_ACTION = 'hfo_golf_registration_submit';
	const NONCE_NAME   = 'hfo_golf_registration_nonce';

	public function register_hooks() {
		add_shortcode( 'hfo_golf_registration', array( $this, 'render_shortcode' ) );
		add_shortcode( 'hfo_golf_registration_form', array( $this, 'render_shortcode' ) );
		add_action( 'admin_post_nopriv_hfo_golf_registration_submit', array( $this, 'handle_submission' ) );
		add_action( 'admin_post_hfo_golf_registration_submit', array( $this, 'handle_submission' ) );
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'event_id' => 0,
			),
			$atts,
			'hfo_golf_registration'
		);

		$event_id = absint( $atts['event_id'] );
		$event    = $this->get_valid_event( $event_id );

		if ( ! $event ) {
			return '<p>' . esc_html__( 'Invalid event specified.', 'hfo-golf-registration' ) . '</p>';
		}

		if ( isset( $_GET['hfo_registration_success'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['hfo_registration_success'] ) ) ) {
			$thank_you = (string) get_post_meta( $event_id, 'thank_you_message', true );
			$thank_you = '' !== trim( $thank_you ) ? $thank_you : __( 'Thank you for your registration.', 'hfo-golf-registration' );
			return '<div class="hfo-registration-success"><p>' . esc_html( $thank_you ) . '</p></div>';
		}

		$registration_status = sanitize_key( (string) get_post_meta( $event_id, 'registration_status', true ) );
		if ( 'open' !== $registration_status ) {
			return '<p>' . esc_html__( 'Registration is currently closed for this event.', 'hfo-golf-registration' ) . '</p>';
		}

		$redirect_to = get_permalink();
		if ( ! $redirect_to ) {
			$redirect_to = home_url( '/' );
		}

		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hfo_golf_registration_submit" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

			<h3><?php esc_html_e( 'Main Contact', 'hfo-golf-registration' ); ?></h3>
			<?php $this->render_text_field( 'main_contact_name', true ); ?>
			<?php $this->render_email_field( 'main_contact_email', true ); ?>
			<?php $this->render_text_field( 'main_contact_phone', true ); ?>
			<?php $this->render_text_field( 'main_contact_address', true ); ?>
			<?php $this->render_text_field( 'main_contact_city', true ); ?>
			<?php $this->render_text_field( 'main_contact_state', true ); ?>
			<?php $this->render_text_field( 'main_contact_zip', true ); ?>

			<h3><?php esc_html_e( 'Team Captain', 'hfo-golf-registration' ); ?></h3>
			<?php $this->render_text_field( 'captain_name' ); ?>
			<?php $this->render_email_field( 'captain_email' ); ?>
			<?php $this->render_text_field( 'captain_phone' ); ?>
			<?php $this->render_text_field( 'captain_handicap' ); ?>

			<h3><?php esc_html_e( 'Members', 'hfo-golf-registration' ); ?></h3>
			<?php foreach ( array( 2, 3, 4 ) as $member ) : ?>
				<?php $this->render_text_field( "member_{$member}_name" ); ?>
				<?php $this->render_email_field( "member_{$member}_email" ); ?>
				<?php $this->render_text_field( "member_{$member}_phone" ); ?>
				<?php $this->render_text_field( "member_{$member}_handicap" ); ?>
			<?php endforeach; ?>

			<h3><?php esc_html_e( 'Quantities', 'hfo-golf-registration' ); ?></h3>
			<?php $this->render_number_field( 'golf_qty' ); ?>
			<?php $this->render_number_field( 'lunch_qty' ); ?>
			<?php $this->render_number_field( 'dinner_qty' ); ?>
			<?php $this->render_number_field( 'platinum_sponsor_qty' ); ?>
			<?php $this->render_number_field( 'gold_sponsor_qty' ); ?>
			<?php $this->render_number_field( 'silver_sponsor_qty' ); ?>
			<?php $this->render_number_field( 'tee_sponsor_qty' ); ?>

			<h3><?php esc_html_e( 'Other', 'hfo-golf-registration' ); ?></h3>
			<?php $this->render_text_field( 'discount_code_used' ); ?>
			<?php $this->render_textarea_field( 'additional_guests_details' ); ?>

			<p><button type="submit"><?php esc_html_e( 'Submit Registration', 'hfo-golf-registration' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public function handle_submission() {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hfo-golf-registration' ) );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
		$event    = $this->get_valid_event( $event_id );
		if ( ! $event ) {
			wp_die( esc_html__( 'Invalid event.', 'hfo-golf-registration' ) );
		}

		$registration_status = sanitize_key( (string) get_post_meta( $event_id, 'registration_status', true ) );
		if ( 'open' !== $registration_status ) {
			wp_die( esc_html__( 'Registration is currently closed for this event.', 'hfo-golf-registration' ) );
		}

		$main_contact_name  = $this->sanitize_post_text( 'main_contact_name' );
		$main_contact_email = sanitize_email( $this->sanitize_post_text( 'main_contact_email' ) );
		$main_contact_phone = $this->sanitize_post_text( 'main_contact_phone' );

		if ( '' === $main_contact_name || '' === $main_contact_phone || ! is_email( $main_contact_email ) ) {
			wp_die( esc_html__( 'Please provide required main contact details.', 'hfo-golf-registration' ) );
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
			'main_contact_address'      => $this->sanitize_post_text( 'main_contact_address' ),
			'main_contact_city'         => $this->sanitize_post_text( 'main_contact_city' ),
			'main_contact_state'        => $this->sanitize_post_text( 'main_contact_state' ),
			'main_contact_zip'          => $this->sanitize_post_text( 'main_contact_zip' ),
			'captain_name'              => $this->sanitize_post_text( 'captain_name' ),
			'captain_email'             => sanitize_email( $this->sanitize_post_text( 'captain_email' ) ),
			'captain_phone'             => $this->sanitize_post_text( 'captain_phone' ),
			'captain_handicap'          => $this->sanitize_post_text( 'captain_handicap' ),
			'member_2_name'             => $this->sanitize_post_text( 'member_2_name' ),
			'member_2_email'            => sanitize_email( $this->sanitize_post_text( 'member_2_email' ) ),
			'member_2_phone'            => $this->sanitize_post_text( 'member_2_phone' ),
			'member_2_handicap'         => $this->sanitize_post_text( 'member_2_handicap' ),
			'member_3_name'             => $this->sanitize_post_text( 'member_3_name' ),
			'member_3_email'            => sanitize_email( $this->sanitize_post_text( 'member_3_email' ) ),
			'member_3_phone'            => $this->sanitize_post_text( 'member_3_phone' ),
			'member_3_handicap'         => $this->sanitize_post_text( 'member_3_handicap' ),
			'member_4_name'             => $this->sanitize_post_text( 'member_4_name' ),
			'member_4_email'            => sanitize_email( $this->sanitize_post_text( 'member_4_email' ),
			),
			'member_4_phone'            => $this->sanitize_post_text( 'member_4_phone' ),
			'member_4_handicap'         => $this->sanitize_post_text( 'member_4_handicap' ),
			'discount_code_used'        => $this->sanitize_post_text( 'discount_code_used' ),
			'additional_guests_details' => sanitize_textarea_field( $this->sanitize_post_textarea( 'additional_guests_details' ) ),
			'additional_lunch_count'    => '0',
			'additional_dinner_count'   => '0',
		);

		foreach ( $quantity_to_price as $qty_key => $price_key ) {
			$qty          = (string) max( 0, absint( $this->sanitize_post_text( $qty_key ) ) );
			$price        = (float) get_post_meta( $event_id, $price_key, true );
			$meta[ $qty_key ] = $qty;
			$subtotal    += ( (float) $qty ) * $price;
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
			wp_die( esc_html__( 'Unable to save registration.', 'hfo-golf-registration' ) );
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $registration_id, $key, $value );
		}

		$this->send_confirmation_email( $main_contact_email, $event_id );
		$this->send_admin_notification( $event_id, $registration_id, $main_contact_name, $main_contact_email );

		$redirect_to = $this->get_redirect_url();
		wp_safe_redirect( add_query_arg( 'hfo_registration_success', '1', $redirect_to ) );
		exit;
	}

	private function get_valid_event( $event_id ) {
		if ( ! $event_id ) {
			return false;
		}

		$event = get_post( $event_id );
		if ( ! $event || HFO_Golf_Event_Post_Type::POST_TYPE !== $event->post_type ) {
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

	private function send_confirmation_email( $to_email, $event_id ) {
		if ( ! is_email( $to_email ) ) {
			return;
		}

		$subject = __( 'Golf Registration Received', 'hfo-golf-registration' );
		$message = sprintf( __( 'Thank you for registering for event #%d.', 'hfo-golf-registration' ), $event_id );

		wp_mail( $to_email, $subject, $message );
	}

	private function send_admin_notification( $event_id, $registration_id, $main_contact_name, $main_contact_email ) {
		$raw_emails = (string) get_post_meta( $event_id, 'notification_emails', true );
		$emails     = array_filter( array_map( 'trim', explode( ',', $raw_emails ) ) );
		$emails     = array_map( 'sanitize_email', $emails );
		$emails     = array_filter( $emails, 'is_email' );

		if ( empty( $emails ) ) {
			return;
		}

		$subject = __( 'New Golf Registration Submitted', 'hfo-golf-registration' );
		$message = sprintf( __( 'Registration #%1$d submitted by %2$s (%3$s).', 'hfo-golf-registration' ), $registration_id, $main_contact_name, $main_contact_email );

		wp_mail( $emails, $subject, $message );
	}

	private function get_redirect_url() {
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		$validated   = wp_validate_redirect( $redirect_to, home_url( '/' ) );
		return $validated ? $validated : home_url( '/' );
	}

	private function sanitize_post_text( $key ) {
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		return sanitize_text_field( $value );
	}

	private function sanitize_post_textarea( $key ) {
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		return sanitize_textarea_field( $value );
	}

	private function render_text_field( $name, $required = false ) {
		?>
		<p><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $name ) ) ); ?></label><br /><input type="text" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" <?php echo $required ? 'required' : ''; ?>></p>
		<?php
	}

	private function render_email_field( $name, $required = false ) {
		?>
		<p><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $name ) ) ); ?></label><br /><input type="email" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" <?php echo $required ? 'required' : ''; ?>></p>
		<?php
	}

	private function render_number_field( $name ) {
		?>
		<p><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $name ) ) ); ?></label><br /><input type="number" min="0" step="1" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>" value="0"></p>
		<?php
	}

	private function render_textarea_field( $name ) {
		?>
		<p><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $name ) ) ); ?></label><br /><textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $name ); ?>"></textarea></p>
		<?php
	}
}
