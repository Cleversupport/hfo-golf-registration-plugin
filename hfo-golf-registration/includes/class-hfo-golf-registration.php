<?php
/**
 * Main plugin bootstrap.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin hooks.
 */
class HFO_Golf_Registration {

	/**
	 * Registers WordPress hooks used by the plugin.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_frontend_hooks' ) );

		$event_meta_boxes = new HFO_Golf_Event_Meta_Boxes();
		$event_meta_boxes->register_hooks();

		$registration_meta_boxes = new HFO_Golf_Registration_Meta_Boxes();
		$registration_meta_boxes->register_hooks();

		$settings = new HFO_Golf_Registration_Settings();
		$settings->register_hooks();
	}

	/**
	 * Registers the plugin custom post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		HFO_Golf_Event_Post_Type::register();
		HFO_Golf_Registration_Post_Type::register();
	}

	/**
	 * Registers front-end shortcode and form handlers.
	 *
	 * @return void
	 */
	public function register_frontend_hooks() {
		add_shortcode( 'hfo_golf_registration', array( $this, 'render_shortcode' ) );
		add_action( 'admin_post_nopriv_hfo_submit_golf_registration', array( $this, 'handle_registration_submission' ) );
		add_action( 'admin_post_hfo_submit_golf_registration', array( $this, 'handle_registration_submission' ) );
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
		if ( $event_id <= 0 ) {
			return 'Invalid event.';
		}

		$event = get_post( $event_id );
		if ( ! $event || HFO_Golf_Event_Post_Type::POST_TYPE !== $event->post_type ) {
			return 'Invalid event.';
		}

		if ( isset( $_GET['hfo_registration_success'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['hfo_registration_success'] ) ) ) {
			$thank_you_message = get_post_meta( $event_id, 'thank_you_message', true );
			return ! empty( $thank_you_message ) ? wpautop( wp_kses_post( $thank_you_message ) ) : 'Thank you for your registration.';
		}

		if ( 'open' !== get_post_meta( $event_id, 'registration_status', true ) ) {
			return 'Registration is currently closed for this event.';
		}

		$action_url = esc_url( admin_url( 'admin-post.php' ) );
		$redirect_to = esc_url( get_permalink() );
		ob_start();
		?>
		<form method="post" action="<?php echo $action_url; ?>">
			<input type="hidden" name="action" value="hfo_submit_golf_registration" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo $redirect_to; ?>" />
			<?php wp_nonce_field( 'hfo_submit_golf_registration', 'hfo_submit_golf_registration_nonce' ); ?>

			<input type="text" name="main_contact_name" placeholder="Main Contact Name" required />
			<input type="email" name="main_contact_email" placeholder="Main Contact Email" required />
			<input type="text" name="main_contact_phone" placeholder="Main Contact Phone" />
			<input type="text" name="main_contact_address" placeholder="Main Contact Address" />
			<input type="text" name="main_contact_city" placeholder="Main Contact City" />
			<input type="text" name="main_contact_state" placeholder="Main Contact State" />
			<input type="text" name="main_contact_zip" placeholder="Main Contact ZIP" />

			<input type="text" name="captain_name" placeholder="Team Captain Name" required />
			<input type="email" name="captain_email" placeholder="Team Captain Email" required />
			<input type="text" name="captain_phone" placeholder="Team Captain Phone" />
			<input type="text" name="captain_handicap" placeholder="Team Captain Handicap" />

			<input type="text" name="member_2_name" placeholder="Member 2 Name" />
			<input type="email" name="member_2_email" placeholder="Member 2 Email" />
			<input type="text" name="member_2_phone" placeholder="Member 2 Phone" />
			<input type="text" name="member_2_handicap" placeholder="Member 2 Handicap" />

			<input type="text" name="member_3_name" placeholder="Member 3 Name" />
			<input type="email" name="member_3_email" placeholder="Member 3 Email" />
			<input type="text" name="member_3_phone" placeholder="Member 3 Phone" />
			<input type="text" name="member_3_handicap" placeholder="Member 3 Handicap" />

			<input type="text" name="member_4_name" placeholder="Member 4 Name" />
			<input type="email" name="member_4_email" placeholder="Member 4 Email" />
			<input type="text" name="member_4_phone" placeholder="Member 4 Phone" />
			<input type="text" name="member_4_handicap" placeholder="Member 4 Handicap" />

			<input type="text" name="discount_code" placeholder="Discount Code" />
			<textarea name="registration_notes" placeholder="Registration Notes"></textarea>

			<button type="submit">Submit Registration</button>
		</form>
		<?php
		return ob_get_clean();
	}

