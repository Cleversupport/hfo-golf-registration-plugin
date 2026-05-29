<?php
/**
 * Golf registration checkout handler.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends golf registrations to WooCommerce checkout.
 */
class HFO_Golf_Registration_Checkout_Handler {

	/**
	 * Admin-post action used by the checkout button.
	 *
	 * @var string
	 */
	const ACTION = 'hfo_golf_registration_send_to_checkout';

	/**
	 * Nonce action used by the checkout button.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'hfo_golf_registration_send_to_checkout';

	/**
	 * Nonce field name used by the checkout button.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'hfo_golf_registration_checkout_nonce';

	/**
	 * Query argument used for admin checkout notices.
	 *
	 * @var string
	 */
	const NOTICE_QUERY_ARG = 'hfo_golf_registration_checkout_notice';

	/**
	 * Registers WordPress and WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_checkout_actions_meta_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_send_to_checkout' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_custom_cart_item_prices' ) );
	}

	/**
	 * Adds the checkout actions meta box to the registration edit screen.
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
					'action'          => self::ACTION,
					'registration_id' => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION,
			self::NONCE_NAME
		);
		?>
		<p><?php esc_html_e( 'Send this registration to WooCommerce checkout.', 'hfo-golf-registration' ); ?></p>
		<p>
			<?php if ( current_user_can( 'edit_post', $post->ID ) ) : ?>
				<a class="button button-primary button-large" href="<?php echo esc_url( $checkout_url ); ?>">
					<?php esc_html_e( 'Send to Checkout', 'hfo-golf-registration' ); ?>
				</a>
			<?php else : ?>
				<span class="button button-primary button-large disabled" aria-disabled="true">
					<?php esc_html_e( 'Send to Checkout', 'hfo-golf-registration' ); ?>
				</span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Handles the admin-post request that builds the WooCommerce cart.
	 *
	 * @return void
	 */
	public function handle_send_to_checkout() {
		$registration_id = isset( $_REQUEST['registration_id'] ) ? absint( wp_unslash( $_REQUEST['registration_id'] ) ) : 0;
		$nonce          = isset( $_REQUEST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ self::NONCE_NAME ] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->redirect_to_registration( $registration_id, __( 'Invalid checkout request. Please try again.', 'hfo-golf-registration' ), 'error' );
		}

		if ( ! $this->is_valid_registration( $registration_id ) ) {
			$this->redirect_to_registration( $registration_id, __( 'Invalid golf registration.', 'hfo-golf-registration' ), 'error' );
		}

		if ( ! current_user_can( 'edit_post', $registration_id ) ) {
			$this->redirect_to_registration( $registration_id, __( 'You do not have permission to send this registration to checkout.', 'hfo-golf-registration' ), 'error' );
		}

		$event_id = absint( get_post_meta( $registration_id, 'related_event', true ) );

		if ( ! $this->is_valid_event( $event_id ) ) {
			$this->redirect_to_registration( $registration_id, __( 'Please select a valid related event before sending this registration to checkout.', 'hfo-golf-registration' ), 'error' );
		}

		if ( ! $this->is_woocommerce_available() ) {
			$this->redirect_to_registration( $registration_id, __( 'WooCommerce must be active before sending a registration to checkout.', 'hfo-golf-registration' ), 'error' );
		}

		$cart_items = $this->build_cart_items( $registration_id, $event_id );

		if ( is_wp_error( $cart_items ) ) {
			$this->redirect_to_registration( $registration_id, $cart_items->get_error_message(), 'error' );
		}

		if ( empty( $cart_items ) ) {
			$this->redirect_to_registration( $registration_id, __( 'Please add at least one checkout item quantity before sending this registration to checkout.', 'hfo-golf-registration' ), 'error' );
		}

		$this->ensure_cart_is_loaded();

		if ( ! WC()->cart ) {
			$this->redirect_to_registration( $registration_id, __( 'WooCommerce cart is unavailable. Please try again.', 'hfo-golf-registration' ), 'error' );
		}

		WC()->cart->empty_cart();

