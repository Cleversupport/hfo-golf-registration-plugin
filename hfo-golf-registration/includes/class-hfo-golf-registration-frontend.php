<?php
/**
 * Frontend registration shortcode and form handler.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HFO_Golf_Registration_Frontend {
	const SHORTCODE_TAG = 'hfo_golf_registration';
	const SHORTCODE_ALIAS = 'hfo_golf_registration_form';
	const ACTION = 'hfo_golf_submit_registration';
	const NONCE_ACTION = 'hfo_golf_registration_submit';
	const NONCE_NAME = 'hfo_golf_registration_nonce';

	public function register_hooks() {
		add_shortcode( self::SHORTCODE_TAG, array( $this, 'render_shortcode' ) );
		add_shortcode( self::SHORTCODE_ALIAS, array( $this, 'render_shortcode' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submission' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submission' ) );
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'event_id' => 0 ), $atts, self::SHORTCODE_TAG );
		$event_id = absint( $atts['event_id'] );

		if ( 0 === $event_id || HFO_Golf_Event_Post_Type::POST_TYPE !== get_post_type( $event_id ) ) {
			return '<p>' . esc_html__( 'Invalid event.', 'hfo-golf-registration' ) . '</p>';
		}

		if ( isset( $_GET['registration_success'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['registration_success'] ) ) ) {
			return '<p>' . esc_html__( 'Thank you for your registration.', 'hfo-golf-registration' ) . '</p>';
		}

		if ( 'open' !== get_post_meta( $event_id, 'registration_status', true ) ) {
			return '<p>' . esc_html__( 'Registration is currently closed for this event.', 'hfo-golf-registration' ) . '</p>';
		}

		$current_page_id = get_the_ID();
		$redirect_to = $current_page_id ? get_permalink( $current_page_id ) : '';
		if ( empty( $redirect_to ) ) {
			$redirect_to = home_url( '/' );
		}

		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<?php foreach ( $this->get_fields() as $key => $label ) : ?>
				<p>
					<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label><br />
					<?php echo $this->render_input( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
			<?php endforeach; ?>
			<button type="submit"><?php esc_html_e( 'Submit Registration', 'hfo-golf-registration' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	public function handle_submission() {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid submission.', 'hfo-golf-registration' ) );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
		if ( 0 === $event_id || HFO_Golf_Event_Post_Type::POST_TYPE !== get_post_type( $event_id ) ) {
			wp_die( esc_html__( 'Invalid event.', 'hfo-golf-registration' ) );
		}

		$main_contact_name = isset( $_POST['main_contact_name'] ) ? sanitize_text_field( wp_unslash( $_POST['main_contact_name'] ) ) : '';
		$main_contact_email = isset( $_POST['main_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['main_contact_email'] ) ) : '';
		$main_contact_phone = isset( $_POST['main_contact_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['main_contact_phone'] ) ) : '';

		if ( '' === $main_contact_name ) { wp_die( esc_html__( 'Main contact name is required.', 'hfo-golf-registration' ) ); }
		if ( '' === $main_contact_email || ! is_email( $main_contact_email ) ) { wp_die( esc_html__( 'A valid main contact email is required.', 'hfo-golf-registration' ) ); }
		if ( '' === $main_contact_phone ) { wp_die( esc_html__( 'Main contact phone is required.', 'hfo-golf-registration' ) ); }

		$post_id = wp_insert_post(array('post_type'=>HFO_Golf_Registration_Post_Type::POST_TYPE,'post_status'=>'publish','post_title'=>$main_contact_name));
		if ( is_wp_error( $post_id ) || 0 === $post_id ) { wp_die( esc_html__( 'Unable to save registration.', 'hfo-golf-registration' ) ); }

		update_post_meta( $post_id, 'related_event', (string) $event_id );
		foreach ( array_keys( $this->get_fields() ) as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) { continue; }
			$value = wp_unslash( $_POST[ $key ] );
			if ( 'additional_guests_details' === $key ) { $value = sanitize_textarea_field( $value ); }
			elseif ( in_array( $key, $this->get_email_fields(), true ) ) { $value = sanitize_email( $value ); }
			elseif ( in_array( $key, $this->get_quantity_fields(), true ) ) { $value = (string) max( 0, absint( $value ) ); }
			else { $value = sanitize_text_field( $value ); }
			if ( in_array( $key, array( 'subtotal', 'discount_amount', 'grand_total' ), true ) ) { $value = number_format( (float) $value, 2, '.', '' ); }
			update_post_meta( $post_id, $key, $value );
		}

		$this->send_notification_email( $post_id, $main_contact_email, $main_contact_name );

		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
		$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/' ) );
		wp_safe_redirect( add_query_arg( 'registration_success', '1', $redirect_to ) );
		exit;
	}

	private function render_input( $key ) {
		if ( 'additional_guests_details' === $key ) { return '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"></textarea>'; }
		$type = 'text';
		$attrs = '';
		if ( in_array( $key, $this->get_email_fields(), true ) ) { $type = 'email'; }
		if ( in_array( $key, $this->get_quantity_fields(), true ) ) { $type = 'number'; $attrs = ' min="0" step="1"'; }
		return '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '"' . $attrs . ' />';
	}

	private function get_email_fields() { return array( 'main_contact_email', 'captain_email', 'member_2_email', 'member_3_email', 'member_4_email', 'sponsor_email' ); }
	private function get_quantity_fields() { return array( 'additional_lunch_count', 'additional_dinner_count', 'golf_qty', 'lunch_qty', 'dinner_qty', 'platinum_sponsor_qty', 'gold_sponsor_qty', 'silver_sponsor_qty', 'tee_sponsor_qty' ); }
	private function get_fields() {
		return array(
			'registration_type'=>'Registration Type','registration_status'=>'Registration Status','main_contact_name'=>'Main Contact Name','main_contact_email'=>'Main Contact Email','main_contact_phone'=>'Main Contact Phone','main_contact_address'=>'Main Contact Address','main_contact_city'=>'Main Contact City','main_contact_state'=>'Main Contact State','main_contact_zip'=>'Main Contact ZIP','captain_name'=>'Captain Name','captain_email'=>'Captain Email','captain_phone'=>'Captain Phone','captain_address'=>'Captain Address','captain_city'=>'Captain City','captain_state'=>'Captain State','captain_zip'=>'Captain ZIP','captain_handicap'=>'Captain Handicap','captain_participation_type'=>'Captain Participation Type','member_2_name'=>'Member #2 Name','member_2_email'=>'Member #2 Email','member_2_phone'=>'Member #2 Phone','member_2_address'=>'Member #2 Address','member_2_city'=>'Member #2 City','member_2_state'=>'Member #2 State','member_2_zip'=>'Member #2 ZIP','member_2_handicap'=>'Member #2 Handicap','member_2_participation_type'=>'Member #2 Participation Type','member_3_name'=>'Member #3 Name','member_3_email'=>'Member #3 Email','member_3_phone'=>'Member #3 Phone','member_3_address'=>'Member #3 Address','member_3_city'=>'Member #3 City','member_3_state'=>'Member #3 State','member_3_zip'=>'Member #3 ZIP','member_3_handicap'=>'Member #3 Handicap','member_3_participation_type'=>'Member #3 Participation Type','member_4_name'=>'Member #4 Name','member_4_email'=>'Member #4 Email','member_4_phone'=>'Member #4 Phone','member_4_address'=>'Member #4 Address','member_4_city'=>'Member #4 City','member_4_state'=>'Member #4 State','member_4_zip'=>'Member #4 ZIP','member_4_handicap'=>'Member #4 Handicap','member_4_participation_type'=>'Member #4 Participation Type','additional_lunch_count'=>'Additional Lunch Count','additional_dinner_count'=>'Additional Dinner Count','additional_guests_details'=>'Additional Guests Details','sponsorship_level'=>'Sponsorship Level','sponsorship_amount'=>'Sponsorship Amount','sponsor_program_name'=>'Sponsor Program Name','sponsor_contact_name'=>'Sponsor Contact Name','sponsor_email'=>'Sponsor Email','sponsor_phone'=>'Sponsor Phone','sponsor_address'=>'Sponsor Address','sponsor_city'=>'Sponsor City','sponsor_state'=>'Sponsor State','sponsor_zip'=>'Sponsor ZIP','golf_qty'=>'Golf Quantity','lunch_qty'=>'Lunch Quantity','dinner_qty'=>'Dinner Quantity','platinum_sponsor_qty'=>'Platinum Sponsor Qty','gold_sponsor_qty'=>'Gold Sponsor Qty','silver_sponsor_qty'=>'Silver Sponsor Qty','tee_sponsor_qty'=>'Tee Sponsor Qty','discount_code_used'=>'Discount Code Used','subtotal'=>'Subtotal','discount_amount'=>'Discount Amount','grand_total'=>'Grand Total'
		);
	}
	private function send_notification_email( $post_id, $email, $name ) { wp_mail( $email, 'Registration Received', 'Thank you ' . $name . '. Registration #' . $post_id . ' has been received.' ); }
}
