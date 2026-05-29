<?php
/**
 * Golf Registration meta boxes.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and saves meta boxes for golf_registration posts.
 */
class HFO_Golf_Registration_Meta_Boxes {

	/**
	 * Meta box nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'hfo_golf_registration_meta_boxes_save';

	/**
	 * Meta box nonce field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'hfo_golf_registration_meta_boxes_nonce';

	/**
	 * Admin-post action used to send a registration to WooCommerce checkout.
	 *
	 * @var string
	 */
	const SEND_TO_CHECKOUT_ACTION = 'hfo_golf_registration_send_to_checkout';

	/**
	 * Nonce action used to send a registration to WooCommerce checkout.
	 *
	 * @var string
	 */
	const SEND_TO_CHECKOUT_NONCE_ACTION = 'hfo_golf_registration_send_to_checkout_nonce';

	/**
	 * Query argument used for the send-to-checkout nonce.
	 *
	 * @var string
	 */
	const SEND_TO_CHECKOUT_NONCE_NAME = 'hfo_golf_registration_send_to_checkout_nonce';

	/**
	 * Valid registration type values.
	 *
	 * @var array<string,string>
	 */
	private $registration_types = array(
		'individual' => 'Individual',
		'team'       => 'Team',
	);

	/**
	 * Valid registration status values.
	 *
	 * @var array<string,string>
	 */
	private $registration_statuses = array(
		'pending'   => 'Pending',
		'submitted' => 'Submitted',
		'paid'      => 'Paid',
		'cancelled' => 'Cancelled',
	);

	/**
	 * Valid participation type values.
	 *
	 * @var array<string,string>
	 */
	private $participation_types = array(
		'golf'   => 'Golf',
		'lunch'  => 'Lunch',
		'dinner' => 'Dinner',
	);

	/**
	 * Valid payment status values.
	 *
	 * @var array<string,string>
	 */
	private $payment_statuses = array(
		'unpaid'   => 'Unpaid',
		'pending'  => 'Pending',
		'paid'     => 'Paid',
		'failed'   => 'Failed',
		'refunded' => 'Refunded',
	);

