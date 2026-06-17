<?php
/**
 * Frontend meal coupon manager shortcode.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and processes the [hfo_golf_meal_coupon_manager] shortcode.
 */
class HFO_Golf_Meal_Coupon_Manager_Shortcode {

	const CREATE_ACTION = 'hfo_golf_meal_coupon_create';
	const DISABLE_ACTION = 'hfo_golf_meal_coupon_disable';
	const CREATE_NONCE_ACTION = 'hfo_golf_meal_coupon_create';
	const CREATE_NONCE_NAME = 'hfo_golf_meal_coupon_create_nonce';
	const DISABLE_NONCE_ACTION = 'hfo_golf_meal_coupon_disable';
	const DISABLE_NONCE_NAME = 'hfo_golf_meal_coupon_disable_nonce';
	const META_FLAG = '_hfo_golf_meal_coupon';

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'hfo_golf_meal_coupon_manager', array( $this, 'render_shortcode' ) );
		add_action( 'admin_post_' . self::CREATE_ACTION, array( $this, 'handle_create' ) );
		add_action( 'admin_post_' . self::DISABLE_ACTION, array( $this, 'handle_disable' ) );
	}

	/**
	 * Renders the coupon manager shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		if ( ! $this->current_user_can_manage() ) {
			return '<p class="hfo-golf-meal-coupon-message hfo-golf-meal-coupon-message--error">' . esc_html__( 'You do not have permission to manage meal coupons.', 'hfo-golf-registration' ) . '</p>';
		}

		ob_start();
		?>
		<div class="hfo-golf-meal-coupon-manager">
			<?php $this->render_notice(); ?>
			<form class="hfo-golf-meal-coupon-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE_ACTION ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $this->get_current_url() ); ?>" />
				<?php wp_nonce_field( self::CREATE_NONCE_ACTION, self::CREATE_NONCE_NAME ); ?>

				<p>
					<label for="hfo_meal_coupon_recipient_name"><?php esc_html_e( 'Recipient Name', 'hfo-golf-registration' ); ?></label><br />
					<input id="hfo_meal_coupon_recipient_name" type="text" name="recipient_name" required />
				</p>
				<p>
					<label for="hfo_meal_coupon_recipient_email"><?php esc_html_e( 'Recipient Email', 'hfo-golf-registration' ); ?></label><br />
					<input id="hfo_meal_coupon_recipient_email" type="email" name="recipient_email" />
				</p>
				<p>
					<label><input id="hfo_meal_coupon_restrict_to_email" type="checkbox" name="restrict_to_email" value="1" /> <?php esc_html_e( 'Restrict coupon to this email', 'hfo-golf-registration' ); ?></label>
				</p>
				<p>
					<label for="hfo_meal_coupon_lunch_count"><?php esc_html_e( 'Free Lunch Guests', 'hfo-golf-registration' ); ?></label><br />
					<input id="hfo_meal_coupon_lunch_count" type="number" name="lunch_count" min="0" value="0" />
				</p>
				<p>
					<label for="hfo_meal_coupon_dinner_count"><?php esc_html_e( 'Free Dinner Guests', 'hfo-golf-registration' ); ?></label><br />
					<input id="hfo_meal_coupon_dinner_count" type="number" name="dinner_count" min="0" value="0" />
				</p>
				<p>
					<label for="hfo_meal_coupon_expiration_date"><?php esc_html_e( 'Expiration Date', 'hfo-golf-registration' ); ?></label><br />
					<input id="hfo_meal_coupon_expiration_date" type="date" name="expiration_date" />
				</p>
				<p>
					<label for="hfo_meal_coupon_note"><?php esc_html_e( 'Internal Note', 'hfo-golf-registration' ); ?></label><br />
					<textarea id="hfo_meal_coupon_note" name="internal_note" rows="4"></textarea>
				</p>
				<p><button type="submit"><?php esc_html_e( 'Generate Coupon', 'hfo-golf-registration' ); ?></button></p>
			</form>
			<script>
			(function(){
				var email = document.getElementById('hfo_meal_coupon_recipient_email');
				var restrict = document.getElementById('hfo_meal_coupon_restrict_to_email');
				if (!email || !restrict) { return; }
				email.addEventListener('input', function(){
					if (email.value.trim()) { restrict.checked = true; }
				});
			}());
			</script>

			<h3><?php esc_html_e( 'Recent Meal Coupons', 'hfo-golf-registration' ); ?></h3>
			<?php $this->render_coupon_table(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Handle coupon creation. */
	public function handle_create() {
		if ( ! $this->current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage meal coupons.', 'hfo-golf-registration' ), 403 );
		}
		check_admin_referer( self::CREATE_NONCE_ACTION, self::CREATE_NONCE_NAME );

		$redirect_to       = $this->get_redirect_url();
		$recipient_name    = isset( $_POST['recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['recipient_name'] ) ) : '';
		$recipient_email   = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) ) : '';
		$restrict_to_email = ! empty( $_POST['restrict_to_email'] );
		$lunch_count       = isset( $_POST['lunch_count'] ) ? max( 0, absint( $_POST['lunch_count'] ) ) : 0;
		$dinner_count      = isset( $_POST['dinner_count'] ) ? max( 0, absint( $_POST['dinner_count'] ) ) : 0;
		$expiration_date   = isset( $_POST['expiration_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expiration_date'] ) ) : '';
		$internal_note     = isset( $_POST['internal_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_note'] ) ) : '';

		$errors = $this->validate_create_request( $recipient_name, $recipient_email, $restrict_to_email, $lunch_count, $dinner_count, $expiration_date );
		if ( ! empty( $errors ) ) {
			wp_safe_redirect( add_query_arg( array( 'hfo_meal_coupon_error' => rawurlencode( reset( $errors ) ) ), $redirect_to ) );
			exit;
		}

		$product_ids = array();
		if ( $lunch_count > 0 ) {
			$product_ids[] = absint( get_option( 'hfo_golf_registration_lunch_product_id', 0 ) );
		}
		if ( $dinner_count > 0 ) {
			$product_ids[] = absint( get_option( 'hfo_golf_registration_dinner_product_id', 0 ) );
		}

		$code   = $this->generate_coupon_code();
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 100 );
		$coupon->set_product_ids( array_values( array_unique( $product_ids ) ) );
		$coupon->set_usage_limit( 1 );
		$coupon->set_limit_usage_to_x_items( $lunch_count + $dinner_count );
		$coupon->set_individual_use( false );
		$coupon->set_description( sprintf( 'Meal coupon for %s. Lunch guests: %d. Dinner guests: %d.', $recipient_name, $lunch_count, $dinner_count ) );
		if ( $expiration_date ) {
			$coupon->set_date_expires( $expiration_date );
		}
		if ( $restrict_to_email ) {
			$coupon->set_email_restrictions( array( $recipient_email ) );
		}

		$coupon_id = $coupon->save();
		update_post_meta( $coupon_id, self::META_FLAG, '1' );
		update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_recipient_name', $recipient_name );
		update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_recipient_email', $recipient_email );
		update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_lunch_count', $lunch_count );
		update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_dinner_count', $dinner_count );
		update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_restrict_to_email', $restrict_to_email ? '1' : '0' );
		update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_note', $internal_note );
		update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_created_by', get_current_user_id() );

		wp_safe_redirect( add_query_arg( array( 'hfo_meal_coupon_created' => rawurlencode( $code ) ), $redirect_to ) );
		exit;
	}

	/** Handle disabling a coupon. */
	public function handle_disable() {
		if ( ! $this->current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage meal coupons.', 'hfo-golf-registration' ), 403 );
		}
		check_admin_referer( self::DISABLE_NONCE_ACTION, self::DISABLE_NONCE_NAME );

		$coupon_id = isset( $_POST['coupon_id'] ) ? absint( $_POST['coupon_id'] ) : 0;
		if ( $coupon_id && '1' === get_post_meta( $coupon_id, self::META_FLAG, true ) ) {
			wp_update_post(
				array(
					'ID'          => $coupon_id,
					'post_status' => 'draft',
				)
			);
		}

		wp_safe_redirect( add_query_arg( array( 'hfo_meal_coupon_disabled' => '1' ), $this->get_redirect_url() ) );
		exit;
	}

	private function render_notice() {
		if ( ! empty( $_GET['hfo_meal_coupon_created'] ) ) {
			$code = sanitize_text_field( wp_unslash( $_GET['hfo_meal_coupon_created'] ) );
			echo '<p class="hfo-golf-meal-coupon-message hfo-golf-meal-coupon-message--success">' . esc_html__( 'Coupon created successfully:', 'hfo-golf-registration' ) . ' <strong>' . esc_html( $code ) . '</strong> <button type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(\'' . esc_js( $code ) . '\');">' . esc_html__( 'Copy Code', 'hfo-golf-registration' ) . '</button></p>';
		}
		if ( ! empty( $_GET['hfo_meal_coupon_error'] ) ) {
			echo '<p class="hfo-golf-meal-coupon-message hfo-golf-meal-coupon-message--error">' . esc_html( sanitize_text_field( wp_unslash( $_GET['hfo_meal_coupon_error'] ) ) ) . '</p>';
		}
		if ( ! empty( $_GET['hfo_meal_coupon_disabled'] ) ) {
			echo '<p class="hfo-golf-meal-coupon-message hfo-golf-meal-coupon-message--success">' . esc_html__( 'Coupon disabled successfully.', 'hfo-golf-registration' ) . '</p>';
		}
	}

	private function render_coupon_table() {
		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 20,
				'meta_key'       => self::META_FLAG,
				'meta_value'     => '1',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<table class="hfo-golf-meal-coupon-table">
			<thead><tr><th><?php esc_html_e( 'Code', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Recipient Name', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Recipient Email', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Lunch Guests', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Dinner Guests', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Usage', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Expiration', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Status', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Actions', 'hfo-golf-registration' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $coupons ) ) : ?>
				<tr><td colspan="9"><?php esc_html_e( 'No meal coupons found.', 'hfo-golf-registration' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $coupons as $coupon_post ) : $coupon = new WC_Coupon( $coupon_post->ID ); $code = $coupon->get_code(); ?>
				<tr>
					<td><code><?php echo esc_html( $code ); ?></code></td>
					<td><?php echo esc_html( get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_recipient_name', true ) ); ?></td>
					<td><?php echo esc_html( get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_recipient_email', true ) ); ?></td>
					<td><?php echo esc_html( get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_lunch_count', true ) ); ?></td>
					<td><?php echo esc_html( get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_dinner_count', true ) ); ?></td>
					<td><?php echo esc_html( $coupon->get_usage_count() . ' / ' . $coupon->get_usage_limit() ); ?></td>
					<td><?php $expires = $coupon->get_date_expires(); echo esc_html( $expires ? $expires->date_i18n( get_option( 'date_format' ) ) : '—' ); ?></td>
					<td><?php echo esc_html( get_post_status_object( $coupon_post->post_status )->label ); ?></td>
					<td>
						<button type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?php echo esc_js( $code ); ?>');"><?php esc_html_e( 'Copy Code', 'hfo-golf-registration' ); ?></button>
						<?php if ( 'draft' !== $coupon_post->post_status ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::DISABLE_ACTION ); ?>" />
							<input type="hidden" name="coupon_id" value="<?php echo esc_attr( $coupon_post->ID ); ?>" />
							<input type="hidden" name="redirect_to" value="<?php echo esc_url( $this->get_current_url() ); ?>" />
							<?php wp_nonce_field( self::DISABLE_NONCE_ACTION, self::DISABLE_NONCE_NAME ); ?>
							<button type="submit"><?php esc_html_e( 'Disable Coupon', 'hfo-golf-registration' ); ?></button>
						</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function validate_create_request( $recipient_name, $recipient_email, $restrict_to_email, $lunch_count, $dinner_count, $expiration_date ) {
		$errors = array();
		if ( '' === $recipient_name ) {
			$errors[] = __( 'Recipient Name is required.', 'hfo-golf-registration' );
		}
		if ( 0 >= ( $lunch_count + $dinner_count ) ) {
			$errors[] = __( 'Please enter at least one free lunch or dinner guest.', 'hfo-golf-registration' );
		}
		if ( $restrict_to_email && ( '' === $recipient_email || ! is_email( $recipient_email ) ) ) {
			$errors[] = __( 'Recipient Email is required and must be valid when restricting the coupon to an email.', 'hfo-golf-registration' );
		}
		if ( ! class_exists( 'WC_Coupon' ) ) {
			$errors[] = __( 'WooCommerce coupons are not available.', 'hfo-golf-registration' );
		}
		if ( $lunch_count > 0 && ! $this->is_valid_product( absint( get_option( 'hfo_golf_registration_lunch_product_id', 0 ) ) ) ) {
			$errors[] = __( 'Lunch Product is not configured.', 'hfo-golf-registration' );
		}
		if ( $dinner_count > 0 && ! $this->is_valid_product( absint( get_option( 'hfo_golf_registration_dinner_product_id', 0 ) ) ) ) {
			$errors[] = __( 'Dinner Product is not configured.', 'hfo-golf-registration' );
		}
		if ( $expiration_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expiration_date ) ) {
			$errors[] = __( 'Expiration Date must be a valid date.', 'hfo-golf-registration' );
		}
		return $errors;
	}

	private function current_user_can_manage() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
	}

	private function is_valid_product( $product_id ) {
		return $product_id && function_exists( 'wc_get_product' ) && wc_get_product( $product_id ) && 'publish' === get_post_status( $product_id );
	}

	private function generate_coupon_code() {
		do {
			$code = 'HFO-MEAL-' . strtoupper( wp_generate_password( 8, false, false ) );
		} while ( wc_get_coupon_id_by_code( $code ) );
		return $code;
	}

	private function get_redirect_url() {
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
		return $redirect_to ? $redirect_to : home_url( '/' );
	}

	private function get_current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}
}
