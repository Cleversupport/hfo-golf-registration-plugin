<?php
/**
 * Frontend multi-step golf registration form shortcode.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and processes the [hfo_golf_registration_form] shortcode.
 */
class HFO_Golf_Registration_Form_Shortcode {

	/**
	 * Admin-post action used by the frontend form.
	 *
	 * @var string
	 */
	const ACTION = 'hfo_golf_registration_form_submit';

	/**
	 * Nonce action used by the frontend form.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'hfo_golf_registration_form_submit';

	/**
	 * Nonce field used by the frontend form.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'hfo_golf_registration_form_nonce';

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'hfo_golf_registration_form', array( $this, 'render_shortcode' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submission' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submission' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Renders the multi-step registration form.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'event_id' => 0,
			),
			$atts,
			'hfo_golf_registration_form'
		);

		$event_id = absint( $atts['event_id'] );

		if ( ! $this->get_valid_open_event( $event_id ) ) {
			return '<p class="hfo-golf-registration-message">' . esc_html__( 'Registration is not currently available for this event.', 'hfo-golf-registration' ) . '</p>';
		}

		$redirect_to = get_permalink();
		if ( ! $redirect_to ) {
			$redirect_to = home_url( '/' );
		}

		$event_prices = $this->get_event_prices( $event_id );

		ob_start();
		?>
		<form class="hfo-golf-registration-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-hfo-golf-registration-form data-hfo-event-prices="<?php echo esc_attr( wp_json_encode( $event_prices ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="related_event" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

			<?php $this->render_event_summary_card( $event_id ); ?>

			<?php $this->render_step_header(); ?>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step>
				<h3><?php esc_html_e( 'Step 1: Registration Type', 'hfo-golf-registration' ); ?></h3>
				<?php
				$this->render_select_field(
					'registration_type',
					esc_html__( 'Registration Type', 'hfo-golf-registration' ),
					array(
						'team'         => esc_html__( 'Team', 'hfo-golf-registration' ),
						'individual'   => esc_html__( 'Individual', 'hfo-golf-registration' ),
						'sponsor_only' => esc_html__( 'Sponsor Only', 'hfo-golf-registration' ),
					),
					true
				);
				?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step hidden>
				<h3><?php esc_html_e( 'Step 2: Main Contact', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_text_field( 'main_contact_name', esc_html__( 'Name', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_email_field( 'main_contact_email', esc_html__( 'Email', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_text_field( 'main_contact_phone', esc_html__( 'Phone', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'main_contact_address', esc_html__( 'Address', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'main_contact_city', esc_html__( 'City', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'main_contact_state', esc_html__( 'State', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'main_contact_zip', esc_html__( 'ZIP', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-hfo-step-type="participant" data-hfo-participant-step="captain" hidden>
				<h3><?php esc_html_e( 'Step 3: Captain', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_participant_fields( 'captain', esc_html__( 'Captain', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-hfo-step-type="participant" data-hfo-participant-step="member_2" hidden>
				<h3><?php esc_html_e( 'Step 4: Member #2', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_participant_fields( 'member_2', esc_html__( 'Member #2', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-hfo-step-type="participant" data-hfo-participant-step="member_3" hidden>
				<h3><?php esc_html_e( 'Step 5: Member #3', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_participant_fields( 'member_3', esc_html__( 'Member #3', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-hfo-step-type="participant" data-hfo-participant-step="member_4" hidden>
				<h3><?php esc_html_e( 'Step 6: Member #4', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_participant_fields( 'member_4', esc_html__( 'Member #4', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-hfo-step-type="guests" hidden>
				<h3><?php esc_html_e( 'Step 7: Additional Guests', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_number_field( 'additional_lunch_count', esc_html__( 'Additional Lunch Count', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_number_field( 'additional_dinner_count', esc_html__( 'Additional Dinner Count', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_textarea_field( 'additional_guests_details', esc_html__( 'Additional Guests Details', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-hfo-step-type="sponsorship" hidden>
				<h3><?php esc_html_e( 'Step 8: Sponsorship', 'hfo-golf-registration' ); ?></h3>
				<?php
				$this->render_select_field(
					'sponsorship_level',
					esc_html__( 'Sponsorship Level', 'hfo-golf-registration' ),
					array(
						''         => esc_html__( 'None', 'hfo-golf-registration' ),
						'platinum' => esc_html__( 'Platinum Sponsor', 'hfo-golf-registration' ),
						'gold'     => esc_html__( 'Gold Sponsor', 'hfo-golf-registration' ),
						'silver'   => esc_html__( 'Silver Sponsor', 'hfo-golf-registration' ),
						'tee'      => esc_html__( 'Tee Sponsor', 'hfo-golf-registration' ),
					)
				);
				?>
				<?php $this->render_number_field( 'sponsorship_amount', esc_html__( 'Sponsorship Amount', 'hfo-golf-registration' ), '0.01' ); ?>
				<?php $this->render_text_field( 'sponsor_program_name', esc_html__( 'Sponsor Program Name', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'sponsor_contact_name', esc_html__( 'Sponsor Contact Name', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_email_field( 'sponsor_email', esc_html__( 'Sponsor Email', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'sponsor_phone', esc_html__( 'Sponsor Phone', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'sponsor_address', esc_html__( 'Sponsor Address', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'sponsor_city', esc_html__( 'Sponsor City', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'sponsor_state', esc_html__( 'Sponsor State', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'sponsor_zip', esc_html__( 'Sponsor ZIP', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-hfo-step-type="review" hidden>
				<h3><?php esc_html_e( 'Step 9: Review & Checkout', 'hfo-golf-registration' ); ?></h3>
				<p class="hfo-golf-registration-help"><?php esc_html_e( 'Review your selections below. Final totals are recalculated securely at checkout.', 'hfo-golf-registration' ); ?></p>
				<dl class="hfo-golf-registration-review" data-hfo-review-summary>
					<dt><?php esc_html_e( 'Registration Type', 'hfo-golf-registration' ); ?></dt><dd data-hfo-review="registration_type">&mdash;</dd>
					<dt><?php esc_html_e( 'Golf Quantity', 'hfo-golf-registration' ); ?></dt><dd data-hfo-review="golf_qty">0</dd>
					<dt><?php esc_html_e( 'Lunch Quantity', 'hfo-golf-registration' ); ?></dt><dd data-hfo-review="lunch_qty">0</dd>
					<dt><?php esc_html_e( 'Dinner Quantity', 'hfo-golf-registration' ); ?></dt><dd data-hfo-review="dinner_qty">0</dd>
					<dt><?php esc_html_e( 'Sponsor Level', 'hfo-golf-registration' ); ?></dt><dd data-hfo-review="sponsorship_level"><?php esc_html_e( 'None', 'hfo-golf-registration' ); ?></dd>
					<dt><?php esc_html_e( 'Subtotal', 'hfo-golf-registration' ); ?></dt><dd data-hfo-review="subtotal">$0.00</dd>
					<dt><?php esc_html_e( 'Discount Amount', 'hfo-golf-registration' ); ?></dt><dd data-hfo-review="discount_amount">$0.00</dd>
					<dt><?php esc_html_e( 'Grand Total', 'hfo-golf-registration' ); ?></dt><dd data-hfo-review="grand_total">$0.00</dd>
				</dl>
				<button class="hfo-golf-registration-submit" type="submit"><?php esc_html_e( 'Continue to Checkout', 'hfo-golf-registration' ); ?></button>
			</section>

			<div class="hfo-golf-registration-navigation">
				<button type="button" data-hfo-golf-registration-back><?php esc_html_e( 'Back', 'hfo-golf-registration' ); ?></button>
				<button type="button" data-hfo-golf-registration-next><?php esc_html_e( 'Next', 'hfo-golf-registration' ); ?></button>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handles the frontend form submit.
	 *
	 * @return void
	 */
	public function handle_submission() {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid registration request.', 'hfo-golf-registration' ) );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
		$event    = $this->get_valid_open_event( $event_id );

		if ( ! $event ) {
			wp_die( esc_html__( 'Registration is not currently available for this event.', 'hfo-golf-registration' ) );
		}

		$meta = $this->get_sanitized_submission_meta( $event_id );

		if ( ! is_email( $meta['main_contact_email'] ) ) {
			wp_die( esc_html__( 'Please enter a valid main contact email address.', 'hfo-golf-registration' ) );
		}

		if ( 'sponsor_only' === $meta['registration_type'] && '' === $meta['sponsorship_level'] ) {
			wp_die( esc_html__( 'Please select a sponsorship level for Sponsor Only registration.', 'hfo-golf-registration' ) );
		}

		if ( ! $this->has_billable_checkout_items( $meta ) ) {
			wp_die( esc_html__( 'Please select at least one golfer, guest meal, or sponsorship before continuing to checkout.', 'hfo-golf-registration' ) );
		}

		if ( 0 >= (float) $meta['grand_total'] ) {
			wp_die( esc_html__( 'Unable to continue to checkout with a zero total registration.', 'hfo-golf-registration' ) );
		}

		$registration_id = wp_insert_post(
			array(
				'post_type'   => HFO_Golf_Registration_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: %s: main contact name. */
					__( 'Registration - %s', 'hfo-golf-registration' ),
					'' !== $meta['main_contact_name'] ? $meta['main_contact_name'] : $meta['main_contact_email']
				),
			),
			true
		);

