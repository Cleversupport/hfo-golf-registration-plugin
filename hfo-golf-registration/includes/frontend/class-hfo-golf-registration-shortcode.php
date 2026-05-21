<?php
/**
 * Frontend shortcode renderer.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles shortcode rendering for registration forms.
 */
class HFO_Golf_Registration_Shortcode {

	/**
	 * Registers shortcode and asset hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'hfo_golf_registration', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Registers frontend assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'hfo-golf-registration-frontend',
			plugins_url( 'assets/css/hfo-golf-registration-frontend.css', HFO_GOLF_REGISTRATION_FILE ),
			array(),
			HFO_GOLF_REGISTRATION_VERSION
		);
	}

	/**
	 * Renders the registration shortcode.
	 *
	 * @param array<string, string> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'event_id' => 0,
			),
			$atts,
			'hfo_golf_registration'
		);

		$event_id = absint( $atts['event_id'] );
		if ( ! $event_id || 'golf_event' !== get_post_type( $event_id ) ) {
			return $this->render_message( __( 'Invalid event selected.', 'hfo-golf-registration' ) );
		}

		if ( 'open' !== get_post_meta( $event_id, 'registration_status', true ) ) {
			return $this->render_message( __( 'Registration is currently closed for this event.', 'hfo-golf-registration' ) );
		}

		wp_enqueue_style( 'hfo-golf-registration-frontend' );

		if ( '1' === filter_input( INPUT_GET, 'registration_success', FILTER_SANITIZE_NUMBER_INT ) ) {
			return $this->render_success_message( $event_id );
		}

		$redirect_to = get_permalink();
		if ( ! $redirect_to ) {
			$redirect_to = home_url( '/' );
		}

		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hfo-golf-registration-form">
			<input type="hidden" name="action" value="hfo_golf_registration_submit" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
			<?php wp_nonce_field( 'hfo_golf_registration_submit', 'hfo_golf_registration_nonce' ); ?>

			<p>
				<label for="main_contact_name"><?php esc_html_e( 'Main Contact Name', 'hfo-golf-registration' ); ?></label>
				<input type="text" id="main_contact_name" name="main_contact_name" required />
			</p>

			<p>
				<label for="main_contact_email"><?php esc_html_e( 'Main Contact Email', 'hfo-golf-registration' ); ?></label>
				<input type="email" id="main_contact_email" name="main_contact_email" required />
			</p>

			<p>
				<label for="main_contact_phone"><?php esc_html_e( 'Main Contact Phone', 'hfo-golf-registration' ); ?></label>
				<input type="text" id="main_contact_phone" name="main_contact_phone" required />
			</p>

			<p>
				<button type="submit"><?php esc_html_e( 'Submit Registration', 'hfo-golf-registration' ); ?></button>
			</p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders a simple message container.
	 *
	 * @param string $message Message text.
	 *
	 * @return string
	 */
	private function render_message( $message ) {
		return '<div class="hfo-golf-registration-message">' . esc_html( $message ) . '</div>';
	}

	/**
	 * Renders success or thank you content.
	 *
	 * @param int $event_id Event post ID.
	 *
	 * @return string
	 */
	private function render_success_message( $event_id ) {
		$thank_you_message = trim( (string) get_post_meta( $event_id, 'thank_you_message', true ) );

		if ( '' === $thank_you_message ) {
			$thank_you_message = __( 'Registration submitted successfully. Thank you!', 'hfo-golf-registration' );
		}

		return '<div class="hfo-golf-registration-success">' . wp_kses_post( wpautop( $thank_you_message ) ) . '</div>';
	}
}