		foreach ( $cart_items as $cart_item ) {
			$added = WC()->cart->add_to_cart(
				$cart_item['product_id'],
				$cart_item['quantity'],
				0,
				array(),
				$cart_item['data']
			);

			if ( ! $added ) {
				$this->redirect_to_registration(
					$registration_id,
					sprintf(
						/* translators: %s: checkout item label. */
						__( 'Unable to add %s to the WooCommerce cart.', 'hfo-golf-registration' ),
						$cart_item['label']
					),
					'error'
				);
			}
		}

		if ( method_exists( WC()->cart, 'set_session' ) ) {
			WC()->cart->set_session();
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Golf registration items were added to checkout.', 'hfo-golf-registration' ), 'success' );
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Applies stored custom prices to checkout cart items.
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
			if ( empty( $cart_item['hfo_golf_registration_custom_price'] ) || empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$price = (float) $cart_item['hfo_golf_registration_custom_price'];

			if ( $price > 0 && method_exists( $cart_item['data'], 'set_price' ) ) {
				$cart_item['data']->set_price( $price );
			}
		}
	}

	/**
	 * Renders checkout notices on the registration edit screen.
	 *
	 * @return void
	 */
	public function render_admin_notice() {
		if ( ! isset( $_GET[ self::NOTICE_QUERY_ARG ] ) ) {
			return;
		}

		$message = sanitize_text_field( wp_unslash( $_GET[ self::NOTICE_QUERY_ARG ] ) );
		$type    = isset( $_GET['hfo_golf_registration_checkout_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['hfo_golf_registration_checkout_notice_type'] ) ) : 'error';
		$class   = 'success' === $type ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}

	/**
	 * Builds checkout cart item definitions from registration quantities and event prices.
	 *
	 * @param int $registration_id Registration post ID.
	 * @param int $event_id        Related event post ID.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function build_cart_items( $registration_id, $event_id ) {
		$items            = array();
		$missing_prices   = array();
		$missing_products = array();

		foreach ( $this->get_checkout_item_definitions() as $item_type => $definition ) {
			$quantity = absint( get_post_meta( $registration_id, $definition['quantity_key'], true ) );

			if ( 0 === $quantity ) {
				continue;
			}

			$raw_price = get_post_meta( $event_id, $definition['price_key'], true );
			$price     = (float) $raw_price;

			if ( '' === $raw_price || $price <= 0 ) {
				$missing_prices[] = $definition['label'];
				continue;
			}

			$product_id = absint( get_option( $definition['product_option'], 0 ) );

			if ( ! $this->is_valid_product( $product_id ) ) {
				$missing_products[] = $definition['label'];
				continue;
			}

			$items[] = array(
				'product_id' => $product_id,
				'quantity'   => $quantity,
				'label'      => $definition['label'],
				'data'       => array(
					'hfo_golf_registration_id'           => $registration_id,
					'hfo_golf_registration_event_id'     => $event_id,
					'hfo_golf_registration_item_type'    => $item_type,
					'hfo_golf_registration_item_label'   => $definition['label'],
					'hfo_golf_registration_custom_price' => number_format( $price, 2, '.', '' ),
				),
			);
		}

		if ( ! empty( $missing_prices ) ) {
			return new WP_Error(
				'hfo_golf_registration_missing_prices',
				sprintf(
					/* translators: %s: comma-separated checkout item labels. */
					__( 'Missing or invalid prices on the related golf event for: %s. Please enter prices greater than 0 before sending this registration to checkout.', 'hfo-golf-registration' ),
					implode( ', ', $missing_prices )
				)
			);
		}

		if ( ! empty( $missing_products ) ) {
			return new WP_Error(
				'hfo_golf_registration_missing_products',
				sprintf(
					/* translators: %s: comma-separated checkout item labels. */
					__( 'Missing WooCommerce product mappings for: %s. Please configure product mappings before sending this registration to checkout.', 'hfo-golf-registration' ),
					implode( ', ', $missing_products )
				)
			);
		}

		return $items;
	}

	/**
	 * Redirects back to a registration edit screen with an admin notice.
	 *
	 * @param int    $registration_id Registration post ID.
	 * @param string $message         Notice message.
	 * @param string $type            Notice type.
	 * @return void
	 */
	private function redirect_to_registration( $registration_id, $message, $type = 'error' ) {
		$redirect_url = $registration_id ? get_edit_post_link( $registration_id, 'raw' ) : admin_url( 'edit.php?post_type=' . HFO_Golf_Registration_Post_Type::POST_TYPE );
		$redirect_url = add_query_arg(
			array(
				self::NOTICE_QUERY_ARG                         => $message,
				'hfo_golf_registration_checkout_notice_type' => sanitize_key( $type ),
			),
			$redirect_url
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Ensures the WooCommerce cart is available during admin-post processing.
	 *
	 * @return void
	 */
	private function ensure_cart_is_loaded() {
		if ( WC()->cart ) {
			return;
		}

		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
	}

	/**
	 * Checks whether a registration post ID is valid.
	 *
	 * @param int $registration_id Registration post ID.
	 * @return bool
	 */
	private function is_valid_registration( $registration_id ) {
		$post = get_post( $registration_id );

		return $post && HFO_Golf_Registration_Post_Type::POST_TYPE === $post->post_type;
	}

	/**
	 * Checks whether a related event post ID is valid.
	 *
	 * @param int $event_id Event post ID.
	 * @return bool
	 */
	private function is_valid_event( $event_id ) {
		$post = get_post( $event_id );

		return $post && HFO_Golf_Event_Post_Type::POST_TYPE === $post->post_type;
	}

	/**
	 * Checks whether WooCommerce is active enough for cart operations.
	 *
	 * @return bool
	 */
	private function is_woocommerce_available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && function_exists( 'wc_get_checkout_url' );
	}

	/**
	 * Checks whether a WooCommerce product is valid for checkout.
	 *
	 * @param int $product_id Product post ID.
	 * @return bool
	 */
	private function is_valid_product( $product_id ) {
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		return $product && 'publish' === get_post_status( $product_id );
	}

	/**
	 * Gets checkout item definitions.
	 *
	 * @return array<string,array<string,string>>
	 */
	private function get_checkout_item_definitions() {
		return array(
			'golf'             => array(
				'label'          => __( 'Golf', 'hfo-golf-registration' ),
				'quantity_key'   => 'golf_qty',
				'price_key'      => 'golf_price',
				'product_option' => 'hfo_golf_registration_golf_product_id',
			),
			'lunch'            => array(
				'label'          => __( 'Lunch', 'hfo-golf-registration' ),
				'quantity_key'   => 'lunch_qty',
				'price_key'      => 'lunch_price',
				'product_option' => 'hfo_golf_registration_lunch_product_id',
			),
			'dinner'           => array(
				'label'          => __( 'Dinner', 'hfo-golf-registration' ),
				'quantity_key'   => 'dinner_qty',
				'price_key'      => 'dinner_price',
				'product_option' => 'hfo_golf_registration_dinner_product_id',
			),
			'platinum_sponsor' => array(
				'label'          => __( 'Platinum Sponsor', 'hfo-golf-registration' ),
				'quantity_key'   => 'platinum_sponsor_qty',
				'price_key'      => 'platinum_sponsor_price',
				'product_option' => 'hfo_golf_registration_platinum_sponsor_product_id',
			),
			'gold_sponsor'     => array(
				'label'          => __( 'Gold Sponsor', 'hfo-golf-registration' ),
				'quantity_key'   => 'gold_sponsor_qty',
				'price_key'      => 'gold_sponsor_price',
				'product_option' => 'hfo_golf_registration_gold_sponsor_product_id',
			),
			'silver_sponsor'   => array(
				'label'          => __( 'Silver Sponsor', 'hfo-golf-registration' ),
				'quantity_key'   => 'silver_sponsor_qty',
				'price_key'      => 'silver_sponsor_price',
				'product_option' => 'hfo_golf_registration_silver_sponsor_product_id',
			),
			'tee_sponsor'      => array(
				'label'          => __( 'Tee Sponsor', 'hfo-golf-registration' ),
				'quantity_key'   => 'tee_sponsor_qty',
				'price_key'      => 'tee_sponsor_price',
				'product_option' => 'hfo_golf_registration_tee_sponsor_product_id',
			),
		);
	}
}