		if ( is_wp_error( $registration_id ) || ! $registration_id ) {
			wp_die( esc_html__( 'Unable to save registration.', 'hfo-golf-registration' ) );
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $registration_id, $key, $value );
		}

		$checkout_handler = new HFO_Golf_Registration_Checkout_Handler();
		$checkout_handler->send_registration_to_checkout( $registration_id );
	}

	/**
	 * Enqueues frontend form assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'hfo-golf-registration-form',
			plugins_url( 'assets/css/hfo-golf-registration-form.css', HFO_GOLF_REGISTRATION_FILE ),
			array(),
			HFO_GOLF_REGISTRATION_VERSION
		);

		wp_enqueue_script(
			'hfo-golf-registration-form',
			plugins_url( 'assets/js/hfo-golf-registration-form.js', HFO_GOLF_REGISTRATION_FILE ),
			array(),
			HFO_GOLF_REGISTRATION_VERSION,
			true
		);
	}

	/**
	 * Renders an event summary card for the selected event.
	 *
	 * @param int $event_id Event post ID.
	 * @return void
	 */
	private function render_event_summary_card( $event_id ) {
		$event_title = get_the_title( $event_id );
		$event_date  = (string) get_post_meta( $event_id, 'event_date', true );
		$start_time  = (string) get_post_meta( $event_id, 'event_start_time', true );
		$end_time    = (string) get_post_meta( $event_id, 'event_end_time', true );
		$location    = (string) get_post_meta( $event_id, 'event_location', true );
		$caption     = (string) get_post_meta( $event_id, 'event_caption', true );
		$time_range  = trim( $start_time . ( '' !== $start_time && '' !== $end_time ? ' - ' : '' ) . $end_time );
		?>
		<aside class="hfo-golf-registration-event-summary" aria-label="<?php esc_attr_e( 'Event Summary', 'hfo-golf-registration' ); ?>">
			<h2><?php echo esc_html( $event_title ); ?></h2>
			<?php if ( '' !== $caption ) : ?>
				<p class="hfo-golf-registration-event-summary__caption"><?php echo esc_html( $caption ); ?></p>
			<?php endif; ?>
			<dl>
				<?php if ( '' !== $event_date ) : ?>
					<dt><?php esc_html_e( 'Date', 'hfo-golf-registration' ); ?></dt><dd><?php echo esc_html( $event_date ); ?></dd>
				<?php endif; ?>
				<?php if ( '' !== $time_range ) : ?>
					<dt><?php esc_html_e( 'Time', 'hfo-golf-registration' ); ?></dt><dd><?php echo esc_html( $time_range ); ?></dd>
				<?php endif; ?>
				<?php if ( '' !== $location ) : ?>
					<dt><?php esc_html_e( 'Location', 'hfo-golf-registration' ); ?></dt><dd><?php echo esc_html( $location ); ?></dd>
				<?php endif; ?>
			</dl>
		</aside>
		<?php
	}

	/**
	 * Gets event prices used by the live frontend review summary.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string,float>
	 */
	private function get_event_prices( $event_id ) {
		$prices = array();

		foreach ( array( 'golf_price', 'lunch_price', 'dinner_price', 'platinum_sponsor_price', 'gold_sponsor_price', 'silver_sponsor_price', 'tee_sponsor_price' ) as $price_key ) {
			$prices[ $price_key ] = $this->get_event_price( $event_id, $price_key );
		}

		return $prices;
	}

	/**
	 * Checks whether a registration has at least one checkout line item selected.
	 *
	 * @param array<string,string> $meta Sanitized submitted meta.
	 * @return bool
	 */
	private function has_billable_checkout_items( $meta ) {
		$quantity_keys = array(
			'golf_qty',
			'lunch_qty',
			'dinner_qty',
			'platinum_sponsor_qty',
			'gold_sponsor_qty',
			'silver_sponsor_qty',
			'tee_sponsor_qty',
		);

		foreach ( $quantity_keys as $quantity_key ) {
			if ( 0 < absint( $meta[ $quantity_key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets sanitized meta and calculated quantities/totals.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string,string>
	 */
	private function get_sanitized_submission_meta( $event_id ) {
		$registration_type = $this->sanitize_choice( 'registration_type', array( 'team', 'individual', 'sponsor_only' ), 'individual' );
		$sponsorship_level = $this->sanitize_choice( 'sponsorship_level', array( 'platinum', 'gold', 'silver', 'tee', '' ), '' );

		$meta = array(
			'related_event'             => (string) $event_id,
			'registration_type'         => $registration_type,
			'registration_status'       => 'submitted',
			'payment_status'            => 'pending',
			'main_contact_name'         => $this->sanitize_post_text( 'main_contact_name' ),
			'main_contact_email'        => sanitize_email( $this->sanitize_post_text( 'main_contact_email' ) ),
			'main_contact_phone'        => $this->sanitize_post_text( 'main_contact_phone' ),
			'main_contact_address'      => $this->sanitize_post_text( 'main_contact_address' ),
			'main_contact_city'         => $this->sanitize_post_text( 'main_contact_city' ),
			'main_contact_state'        => $this->sanitize_post_text( 'main_contact_state' ),
			'main_contact_zip'          => $this->sanitize_post_text( 'main_contact_zip' ),
			'additional_lunch_count'    => (string) $this->sanitize_post_count( 'additional_lunch_count' ),
			'additional_dinner_count'   => (string) $this->sanitize_post_count( 'additional_dinner_count' ),
			'additional_guests_details' => $this->sanitize_post_textarea( 'additional_guests_details' ),
			'sponsorship_level'         => $sponsorship_level,
			'sponsorship_amount'        => $this->sanitize_post_amount( 'sponsorship_amount' ),
			'sponsor_program_name'      => $this->sanitize_post_text( 'sponsor_program_name' ),
			'sponsor_contact_name'      => $this->sanitize_post_text( 'sponsor_contact_name' ),
			'sponsor_email'             => sanitize_email( $this->sanitize_post_text( 'sponsor_email' ) ),
			'sponsor_phone'             => $this->sanitize_post_text( 'sponsor_phone' ),
			'sponsor_address'           => $this->sanitize_post_text( 'sponsor_address' ),
			'sponsor_city'              => $this->sanitize_post_text( 'sponsor_city' ),
			'sponsor_state'             => $this->sanitize_post_text( 'sponsor_state' ),
			'sponsor_zip'               => $this->sanitize_post_text( 'sponsor_zip' ),
			'discount_code_used'        => '',
			'discount_amount'           => '0.00',
			'woocommerce_order_id'       => '0',
		);

		foreach ( array( 'captain', 'member_2', 'member_3', 'member_4' ) as $participant ) {
			$meta = array_merge( $meta, $this->get_sanitized_participant_meta( $participant ) );
		}

		$calculated = $this->calculate_quantities_and_totals( $event_id, $meta );

		return array_merge( $meta, $calculated );
	}

	/**
	 * Gets sanitized participant meta for a participant prefix.
	 *
	 * @param string $prefix Participant meta key prefix.
	 * @return array<string,string>
	 */
	private function get_sanitized_participant_meta( $prefix ) {
		return array(
			$prefix . '_name'               => $this->sanitize_post_text( $prefix . '_name' ),
			$prefix . '_email'              => sanitize_email( $this->sanitize_post_text( $prefix . '_email' ) ),
			$prefix . '_phone'              => $this->sanitize_post_text( $prefix . '_phone' ),
			$prefix . '_address'            => $this->sanitize_post_text( $prefix . '_address' ),
			$prefix . '_city'               => $this->sanitize_post_text( $prefix . '_city' ),
			$prefix . '_state'              => $this->sanitize_post_text( $prefix . '_state' ),
			$prefix . '_zip'                => $this->sanitize_post_text( $prefix . '_zip' ),
			$prefix . '_handicap'           => $this->sanitize_post_text( $prefix . '_handicap' ),
			$prefix . '_participation_type' => $this->sanitize_choice( $prefix . '_participation_type', array( '', 'golf', 'lunch', 'dinner' ), '' ),
		);
	}

	/**
	 * Calculates basic checkout quantities and totals from submitted meta.
	 *
	 * @param int                  $event_id Event post ID.
	 * @param array<string,string> $meta     Sanitized submitted meta.
	 * @return array<string,string>
	 */
	private function calculate_quantities_and_totals( $event_id, $meta ) {
		$golf_qty = 0;
		foreach ( array( 'captain', 'member_2', 'member_3', 'member_4' ) as $participant ) {
			if ( 'golf' === $meta[ $participant . '_participation_type' ] ) {
				$golf_qty++;
			}
		}

		$lunch_qty  = absint( $meta['additional_lunch_count'] );
		$dinner_qty = absint( $meta['additional_dinner_count'] );

		$sponsor_quantities = array(
			'platinum_sponsor_qty' => '0',
			'gold_sponsor_qty'     => '0',
			'silver_sponsor_qty'   => '0',
			'tee_sponsor_qty'      => '0',
		);

		if ( '' !== $meta['sponsorship_level'] ) {
			$sponsor_quantities[ $meta['sponsorship_level'] . '_sponsor_qty' ] = '1';
		}

		$subtotal = ( $golf_qty * $this->get_event_price( $event_id, 'golf_price' ) )
			+ ( $lunch_qty * $this->get_event_price( $event_id, 'lunch_price' ) )
			+ ( $dinner_qty * $this->get_event_price( $event_id, 'dinner_price' ) )
			+ ( absint( $sponsor_quantities['platinum_sponsor_qty'] ) * $this->get_event_price( $event_id, 'platinum_sponsor_price' ) )
			+ ( absint( $sponsor_quantities['gold_sponsor_qty'] ) * $this->get_event_price( $event_id, 'gold_sponsor_price' ) )
			+ ( absint( $sponsor_quantities['silver_sponsor_qty'] ) * $this->get_event_price( $event_id, 'silver_sponsor_price' ) )
			+ ( absint( $sponsor_quantities['tee_sponsor_qty'] ) * $this->get_event_price( $event_id, 'tee_sponsor_price' ) );

		return array_merge(
			array(
				'golf_qty'    => (string) $golf_qty,
				'lunch_qty'   => (string) $lunch_qty,
				'dinner_qty'  => (string) $dinner_qty,
				'subtotal'    => number_format( (float) $subtotal, 2, '.', '' ),
				'grand_total' => number_format( (float) $subtotal, 2, '.', '' ),
			),
			$sponsor_quantities
		);
	}

	/**
	 * Gets a valid published and open golf event.
	 *
	 * @param int $event_id Event post ID.
	 * @return WP_Post|false
	 */
	private function get_valid_open_event( $event_id ) {
		$event = get_post( $event_id );

		if ( ! $event || HFO_Golf_Event_Post_Type::POST_TYPE !== $event->post_type || 'publish' !== $event->post_status ) {
			return false;
		}

		$registration_status = sanitize_key( (string) get_post_meta( $event_id, 'registration_status', true ) );

		return 'open' === $registration_status ? $event : false;
	}

	/**
	 * Gets an event price as a float.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $meta_key Price meta key.
	 * @return float
	 */
	private function get_event_price( $event_id, $meta_key ) {
		return (float) get_post_meta( $event_id, $meta_key, true );
	}

	/**
	 * Sanitizes a posted choice against allowed values.
	 *
	 * @param string        $key     Posted key.
	 * @param array<int,string> $allowed Allowed values.
	 * @param string        $default Default value.
	 * @return string
	 */
	private function sanitize_choice( $key, $allowed, $default ) {
		$value = sanitize_key( $this->sanitize_post_text( $key ) );

		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Sanitizes a posted text field.
	 *
	 * @param string $key Posted key.
	 * @return string
	 */
	private function sanitize_post_text( $key ) {
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitizes a posted textarea field.
	 *
	 * @param string $key Posted key.
	 * @return string
	 */
	private function sanitize_post_textarea( $key ) {
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		return sanitize_textarea_field( $value );
	}

	/**
	 * Sanitizes a posted count field.
	 *
	 * @param string $key Posted key.
	 * @return int
	 */
	private function sanitize_post_count( $key ) {
		return max( 0, absint( $this->sanitize_post_text( $key ) ) );
	}

	/**
	 * Sanitizes a posted amount field.
	 *
	 * @param string $key Posted key.
	 * @return string
	 */
	private function sanitize_post_amount( $key ) {
		$value  = $this->sanitize_post_text( $key );
		$amount = (float) preg_replace( '/[^0-9.\-]/', '', $value );

		return number_format( max( 0, $amount ), 2, '.', '' );
	}

	/**
	 * Renders the form step header.
	 *
	 * @return void
	 */
	private function render_step_header() {
		?>
		<ol class="hfo-golf-registration-steps" data-hfo-golf-registration-steps>
			<li class="is-active"><?php esc_html_e( 'Registration Type', 'hfo-golf-registration' ); ?></li>
			<li><?php esc_html_e( 'Main Contact', 'hfo-golf-registration' ); ?></li>
			<li data-hfo-step-label="captain"><?php esc_html_e( 'Captain', 'hfo-golf-registration' ); ?></li>
			<li data-hfo-step-label="member_2"><?php esc_html_e( 'Member #2', 'hfo-golf-registration' ); ?></li>
			<li data-hfo-step-label="member_3"><?php esc_html_e( 'Member #3', 'hfo-golf-registration' ); ?></li>
			<li data-hfo-step-label="member_4"><?php esc_html_e( 'Member #4', 'hfo-golf-registration' ); ?></li>
			<li data-hfo-step-label="guests"><?php esc_html_e( 'Additional Guests', 'hfo-golf-registration' ); ?></li>
			<li><?php esc_html_e( 'Sponsorship', 'hfo-golf-registration' ); ?></li>
			<li><?php esc_html_e( 'Review & Checkout', 'hfo-golf-registration' ); ?></li>
		</ol>
		<?php
	}

	/**
	 * Renders participant fields.
	 *
	 * @param string $prefix Participant prefix.
	 * @param string $legend Fieldset legend.
	 * @return void
	 */
	private function render_participant_fields( $prefix, $legend ) {
		?>
		<fieldset class="hfo-golf-registration-fieldset">
			<legend><?php echo esc_html( $legend ); ?></legend>
			<?php $this->render_text_field( $prefix . '_name', esc_html__( 'Name', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_email_field( $prefix . '_email', esc_html__( 'Email', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_phone', esc_html__( 'Phone', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_address', esc_html__( 'Address', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_city', esc_html__( 'City', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_state', esc_html__( 'State', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_zip', esc_html__( 'ZIP', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_handicap', esc_html__( 'Handicap', 'hfo-golf-registration' ) ); ?>
			<?php
			$this->render_select_field(
				$prefix . '_participation_type',
				esc_html__( 'Participation Type', 'hfo-golf-registration' ),
				array(
					''       => esc_html__( 'None', 'hfo-golf-registration' ),
					'golf'   => esc_html__( 'Golf', 'hfo-golf-registration' ),
					'lunch'  => esc_html__( 'Lunch', 'hfo-golf-registration' ),
					'dinner' => esc_html__( 'Dinner', 'hfo-golf-registration' ),
				)
			);
			?>
		</fieldset>
		<?php
	}

	/**
	 * Renders a text input field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param bool   $required Whether the field is required.
	 * @return void
	 */
	private function render_text_field( $name, $label, $required = false ) {
		$this->render_input_field( $name, $label, 'text', $required );
	}

	/**
	 * Renders an email input field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param bool   $required Whether the field is required.
	 * @return void
	 */
	private function render_email_field( $name, $label, $required = false ) {
		$this->render_input_field( $name, $label, 'email', $required );
	}

	/**
	 * Renders a number input field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param string $step Number step.
	 * @return void
	 */
	private function render_number_field( $name, $label, $step = '1' ) {
		$this->render_input_field( $name, $label, 'number', false, $step );
	}

	/**
	 * Renders a basic input field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param string $type Input type.
	 * @param bool   $required Whether the field is required.
	 * @param string $step Number step.
	 * @return void
	 */
	private function render_input_field( $name, $label, $type, $required = false, $step = '' ) {
		?>
		<p class="hfo-golf-registration-field">
			<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<input id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"<?php echo $required ? ' required' : ''; ?><?php echo 'number' === $type ? ' min="0"' : ''; ?><?php echo '' !== $step ? ' step="' . esc_attr( $step ) . '"' : ''; ?> />
		</p>
		<?php
	}

	/**
	 * Renders a select field.
	 *
	 * @param string               $name Field name.
	 * @param string               $label Field label.
	 * @param array<string,string> $options Select options.
	 * @param bool                 $required Whether the field is required.
	 * @return void
	 */
	private function render_select_field( $name, $label, $options, $required = false ) {
		?>
		<p class="hfo-golf-registration-field">
			<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $required ? ' required' : ''; ?>>
				<?php foreach ( $options as $value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Renders a textarea field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @return void
	 */
	private function render_textarea_field( $name, $label ) {
		?>
		<p class="hfo-golf-registration-field hfo-golf-registration-field--wide">
			<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<textarea id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4"></textarea>
		</p>
		<?php
	}
}
