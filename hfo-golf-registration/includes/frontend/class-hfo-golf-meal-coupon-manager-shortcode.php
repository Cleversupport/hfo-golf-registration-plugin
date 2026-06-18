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
 * Registers and processes the meal coupon manager shortcodes.
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
		add_shortcode( 'hfo_golf_meal_coupon_form', array( $this, 'render_form_shortcode' ) );
		add_shortcode( 'hfo_golf_meal_coupon_table', array( $this, 'render_table_shortcode' ) );
		add_shortcode( 'hfo_golf_meal_coupon_email_log', array( $this, 'render_email_log_shortcode' ) );
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
			return $this->render_permission_message();
		}

		$this->enqueue_assets();

		ob_start();
		?>
		<div class="hfo-golf-meal-coupon-manager">
			<?php $this->render_intro_section(); ?>
			<?php $this->render_form_section(); ?>
			<?php $this->render_table_section(); ?>
			<?php $this->render_email_log_section(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders only the meal coupon form shortcode.
	 *
	 * @return string
	 */
	public function render_form_shortcode() {
		if ( ! $this->current_user_can_manage() ) {
			return $this->render_permission_message();
		}

		$this->enqueue_assets();

		ob_start();
		?>
		<div class="hfo-golf-meal-coupon-manager">
			<?php $this->render_intro_section(); ?>
			<?php $this->render_form_section(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders only the recent meal coupons table shortcode.
	 *
	 * @return string
	 */
	public function render_table_shortcode() {
		if ( ! $this->current_user_can_manage() ) {
			return $this->render_permission_message();
		}

		$this->enqueue_assets();

		ob_start();
		?>
		<div class="hfo-golf-meal-coupon-manager">
			<?php $this->render_table_section(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders only the meal coupon email log shortcode.
	 *
	 * @return string
	 */
	public function render_email_log_shortcode() {
		if ( ! $this->current_user_can_manage() ) {
			return $this->render_permission_message();
		}

		$this->enqueue_assets();

		ob_start();
		?>
		<div class="hfo-golf-meal-coupon-manager">
			<?php $this->render_email_log_section(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueues shortcode-specific frontend assets.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'hfo-golf-meal-coupon-manager',
			plugins_url( 'assets/css/hfo-golf-meal-coupon-manager.css', HFO_GOLF_REGISTRATION_FILE ),
			array(),
			HFO_GOLF_REGISTRATION_VERSION
		);
	}

	/**
	 * Returns the permission error message.
	 *
	 * @return string
	 */
	private function render_permission_message() {
		return '<p class="hfo-golf-meal-coupon-message hfo-golf-meal-coupon-message--error">' . esc_html__( 'You do not have permission to manage meal coupons.', 'hfo-golf-registration' ) . '</p>';
	}

	/**
	 * Renders the manager intro and helper cards.
	 *
	 * @return void
	 */
	private function render_intro_section() {
		?>
		<section class="hfo-golf-meal-coupon-intro" aria-labelledby="hfo-golf-meal-coupon-intro-title">
			<div>
				<p class="hfo-golf-meal-coupon-eyebrow"><?php esc_html_e( 'HFO Golf Registration', 'hfo-golf-registration' ); ?></p>
				<h1 id="hfo-golf-meal-coupon-intro-title"><?php esc_html_e( 'Meal Coupon Manager', 'hfo-golf-registration' ); ?></h1>
				<p><?php esc_html_e( 'Generate, copy, and disable approved meal coupons from one polished admin workspace.', 'hfo-golf-registration' ); ?></p>
			</div>
		</section>
		<div class="hfo-golf-meal-coupon-helper-grid" aria-label="<?php esc_attr_e( 'Meal coupon guidance', 'hfo-golf-registration' ); ?>">
			<div class="hfo-golf-meal-coupon-helper-card">
				<strong><?php esc_html_e( 'Meal coverage', 'hfo-golf-registration' ); ?></strong>
				<span><?php esc_html_e( 'Lunch and dinner quantities determine the covered guest meal items.', 'hfo-golf-registration' ); ?></span>
			</div>
			<div class="hfo-golf-meal-coupon-helper-card">
				<strong><?php esc_html_e( 'Email restriction', 'hfo-golf-registration' ); ?></strong>
				<span><?php esc_html_e( 'Add a recipient email to automatically offer the email restriction option.', 'hfo-golf-registration' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the coupon creation form section.
	 *
	 * @return void
	 */
	private function render_form_section() {
		?>
		<section class="hfo-golf-meal-coupon-card" aria-labelledby="hfo-golf-meal-coupon-form-title">
			<?php $this->render_create_notice(); ?>
			<div class="hfo-golf-meal-coupon-card__header">
				<h2 id="hfo-golf-meal-coupon-form-title" class="hfo-golf-meal-coupon-card__title"><?php esc_html_e( 'Generate a Meal Coupon', 'hfo-golf-registration' ); ?></h2>
				<p class="hfo-golf-meal-coupon-card__description"><?php esc_html_e( 'Create complimentary meal coupons for approved guests. Coupons apply only to Lunch Only Guest and Dinner Only Guest products.', 'hfo-golf-registration' ); ?></p>
			</div>
			<form class="hfo-golf-meal-coupon-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE_ACTION ); ?>" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $this->get_current_url() ); ?>" />
				<?php wp_nonce_field( self::CREATE_NONCE_ACTION, self::CREATE_NONCE_NAME ); ?>

				<div class="hfo-golf-meal-coupon-grid">
					<p class="hfo-golf-meal-coupon-field">
						<label for="hfo_meal_coupon_recipient_name"><?php esc_html_e( 'Recipient Name', 'hfo-golf-registration' ); ?></label>
						<input id="hfo_meal_coupon_recipient_name" type="text" name="recipient_name" required />
					</p>
					<p class="hfo-golf-meal-coupon-field">
						<label for="hfo_meal_coupon_recipient_email"><?php esc_html_e( 'Recipient Email', 'hfo-golf-registration' ); ?></label>
						<input id="hfo_meal_coupon_recipient_email" type="email" name="recipient_email" />
					</p>
					<p class="hfo-golf-meal-coupon-field hfo-golf-meal-coupon-field--full">
						<label class="hfo-golf-meal-coupon-checkbox"><input id="hfo_meal_coupon_restrict_to_email" type="checkbox" name="restrict_to_email" value="1" /> <?php esc_html_e( 'Restrict coupon to this email', 'hfo-golf-registration' ); ?></label>
					</p>
					<p class="hfo-golf-meal-coupon-field hfo-golf-meal-coupon-field--full">
						<label class="hfo-golf-meal-coupon-checkbox"><input id="hfo_meal_coupon_email_coupon_to_recipient" type="checkbox" name="email_coupon_to_recipient" value="1" /> <?php esc_html_e( 'Email coupon to recipient', 'hfo-golf-registration' ); ?></label>
					</p>
					<p class="hfo-golf-meal-coupon-field hfo-golf-meal-coupon-field--full hfo-golf-meal-coupon-help"><?php esc_html_e( 'Enter how many lunch and/or dinner guest meals should be covered by this coupon.', 'hfo-golf-registration' ); ?></p>
					<p class="hfo-golf-meal-coupon-field">
						<label for="hfo_meal_coupon_lunch_count"><?php esc_html_e( 'Free Lunch Guests', 'hfo-golf-registration' ); ?></label>
						<input id="hfo_meal_coupon_lunch_count" type="number" name="lunch_count" min="0" value="0" />
					</p>
					<p class="hfo-golf-meal-coupon-field">
						<label for="hfo_meal_coupon_dinner_count"><?php esc_html_e( 'Free Dinner Guests', 'hfo-golf-registration' ); ?></label>
						<input id="hfo_meal_coupon_dinner_count" type="number" name="dinner_count" min="0" value="0" />
					</p>
					<p class="hfo-golf-meal-coupon-field">
						<label for="hfo_meal_coupon_expiration_date"><?php esc_html_e( 'Expiration Date', 'hfo-golf-registration' ); ?></label>
						<input id="hfo_meal_coupon_expiration_date" type="date" name="expiration_date" />
					</p>
					<p class="hfo-golf-meal-coupon-field hfo-golf-meal-coupon-field--full">
						<label for="hfo_meal_coupon_note"><?php esc_html_e( 'Internal Note', 'hfo-golf-registration' ); ?></label>
						<textarea id="hfo_meal_coupon_note" name="internal_note" rows="4"></textarea>
					</p>
				</div>
				<p class="hfo-golf-meal-coupon-actions"><button type="submit"><?php esc_html_e( 'Generate Coupon', 'hfo-golf-registration' ); ?></button></p>
			</form>
		</section>
		<script>
		(function(){
			var email = document.getElementById('hfo_meal_coupon_recipient_email');
			var restrict = document.getElementById('hfo_meal_coupon_restrict_to_email');
			var emailCoupon = document.getElementById('hfo_meal_coupon_email_coupon_to_recipient');
			if (!email || !restrict || !emailCoupon) { return; }
			var emailCouponChanged = false;
			function syncEmailCouponDefault() {
				if (!emailCouponChanged) {
					emailCoupon.checked = !!email.value.trim();
				}
			}
			syncEmailCouponDefault();
			emailCoupon.addEventListener('change', function(){
				emailCouponChanged = true;
			});
			email.addEventListener('input', function(){
				if (email.value.trim()) { restrict.checked = true; }
				syncEmailCouponDefault();
			});
		}());
		</script>
		<?php
	}

	/**
	 * Renders the recent coupons table section.
	 *
	 * @return void
	 */
	private function render_table_section() {
		?>
		<section class="hfo-golf-meal-coupon-table-section" aria-labelledby="hfo-golf-meal-coupon-table-title">
			<?php $this->render_disable_notice(); ?>
			<div class="hfo-golf-meal-coupon-card__header">
				<h2 id="hfo-golf-meal-coupon-table-title" class="hfo-golf-meal-coupon-card__title"><?php esc_html_e( 'Recent Meal Coupons', 'hfo-golf-registration' ); ?></h2>
				<p class="hfo-golf-meal-coupon-card__description"><?php esc_html_e( 'Review recently created meal coupons, copy codes, or disable coupons that should no longer be used.', 'hfo-golf-registration' ); ?></p>
			</div>
			<div class="hfo-golf-meal-coupon-table-wrap">
				<?php $this->render_coupon_table(); ?>
			</div>
		</section>
		<?php
	}


	/**
	 * Renders the email log section.
	 *
	 * @return void
	 */
	private function render_email_log_section() {
		$entries = $this->get_recent_email_log_entries();
		?>
		<section class="hfo-golf-meal-coupon-table-section hfo-golf-meal-coupon-email-log-section" aria-labelledby="hfo-golf-meal-coupon-email-log-title">
			<div class="hfo-golf-meal-coupon-card__header">
				<h2 id="hfo-golf-meal-coupon-email-log-title" class="hfo-golf-meal-coupon-card__title"><?php esc_html_e( 'Email Send Log', 'hfo-golf-registration' ); ?></h2>
				<p class="hfo-golf-meal-coupon-card__description"><?php esc_html_e( 'Review recent meal coupon email send attempts.', 'hfo-golf-registration' ); ?></p>
			</div>
			<?php if ( empty( $entries ) ) : ?>
				<p class="hfo-golf-meal-coupon-empty-card"><?php esc_html_e( 'No meal coupon emails have been sent yet.', 'hfo-golf-registration' ); ?></p>
			<?php else : ?>
				<div class="hfo-golf-meal-coupon-table-wrap hfo-golf-meal-coupon-email-log-table-wrap">
					<table class="hfo-golf-meal-coupon-table hfo-golf-meal-coupon-email-log-table">
						<thead><tr><th><?php esc_html_e( 'Coupon Code', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Recipient Name', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Recipient Email', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Date/Time', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Status', 'hfo-golf-registration' ); ?></th><th><?php esc_html_e( 'Sent By', 'hfo-golf-registration' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $entries as $entry ) : ?>
							<tr><td><code class="hfo-golf-meal-coupon-code"><?php echo esc_html( $entry['code'] ); ?></code></td><td><?php echo esc_html( $entry['recipient_name'] ); ?></td><td><?php echo esc_html( $entry['recipient_email'] ); ?></td><td><?php echo esc_html( $entry['sent_at_display'] ); ?></td><td><?php $this->render_email_log_status_badge( $entry['status'] ); ?></td><td><?php echo esc_html( $entry['sent_by'] ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<div class="hfo-golf-meal-coupon-email-log-card-list">
						<?php foreach ( $entries as $entry ) : ?>
							<article class="hfo-golf-meal-coupon-email-log-card">
								<div class="hfo-golf-meal-coupon-email-log-card-header"><code class="hfo-golf-meal-coupon-card-code"><?php echo esc_html( $entry['code'] ); ?></code><?php $this->render_email_log_status_badge( $entry['status'] ); ?></div>
								<dl class="hfo-golf-meal-coupon-email-log-card-meta">
									<div><dt><?php esc_html_e( 'Recipient Name', 'hfo-golf-registration' ); ?></dt><dd><?php echo esc_html( $entry['recipient_name'] ); ?></dd></div>
									<div><dt><?php esc_html_e( 'Recipient Email', 'hfo-golf-registration' ); ?></dt><dd><?php echo esc_html( $entry['recipient_email'] ); ?></dd></div>
									<div><dt><?php esc_html_e( 'Date/Time', 'hfo-golf-registration' ); ?></dt><dd><?php echo esc_html( $entry['sent_at_display'] ); ?></dd></div>
									<div><dt><?php esc_html_e( 'Sent By', 'hfo-golf-registration' ); ?></dt><dd><?php echo esc_html( $entry['sent_by'] ); ?></dd></div>
								</dl>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</section>
		<?php
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
		$email_requested   = ! empty( $_POST['email_coupon_to_recipient'] );
		$lunch_count       = isset( $_POST['lunch_count'] ) ? max( 0, absint( $_POST['lunch_count'] ) ) : 0;
		$dinner_count      = isset( $_POST['dinner_count'] ) ? max( 0, absint( $_POST['dinner_count'] ) ) : 0;
		$expiration_date   = isset( $_POST['expiration_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expiration_date'] ) ) : '';
		$internal_note     = isset( $_POST['internal_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['internal_note'] ) ) : '';

		$errors = $this->validate_create_request( $recipient_name, $recipient_email, $restrict_to_email, $email_requested, $lunch_count, $dinner_count, $expiration_date );
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
		update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_email_requested', $email_requested ? '1' : '0' );

		$email_sent = false;
		if ( $email_requested && is_email( $recipient_email ) ) {
			$email_sent = $this->send_coupon_email( $recipient_email, $recipient_name, $code, $lunch_count, $dinner_count, $restrict_to_email );
		}
		update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_email_sent', $email_sent ? '1' : '0' );
		if ( $email_sent ) {
			update_post_meta( $coupon_id, '_hfo_golf_meal_coupon_email_sent_at', current_time( 'mysql' ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'hfo_meal_coupon_created'      => rawurlencode( $code ),
					'hfo_meal_coupon_email_status' => $email_requested ? ( $email_sent ? 'sent' : 'failed' ) : 'not_requested',
				),
				$redirect_to
			)
		);
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

	private function render_create_notice() {
		if ( ! empty( $_GET['hfo_meal_coupon_created'] ) ) {
			$code         = sanitize_text_field( wp_unslash( $_GET['hfo_meal_coupon_created'] ) );
			$email_status = isset( $_GET['hfo_meal_coupon_email_status'] ) ? sanitize_key( wp_unslash( $_GET['hfo_meal_coupon_email_status'] ) ) : 'not_requested';
			$message      = __( 'Coupon created successfully:', 'hfo-golf-registration' );
			if ( 'sent' === $email_status ) {
				$message = __( 'Coupon created and emailed successfully:', 'hfo-golf-registration' );
			} elseif ( 'failed' === $email_status ) {
				$message = __( 'Coupon created successfully, but the email could not be sent:', 'hfo-golf-registration' );
			}
			$notice_class = 'failed' === $email_status ? 'hfo-golf-meal-coupon-message--warning' : 'hfo-golf-meal-coupon-message--success';
			echo '<div class="hfo-golf-meal-coupon-message ' . esc_attr( $notice_class ) . '"><span>' . esc_html( $message ) . ' <strong>' . esc_html( $code ) . '</strong></span> <button class="hfo-golf-meal-coupon-button hfo-golf-meal-coupon-button--small hfo-golf-meal-coupon-action-primary" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(\'' . esc_js( $code ) . '\');" aria-label="' . esc_attr__( 'Copy created coupon code', 'hfo-golf-registration' ) . '" title="' . esc_attr__( 'Copy Code', 'hfo-golf-registration' ) . '"><span aria-hidden="true">⧉</span> ' . esc_html__( 'Copy Code', 'hfo-golf-registration' ) . '</button></div>';
		}
		if ( ! empty( $_GET['hfo_meal_coupon_error'] ) ) {
			echo '<p class="hfo-golf-meal-coupon-message hfo-golf-meal-coupon-message--error">' . esc_html( sanitize_text_field( wp_unslash( $_GET['hfo_meal_coupon_error'] ) ) ) . '</p>';
		}
	}

	private function render_disable_notice() {
		if ( ! empty( $_GET['hfo_meal_coupon_disabled'] ) ) {
			echo '<p class="hfo-golf-meal-coupon-message hfo-golf-meal-coupon-message--success">' . esc_html__( 'Coupon disabled successfully.', 'hfo-golf-registration' ) . '</p>';
		}
	}

	private function render_coupon_table() {
		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'meta_key'       => self::META_FLAG,
				'meta_value'     => '1',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		?>
		<table class="hfo-golf-meal-coupon-table">
			<thead>
				<tr>
					<th class="hfo-golf-meal-coupon-details-cell"><?php esc_html_e( 'Coupon Details', 'hfo-golf-registration' ); ?></th>
					<th class="hfo-golf-meal-coupon-column-code"><?php esc_html_e( 'Code', 'hfo-golf-registration' ); ?></th>
					<th class="hfo-golf-meal-coupon-column-recipient"><?php esc_html_e( 'Recipient', 'hfo-golf-registration' ); ?></th>
					<th class="hfo-golf-meal-coupon-column-meals"><?php esc_html_e( 'Meals', 'hfo-golf-registration' ); ?></th>
					<th class="hfo-golf-meal-coupon-column-usage"><?php esc_html_e( 'Usage', 'hfo-golf-registration' ); ?></th>
					<th class="hfo-golf-meal-coupon-column-expiration"><?php esc_html_e( 'Expiration', 'hfo-golf-registration' ); ?></th>
					<th class="hfo-golf-meal-coupon-column-actions"><?php esc_html_e( 'Actions', 'hfo-golf-registration' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $coupons ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No active meal coupons found.', 'hfo-golf-registration' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $coupons as $coupon_post ) : $coupon = new WC_Coupon( $coupon_post->ID ); $code = $coupon->get_code(); $recipient_name = get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_recipient_name', true ); $recipient_email = get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_recipient_email', true ); $lunch_count = get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_lunch_count', true ); $dinner_count = get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_dinner_count', true ); $usage = $coupon->get_usage_count() . ' / ' . $coupon->get_usage_limit(); $expires = $coupon->get_date_expires(); $expiration = $expires ? $expires->date_i18n( get_option( 'date_format' ) ) : '—'; ?>
				<tr>
					<td class="hfo-golf-meal-coupon-details-cell">
						<div class="hfo-golf-meal-coupon-details">
							<code class="hfo-golf-meal-coupon-code hfo-golf-meal-coupon-details-code"><?php echo esc_html( $code ); ?></code>
							<div class="hfo-golf-meal-coupon-details-recipient">
								<span class="hfo-golf-meal-coupon-recipient-name"><?php echo esc_html( $recipient_name ); ?></span>
								<?php $this->render_recipient_email_line( $coupon_post->ID, $recipient_email ); ?>
							</div>
							<div class="hfo-golf-meal-coupon-details-meals hfo-golf-meal-coupon-meals" aria-label="<?php echo esc_attr( sprintf( __( 'Lunch: %1$s, Dinner: %2$s', 'hfo-golf-registration' ), $lunch_count, $dinner_count ) ); ?>">
								<span class="hfo-golf-meal-coupon-meal-pill hfo-golf-meal-coupon-meal-pill--lunch"><?php echo esc_html( sprintf( __( 'Lunch %s', 'hfo-golf-registration' ), $lunch_count ) ); ?></span>
								<span class="hfo-golf-meal-coupon-meal-pill hfo-golf-meal-coupon-meal-pill--dinner"><?php echo esc_html( sprintf( __( 'Dinner %s', 'hfo-golf-registration' ), $dinner_count ) ); ?></span>
							</div>
							<div class="hfo-golf-meal-coupon-details-meta">
								<span><?php echo esc_html( sprintf( __( 'Usage: %s', 'hfo-golf-registration' ), $usage ) ); ?></span>
								<span><?php echo esc_html( sprintf( __( 'Expiration: %s', 'hfo-golf-registration' ), $expiration ) ); ?></span>
							</div>
						</div>
					</td>
					<td class="hfo-golf-meal-coupon-column-code"><code class="hfo-golf-meal-coupon-code"><?php echo esc_html( $code ); ?></code></td>
					<td class="hfo-golf-meal-coupon-column-recipient">
						<div class="hfo-golf-meal-coupon-recipient">
							<span class="hfo-golf-meal-coupon-recipient-name"><?php echo esc_html( $recipient_name ); ?></span>
							<?php $this->render_recipient_email_line( $coupon_post->ID, $recipient_email ); ?>
						</div>
					</td>
					<td class="hfo-golf-meal-coupon-column-meals">
						<div class="hfo-golf-meal-coupon-meals" aria-label="<?php echo esc_attr( sprintf( __( 'Lunch: %1$s, Dinner: %2$s', 'hfo-golf-registration' ), $lunch_count, $dinner_count ) ); ?>">
							<span class="hfo-golf-meal-coupon-meal-pill hfo-golf-meal-coupon-meal-pill--lunch"><?php echo esc_html( sprintf( __( 'Lunch %s', 'hfo-golf-registration' ), $lunch_count ) ); ?></span>
							<span class="hfo-golf-meal-coupon-meal-pill hfo-golf-meal-coupon-meal-pill--dinner"><?php echo esc_html( sprintf( __( 'Dinner %s', 'hfo-golf-registration' ), $dinner_count ) ); ?></span>
						</div>
					</td>
					<td class="hfo-golf-meal-coupon-column-usage"><?php echo esc_html( $usage ); ?></td>
					<td class="hfo-golf-meal-coupon-column-expiration"><?php echo esc_html( $expiration ); ?></td>
					<td class="hfo-golf-meal-coupon-actions-cell hfo-golf-meal-coupon-column-actions">
						<div class="hfo-golf-meal-coupon-actions-inner">
							<button class="hfo-golf-meal-coupon-button hfo-golf-meal-coupon-button--small hfo-golf-meal-coupon-action-button" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?php echo esc_js( $code ); ?>');" aria-label="<?php echo esc_attr( sprintf( __( 'Copy coupon code %s', 'hfo-golf-registration' ), $code ) ); ?>" title="<?php esc_attr_e( 'Copy Code', 'hfo-golf-registration' ); ?>"><span class="hfo-golf-meal-coupon-action-icon" aria-hidden="true">⧉</span><span><?php esc_html_e( 'Copy', 'hfo-golf-registration' ); ?></span></button>
							<form class="hfo-golf-meal-coupon-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="<?php echo esc_attr( self::DISABLE_ACTION ); ?>" />
								<input type="hidden" name="coupon_id" value="<?php echo esc_attr( $coupon_post->ID ); ?>" />
								<input type="hidden" name="redirect_to" value="<?php echo esc_url( $this->get_current_url() ); ?>" />
								<?php wp_nonce_field( self::DISABLE_NONCE_ACTION, self::DISABLE_NONCE_NAME ); ?>
								<button class="hfo-golf-meal-coupon-button hfo-golf-meal-coupon-button--small hfo-golf-meal-coupon-action-button" type="submit" aria-label="<?php echo esc_attr( sprintf( __( 'Disable coupon %s', 'hfo-golf-registration' ), $code ) ); ?>" title="<?php esc_attr_e( 'Disable Coupon', 'hfo-golf-registration' ); ?>"><span class="hfo-golf-meal-coupon-action-icon" aria-hidden="true">⊘</span><span><?php esc_html_e( 'Disable', 'hfo-golf-registration' ); ?></span></button>
							</form>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<div class="hfo-golf-meal-coupon-card-list">
			<?php if ( empty( $coupons ) ) : ?>
				<div class="hfo-golf-meal-coupon-empty-card"><?php esc_html_e( 'No active meal coupons found.', 'hfo-golf-registration' ); ?></div>
			<?php endif; ?>
			<?php foreach ( $coupons as $coupon_post ) : $coupon = new WC_Coupon( $coupon_post->ID ); $code = $coupon->get_code(); $recipient_name = get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_recipient_name', true ); $recipient_email = get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_recipient_email', true ); $lunch_count = get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_lunch_count', true ); $dinner_count = get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_dinner_count', true ); $usage = $coupon->get_usage_count() . ' / ' . $coupon->get_usage_limit(); $expires = $coupon->get_date_expires(); $expiration = $expires ? $expires->date_i18n( get_option( 'date_format' ) ) : '—'; ?>
				<article class="hfo-golf-meal-coupon-card-item">
					<div class="hfo-golf-meal-coupon-card-header">
						<code class="hfo-golf-meal-coupon-card-code"><?php echo esc_html( $code ); ?></code>
					</div>
					<div class="hfo-golf-meal-coupon-card-body">
						<div class="hfo-golf-meal-coupon-card-primary">
							<div class="hfo-golf-meal-coupon-card-recipient">
								<span class="hfo-golf-meal-coupon-recipient-name"><?php echo esc_html( $recipient_name ); ?></span>
								<?php $this->render_recipient_email_line( $coupon_post->ID, $recipient_email ); ?>
							</div>
							<div class="hfo-golf-meal-coupon-card-meals hfo-golf-meal-coupon-meals" aria-label="<?php echo esc_attr( sprintf( __( 'Lunch: %1$s, Dinner: %2$s', 'hfo-golf-registration' ), $lunch_count, $dinner_count ) ); ?>">
								<span class="hfo-golf-meal-coupon-meal-pill hfo-golf-meal-coupon-meal-pill--lunch"><?php echo esc_html( sprintf( __( 'Lunch %s', 'hfo-golf-registration' ), $lunch_count ) ); ?></span>
								<span class="hfo-golf-meal-coupon-meal-pill hfo-golf-meal-coupon-meal-pill--dinner"><?php echo esc_html( sprintf( __( 'Dinner %s', 'hfo-golf-registration' ), $dinner_count ) ); ?></span>
							</div>
						</div>
						<div class="hfo-golf-meal-coupon-card-secondary">
							<dl class="hfo-golf-meal-coupon-card-meta">
								<div><dt><?php esc_html_e( 'Usage', 'hfo-golf-registration' ); ?></dt><dd><?php echo esc_html( $usage ); ?></dd></div>
								<div><dt><?php esc_html_e( 'Expiration', 'hfo-golf-registration' ); ?></dt><dd><?php echo esc_html( $expiration ); ?></dd></div>
							</dl>
						</div>
					</div>
					<div class="hfo-golf-meal-coupon-card-actions">
						<button class="hfo-golf-meal-coupon-button hfo-golf-meal-coupon-button--small hfo-golf-meal-coupon-action-primary" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?php echo esc_js( $code ); ?>');" aria-label="<?php echo esc_attr( sprintf( __( 'Copy coupon code %s', 'hfo-golf-registration' ), $code ) ); ?>" title="<?php esc_attr_e( 'Copy Code', 'hfo-golf-registration' ); ?>"><span class="hfo-golf-meal-coupon-action-icon" aria-hidden="true">⧉</span><span><?php esc_html_e( 'Copy Code', 'hfo-golf-registration' ); ?></span></button>
						<form class="hfo-golf-meal-coupon-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::DISABLE_ACTION ); ?>" />
							<input type="hidden" name="coupon_id" value="<?php echo esc_attr( $coupon_post->ID ); ?>" />
							<input type="hidden" name="redirect_to" value="<?php echo esc_url( $this->get_current_url() ); ?>" />
							<?php wp_nonce_field( self::DISABLE_NONCE_ACTION, self::DISABLE_NONCE_NAME ); ?>
							<button class="hfo-golf-meal-coupon-button hfo-golf-meal-coupon-button--small hfo-golf-meal-coupon-action-secondary" type="submit" aria-label="<?php echo esc_attr( sprintf( __( 'Disable coupon %s', 'hfo-golf-registration' ), $code ) ); ?>" title="<?php esc_attr_e( 'Disable Coupon', 'hfo-golf-registration' ); ?>"><span class="hfo-golf-meal-coupon-action-icon" aria-hidden="true">⊘</span><span><?php esc_html_e( 'Disable', 'hfo-golf-registration' ); ?></span></button>
						</form>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}


	/**
	 * Renders a recipient email line with an email-sent tooltip when available.
	 *
	 * @param int    $coupon_id Coupon post ID.
	 * @param string $recipient_email Recipient email address.
	 * @return void
	 */
	private function render_recipient_email_line( $coupon_id, $recipient_email ) {
		$email_sent = '1' === get_post_meta( $coupon_id, '_hfo_golf_meal_coupon_email_sent', true );
		$tooltip    = $email_sent ? $this->get_email_sent_tooltip( $coupon_id, $recipient_email ) : '';
		$classes    = 'hfo-golf-meal-coupon-email-line';
		if ( $email_sent ) {
			$classes .= ' hfo-golf-meal-coupon-email-line--sent';
		}
		?>
		<span class="<?php echo esc_attr( $classes ); ?>"<?php echo $tooltip ? ' title="' . esc_attr( $tooltip ) . '" aria-label="' . esc_attr( $tooltip ) . '"' : ''; ?>>
			<?php if ( $recipient_email ) : ?>
				<a class="hfo-golf-meal-coupon-recipient-email" href="mailto:<?php echo esc_attr( $recipient_email ); ?>"<?php echo $tooltip ? ' title="' . esc_attr( $tooltip ) . '"' : ''; ?>><?php echo esc_html( $recipient_email ); ?></a>
			<?php else : ?>
				<span class="hfo-golf-meal-coupon-recipient-email">—</span>
			<?php endif; ?>
			<?php if ( $email_sent ) : ?>
				<span class="hfo-golf-meal-coupon-email-sent-icon" title="<?php echo esc_attr( $tooltip ); ?>" aria-hidden="true">✉</span>
			<?php endif; ?>
		</span>
		<?php
	}

	private function get_email_sent_tooltip( $coupon_id, $recipient_email ) {
		$sent_at = get_post_meta( $coupon_id, '_hfo_golf_meal_coupon_email_sent_at', true );
		if ( $sent_at ) {
			return sprintf( __( 'Coupon was emailed to %1$s on %2$s.', 'hfo-golf-registration' ), $recipient_email, $this->format_email_log_datetime( $sent_at ) );
		}
		return __( 'Coupon was emailed to this address.', 'hfo-golf-registration' );
	}

	private function get_recent_email_log_entries() {
		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
				'meta_key'       => '_hfo_golf_meal_coupon_email_requested',
				'meta_value'     => '1',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$entries = array();
		foreach ( $coupons as $coupon_post ) {
			$coupon = new WC_Coupon( $coupon_post->ID );
			$user   = get_user_by( 'id', absint( get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_created_by', true ) ) );
			$sent_at = get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_email_sent_at', true );
			$entries[] = array(
				'code'            => $coupon->get_code(),
				'recipient_name'  => get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_recipient_name', true ),
				'recipient_email' => get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_recipient_email', true ),
				'sent_at_display' => $sent_at ? $this->format_email_log_datetime( $sent_at ) : get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $coupon_post ),
				'status'          => '1' === get_post_meta( $coupon_post->ID, '_hfo_golf_meal_coupon_email_sent', true ) ? 'sent' : 'failed',
				'sent_by'         => $user ? $user->display_name : __( 'Unknown', 'hfo-golf-registration' ),
			);
		}
		return $entries;
	}

	private function format_email_log_datetime( $mysql_datetime ) {
		$timestamp = strtotime( $mysql_datetime );
		if ( ! $timestamp ) {
			return $mysql_datetime;
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	private function render_email_log_status_badge( $status ) {
		$label = 'sent' === $status ? __( 'Sent', 'hfo-golf-registration' ) : __( 'Failed', 'hfo-golf-registration' );
		$class = 'sent' === $status ? 'hfo-golf-meal-coupon-email-log-status--sent' : 'hfo-golf-meal-coupon-email-log-status--failed';
		echo '<span class="hfo-golf-meal-coupon-email-log-status ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	private function validate_create_request( $recipient_name, $recipient_email, $restrict_to_email, $email_requested, $lunch_count, $dinner_count, $expiration_date ) {
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
		if ( $email_requested && ( '' === $recipient_email || ! is_email( $recipient_email ) ) ) {
			$errors[] = __( 'Recipient Email is required and must be valid when emailing the coupon to the recipient.', 'hfo-golf-registration' );
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

	private function send_coupon_email( $recipient_email, $recipient_name, $code, $lunch_count, $dinner_count, $restrict_to_email ) {
		$subject = __( 'Your complimentary meal coupon from Hearts of the Father Outreach', 'hfo-golf-registration' );
		$body    = '<p>' . esc_html( $recipient_name ? sprintf( __( 'Hello %s,', 'hfo-golf-registration' ), $recipient_name ) : __( 'Hello,', 'hfo-golf-registration' ) ) . '</p>';
		$body   .= '<p>' . esc_html__( 'Hearts of the Father Outreach has created a complimentary meal coupon for you.', 'hfo-golf-registration' ) . '</p>';
		$body   .= '<p><strong>' . esc_html__( 'Coupon code:', 'hfo-golf-registration' ) . '</strong> ' . esc_html( $code ) . '</p>';
		$body   .= '<p><strong>' . esc_html__( 'This coupon covers:', 'hfo-golf-registration' ) . '</strong></p>';
		$body   .= '<ul>';
		$body   .= '<li>' . esc_html( sprintf( __( 'Lunch guest quantity: %d', 'hfo-golf-registration' ), $lunch_count ) ) . '</li>';
		$body   .= '<li>' . esc_html( sprintf( __( 'Dinner guest quantity: %d', 'hfo-golf-registration' ), $dinner_count ) ) . '</li>';
		$body   .= '</ul>';
		$body   .= '<p>' . esc_html__( 'Use this coupon code during checkout for complimentary lunch and/or dinner guest meals.', 'hfo-golf-registration' ) . '</p>';
		if ( $restrict_to_email ) {
			$body .= '<p>' . esc_html__( 'This coupon is restricted to the email address used for this invitation.', 'hfo-golf-registration' ) . '</p>';
		}
		$body .= '<p>' . esc_html__( 'With appreciation,', 'hfo-golf-registration' ) . '<br />' . esc_html__( 'Hearts of the Father Outreach', 'hfo-golf-registration' ) . '</p>';

		return wp_mail(
			$recipient_email,
			$subject,
			$body,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
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