	public function handle_registration_submission() {
		if ( ! isset( $_POST['hfo_submit_golf_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hfo_submit_golf_registration_nonce'] ) ), 'hfo_submit_golf_registration' ) ) {
			wp_die( 'Invalid request.' );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
		$event    = get_post( $event_id );
		if ( $event_id <= 0 || ! $event || HFO_Golf_Event_Post_Type::POST_TYPE !== $event->post_type ) {
			wp_die( 'Invalid event.' );
		}

		if ( 'open' !== get_post_meta( $event_id, 'registration_status', true ) ) {
			wp_die( 'Registration is currently closed for this event.' );
		}

		$fields = $this->get_submission_fields();
		$data   = array();
		foreach ( $fields as $key => $type ) {
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			switch ( $type ) {
				case 'email':
					$data[ $key ] = sanitize_email( $raw );
					break;
				case 'textarea':
					$data[ $key ] = sanitize_textarea_field( $raw );
					break;
				default:
					$data[ $key ] = sanitize_text_field( $raw );
					break;
			}
		}

		$subtotal = (float) get_post_meta( $event_id, 'team_price', true );
		$discount = $this->calculate_discount_amount( $event_id, $subtotal, $data['discount_code'] );
		$total    = max( 0, $subtotal - $discount );

		$post_id = wp_insert_post(
			array(
				'post_type'   => HFO_Golf_Registration_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Team Registration - %s', $data['main_contact_name'] ),
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_die( 'Could not save registration.' );
		}

		$meta = array_merge(
			$data,
			array(
				'related_event'        => $event_id,
				'registration_type'   => 'team',
				'registration_status' => 'submitted',
				'payment_status'      => 'unpaid',
				'subtotal_amount'     => $subtotal,
				'discount_amount'     => $discount,
				'total_amount'        => $total,
			)
		);

		if ( ! empty( $data['discount_code'] ) ) {
			$meta['discount_code_used'] = $data['discount_code'];
		}

		foreach ( $meta as $meta_key => $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}

		$this->send_registration_emails( $event_id, $data );

		$redirect_to = wp_get_referer();
		if ( isset( $_POST['redirect_to'] ) ) {
			$redirect_to = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ), $redirect_to );
		}
		if ( empty( $redirect_to ) ) {
			$redirect_to = home_url( '/' );
		}

		wp_safe_redirect( add_query_arg( 'hfo_registration_success', '1', $redirect_to ) );
		exit;
	}

	private function calculate_discount_amount( $event_id, $subtotal, $discount_code ) {
		$discount_code = strtolower( trim( $discount_code ) );
		$code_15       = strtolower( trim( (string) get_post_meta( $event_id, 'discount_code_15', true ) ) );
		$code_30       = strtolower( trim( (string) get_post_meta( $event_id, 'discount_code_30', true ) ) );

		if ( ! empty( $discount_code ) && ! empty( $code_30 ) && hash_equals( $code_30, $discount_code ) ) {
			return round( $subtotal * 0.3, 2 );
		}

		if ( ! empty( $discount_code ) && ! empty( $code_15 ) && hash_equals( $code_15, $discount_code ) ) {
			return round( $subtotal * 0.15, 2 );
		}

		return 0.0;
	}

	private function send_registration_emails( $event_id, $data ) {
		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf( 'New golf registration for %s', get_the_title( $event_id ) );
		$message     = sprintf( "Main Contact: %s\nEmail: %s", $data['main_contact_name'], $data['main_contact_email'] );

		wp_mail( $data['main_contact_email'], 'Registration received', 'Thank you for registering.' );
		wp_mail( $admin_email, $subject, $message );
	}

	private function get_submission_fields() {
		return array(
			'main_contact_name'    => 'text',
			'main_contact_email'   => 'email',
			'main_contact_phone'   => 'text',
			'main_contact_address' => 'text',
			'main_contact_city'    => 'text',
			'main_contact_state'   => 'text',
			'main_contact_zip'     => 'text',
			'captain_name'         => 'text',
			'captain_email'        => 'email',
			'captain_phone'        => 'text',
			'captain_handicap'     => 'text',
			'member_2_name'        => 'text',
			'member_2_email'       => 'email',
			'member_2_phone'       => 'text',
			'member_2_handicap'    => 'text',
			'member_3_name'        => 'text',
			'member_3_email'       => 'email',
			'member_3_phone'       => 'text',
			'member_3_handicap'    => 'text',
			'member_4_name'        => 'text',
			'member_4_email'       => 'email',
			'member_4_phone'       => 'text',
			'member_4_handicap'    => 'text',
			'discount_code'        => 'text',
			'registration_notes'   => 'textarea',
		);
	}
}
