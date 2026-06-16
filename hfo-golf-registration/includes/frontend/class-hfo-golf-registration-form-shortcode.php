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
	 * Option key for custom frontend CSS.
	 *
	 * @var string
	 */
	const CUSTOM_FRONTEND_CSS_OPTION = 'hfo_golf_registration_custom_frontend_css';

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'hfo_golf_registration_form', array( $this, 'render_shortcode' ) );
		add_shortcode( 'hfo_golf_event_details', array( $this, 'render_event_details_shortcode' ) );
		add_shortcode( 'hfo_golf_event_pricing', array( $this, 'render_event_pricing_shortcode' ) );
		add_shortcode( 'hfo_golf_event_schedule', array( $this, 'render_event_schedule_shortcode' ) );
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

		ob_start();
		?>
		<form class="hfo-golf-registration-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-hfo-golf-registration-form data-golf-price="<?php echo esc_attr( $this->get_event_price( $event_id, 'golf_price' ) ); ?>" data-lunch-price="<?php echo esc_attr( $this->get_event_price( $event_id, 'lunch_price' ) ); ?>" data-dinner-price="<?php echo esc_attr( $this->get_event_price( $event_id, 'dinner_price' ) ); ?>" data-platinum-sponsor-price="<?php echo esc_attr( $this->get_event_price( $event_id, 'platinum_sponsor_price' ) ); ?>" data-gold-sponsor-price="<?php echo esc_attr( $this->get_event_price( $event_id, 'gold_sponsor_price' ) ); ?>" data-silver-sponsor-price="<?php echo esc_attr( $this->get_event_price( $event_id, 'silver_sponsor_price' ) ); ?>" data-tee-sponsor-price="<?php echo esc_attr( $this->get_event_price( $event_id, 'tee_sponsor_price' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="related_event" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>

			<?php $this->render_step_header(); ?>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-step-key="registration_type">
				<h3><?php esc_html_e( 'Step 1: Registration Type', 'hfo-golf-registration' ); ?></h3>
				<?php
				$this->render_select_field(
					'registration_type',
					esc_html__( 'Registration Type', 'hfo-golf-registration' ),
					array(
						'team'         => esc_html__( 'Team', 'hfo-golf-registration' ),
						'individual'   => esc_html__( 'Individual', 'hfo-golf-registration' ),
						'sponsor_only'       => esc_html__( 'Sponsor Only', 'hfo-golf-registration' ),
						'additional_guests'  => esc_html__( 'Additional Guests', 'hfo-golf-registration' ),
					),
					true
				);
				?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-step-key="main_contact" hidden>
				<h3><?php esc_html_e( 'Step 2: Main Contact', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_text_field( 'main_contact_name', esc_html__( 'Name', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_email_field( 'main_contact_email', esc_html__( 'Email', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_text_field( 'main_contact_phone', esc_html__( 'Phone', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'main_contact_address', esc_html__( 'Address', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'main_contact_city', esc_html__( 'City', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'main_contact_state', esc_html__( 'State', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'main_contact_zip', esc_html__( 'ZIP', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-step-key="captain" hidden>
				<h3 data-participant-title data-team-label="<?php esc_attr_e( 'Step 3: Captain / Player 1', 'hfo-golf-registration' ); ?>" data-individual-label="<?php esc_attr_e( 'Step 2: Player 1', 'hfo-golf-registration' ); ?>"><?php esc_html_e( 'Step 3: Captain / Player 1', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_participant_fields( 'captain', esc_html__( 'Captain / Player 1', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-step-key="member_2" data-team-only hidden>
				<h3><?php esc_html_e( 'Step 4: Member #2', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_participant_fields( 'member_2', esc_html__( 'Member #2', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-step-key="member_3" data-team-only hidden>
				<h3><?php esc_html_e( 'Step 5: Member #3', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_participant_fields( 'member_3', esc_html__( 'Member #3', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-step-key="member_4" data-team-only hidden>
				<h3><?php esc_html_e( 'Step 6: Member #4', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_participant_fields( 'member_4', esc_html__( 'Member #4', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-step-key="additional_guests" data-player-only hidden>
				<h3><?php esc_html_e( 'Step 7: Additional Guests', 'hfo-golf-registration' ); ?></h3>
				<?php $this->render_number_field( 'additional_lunch_count', esc_html__( 'Additional Lunch Count', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_number_field( 'additional_dinner_count', esc_html__( 'Additional Dinner Count', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_textarea_field( 'additional_guests_details', esc_html__( 'Additional Guests Details', 'hfo-golf-registration' ) ); ?>
			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-step-key="sponsorship" hidden>
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
					)
				);
				?>
				<?php $this->render_checkbox_field( 'tee_sponsor_selected', esc_html__( 'Add Tee Sponsor', 'hfo-golf-registration' ) ); ?>
				<div data-hfo-golf-sponsor-fields hidden>
					<?php $this->render_text_field( 'sponsor_program_name', esc_html__( 'Sponsor Name', 'hfo-golf-registration' ) ); ?>
					<?php $this->render_text_field( 'sponsor_contact_name', esc_html__( 'Sponsor Contact Name', 'hfo-golf-registration' ) ); ?>
					<?php $this->render_email_field( 'sponsor_email', esc_html__( 'Sponsor Email', 'hfo-golf-registration' ) ); ?>
					<?php $this->render_text_field( 'sponsor_phone', esc_html__( 'Sponsor Phone', 'hfo-golf-registration' ) ); ?>
					<?php $this->render_text_field( 'sponsor_address', esc_html__( 'Sponsor Address', 'hfo-golf-registration' ) ); ?>
					<?php $this->render_text_field( 'sponsor_city', esc_html__( 'Sponsor City', 'hfo-golf-registration' ) ); ?>
					<?php $this->render_text_field( 'sponsor_state', esc_html__( 'Sponsor State', 'hfo-golf-registration' ) ); ?>
					<?php $this->render_text_field( 'sponsor_zip', esc_html__( 'Sponsor ZIP', 'hfo-golf-registration' ) ); ?>
				</div>

			</section>

			<section class="hfo-golf-registration-step" data-hfo-golf-registration-step data-step-key="review" hidden>
				<h3><?php esc_html_e( 'Step 9: Review & Checkout', 'hfo-golf-registration' ); ?></h3>
				<dl class="hfo-golf-registration-review" aria-live="polite">
					<dt><?php esc_html_e( 'Registration Type', 'hfo-golf-registration' ); ?></dt><dd data-summary="registration_type">&mdash;</dd>
					<dt><?php esc_html_e( 'Golf Quantity', 'hfo-golf-registration' ); ?></dt><dd data-summary="golf_qty">0</dd>
					<dt><?php esc_html_e( 'Player Lunch Attendance', 'hfo-golf-registration' ); ?></dt><dd data-summary="player_lunch_qty">0</dd>
					<dt><?php esc_html_e( 'Player Dinner Attendance', 'hfo-golf-registration' ); ?></dt><dd data-summary="player_dinner_qty">0</dd>
					<dt><?php esc_html_e( 'Additional Lunch Guests', 'hfo-golf-registration' ); ?></dt><dd data-summary="additional_lunch_qty">0</dd>
					<dt><?php esc_html_e( 'Additional Dinner Guests', 'hfo-golf-registration' ); ?></dt><dd data-summary="additional_dinner_qty">0</dd>
					<dt><?php esc_html_e( 'Main Sponsor Level', 'hfo-golf-registration' ); ?></dt><dd data-summary="sponsorship_level"><?php esc_html_e( 'None', 'hfo-golf-registration' ); ?></dd>
					<dt><?php esc_html_e( 'Tee Sponsor', 'hfo-golf-registration' ); ?></dt><dd data-summary="tee_sponsor_selected"><?php esc_html_e( 'No', 'hfo-golf-registration' ); ?></dd>
					<dt><?php esc_html_e( 'Subtotal', 'hfo-golf-registration' ); ?></dt><dd data-summary="subtotal">$0.00</dd>
					<dt><?php esc_html_e( 'Grand Total', 'hfo-golf-registration' ); ?></dt><dd data-summary="grand_total">$0.00</dd>
				</dl>
			</section>

			<div class="hfo-golf-registration-navigation">
				<button type="button" data-hfo-golf-registration-back><?php esc_html_e( 'Back', 'hfo-golf-registration' ); ?></button>
				<button type="button" data-hfo-golf-registration-next><?php esc_html_e( 'Next', 'hfo-golf-registration' ); ?></button>
				<button class="hfo-golf-registration-submit" type="submit" hidden><?php esc_html_e( 'Checkout', 'hfo-golf-registration' ); ?></button>
			</div>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders public event detail content for single event templates.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_event_details_shortcode( $atts ) {
		$event_id = $this->get_shortcode_event_id( $atts, 'hfo_golf_event_details' );

		if ( ! $event_id ) {
			return '';
		}

		$caption        = (string) get_post_meta( $event_id, 'event_caption', true );
		$date           = (string) get_post_meta( $event_id, 'event_date', true );
		$start_time     = (string) get_post_meta( $event_id, 'event_start_time', true );
		$end_time       = (string) get_post_meta( $event_id, 'event_end_time', true );
		$venue          = $this->get_event_venue( $event_id );
		$address_parts  = $this->get_event_address_parts( $event_id );
		$status         = sanitize_key( (string) get_post_meta( $event_id, 'registration_status', true ) );
		$why            = (string) get_post_meta( $event_id, 'why_this_tournament_matters', true );
		$included       = (string) get_post_meta( $event_id, 'whats_included', true );
		$packet_url     = (string) get_post_meta( $event_id, 'sponsor_packet_pdf_url', true );
		$flyer_url      = (string) get_post_meta( $event_id, 'event_flyer_image_url', true );

		ob_start();
		?>
		<div class="hfo-golf-event-details">
			<h2><?php echo esc_html( get_the_title( $event_id ) ); ?></h2>
			<?php if ( '' !== trim( $caption ) ) : ?>
				<p class="hfo-golf-event-caption"><?php echo esc_html( $caption ); ?></p>
			<?php endif; ?>
			<ul class="hfo-golf-event-facts">
				<?php if ( '' !== $date ) : ?><li><strong><?php esc_html_e( 'Date:', 'hfo-golf-registration' ); ?></strong> <?php echo esc_html( $date ); ?></li><?php endif; ?>
				<?php if ( '' !== $start_time || '' !== $end_time ) : ?><li><strong><?php esc_html_e( 'Time:', 'hfo-golf-registration' ); ?></strong> <?php echo esc_html( $this->format_time_range( $start_time, $end_time ) ); ?></li><?php endif; ?>
				<?php if ( '' !== $venue ) : ?><li><strong><?php esc_html_e( 'Venue:', 'hfo-golf-registration' ); ?></strong> <?php echo esc_html( $venue ); ?></li><?php endif; ?>
				<?php if ( ! empty( $address_parts ) ) : ?><li><strong><?php esc_html_e( 'Address:', 'hfo-golf-registration' ); ?></strong> <?php echo esc_html( implode( ' ', $address_parts ) ); ?></li><?php endif; ?>
				<?php if ( '' !== $status ) : ?><li><strong><?php esc_html_e( 'Registration:', 'hfo-golf-registration' ); ?></strong> <?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></li><?php endif; ?>
			</ul>
			<?php if ( '' !== trim( $why ) ) : ?>
				<section class="hfo-golf-event-section"><h3><?php esc_html_e( 'Why This Tournament Matters', 'hfo-golf-registration' ); ?></h3><?php echo wpautop( wp_kses_post( $why ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
			<?php endif; ?>
			<?php if ( '' !== trim( $included ) ) : ?>
				<section class="hfo-golf-event-section"><h3><?php esc_html_e( 'What’s Included', 'hfo-golf-registration' ); ?></h3><?php echo wpautop( wp_kses_post( $included ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></section>
			<?php endif; ?>
			<?php if ( '' !== $packet_url ) : ?>
				<p><a class="button hfo-golf-event-sponsor-packet" href="<?php echo esc_url( $packet_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Download Sponsor Packet', 'hfo-golf-registration' ); ?></a></p>
			<?php endif; ?>
			<?php if ( '' !== $flyer_url ) : ?>
				<p class="hfo-golf-event-flyer"><img src="<?php echo esc_url( $flyer_url ); ?>" alt="<?php echo esc_attr( get_the_title( $event_id ) ); ?>" /></p>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders event pricing cards for templates.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_event_pricing_shortcode( $atts ) {
		$event_id = $this->get_shortcode_event_id( $atts, 'hfo_golf_event_pricing' );

		if ( ! $event_id ) {
			return '';
		}

		$fields = array(
			'golf_price'             => __( 'Golf', 'hfo-golf-registration' ),
			'lunch_price'            => __( 'Lunch', 'hfo-golf-registration' ),
			'dinner_price'           => __( 'Dinner', 'hfo-golf-registration' ),
			'platinum_sponsor_price' => __( 'Platinum Sponsor', 'hfo-golf-registration' ),
			'gold_sponsor_price'     => __( 'Gold Sponsor', 'hfo-golf-registration' ),
			'silver_sponsor_price'   => __( 'Silver Sponsor', 'hfo-golf-registration' ),
			'tee_sponsor_price'      => __( 'Tee Sponsor', 'hfo-golf-registration' ),
		);

		ob_start();
		?>
		<div class="hfo-golf-event-pricing">
			<?php foreach ( $fields as $key => $label ) : ?>
				<?php $price = $this->get_event_price( $event_id, $key ); ?>
				<?php if ( $price > 0 ) : ?>
					<div class="hfo-golf-event-price-card"><strong><?php echo esc_html( $label ); ?></strong><span><?php echo esc_html( '$' . number_format( $price, 2 ) ); ?></span></div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the event schedule list for templates.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_event_schedule_shortcode( $atts ) {
		$event_id = $this->get_shortcode_event_id( $atts, 'hfo_golf_event_schedule' );

		if ( ! $event_id ) {
			return '';
		}

		$schedule = (string) get_post_meta( $event_id, 'event_schedule', true );

		if ( '' === trim( $schedule ) ) {
			return '';
		}

		return '<div class="hfo-golf-event-schedule"><h3>' . esc_html__( 'Event Schedule', 'hfo-golf-registration' ) . '</h3>' . wpautop( wp_kses_post( $schedule ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

		if ( in_array( $meta['registration_type'], array( 'team', 'individual', 'additional_guests' ), true ) && ! is_email( $meta['main_contact_email'] ) ) {
			wp_die( esc_html__( 'Please enter a valid main contact email address.', 'hfo-golf-registration' ) );
		}

		if ( 'sponsor_only' === $meta['registration_type'] && '' !== $meta['sponsor_email'] && ! is_email( $meta['sponsor_email'] ) ) {
			wp_die( esc_html__( 'Please enter a valid sponsor email address.', 'hfo-golf-registration' ) );
		}

		if ( 'additional_guests' === $meta['registration_type'] ) {
			foreach ( array( 'main_contact_name', 'main_contact_email', 'main_contact_phone', 'main_contact_address', 'main_contact_city', 'main_contact_state', 'main_contact_zip' ) as $required_main_contact_key ) {
				if ( '' === trim( $meta[ $required_main_contact_key ] ) ) {
					wp_die( esc_html__( 'Please complete all main contact fields for Additional Guests registration.', 'hfo-golf-registration' ) );
				}
			}

			if ( '' === trim( $meta['additional_guests_details'] ) ) {
				wp_die( esc_html__( 'Please enter additional guest details.', 'hfo-golf-registration' ) );
			}

			if ( absint( $meta['additional_lunch_count'] ) + absint( $meta['additional_dinner_count'] ) <= 0 ) {
				wp_die( esc_html__( 'Please enter at least one additional lunch or dinner guest.', 'hfo-golf-registration' ) );
			}
		}

		if ( 'sponsor_only' === $meta['registration_type'] && '' === $meta['sponsorship_level'] && '1' !== $meta['tee_sponsor_selected'] ) {
			wp_die( esc_html__( 'Please select at least one sponsorship item for Sponsor Only registration.', 'hfo-golf-registration' ) );
		}

		if ( ! $this->has_billable_checkout_items( $meta ) ) {
			wp_die( esc_html__( 'Please select at least one golfer, guest, or sponsorship before continuing to checkout.', 'hfo-golf-registration' ) );
		}

		if ( (float) $meta['grand_total'] <= 0 ) {
			wp_die( esc_html__( 'Unable to continue to checkout with a zero total registration.', 'hfo-golf-registration' ) );
		}

		$registration_title_contact = $this->get_registration_title_contact( $meta );

		$registration_id = wp_insert_post(
			array(
				'post_type'   => HFO_Golf_Registration_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: %s: main contact name. */
					__( 'Registration - %s', 'hfo-golf-registration' ),
					$registration_title_contact
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
	 * Gets the best available contact label for the registration post title.
	 *
	 * @param array<string,string> $meta Sanitized submitted meta.
	 * @return string
	 */
	private function get_registration_title_contact( $meta ) {
		foreach ( array( 'main_contact_name', 'main_contact_email', 'sponsor_program_name', 'sponsor_contact_name', 'sponsor_email' ) as $key ) {
			if ( ! empty( $meta[ $key ] ) ) {
				return $meta[ $key ];
			}
		}

		return __( 'Sponsor Only', 'hfo-golf-registration' );
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

		$custom_css = (string) get_option( self::CUSTOM_FRONTEND_CSS_OPTION, '' );

		if ( '' !== trim( $custom_css ) ) {
			wp_add_inline_style( 'hfo-golf-registration-form', $custom_css );
		}

		wp_enqueue_script(
			'hfo-golf-registration-form',
			plugins_url( 'assets/js/hfo-golf-registration-form.js', HFO_GOLF_REGISTRATION_FILE ),
			array(),
			HFO_GOLF_REGISTRATION_VERSION,
			true
		);
	}

	/**
	 * Gets sanitized meta and calculated quantities/totals.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<string,string>
	 */
	private function get_sanitized_submission_meta( $event_id ) {
		$registration_type = $this->sanitize_choice( 'registration_type', array( 'team', 'individual', 'sponsor_only', 'additional_guests' ), 'individual' );
		$sponsorship_level    = $this->sanitize_choice( 'sponsorship_level', array( 'platinum', 'gold', 'silver', '' ), '' );
		$tee_sponsor_selected = $this->sanitize_post_checkbox( 'tee_sponsor_selected' );

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
			'tee_sponsor_selected'      => $tee_sponsor_selected,
			'sponsorship_amount'        => '0.00',
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

		if ( 'individual' === $registration_type ) {
			$meta = $this->copy_captain_to_main_contact_meta( $meta );
		}

		$meta = $this->normalize_participant_meta_for_registration_type( $meta );

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
			$prefix . '_golf_selected'      => $this->sanitize_post_checkbox( $prefix . '_golf_selected' ),
			$prefix . '_lunch_selected'     => $this->sanitize_post_checkbox( $prefix . '_lunch_selected' ),
			$prefix . '_dinner_selected'    => $this->sanitize_post_checkbox( $prefix . '_dinner_selected' ),
			$prefix . '_participation_type' => $this->sanitize_choice( $prefix . '_participation_type', array( '', 'golf', 'lunch', 'dinner' ), '' ),
		);
	}

	/**
	 * Normalizes participant meta for the selected registration type.
	 *
	 * Hidden participant fields can still be posted by the browser. This keeps the
	 * backend authoritative before totals are calculated or registration meta is saved.
	 *
	 * @param array<string,string> $meta Sanitized submitted meta.
	 * @return array<string,string>
	 */
	private function normalize_participant_meta_for_registration_type( $meta ) {
		$registration_type = isset( $meta['registration_type'] ) ? $meta['registration_type'] : 'individual';

		if ( 'team' === $registration_type ) {
			return $meta;
		}

		$participants_to_clear = array();

		if ( 'individual' === $registration_type ) {
			$participants_to_clear = array( 'member_2', 'member_3', 'member_4' );
		} elseif ( 'sponsor_only' === $registration_type || 'additional_guests' === $registration_type ) {
			$participants_to_clear = array( 'captain', 'member_2', 'member_3', 'member_4' );
		}

		foreach ( $participants_to_clear as $participant ) {
			$meta = $this->clear_participant_meta( $meta, $participant );
		}

		return $meta;
	}

	/**
	 * Clears participant contact fields and selected options.
	 *
	 * @param array<string,string> $meta        Sanitized submitted meta.
	 * @param string               $participant Participant meta key prefix.
	 * @return array<string,string>
	 */
	private function clear_participant_meta( $meta, $participant ) {
		foreach ( array( 'golf_selected', 'lunch_selected', 'dinner_selected' ) as $selection_field ) {
			$meta[ $participant . '_' . $selection_field ] = '0';
		}

		foreach ( array( 'name', 'email', 'phone', 'address', 'city', 'state', 'zip', 'handicap', 'participation_type' ) as $contact_field ) {
			$meta[ $participant . '_' . $contact_field ] = '';
		}

		return $meta;
	}

	/**
	 * Copies captain/player fields to main contact fields for individual registrations.
	 *
	 * @param array<string,string> $meta Sanitized submitted meta.
	 * @return array<string,string>
	 */
	private function copy_captain_to_main_contact_meta( $meta ) {
		$field_map = array(
			'main_contact_name'    => 'captain_name',
			'main_contact_email'   => 'captain_email',
			'main_contact_phone'   => 'captain_phone',
			'main_contact_address' => 'captain_address',
			'main_contact_city'    => 'captain_city',
			'main_contact_state'   => 'captain_state',
			'main_contact_zip'     => 'captain_zip',
		);

		foreach ( $field_map as $main_contact_key => $captain_key ) {
			$meta[ $main_contact_key ] = isset( $meta[ $captain_key ] ) ? $meta[ $captain_key ] : '';
		}

		return $meta;
	}

	/**
	 * Calculates basic checkout quantities and totals from submitted meta.
	 *
	 * @param int                  $event_id Event post ID.
	 * @param array<string,string> $meta     Sanitized submitted meta.
	 * @return array<string,string>
	 */
	private function calculate_quantities_and_totals( $event_id, $meta ) {
		$golf_qty   = 0;
		$lunch_qty  = 0;
		$dinner_qty = 0;

		foreach ( $this->get_visible_participant_keys_for_registration_type( $meta['registration_type'] ) as $participant ) {
			$legacy_participation_type = isset( $meta[ $participant . '_participation_type' ] ) ? $meta[ $participant . '_participation_type' ] : '';

			if ( '1' === $meta[ $participant . '_golf_selected' ] || 'golf' === $legacy_participation_type ) {
				$golf_qty++;
			}
		}

		$lunch_qty  += absint( $meta['additional_lunch_count'] );
		$dinner_qty += absint( $meta['additional_dinner_count'] );

		$sponsor_quantities = array(
			'platinum_sponsor_qty' => '0',
			'gold_sponsor_qty'     => '0',
			'silver_sponsor_qty'   => '0',
			'tee_sponsor_qty'      => '0',
		);

		if ( 'additional_guests' === $meta['registration_type'] ) {
			$meta['sponsorship_level']    = '';
			$meta['tee_sponsor_selected'] = '0';
		}

		if ( 'platinum' === $meta['sponsorship_level'] ) {
			$sponsor_quantities['platinum_sponsor_qty'] = '1';
		} elseif ( 'gold' === $meta['sponsorship_level'] ) {
			$sponsor_quantities['gold_sponsor_qty'] = '1';
		} elseif ( 'silver' === $meta['sponsorship_level'] ) {
			$sponsor_quantities['silver_sponsor_qty'] = '1';
		}

		if ( '1' === $meta['tee_sponsor_selected'] ) {
			$sponsor_quantities['tee_sponsor_qty'] = '1';
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
	 * Gets participant keys visible for a registration type.
	 *
	 * @param string $registration_type Registration type.
	 * @return array<int,string>
	 */
	private function get_visible_participant_keys_for_registration_type( $registration_type ) {
		if ( 'team' === $registration_type ) {
			return array( 'captain', 'member_2', 'member_3', 'member_4' );
		}

		if ( 'individual' === $registration_type ) {
			return array( 'captain' );
		}

		return array();
	}

	/**
	 * Checks whether sanitized submission meta has checkout items with billable quantities.
	 *
	 * @param array<string,string> $meta Sanitized submitted meta.
	 * @return bool
	 */
	private function has_billable_checkout_items( $meta ) {
		foreach (
			array(
				'golf_qty',
				'lunch_qty',
				'dinner_qty',
				'platinum_sponsor_qty',
				'gold_sponsor_qty',
				'silver_sponsor_qty',
				'tee_sponsor_qty',
			) as $quantity_key
		) {
			if ( ! empty( $meta[ $quantity_key ] ) && absint( $meta[ $quantity_key ] ) > 0 ) {
				return true;
			}
		}

		return false;
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
	 * Gets an event ID from shortcode attributes or the current golf event post.
	 *
	 * @param array<string,mixed> $atts          Shortcode attributes.
	 * @param string              $shortcode_tag Shortcode tag.
	 * @return int
	 */
	private function get_shortcode_event_id( $atts, $shortcode_tag ) {
		$atts = shortcode_atts(
			array(
				'event_id' => 0,
			),
			$atts,
			$shortcode_tag
		);

		$event_id = absint( $atts['event_id'] );

		if ( ! $event_id && is_singular( HFO_Golf_Event_Post_Type::POST_TYPE ) ) {
			$event_id = get_the_ID();
		}

		$event = get_post( $event_id );

		return ( $event && HFO_Golf_Event_Post_Type::POST_TYPE === $event->post_type && 'publish' === $event->post_status ) ? $event_id : 0;
	}

	/**
	 * Gets the preferred public event venue, falling back to legacy location.
	 *
	 * @param int $event_id Event post ID.
	 * @return string
	 */
	private function get_event_venue( $event_id ) {
		$venue = (string) get_post_meta( $event_id, 'event_venue', true );

		if ( '' === trim( $venue ) ) {
			$venue = (string) get_post_meta( $event_id, 'event_location', true );
		}

		return $venue;
	}

	/**
	 * Gets formatted public event address parts.
	 *
	 * @param int $event_id Event post ID.
	 * @return array<int,string>
	 */
	private function get_event_address_parts( $event_id ) {
		$street = trim( (string) get_post_meta( $event_id, 'event_address', true ) );
		$city   = trim( (string) get_post_meta( $event_id, 'event_city', true ) );
		$state  = trim( (string) get_post_meta( $event_id, 'event_state', true ) );
		$zip    = trim( (string) get_post_meta( $event_id, 'event_zip', true ) );
		$city_state_zip = trim( implode( ' ', array_filter( array( $state, $zip ) ) ) );

		if ( '' !== $city && '' !== $city_state_zip ) {
			$city_state_zip = $city . ', ' . $city_state_zip;
		} elseif ( '' !== $city ) {
			$city_state_zip = $city;
		}

		return array_values( array_filter( array( $street, $city_state_zip ) ) );
	}

	/**
	 * Formats an event time range.
	 *
	 * @param string $start_time Start time.
	 * @param string $end_time   End time.
	 * @return string
	 */
	private function format_time_range( $start_time, $end_time ) {
		if ( '' !== $start_time && '' !== $end_time ) {
			return $start_time . ' - ' . $end_time;
		}

		return '' !== $start_time ? $start_time : $end_time;
	}

	/**
	 * Renders sanitized textarea lines as a list.
	 *
	 * @param string $value Textarea value.
	 * @return string
	 */
	private function render_line_list( $value ) {
		$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ) );

		if ( empty( $lines ) ) {
			return '';
		}

		$output = '<ul>';

		foreach ( $lines as $line ) {
			$output .= '<li>' . esc_html( $line ) . '</li>';
		}

		$output .= '</ul>';

		return $output;
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
	 * Sanitizes a posted checkbox field to a stored 1/0 string.
	 *
	 * @param string $key Posted key.
	 * @return string
	 */
	private function sanitize_post_checkbox( $key ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : '';

		return in_array( $value, array( '1', 'yes', 'on', 'true' ), true ) ? '1' : '0';
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
			<li class="is-active" data-step-key="registration_type"><?php esc_html_e( 'Registration Type', 'hfo-golf-registration' ); ?></li>
			<li data-step-key="main_contact"><?php esc_html_e( 'Main Contact', 'hfo-golf-registration' ); ?></li>
			<li data-step-key="captain" data-team-label="<?php esc_attr_e( 'Captain / Player 1', 'hfo-golf-registration' ); ?>" data-individual-label="<?php esc_attr_e( 'Player 1', 'hfo-golf-registration' ); ?>"><?php esc_html_e( 'Captain / Player 1', 'hfo-golf-registration' ); ?></li>
			<li data-step-key="member_2" data-team-only><?php esc_html_e( 'Member #2', 'hfo-golf-registration' ); ?></li>
			<li data-step-key="member_3" data-team-only><?php esc_html_e( 'Member #3', 'hfo-golf-registration' ); ?></li>
			<li data-step-key="member_4" data-team-only><?php esc_html_e( 'Member #4', 'hfo-golf-registration' ); ?></li>
			<li data-step-key="additional_guests" data-player-only><?php esc_html_e( 'Additional Guests', 'hfo-golf-registration' ); ?></li>
			<li data-step-key="sponsorship"><?php esc_html_e( 'Sponsorship', 'hfo-golf-registration' ); ?></li>
			<li data-step-key="review"><?php esc_html_e( 'Review & Checkout', 'hfo-golf-registration' ); ?></li>
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
			<legend data-participant-legend data-team-label="<?php echo esc_attr( $legend ); ?>" data-individual-label="<?php esc_attr_e( 'Player 1', 'hfo-golf-registration' ); ?>"><?php echo esc_html( $legend ); ?></legend>
			<?php $this->render_text_field( $prefix . '_name', esc_html__( 'Name', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_email_field( $prefix . '_email', esc_html__( 'Email', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_phone', esc_html__( 'Phone', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_address', esc_html__( 'Address', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_city', esc_html__( 'City', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_state', esc_html__( 'State', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_zip', esc_html__( 'ZIP', 'hfo-golf-registration' ) ); ?>
			<?php $this->render_text_field( $prefix . '_handicap', esc_html__( 'Handicap', 'hfo-golf-registration' ) ); ?>
			<input name="<?php echo esc_attr( $prefix . '_golf_selected' ); ?>" type="hidden" value="1" />
			<?php $this->render_checkbox_field( $prefix . '_lunch_selected', esc_html__( 'Lunch', 'hfo-golf-registration' ), true ); ?>
			<?php $this->render_checkbox_field( $prefix . '_dinner_selected', esc_html__( 'Dinner', 'hfo-golf-registration' ), true ); ?>
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
	 * Renders a checkbox field.
	 *
	 * @param string $name Field name.
	 * @param string $label Field label.
	 * @param bool   $checked Whether the checkbox is checked by default.
	 * @return void
	 */
	private function render_checkbox_field( $name, $label, $checked = false ) {
		?>
		<p class="hfo-golf-registration-field hfo-golf-registration-field--checkbox">
			<label for="<?php echo esc_attr( $name ); ?>">
				<input id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" type="checkbox" value="1"<?php checked( $checked ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
		</p>
		<?php
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
