<?php
/**
 * Frontend shortcode for golf registrations.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles frontend shortcode rendering.
 */
class HFO_Golf_Registration_Shortcode {

	/**
	 * Whether frontend CSS should be enqueued.
	 *
	 * @var bool
	 */
	private $should_enqueue_assets = false;

	/**
	 * Registers shortcode and asset hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'hfo_golf_registration', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'conditionally_enqueue_assets' ), 20 );
	}

	/**
	 * Registers frontend assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'hfo-golf-registration-frontend',
			plugins_url( 'assets/css/frontend.css', HFO_GOLF_REGISTRATION_FILE ),
			array(),
			HFO_GOLF_REGISTRATION_VERSION
		);
	}

	/**
	 * Enqueues assets only when shortcode is rendered.
	 *
	 * @return void
	 */
	public function conditionally_enqueue_assets() {
		if ( $this->should_enqueue_assets ) {
			wp_enqueue_style( 'hfo-golf-registration-frontend' );
		}
	}

	/**
	 * Renders shortcode output.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts     = shortcode_atts( array( 'event_id' => 0 ), $atts, 'hfo_golf_registration' );
		$event_id = absint( $atts['event_id'] );

		if ( 0 === $event_id || HFO_Golf_Event_Post_Type::POST_TYPE !== get_post_type( $event_id ) ) {
			return '<p>' . esc_html__( 'A valid event is required to register.', 'hfo-golf-registration' ) . '</p>';
		}

		if ( 'open' !== get_post_meta( $event_id, 'registration_status', true ) ) {
			return '<p>' . esc_html__( 'Registration is currently closed for this event.', 'hfo-golf-registration' ) . '</p>';
		}

		$this->should_enqueue_assets = true;

		$event_meta = $this->get_event_meta( $event_id );
		$action_url = admin_url( 'admin-post.php' );

		ob_start();
		?>
		<div class="hfo-golf-registration-form-wrap">
			<h3><?php echo esc_html( get_the_title( $event_id ) ); ?></h3>
			<p><?php echo esc_html( $event_meta['event_year'] ); ?> | <?php echo esc_html( $event_meta['event_date'] ); ?> | <?php echo esc_html( $event_meta['event_location'] ); ?></p>
			<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="hfo-golf-registration-form">
				<input type="hidden" name="action" value="hfo_golf_submit_registration" />
				<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
				<?php wp_nonce_field( 'hfo_golf_submit_registration', 'hfo_golf_registration_nonce' ); ?>

				<?php $this->render_text_field( 'main_contact_name', __( 'Main Contact Name', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_email_field( 'main_contact_email', __( 'Main Contact Email', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_text_field( 'main_contact_phone', __( 'Main Contact Phone', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_text_field( 'main_contact_address', __( 'Address', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_text_field( 'main_contact_city', __( 'City', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_text_field( 'main_contact_state', __( 'State', 'hfo-golf-registration' ), true ); ?>
				<?php $this->render_text_field( 'main_contact_zip', __( 'ZIP', 'hfo-golf-registration' ), true ); ?>

				<h4><?php esc_html_e( 'Participation & Payment', 'hfo-golf-registration' ); ?></h4>
				<?php $this->render_number_field( 'golf_qty', __( 'Golf Quantity', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_number_field( 'lunch_qty', __( 'Lunch Quantity', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_number_field( 'dinner_qty', __( 'Dinner Quantity', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_number_field( 'platinum_sponsor_qty', __( 'Platinum Sponsor Quantity', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_number_field( 'gold_sponsor_qty', __( 'Gold Sponsor Quantity', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_number_field( 'silver_sponsor_qty', __( 'Silver Sponsor Quantity', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_number_field( 'tee_sponsor_qty', __( 'Tee Sponsor Quantity', 'hfo-golf-registration' ) ); ?>
				<?php $this->render_text_field( 'discount_code_used', __( 'Discount Code', 'hfo-golf-registration' ) ); ?>

				<h4><?php esc_html_e( 'Team Participants', 'hfo-golf-registration' ); ?></h4>
				<?php
				$members = array( 'captain', 'member_2', 'member_3', 'member_4' );
				foreach ( $members as $member_prefix ) {
					$this->render_text_field( $member_prefix . '_name', ucwords( str_replace( '_', ' ', $member_prefix ) ) . ' ' . __( 'Name', 'hfo-golf-registration' ) );
					$this->render_email_field( $member_prefix . '_email', ucwords( str_replace( '_', ' ', $member_prefix ) ) . ' ' . __( 'Email', 'hfo-golf-registration' ) );
					$this->render_text_field( $member_prefix . '_phone', ucwords( str_replace( '_', ' ', $member_prefix ) ) . ' ' . __( 'Phone', 'hfo-golf-registration' ) );
					$this->render_text_field( $member_prefix . '_handicap', ucwords( str_replace( '_', ' ', $member_prefix ) ) . ' ' . __( 'Handicap', 'hfo-golf-registration' ) );
				}
				?>

				<?php $this->render_textarea_field( 'additional_guests_details', __( 'Additional Guests Details', 'hfo-golf-registration' ) ); ?>

				<button type="submit"><?php esc_html_e( 'Submit Registration', 'hfo-golf-registration' ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function get_event_meta( $event_id ) {
		$keys = array(
			'event_year','event_date','event_location','golf_price','lunch_price','dinner_price','platinum_sponsor_price','gold_sponsor_price','silver_sponsor_price','tee_sponsor_price','discount_code_15','discount_code_30','thank_you_message',
		);
		$meta = array();
		foreach ( $keys as $key ) {
			$meta[ $key ] = (string) get_post_meta( $event_id, $key, true );
		}
		return $meta;
	}

	private function render_text_field( $name, $label, $required = false ) { echo '<p><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" ' . ( $required ? 'required' : '' ) . ' /></p>'; }
	private function render_email_field( $name, $label, $required = false ) { echo '<p><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input type="email" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" ' . ( $required ? 'required' : '' ) . ' /></p>'; }
	private function render_number_field( $name, $label ) { echo '<p><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><input type="number" min="0" step="1" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="0" /></p>'; }
	private function render_textarea_field( $name, $label ) { echo '<p><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label><textarea id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" rows="4"></textarea></p>'; }
}
