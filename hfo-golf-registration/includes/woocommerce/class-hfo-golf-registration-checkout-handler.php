<?php
/**
 * WooCommerce checkout handler for golf registrations.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends golf_registration posts to the WooCommerce checkout.
 */
class HFO_Golf_Registration_Checkout_Handler {

	/**
	 * Admin-post action used to send a registration to checkout.
	 *
	 * @var string
	 */
	const ACTION = 'hfo_golf_registration_send_to_checkout';

	/**
	 * Nonce action prefix used for checkout requests.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'hfo_golf_registration_send_to_checkout_';

	/**
	 * Nonce request field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'hfo_golf_registration_checkout_nonce';

	/**
	 * Notice transient prefix.
	 *
	 * @var string
	 */
	const NOTICE_TRANSIENT_PREFIX = 'hfo_golf_registration_checkout_notice_';

	/**
	 * Registers WordPress and WooCommerce hooks used by this handler.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_checkout_actions_meta_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_send_to_checkout' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_custom_cart_item_prices' ), 20 );
	}

	/**
	 * Adds checkout actions to the golf registration edit screen.
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
		if ( ! $post instanceof WP_Post || HFO_Golf_Registration_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}

		$checkout_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::ACTION,
					'post_id' => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION . $post->ID,
			self::NONCE_NAME
		);
		?>
		<p><?php echo esc_html__( 'Send this golf registration to WooCommerce checkout using the saved registration quantities and event prices.', 'hfo-golf-registration' ); ?></p>
		<?php if ( current_user_can( 'edit_post', $post->ID ) ) : ?>
			<p><a class="button button-primary" href="<?php echo esc_url( $checkout_url ); ?>"><?php echo esc_html__( 'Send to Checkout', 'hfo-golf-registration' ); ?></a></p>
		<?php else : ?>
			<p><button type="button" class="button button-primary" disabled="disabled"><?php echo esc_html__( 'Send to Checkout', 'hfo-golf-registration' ); ?></button></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Handles the protected admin-post request to send a registration to checkout.
	 *
	 * @return void
	 */
	public function handle_send_to_checkout() {
		$post_id = isset( $_REQUEST['post_id'] ) ? absint( wp_unslash( $_REQUEST['post_id'] ) ) : 0;

		if ( 0 === $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to send this registration to checkout.', 'hfo-golf-registration' ) );
		}

		check_admin_referer( self::NONCE_ACTION . $post_id, self::NONCE_NAME );

		if ( ! $this->is_woocommerce_active() ) {
			$this->redirect_with_notice( $post_id, 'error', esc_html__( 'WooCommerce must be active before a golf registration can be sent to checkout.', 'hfo-golf-registration' ) );
		}

		if ( HFO_Golf_Registration_Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			$this->redirect_with_notice( $post_id, 'error', esc_html__( 'Only golf registrations can be sent to checkout.', 'hfo-golf-registration' ) );
		}

		$event_id = absint( get_post_meta( $post_id, 'related_event', true ) );

		if ( ! $this->is_valid_golf_event( $event_id ) ) {
			$this->redirect_with_notice( $post_id, 'error', esc_html__( 'Select a valid related golf event before sending this registration to checkout.', 'hfo-golf-registration' ) );
		}

		$cart_items = $this->get_checkout_cart_items( $post_id, $event_id );

		if ( empty( $cart_items ) ) {
			$this->redirect_with_notice( $post_id, 'error', esc_html__( 'At least one registration item quantity must be greater than zero before sending this registration to checkout.', 'hfo-golf-registration' ) );
		}

		$missing_mappings = $this->get_missing_product_mapping_labels( $cart_items );

		if ( ! empty( $missing_mappings ) ) {
			$this->redirect_with_notice(
				$post_id,
				'error',
				sprintf(
					/* translators: %s: comma-separated item labels. */
					esc_html__( 'Configure WooCommerce product mappings for these registration items before sending to checkout: %s.', 'hfo-golf-registration' ),
					implode( ', ', $missing_mappings )
				)
			);
		}

		if ( ! $this->prepare_woocommerce_cart() ) {
			$this->redirect_with_notice( $post_id, 'error', esc_html__( 'WooCommerce cart is unavailable. Please try again after WooCommerce finishes loading.', 'hfo-golf-registration' ) );
		}

		WC()->cart->empty_cart();

		foreach ( $cart_items as $cart_item ) {
			$added = WC()->cart->add_to_cart(
				$cart_item['product_id'],
				$cart_item['quantity'],
				0,
				array(),
				array(
					'hfo_golf_registration_id' => $post_id,
					'hfo_golf_event_id'        => $event_id,
					'hfo_golf_item_type'       => $cart_item['item_type'],
					'hfo_golf_custom_price'    => $cart_item['price'],
				)
			);

			if ( false === $added ) {
				$this->redirect_with_notice(
					$post_id,
					'error',
					sprintf(
						/* translators: %s: item label. */
						esc_html__( 'Unable to add %s to the WooCommerce cart.', 'hfo-golf-registration' ),
						$cart_item['label']
					)
				);
			}
		}

		if ( method_exists( WC()->cart, 'set_session' ) ) {
			WC()->cart->set_session();
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Applies saved custom prices to HFO cart items before WooCommerce calculates totals.
	 *
	 * @param WC_Cart $cart WooCommerce cart object.
	 * @return void
	 */
	public function apply_custom_cart_item_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! isset( $cart_item['data'], $cart_item['hfo_golf_custom_price'] ) || ! is_object( $cart_item['data'] ) || ! method_exists( $cart_item['data'], 'set_price' ) ) {
				continue;
			}

			$custom_price = $this->sanitize_price( $cart_item['hfo_golf_custom_price'] );
			$cart_item['data']->set_price( $custom_price );
		}
	}

	/**
	 * Renders a checkout admin notice on the golf registration edit screen.
	 *
	 * @return void
	 */
	public function render_admin_notice() {
		$screen = get_current_screen();

		if ( ! $screen || HFO_Golf_Registration_Post_Type::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		if ( 0 === $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$notice = get_transient( $this->get_notice_transient_key( $post_id ) );

		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( $this->get_notice_transient_key( $post_id ) );

		$type    = isset( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'error';
		$message = isset( $notice['message'] ) ? (string) $notice['message'] : '';

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Gets cart item definitions that have a positive quantity.
	 *
	 * @param int $registration_id Golf registration post ID.
	 * @param int $event_id        Related golf event post ID.
	 * @return array<int,array{item_type:string,label:string,product_id:int,quantity:int,price:float}>
	 */
	private function get_checkout_cart_items( $registration_id, $event_id ) {
		$items = array();

		foreach ( $this->get_item_definitions() as $definition ) {
			$quantity = absint( get_post_meta( $registration_id, $definition['quantity_meta_key'], true ) );

			if ( 0 === $quantity ) {
				continue;
			}

			$items[] = array(
				'item_type'  => $definition['item_type'],
				'label'      => $definition['label'],
				'product_id' => absint( get_option( $definition['product_option_name'], 0 ) ),
				'quantity'   => $quantity,
				'price'      => $this->sanitize_price( get_post_meta( $event_id, $definition['price_meta_key'], true ) ),
			);
		}

		return $items;
	}

	/**
	 * Gets item labels that are missing valid WooCommerce product mappings.
	 *
	 * @param array<int,array{label:string,product_id:int}> $cart_items Cart item definitions.
	 * @return array<int,string>
	 */
	private function get_missing_product_mapping_labels( $cart_items ) {
		$missing = array();

		foreach ( $cart_items as $cart_item ) {
			if ( empty( $cart_item['product_id'] ) || ! $this->is_valid_product( $cart_item['product_id'] ) ) {
				$missing[] = $cart_item['label'];
			}
		}

		return $missing;
	}

	/**
	 * Loads the WooCommerce cart when available.
	 *
	 * @return bool
	 */
	private function prepare_woocommerce_cart() {
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		return function_exists( 'WC' ) && WC() && isset( WC()->cart ) && WC()->cart;
	}

	/**
	 * Checks whether WooCommerce functions needed by this integration are available.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && function_exists( 'wc_get_checkout_url' );
	}

	/**
	 * Checks whether a post ID is a valid golf_event.
	 *
	 * @param int $event_id Golf event post ID.
	 * @return bool
	 */
	private function is_valid_golf_event( $event_id ) {
		return $event_id > 0 && HFO_Golf_Event_Post_Type::POST_TYPE === get_post_type( $event_id );
	}

	/**
	 * Checks whether a post ID is a published WooCommerce product.
	 *
	 * @param int $product_id Product post ID.
	 * @return bool
	 */
	private function is_valid_product( $product_id ) {
		$product = get_post( $product_id );

		return $product instanceof WP_Post && 'product' === $product->post_type && 'publish' === $product->post_status;
	}

	/**
	 * Sanitizes an amount for use as a WooCommerce item price.
	 *
	 * @param mixed $price Raw price value.
	 * @return float
	 */
	private function sanitize_price( $price ) {
		$price = is_scalar( $price ) ? preg_replace( '/[^0-9.\-]/', '', (string) $price ) : '';
		$price = (float) $price;

		return max( 0, $price );
	}

	/**
	 * Stores a notice and redirects back to the registration edit screen.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function redirect_with_notice( $post_id, $type, $message ) {
		set_transient(
			$this->get_notice_transient_key( $post_id ),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}

	/**
	 * Gets the current user's checkout notice transient key for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_notice_transient_key( $post_id ) {
		return self::NOTICE_TRANSIENT_PREFIX . get_current_user_id() . '_' . absint( $post_id );
	}

	/**
	 * Gets checkout item definitions keyed by cart item type.
	 *
	 * @return array<int,array{item_type:string,label:string,quantity_meta_key:string,price_meta_key:string,product_option_name:string}>
	 */
	private function get_item_definitions() {
		return array(
			array(
				'item_type'           => 'golf',
				'label'               => esc_html__( 'Golf', 'hfo-golf-registration' ),
				'quantity_meta_key'   => 'golf_qty',
				'price_meta_key'      => 'golf_price',
				'product_option_name' => 'hfo_golf_registration_golf_product_id',
			),
			array(
				'item_type'           => 'lunch',
				'label'               => esc_html__( 'Lunch', 'hfo-golf-registration' ),
				'quantity_meta_key'   => 'lunch_qty',
				'price_meta_key'      => 'lunch_price',
				'product_option_name' => 'hfo_golf_registration_lunch_product_id',
			),
			array(
				'item_type'           => 'dinner',
				'label'               => esc_html__( 'Dinner', 'hfo-golf-registration' ),
				'quantity_meta_key'   => 'dinner_qty',
				'price_meta_key'      => 'dinner_price',
				'product_option_name' => 'hfo_golf_registration_dinner_product_id',
			),
			array(
				'item_type'           => 'platinum_sponsor',
				'label'               => esc_html__( 'Platinum Sponsor', 'hfo-golf-registration' ),
				'quantity_meta_key'   => 'platinum_sponsor_qty',
				'price_meta_key'      => 'platinum_sponsor_price',
				'product_option_name' => 'hfo_golf_registration_platinum_sponsor_product_id',
			),
			array(
				'item_type'           => 'gold_sponsor',
				'label'               => esc_html__( 'Gold Sponsor', 'hfo-golf-registration' ),
				'quantity_meta_key'   => 'gold_sponsor_qty',
				'price_meta_key'      => 'gold_sponsor_price',
				'product_option_name' => 'hfo_golf_registration_gold_sponsor_product_id',
			),
			array(
				'item_type'           => 'silver_sponsor',
				'label'               => esc_html__( 'Silver Sponsor', 'hfo-golf-registration' ),
				'quantity_meta_key'   => 'silver_sponsor_qty',
				'price_meta_key'      => 'silver_sponsor_price',
				'product_option_name' => 'hfo_golf_registration_silver_sponsor_product_id',
			),
			array(
				'item_type'           => 'tee_sponsor',
				'label'               => esc_html__( 'Tee Sponsor', 'hfo-golf-registration' ),
				'quantity_meta_key'   => 'tee_sponsor_qty',
				'price_meta_key'      => 'tee_sponsor_price',
				'product_option_name' => 'hfo_golf_registration_tee_sponsor_product_id',
			),
		);
	}
}