	/**
	 * Registers WordPress hooks used by the registration meta boxes.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_checkout_actions_meta_box' ) );
		add_action( 'save_post_' . HFO_Golf_Registration_Post_Type::POST_TYPE, array( $this, 'save_meta_boxes' ) );
		add_action( 'admin_post_' . self::SEND_TO_CHECKOUT_ACTION, array( $this, 'handle_send_to_checkout' ) );
		add_action( 'admin_notices', array( $this, 'render_send_to_checkout_notice' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_custom_cart_item_prices' ) );
	}

	/**
	 * Adds the checkout actions meta box to the golf_registration edit screen.
	 *
	 * @return void
	 */
	public function add_checkout_actions_meta_box() {
		add_meta_box(
			'hfo_golf_registration_checkout_actions',
			esc_html__( 'Checkout Actions', 'hfo-golf-registration' ),
			array( $this, 'render_checkout_actions_meta_box' ),
			HFO_Golf_Registration_Post_Type::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Renders the checkout actions meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_checkout_actions_meta_box( $post ) {
		$url = add_query_arg(
			array(
				'action'                                  => self::SEND_TO_CHECKOUT_ACTION,
				'registration_id'                         => $post->ID,
				self::SEND_TO_CHECKOUT_NONCE_NAME       => wp_create_nonce( self::SEND_TO_CHECKOUT_NONCE_ACTION ),
			),
			admin_url( 'admin-post.php' )
		);
		?>
		<p><?php echo esc_html__( "Add this registration's selected items to the WooCommerce cart and continue to checkout.", 'hfo-golf-registration' ); ?></p>
		<p>
			<a class="button button-primary widefat" href="<?php echo esc_url( $url ); ?>">
				<?php echo esc_html__( 'Send to Checkout', 'hfo-golf-registration' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Adds meta boxes to the golf_registration edit screen.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		foreach ( $this->get_sections() as $section_id => $section ) {
			add_meta_box(
				'hfo_golf_registration_' . $section_id,
				$section['title'],
				array( $this, 'render_section_meta_box' ),
				HFO_Golf_Registration_Post_Type::POST_TYPE,
				'normal',
				$section['priority'],
				array( 'section_id' => $section_id )
			);
		}
	}

	/**
	 * Renders one registration meta box section.
	 *
	 * @param WP_Post $post Current post object.
	 * @param array   $box  Meta box data.
	 * @return void
	 */
	public function render_section_meta_box( $post, $box ) {
		$section_id = isset( $box['args']['section_id'] ) ? sanitize_key( $box['args']['section_id'] ) : '';
		$sections   = $this->get_sections();

		if ( ! isset( $sections[ $section_id ] ) ) {
			return;
		}

		if ( 'general_registration' === $section_id ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		}

		foreach ( $sections[ $section_id ]['fields'] as $key => $field ) {
			$this->render_field( $key, $field, $post->ID );
		}
	}

	/**
	 * Saves golf_registration meta box values.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return void
	 */
	public function save_meta_boxes( $post_id ) {
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}

		foreach ( $this->get_fields() as $key => $field ) {
			$this->save_meta_value( $post_id, $key, $field['type'] );
		}
	}

	/**
	 * Checks whether the current request can save meta box values.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return bool
	 */
	private function can_save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Saves one meta value after sanitizing it by type.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $type    Sanitization type.
	 * @return void
	 */
	private function save_meta_value( $post_id, $key, $type ) {
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		$value = $this->sanitize_meta_value( $value, $type );

		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Sanitizes a meta value by type.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Sanitization type.
	 * @return string
	 */
	private function sanitize_meta_value( $value, $type ) {
		switch ( $type ) {
			case 'amount':
				$amount = is_scalar( $value ) ? preg_replace( '/[^0-9.\-]/', '', (string) $value ) : '';
				$amount = (float) $amount;
				$amount = max( 0, $amount );
				return number_format( $amount, 2, '.', '' );

			case 'count':
				return (string) max( 0, absint( $value ) );

			case 'email':
				return sanitize_email( $value );

			case 'event_id':
				$event_id = absint( $value );
				return $this->is_valid_event_id( $event_id ) ? (string) $event_id : '';

			case 'registration_type':
				$registration_type = sanitize_key( $value );
				return array_key_exists( $registration_type, $this->registration_types ) ? $registration_type : 'individual';

			case 'registration_status':
				$registration_status = sanitize_key( $value );
				return array_key_exists( $registration_status, $this->registration_statuses ) ? $registration_status : 'pending';

			case 'participation_type':
				$participation_type = sanitize_key( $value );
				return array_key_exists( $participation_type, $this->participation_types ) ? $participation_type : 'golf';

			case 'payment_status':
				$payment_status = sanitize_key( $value );
				return array_key_exists( $payment_status, $this->payment_statuses ) ? $payment_status : 'unpaid';

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Handles the admin-post request to send a registration to WooCommerce checkout.
	 *
	 * @return void
	 */
	public function handle_send_to_checkout() {
		$registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
		$nonce           = isset( $_GET[ self::SEND_TO_CHECKOUT_NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::SEND_TO_CHECKOUT_NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::SEND_TO_CHECKOUT_NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid checkout request.', 'hfo-golf-registration' ) );
		}

		if ( ! $registration_id || HFO_Golf_Registration_Post_Type::POST_TYPE !== get_post_type( $registration_id ) ) {
			wp_die( esc_html__( 'Invalid registration.', 'hfo-golf-registration' ) );
		}

		if ( ! current_user_can( 'edit_post', $registration_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to send this registration to checkout.', 'hfo-golf-registration' ) );
		}

		$event_id = absint( get_post_meta( $registration_id, 'related_event', true ) );
		if ( ! $this->is_valid_event_id( $event_id ) ) {
			$this->set_send_to_checkout_notice( 'error', esc_html__( 'Select a valid related event before sending this registration to checkout.', 'hfo-golf-registration' ) );
			$this->redirect_to_registration_edit_screen( $registration_id );
		}

		$cart_items            = $this->get_registration_checkout_cart_items( $registration_id, $event_id );
		$missing_price_labels = $this->get_missing_checkout_price_labels( $cart_items );

		if ( ! empty( $missing_price_labels ) ) {
			$this->set_send_to_checkout_notice(
				'error',
				sprintf(
					/* translators: %s: comma-separated list of registration item labels. */
					esc_html__( 'Cannot send this registration to checkout because the related event is missing valid prices for: %s.', 'hfo-golf-registration' ),
					esc_html( implode( ', ', $missing_price_labels ) )
				)
			);
			$this->redirect_to_registration_edit_screen( $registration_id );
		}

		if ( empty( $cart_items ) ) {
			$this->set_send_to_checkout_notice( 'error', esc_html__( 'This registration does not have any checkout items with a quantity greater than zero.', 'hfo-golf-registration' ) );
			$this->redirect_to_registration_edit_screen( $registration_id );
		}

		if ( ! $this->prepare_woocommerce_cart() ) {
			$this->set_send_to_checkout_notice( 'error', esc_html__( 'WooCommerce cart is unavailable. Please make sure WooCommerce is active.', 'hfo-golf-registration' ) );
			$this->redirect_to_registration_edit_screen( $registration_id );
		}

		WC()->cart->empty_cart();

		foreach ( $cart_items as $cart_item ) {
			WC()->cart->add_to_cart(
				$cart_item['product_id'],
				$cart_item['quantity'],
				0,
				array(),
				array(
					'hfo_golf_registration_id'           => $registration_id,
					'hfo_golf_registration_item_label'   => $cart_item['label'],
					'hfo_golf_registration_custom_price' => $cart_item['price'],
				)
			);
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Applies event-specific prices to registration cart items.
	 *
	 * @param WC_Cart $cart WooCommerce cart object.
	 * @return void
	 */
	public function apply_custom_cart_item_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart || ! is_a( $cart, 'WC_Cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! isset( $cart_item['hfo_golf_registration_custom_price'], $cart_item['data'] ) ) {
				continue;
			}

			$price = (float) $cart_item['hfo_golf_registration_custom_price'];
			if ( $price <= 0 ) {
				continue;
			}

			$cart_item['data']->set_price( $price );
		}
	}

	/**
	 * Renders a one-time notice for checkout send attempts.
	 *
	 * @return void
	 */
	public function render_send_to_checkout_notice() {
		$notice = get_transient( $this->get_send_to_checkout_notice_transient_key() );

		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( $this->get_send_to_checkout_notice_transient_key() );

		$type    = isset( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'error';
		$message = isset( $notice['message'] ) ? (string) $notice['message'] : '';

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Builds checkout cart item data for quantities selected on a registration.
	 *
	 * @param int $registration_id Registration post ID.
	 * @param int $event_id        Related event post ID.
	 * @return array<int,array{label:string,quantity:int,price:float,price_raw:string,product_id:int}>
	 */
	private function get_registration_checkout_cart_items( $registration_id, $event_id ) {
		$cart_items = array();

		foreach ( $this->get_checkout_item_definitions() as $definition ) {
			$quantity = absint( get_post_meta( $registration_id, $definition['quantity_key'], true ) );

			if ( $quantity <= 0 ) {
				continue;
			}

			$price_raw = get_post_meta( $event_id, $definition['price_key'], true );
			$product_id = absint( get_option( $definition['option_name'], 0 ) );

			$cart_items[] = array(
				'label'      => $definition['label'],
				'quantity'   => $quantity,
				'price'      => (float) $price_raw,
				'price_raw'  => (string) $price_raw,
				'product_id' => $product_id,
			);
		}

		return $cart_items;
	}

	/**
	 * Gets labels for checkout items that have quantity but no valid event price.
	 *
	 * @param array<int,array{label:string,quantity:int,price:float,price_raw:string,product_id:int}> $cart_items Cart item data.
	 * @return array<int,string>
	 */
	private function get_missing_checkout_price_labels( $cart_items ) {
		$missing_price_labels = array();

		foreach ( $cart_items as $cart_item ) {
			if ( '' === trim( $cart_item['price_raw'] ) || $cart_item['price'] <= 0 ) {
				$missing_price_labels[] = $cart_item['label'];
			}
		}

		return $missing_price_labels;
	}

	/**
	 * Prepares the WooCommerce cart for admin-post checkout redirects.
	 *
	 * @return bool
	 */
	private function prepare_woocommerce_cart() {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		return null !== WC()->cart;
	}

	/**
	 * Stores a one-time notice for checkout send attempts.
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_send_to_checkout_notice( $type, $message ) {
		set_transient(
			$this->get_send_to_checkout_notice_transient_key(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Gets the transient key for checkout send notices.
	 *
	 * @return string
	 */
	private function get_send_to_checkout_notice_transient_key() {
		return 'hfo_golf_registration_send_to_checkout_notice_' . get_current_user_id();
	}

	/**
	 * Redirects back to a registration edit screen.
	 *
	 * @param int $registration_id Registration post ID.
	 * @return void
	 */
	private function redirect_to_registration_edit_screen( $registration_id ) {
		wp_safe_redirect( get_edit_post_link( $registration_id, 'raw' ) );
		exit;
	}

	/**
	 * Gets checkout item definitions.
	 *
	 * @return array<int,array{label:string,quantity_key:string,price_key:string,option_name:string}>
	 */
	private function get_checkout_item_definitions() {
		return array(
			array(
				'label'        => esc_html__( 'Golf', 'hfo-golf-registration' ),
				'quantity_key' => 'golf_qty',
				'price_key'    => 'golf_price',
				'option_name'  => 'hfo_golf_registration_golf_product_id',
			),
			array(
				'label'        => esc_html__( 'Lunch', 'hfo-golf-registration' ),
				'quantity_key' => 'lunch_qty',
				'price_key'    => 'lunch_price',
				'option_name'  => 'hfo_golf_registration_lunch_product_id',
			),
			array(
				'label'        => esc_html__( 'Dinner', 'hfo-golf-registration' ),
				'quantity_key' => 'dinner_qty',
				'price_key'    => 'dinner_price',
				'option_name'  => 'hfo_golf_registration_dinner_product_id',
			),
			array(
				'label'        => esc_html__( 'Platinum Sponsor', 'hfo-golf-registration' ),
				'quantity_key' => 'platinum_sponsor_qty',
				'price_key'    => 'platinum_sponsor_price',
				'option_name'  => 'hfo_golf_registration_platinum_sponsor_product_id',
			),
			array(
				'label'        => esc_html__( 'Gold Sponsor', 'hfo-golf-registration' ),
				'quantity_key' => 'gold_sponsor_qty',
				'price_key'    => 'gold_sponsor_price',
				'option_name'  => 'hfo_golf_registration_gold_sponsor_product_id',
			),
			array(
				'label'        => esc_html__( 'Silver Sponsor', 'hfo-golf-registration' ),
				'quantity_key' => 'silver_sponsor_qty',
				'price_key'    => 'silver_sponsor_price',
				'option_name'  => 'hfo_golf_registration_silver_sponsor_product_id',
			),
			array(
				'label'        => esc_html__( 'Tee Sponsor', 'hfo-golf-registration' ),
				'quantity_key' => 'tee_sponsor_qty',
				'price_key'    => 'tee_sponsor_price',
				'option_name'  => 'hfo_golf_registration_tee_sponsor_product_id',
			),
		);
	}

	/**
	 * Renders a field by type.
	 *
	 * @param string $key     Meta key.
	 * @param array  $field   Field settings.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function render_field( $key, $field, $post_id ) {
		switch ( $field['type'] ) {
			case 'event_id':
				$this->render_related_event_field( $key, $field['label'], $post_id );
				break;

			case 'registration_type':
				$this->render_select_field( $key, $field['label'], $post_id, $this->registration_types, 'individual' );
				break;

			case 'registration_status':
				$this->render_select_field( $key, $field['label'], $post_id, $this->registration_statuses, 'pending' );
				break;

			case 'participation_type':
				$this->render_select_field( $key, $field['label'], $post_id, $this->participation_types, 'golf' );
				break;

			case 'payment_status':
				$this->render_select_field( $key, $field['label'], $post_id, $this->payment_statuses, 'unpaid' );
				break;

			case 'textarea':
				$this->render_textarea_field( $key, $field['label'], $post_id );
				break;

			case 'amount':
				$this->render_number_field(
					$key,
					$field['label'],
					$post_id,
					array(
						'min'  => '0',
						'step' => '0.01',
					)
				);
				break;

			case 'count':
				$this->render_number_field(
					$key,
					$field['label'],
					$post_id,
					array(
						'min'  => '0',
						'step' => '1',
					)
				);
				break;

			case 'email':
				$this->render_input_field( $key, $field['label'], $post_id, 'email' );
				break;

			case 'text':
			default:
				$this->render_input_field( $key, $field['label'], $post_id, 'text' );
				break;
		}
	}

	/**
	 * Renders a text-like input field.
	 *
	 * @param string $key     Meta key.
	 * @param string $label   Field label.
	 * @param int    $post_id Post ID.
	 * @param string $type    Input type.
	 * @return void
	 */
	private function render_input_field( $key, $label, $post_id, $type ) {
		$value = get_post_meta( $post_id, $key, true );
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="widefat" />
		</p>
		<?php
	}

	/**
	 * Renders a number input field.
	 *
	 * @param string $key        Meta key.
	 * @param string $label      Field label.
	 * @param int    $post_id    Post ID.
	 * @param array  $attributes Optional number input attributes.
	 * @return void
	 */
	private function render_number_field( $key, $label, $post_id, $attributes = array() ) {
		$value = get_post_meta( $post_id, $key, true );
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<input
				type="number"
				id="<?php echo esc_attr( $key ); ?>"
				name="<?php echo esc_attr( $key ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="widefat"
				<?php foreach ( $attributes as $attribute => $attribute_value ) : ?>
					<?php echo esc_attr( $attribute ); ?>="<?php echo esc_attr( $attribute_value ); ?>"
				<?php endforeach; ?>
			/>
		</p>
		<?php
	}

	/**
	 * Renders a select field.
	 *
	 * @param string $key     Meta key.
	 * @param string $label   Field label.
	 * @param int    $post_id Post ID.
	 * @param array  $options Select options.
	 * @param string $default Default value.
	 * @return void
	 */
	private function render_select_field( $key, $label, $post_id, $options, $default ) {
		$current = get_post_meta( $post_id, $key, true );
		$current = array_key_exists( $current, $options ) ? $current : $default;
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat">
				<?php foreach ( $options as $value => $option_label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Renders the related event select field.
	 *
	 * @param string $key     Meta key.
	 * @param string $label   Field label.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function render_related_event_field( $key, $label, $post_id ) {
		$current = absint( get_post_meta( $post_id, $key, true ) );
		$events  = $this->get_events();
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat">
				<option value=""><?php echo esc_html__( 'Select an event', 'hfo-golf-registration' ); ?></option>
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $current, $event->ID ); ?>><?php echo esc_html( get_the_title( $event ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Renders a textarea field.
	 *
	 * @param string $key     Meta key.
	 * @param string $label   Field label.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function render_textarea_field( $key, $label, $post_id ) {
		$value = get_post_meta( $post_id, $key, true );
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat" rows="4"><?php echo esc_textarea( $value ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Checks whether a post ID belongs to an existing golf_event post.
	 *
	 * @param int $event_id Event post ID.
	 * @return bool
	 */
	private function is_valid_event_id( $event_id ) {
		if ( 0 === $event_id ) {
			return false;
		}

		return HFO_Golf_Event_Post_Type::POST_TYPE === get_post_type( $event_id );
	}

	/**
	 * Gets events for the related event select dropdown.
	 *
	 * @return array<int,WP_Post>
	 */
	private function get_events() {
		return get_posts(
			array(
				'post_type'      => HFO_Golf_Event_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Gets all field definitions keyed by meta key.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function get_fields() {
		$fields = array();

		foreach ( $this->get_sections() as $section ) {
			$fields = array_merge( $fields, $section['fields'] );
		}

		return $fields;
	}

	/**
	 * Gets meta box section definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_sections() {
		return array(
			'general_registration'    => array(
				'title'    => esc_html__( 'General Registration', 'hfo-golf-registration' ),
				'priority' => 'high',
				'fields'   => array(
					'related_event'       => array(
						'label' => esc_html__( 'Related Event', 'hfo-golf-registration' ),
						'type'  => 'event_id',
					),
					'registration_type'   => array(
						'label' => esc_html__( 'Registration Type', 'hfo-golf-registration' ),
						'type'  => 'registration_type',
					),
					'registration_status' => array(
						'label' => esc_html__( 'Registration Status', 'hfo-golf-registration' ),
						'type'  => 'registration_status',
					),
				),
			),
			'main_contact'            => array(
				'title'    => esc_html__( 'Main Contact', 'hfo-golf-registration' ),
				'priority' => 'default',
				'fields'   => array(
					'main_contact_name'    => array(
						'label' => esc_html__( 'Name', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'main_contact_email'   => array(
						'label' => esc_html__( 'Email', 'hfo-golf-registration' ),
						'type'  => 'email',
					),
					'main_contact_phone'   => array(
						'label' => esc_html__( 'Phone', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'main_contact_address' => array(
						'label' => esc_html__( 'Address', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'main_contact_city'    => array(
						'label' => esc_html__( 'City', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'main_contact_state'   => array(
						'label' => esc_html__( 'State', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'main_contact_zip'     => array(
						'label' => esc_html__( 'ZIP', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
				),
			),
			'team_captain'            => array(
				'title'    => esc_html__( 'Team Captain', 'hfo-golf-registration' ),
				'priority' => 'default',
				'fields'   => $this->get_participant_fields( 'captain', esc_html__( 'Captain', 'hfo-golf-registration' ) ),
			),
			'team_member_2'           => array(
				'title'    => esc_html__( 'Team Member #2', 'hfo-golf-registration' ),
				'priority' => 'default',
				'fields'   => $this->get_participant_fields( 'member_2', esc_html__( 'Member #2', 'hfo-golf-registration' ) ),
			),
			'team_member_3'           => array(
				'title'    => esc_html__( 'Team Member #3', 'hfo-golf-registration' ),
				'priority' => 'default',
				'fields'   => $this->get_participant_fields( 'member_3', esc_html__( 'Member #3', 'hfo-golf-registration' ) ),
			),
			'team_member_4'           => array(
				'title'    => esc_html__( 'Team Member #4', 'hfo-golf-registration' ),
				'priority' => 'default',
				'fields'   => $this->get_participant_fields( 'member_4', esc_html__( 'Member #4', 'hfo-golf-registration' ) ),
			),
			'additional_guests'       => array(
				'title'    => esc_html__( 'Additional Guests', 'hfo-golf-registration' ),
				'priority' => 'default',
				'fields'   => array(
					'additional_lunch_count'   => array(
						'label' => esc_html__( 'Additional Lunch Count', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'additional_dinner_count'  => array(
						'label' => esc_html__( 'Additional Dinner Count', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'additional_guests_details' => array(
						'label' => esc_html__( 'Additional Guests Details', 'hfo-golf-registration' ),
						'type'  => 'textarea',
					),
				),
			),
			'sponsorship'             => array(
				'title'    => esc_html__( 'Sponsorship', 'hfo-golf-registration' ),
				'priority' => 'default',
				'fields'   => array(
					'sponsorship_level'   => array(
						'label' => esc_html__( 'Sponsorship Level', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'sponsorship_amount'  => array(
						'label' => esc_html__( 'Sponsorship Amount', 'hfo-golf-registration' ),
						'type'  => 'amount',
					),
					'sponsor_program_name' => array(
						'label' => esc_html__( 'Sponsor Program Name', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'sponsor_contact_name' => array(
						'label' => esc_html__( 'Sponsor Contact Name', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'sponsor_email'        => array(
						'label' => esc_html__( 'Sponsor Email', 'hfo-golf-registration' ),
						'type'  => 'email',
					),
					'sponsor_phone'        => array(
						'label' => esc_html__( 'Sponsor Phone', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'sponsor_address'      => array(
						'label' => esc_html__( 'Sponsor Address', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'sponsor_city'         => array(
						'label' => esc_html__( 'Sponsor City', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'sponsor_state'        => array(
						'label' => esc_html__( 'Sponsor State', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'sponsor_zip'          => array(
						'label' => esc_html__( 'Sponsor ZIP', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
				),
			),
			'payment_summary'         => array(
				'title'    => esc_html__( 'Payment Summary', 'hfo-golf-registration' ),
				'priority' => 'default',
				'fields'   => array(
					'golf_qty'             => array(
						'label' => esc_html__( 'Golf Quantity', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'lunch_qty'            => array(
						'label' => esc_html__( 'Lunch Quantity', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'dinner_qty'           => array(
						'label' => esc_html__( 'Dinner Quantity', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'platinum_sponsor_qty' => array(
						'label' => esc_html__( 'Platinum Sponsor Quantity', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'gold_sponsor_qty'     => array(
						'label' => esc_html__( 'Gold Sponsor Quantity', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'silver_sponsor_qty'   => array(
						'label' => esc_html__( 'Silver Sponsor Quantity', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'tee_sponsor_qty'      => array(
						'label' => esc_html__( 'Tee Sponsor Quantity', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'discount_code_used'   => array(
						'label' => esc_html__( 'Discount Code Used', 'hfo-golf-registration' ),
						'type'  => 'text',
					),
					'subtotal'             => array(
						'label' => esc_html__( 'Subtotal', 'hfo-golf-registration' ),
						'type'  => 'amount',
					),
					'discount_amount'      => array(
						'label' => esc_html__( 'Discount Amount', 'hfo-golf-registration' ),
						'type'  => 'amount',
					),
					'grand_total'          => array(
						'label' => esc_html__( 'Grand Total', 'hfo-golf-registration' ),
						'type'  => 'amount',
					),
				),
			),
			'woocommerce_connection'  => array(
				'title'    => esc_html__( 'WooCommerce Connection', 'hfo-golf-registration' ),
				'priority' => 'default',
				'fields'   => array(
					'woocommerce_order_id' => array(
						'label' => esc_html__( 'WooCommerce Order ID', 'hfo-golf-registration' ),
						'type'  => 'count',
					),
					'payment_status'       => array(
						'label' => esc_html__( 'Payment Status', 'hfo-golf-registration' ),
						'type'  => 'payment_status',
					),
				),
			),
		);
	}

	/**
	 * Gets participant fields for a captain or team member.
	 *
	 * @param string $prefix Meta key prefix.
	 * @param string $label_prefix Label prefix.
	 * @return array<string,array<string,string>>
	 */
	private function get_participant_fields( $prefix, $label_prefix ) {
		return array(
			$prefix . '_name'               => array(
				'label' => sprintf( '%s %s', $label_prefix, esc_html__( 'Name', 'hfo-golf-registration' ) ),
				'type'  => 'text',
			),
			$prefix . '_email'              => array(
				'label' => sprintf( '%s %s', $label_prefix, esc_html__( 'Email', 'hfo-golf-registration' ) ),
				'type'  => 'email',
			),
			$prefix . '_phone'              => array(
				'label' => sprintf( '%s %s', $label_prefix, esc_html__( 'Phone', 'hfo-golf-registration' ) ),
				'type'  => 'text',
			),
			$prefix . '_address'            => array(
				'label' => sprintf( '%s %s', $label_prefix, esc_html__( 'Address', 'hfo-golf-registration' ) ),
				'type'  => 'text',
			),
			$prefix . '_city'               => array(
				'label' => sprintf( '%s %s', $label_prefix, esc_html__( 'City', 'hfo-golf-registration' ) ),
				'type'  => 'text',
			),
			$prefix . '_state'              => array(
				'label' => sprintf( '%s %s', $label_prefix, esc_html__( 'State', 'hfo-golf-registration' ) ),
				'type'  => 'text',
			),
			$prefix . '_zip'                => array(
				'label' => sprintf( '%s %s', $label_prefix, esc_html__( 'ZIP', 'hfo-golf-registration' ) ),
				'type'  => 'text',
			),
			$prefix . '_handicap'           => array(
				'label' => sprintf( '%s %s', $label_prefix, esc_html__( 'Handicap', 'hfo-golf-registration' ) ),
				'type'  => 'text',
			),
			$prefix . '_participation_type' => array(
				'label' => sprintf( '%s %s', $label_prefix, esc_html__( 'Participation Type', 'hfo-golf-registration' ) ),
				'type'  => 'participation_type',
			),
		);
	}
}
