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
	 * Golf Registration meta key used by the admin WooCommerce Order ID field.
	 *
	 * @var string
	 */
	const REGISTRATION_ORDER_ID_META_KEY = 'woocommerce_order_id';

	/**
	 * Golf Registration meta key used by the admin Payment Status field.
	 *
	 * @var string
	 */
	const REGISTRATION_PAYMENT_STATUS_META_KEY = 'payment_status';

	/**
	 * Golf Registration meta key used by the admin Registration Status field.
	 *
	 * @var string
	 */
	const REGISTRATION_STATUS_META_KEY = 'registration_status';

	/**
	 * WooCommerce session key for golf checkout billing prefill data.
	 *
	 * @var string
	 */
	const BILLING_PREFILL_SESSION_KEY = 'hfo_golf_checkout_billing_prefill';

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
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_golf30_coupon' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'add_golf_event_to_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'add_golf_registration_meta_to_order' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_golf_registration_meta_to_order_item' ), 10, 4 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_golf_registration_order_item_meta' ) );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'hide_golf_registration_formatted_order_item_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'link_created_order_to_registration' ) );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'prefill_checkout_field_value' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'sync_registration_status_from_order' ), 10, 4 );
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

		$event_id = $this->get_registration_event_id( $registration_id );

		if ( ! $this->is_valid_event( $event_id ) ) {
			$this->redirect_to_registration( $registration_id, __( 'Please select a valid related event before sending this registration to checkout.', 'hfo-golf-registration' ), 'error' );
		}

		if ( ! $this->is_woocommerce_available() ) {
			$this->redirect_to_registration( $registration_id, __( 'WooCommerce must be active before sending a registration to checkout.', 'hfo-golf-registration' ), 'error' );
		}

		$cart_result = $this->add_registration_to_cart( $registration_id );

		if ( is_wp_error( $cart_result ) ) {
			$this->redirect_to_registration( $registration_id, $cart_result->get_error_message(), 'error' );
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'Golf registration items were added to checkout.', 'hfo-golf-registration' ), 'success' );
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Adds the registration checkout items to the WooCommerce cart.
	 *
	 * @param int $registration_id Registration post ID.
	 * @return true|WP_Error
	 */
	public function add_registration_to_cart( $registration_id ) {
		$registration_id = absint( $registration_id );

		if ( ! $this->is_valid_registration( $registration_id ) ) {
			return new WP_Error( 'hfo_golf_registration_invalid_registration', __( 'Invalid golf registration.', 'hfo-golf-registration' ) );
		}

		$event_id = $this->get_registration_event_id( $registration_id );

		if ( ! $this->is_valid_event( $event_id ) ) {
			return new WP_Error( 'hfo_golf_registration_invalid_event', __( 'Please select a valid related event before sending this registration to checkout.', 'hfo-golf-registration' ) );
		}

		if ( ! $this->is_woocommerce_available() ) {
			return new WP_Error( 'hfo_golf_registration_woocommerce_unavailable', __( 'WooCommerce must be active before sending a registration to checkout.', 'hfo-golf-registration' ) );
		}

		$cart_items = $this->build_cart_items( $registration_id, $event_id );

		if ( is_wp_error( $cart_items ) ) {
			return $cart_items;
		}

		if ( empty( $cart_items ) ) {
			return new WP_Error( 'hfo_golf_registration_empty_cart_items', __( 'Please add at least one checkout item quantity before sending this registration to checkout.', 'hfo-golf-registration' ) );
		}

		$this->ensure_cart_is_loaded();
		$this->store_registration_billing_prefill( $registration_id );

		if ( ! WC()->cart ) {
			return new WP_Error( 'hfo_golf_registration_cart_unavailable', __( 'WooCommerce cart is unavailable. Please try again.', 'hfo-golf-registration' ) );
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
				return new WP_Error(
					'hfo_golf_registration_cart_add_failed',
					sprintf(
						/* translators: %s: checkout item label. */
						__( 'Unable to add %s to the WooCommerce cart.', 'hfo-golf-registration' ),
						$cart_item['label']
					)
				);
			}
		}

		if ( method_exists( WC()->cart, 'set_session' ) ) {
			WC()->cart->set_session();
		}

		return true;
	}

	/**
	 * Adds a registration to the cart and redirects to WooCommerce checkout.
	 *
	 * @param int $registration_id Registration post ID.
	 * @return void
	 */
	public function send_registration_to_checkout( $registration_id ) {
		$cart_result = $this->add_registration_to_cart( $registration_id );

		if ( is_wp_error( $cart_result ) ) {
			wp_die( esc_html( $cart_result->get_error_message() ) );
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
	 * Validates the GOLF30 coupon against the current WooCommerce cart.
	 *
	 * @param bool      $is_valid Whether WooCommerce considers the coupon valid.
	 * @param WC_Coupon $coupon   WooCommerce coupon object.
	 * @return bool
	 * @throws Exception When GOLF30 is applied without a Platinum Sponsor cart item.
	 */
	public function validate_golf30_coupon( $is_valid, $coupon ) {
		if ( ! $coupon || ! method_exists( $coupon, 'get_code' ) ) {
			return $is_valid;
		}

		if ( 'golf30' !== strtolower( (string) $coupon->get_code() ) ) {
			return $is_valid;
		}

		if ( ! $is_valid ) {
			return $is_valid;
		}

		if ( $this->cart_contains_platinum_sponsor() ) {
			return $is_valid;
		}

		throw new Exception( __( 'This coupon is only available when a Platinum Sponsor package is in your cart.', 'hfo-golf-registration' ) );
	}

	/**
	 * Checks whether the current WooCommerce cart contains a Platinum Sponsor item.
	 *
	 * @return bool
	 */
	public function cart_contains_platinum_sponsor() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! method_exists( WC()->cart, 'get_cart' ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$registration_item_type = isset( $cart_item['hfo_golf_registration_item_type'] ) ? sanitize_key( $cart_item['hfo_golf_registration_item_type'] ) : '';
			$item_type              = isset( $cart_item['hfo_golf_item_type'] ) ? sanitize_key( $cart_item['hfo_golf_item_type'] ) : '';

			if ( 'platinum_sponsor' === $registration_item_type || 'platinum_sponsor' === $item_type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Adds the related Golf Event title to WooCommerce cart and checkout item data.
	 *
	 * @param array $item_data WooCommerce display item data.
	 * @param array $cart_item WooCommerce cart item values.
	 * @return array
	 */
	public function add_golf_event_to_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['hfo_golf_registration_id'] ) ) {
			return $item_data;
		}

		$registration_id = absint( $cart_item['hfo_golf_registration_id'] );
		$event_id        = $this->get_event_id_from_cart_item( $cart_item, $registration_id );
		$event_title     = $this->get_event_title_from_cart_item( $cart_item, $event_id );

		$item_data[] = array(
			'key'   => __( 'Event', 'hfo-golf-registration' ),
			'value' => $event_title,
		);

		return $item_data;
	}

	/**
	 * Stores golf registration identifiers on the WooCommerce order during checkout.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @param array    $data  Posted checkout data.
	 * @return void
	 */
	public function add_golf_registration_meta_to_order( $order, $data ) {
		$golf_order_data = $this->get_golf_order_data_from_cart();

		if ( empty( $golf_order_data ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$order->update_meta_data( 'hfo_golf_registration_id', $golf_order_data['registration_id'] );
		$order->update_meta_data( 'hfo_golf_event_id', $golf_order_data['event_id'] );
	}

	/**
	 * Hides internal golf registration order item metadata from customer-facing views and emails.
	 *
	 * @param array $hidden_meta_keys Hidden WooCommerce order item meta keys.
	 * @return array
	 */
	public function hide_golf_registration_order_item_meta( $hidden_meta_keys ) {
		return array_values(
			array_unique(
				array_merge(
					$hidden_meta_keys,
					$this->get_hidden_golf_registration_order_item_meta_keys()
				)
			)
		);
	}

	/**
	 * Removes internal golf registration metadata from WooCommerce's formatted order item meta output.
	 *
	 * WooCommerce uses this formatted data on the Order Received page and in order emails,
	 * so filtering it keeps the internal meta stored for registration/order syncing while
	 * preventing it from being rendered visually.
	 *
	 * @param array         $formatted_meta Formatted WooCommerce order item meta data.
	 * @param WC_Order_Item $item           WooCommerce order item object.
	 * @return array
	 */
	public function hide_golf_registration_formatted_order_item_meta( $formatted_meta, $item ) {
		$hidden_meta_keys = $this->get_hidden_golf_registration_order_item_meta_keys();

		foreach ( $formatted_meta as $meta_id => $meta ) {
			$meta_key = '';

			if ( is_object( $meta ) && isset( $meta->key ) ) {
				$meta_key = (string) $meta->key;
			} elseif ( is_array( $meta ) && isset( $meta['key'] ) ) {
				$meta_key = (string) $meta['key'];
			}

			if ( in_array( $meta_key, $hidden_meta_keys, true ) ) {
				unset( $formatted_meta[ $meta_id ] );
			}
		}

		return $formatted_meta;
	}

	/**
	 * Gets internal golf registration order item meta keys that should not be rendered.
	 *
	 * @return array<int,string>
	 */
	private function get_hidden_golf_registration_order_item_meta_keys() {
		return array(
			'hfo_golf_registration_id',
			'hfo_golf_registration_event_id',
			'hfo_golf_event_id',
			'hfo_golf_event_title',
			'hfo_golf_item_type',
			'hfo_golf_registration_item_type',
			'hfo_golf_item_label',
			'hfo_golf_registration_item_label',
			'hfo_golf_custom_price',
			'hfo_golf_registration_custom_price',
			'_hfo_golf_registration_id',
			'_hfo_golf_event_id',
		);
	}

	/**
	 * Stores golf registration data on each WooCommerce order line item.
	 *
	 * @param WC_Order_Item_Product $item          WooCommerce order item object.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         WooCommerce order object.
	 * @return void
	 */
	public function add_golf_registration_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
		$registration_id = isset( $values['hfo_golf_registration_id'] ) ? absint( $values['hfo_golf_registration_id'] ) : 0;

		if ( ! $this->is_valid_registration( $registration_id ) || ! method_exists( $item, 'add_meta_data' ) ) {
			return;
		}

		$event_id     = $this->get_event_id_from_cart_item( $values, $registration_id );
		$event_title  = $this->get_event_title_from_cart_item( $values, $event_id );
		$item_type    = isset( $values['hfo_golf_registration_item_type'] ) ? sanitize_key( $values['hfo_golf_registration_item_type'] ) : '';
		$item_label   = isset( $values['hfo_golf_registration_item_label'] ) ? sanitize_text_field( $values['hfo_golf_registration_item_label'] ) : '';
		$custom_price = isset( $values['hfo_golf_registration_custom_price'] ) ? wc_format_decimal( $values['hfo_golf_registration_custom_price'] ) : '';

		$item->add_meta_data( __( 'Event', 'hfo-golf-registration' ), $event_title, true );
		$item->add_meta_data( 'hfo_golf_registration_id', $registration_id, true );
		$item->add_meta_data( 'hfo_golf_event_id', $event_id, true );
		$item->add_meta_data( 'hfo_golf_item_type', $item_type, true );
		$item->add_meta_data( 'hfo_golf_item_label', $item_label, true );
		$item->add_meta_data( 'hfo_golf_custom_price', $custom_price, true );
		$item->add_meta_data( '_hfo_golf_registration_id', $registration_id, true );
		$item->add_meta_data( '_hfo_golf_event_id', $event_id, true );
	}

	/**
	 * Links a newly created WooCommerce order to its Golf Registration.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return void
	 */
	public function link_created_order_to_registration( $order ) {
		if ( ! $order || ! method_exists( $order, 'get_id' ) || ! method_exists( $order, 'get_status' ) ) {
			return;
		}

		$golf_order_data = $this->get_golf_order_data_from_order( $order );

		if ( empty( $golf_order_data['registration_id'] ) || ! $this->is_valid_registration( $golf_order_data['registration_id'] ) ) {
			$golf_order_data = $this->get_golf_order_data_from_cart();
		}

		$registration_id = ! empty( $golf_order_data['registration_id'] ) ? absint( $golf_order_data['registration_id'] ) : 0;

		if ( ! $this->is_valid_registration( $registration_id ) ) {
			return;
		}

		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( 'hfo_golf_registration_id', $registration_id );

			if ( ! empty( $golf_order_data['event_id'] ) ) {
				$order->update_meta_data( 'hfo_golf_event_id', absint( $golf_order_data['event_id'] ) );
			}

			if ( method_exists( $order, 'save_meta_data' ) ) {
				$order->save_meta_data();
			}
		}

		$this->sync_registration_meta_for_order_status( $registration_id, $order->get_id(), $order->get_status() );
		$this->clear_billing_prefill();

		if ( method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %d: golf registration post ID. */
					__( 'Linked to Golf Registration #%d.', 'hfo-golf-registration' ),
					$registration_id
				),
				false,
				true
			);
		}
	}

	/**
	 * Syncs Golf Registration payment and registration statuses from WooCommerce.
	 *
	 * @param int      $order_id   WooCommerce order ID.
	 * @param string   $old_status Previous WooCommerce order status.
	 * @param string   $new_status New WooCommerce order status.
	 * @param WC_Order $order      WooCommerce order object.
	 * @return void
	 */
	public function sync_registration_status_from_order( $order_id, $old_status, $new_status, $order ) {
		if ( ! $order || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}

		$registration_id = $this->get_registration_id_from_order( $order );

		if ( ! $this->is_valid_registration( $registration_id ) ) {
			return;
		}

		$this->sync_registration_meta_for_order_status( $registration_id, $order_id, $new_status );
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
		$event_title      = $this->get_event_title( $event_id );

		foreach ( $this->get_checkout_item_definitions() as $item_type => $definition ) {
			$quantity = absint( get_post_meta( $registration_id, $definition['quantity_key'], true ) );

			if ( 'tee_sponsor_qty' === $definition['quantity_key'] ) {
				$quantity = min( 1, $quantity );
			}

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
					'hfo_golf_event_id'                  => $event_id,
					'hfo_golf_event_title'               => $event_title,
					'hfo_golf_registration_item_type'    => $item_type,
					'hfo_golf_item_type'                 => $item_type,
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
	 * Gets the related Golf Event ID for a registration, with legacy and requested fallbacks.
	 *
	 * @param int $registration_id Registration post ID.
	 * @return int
	 */
	private function get_registration_event_id( $registration_id ) {
		$registration_id = absint( $registration_id );
		$event_id        = absint( get_post_meta( $registration_id, 'related_event', true ) );

		if ( $event_id ) {
			return $event_id;
		}

		return absint( get_post_meta( $registration_id, 'hfo_golf_event_id', true ) );
	}

	/**
	 * Gets a safe Golf Event title for display.
	 *
	 * @param int    $event_id       Event post ID.
	 * @param string $provided_title Optional pre-stored event title.
	 * @return string
	 */
	private function get_event_title( $event_id, $provided_title = '' ) {
		$provided_title = sanitize_text_field( $provided_title );

		if ( '' !== $provided_title ) {
			return $provided_title;
		}

		$event_id = absint( $event_id );

		if ( $event_id ) {
			$event_title = sanitize_text_field( get_the_title( $event_id ) );

			if ( '' !== $event_title ) {
				return $event_title;
			}

			return sprintf(
				/* translators: %d: golf event post ID. */
				__( 'Golf Event #%d', 'hfo-golf-registration' ),
				$event_id
			);
		}

		return __( 'Golf Event', 'hfo-golf-registration' );
	}

	/**
	 * Gets the Golf Event ID carried by a WooCommerce cart item.
	 *
	 * @param array $cart_item       WooCommerce cart item values.
	 * @param int   $registration_id Registration post ID for fallback lookup.
	 * @return int
	 */
	private function get_event_id_from_cart_item( $cart_item, $registration_id = 0 ) {
		$event_id = 0;

		if ( isset( $cart_item['hfo_golf_event_id'] ) ) {
			$event_id = absint( $cart_item['hfo_golf_event_id'] );
		}

		if ( ! $event_id && isset( $cart_item['hfo_golf_registration_event_id'] ) ) {
			$event_id = absint( $cart_item['hfo_golf_registration_event_id'] );
		}

		if ( ! $event_id && $registration_id ) {
			$event_id = $this->get_registration_event_id( $registration_id );
		}

		return $event_id;
	}

	/**
	 * Gets the Golf Event display title carried by a WooCommerce cart item.
	 *
	 * @param array $cart_item WooCommerce cart item values.
	 * @param int   $event_id  Event post ID for fallback lookup.
	 * @return string
	 */
	private function get_event_title_from_cart_item( $cart_item, $event_id = 0 ) {
		$event_title = isset( $cart_item['hfo_golf_event_title'] ) ? $cart_item['hfo_golf_event_title'] : '';

		return $this->get_event_title( $event_id, $event_title );
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
	 * Prefills a WooCommerce checkout field from golf registration session data.
	 *
	 * @param mixed  $value WooCommerce's current field value.
	 * @param string $input Checkout field key.
	 * @return mixed
	 */
	public function prefill_checkout_field_value( $value, $input ) {
		$input = sanitize_key( $input );

		if ( ! in_array( $input, self::get_supported_billing_prefill_fields(), true ) ) {
			return $value;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return $value;
		}

		$prefill = WC()->session->get( self::BILLING_PREFILL_SESSION_KEY );

		if ( ! is_array( $prefill ) || empty( $prefill[ $input ] ) ) {
			return $value;
		}

		return $prefill[ $input ];
	}

	/**
	 * Gets sanitized WooCommerce billing fields from submitted or stored registration meta.
	 *
	 * @param array<string,string> $meta Registration meta.
	 * @return array<string,string>
	 */
	public static function get_checkout_billing_contact_from_meta( $meta ) {
		$is_sponsor_only = isset( $meta['registration_type'] ) && 'sponsor_only' === $meta['registration_type'];
		$name            = $is_sponsor_only && ! empty( $meta['sponsor_contact_name'] ) ? $meta['sponsor_contact_name'] : self::get_meta_value( $meta, 'main_contact_name' );
		$name_parts      = preg_split( '/\s+/', trim( sanitize_text_field( $name ) ), 2 );

		$billing_contact = array(
			'billing_first_name' => isset( $name_parts[0] ) ? sanitize_text_field( $name_parts[0] ) : '',
			'billing_last_name'  => isset( $name_parts[1] ) ? sanitize_text_field( $name_parts[1] ) : '',
			'billing_email'      => sanitize_email( $is_sponsor_only && ! empty( $meta['sponsor_email'] ) ? $meta['sponsor_email'] : self::get_meta_value( $meta, 'main_contact_email' ) ),
			'billing_phone'      => sanitize_text_field( $is_sponsor_only && ! empty( $meta['sponsor_phone'] ) ? $meta['sponsor_phone'] : self::get_meta_value( $meta, 'main_contact_phone' ) ),
			'billing_address_1'  => sanitize_text_field( $is_sponsor_only && ! empty( $meta['sponsor_address'] ) ? $meta['sponsor_address'] : self::get_meta_value( $meta, 'main_contact_address' ) ),
			'billing_city'       => sanitize_text_field( $is_sponsor_only && ! empty( $meta['sponsor_city'] ) ? $meta['sponsor_city'] : self::get_meta_value( $meta, 'main_contact_city' ) ),
			'billing_state'      => sanitize_text_field( $is_sponsor_only && ! empty( $meta['sponsor_state'] ) ? $meta['sponsor_state'] : self::get_meta_value( $meta, 'main_contact_state' ) ),
			'billing_postcode'   => sanitize_text_field( $is_sponsor_only && ! empty( $meta['sponsor_zip'] ) ? $meta['sponsor_zip'] : self::get_meta_value( $meta, 'main_contact_zip' ) ),
			'billing_country'    => sanitize_text_field( ! empty( $meta['billing_country'] ) ? $meta['billing_country'] : 'US' ),
		);

		return array_intersect_key( $billing_contact, array_flip( self::get_supported_billing_prefill_fields() ) );
	}

	/**
	 * Converts WooCommerce billing field keys to the existing customer setter data shape.
	 *
	 * @param array<string,string> $billing_contact Billing field data.
	 * @return array<string,string>
	 */
	public static function get_customer_billing_contact_from_billing_fields( $billing_contact ) {
		return array(
			'first_name' => isset( $billing_contact['billing_first_name'] ) ? $billing_contact['billing_first_name'] : '',
			'last_name'  => isset( $billing_contact['billing_last_name'] ) ? $billing_contact['billing_last_name'] : '',
			'email'      => isset( $billing_contact['billing_email'] ) ? $billing_contact['billing_email'] : '',
			'phone'      => isset( $billing_contact['billing_phone'] ) ? $billing_contact['billing_phone'] : '',
			'address_1'  => isset( $billing_contact['billing_address_1'] ) ? $billing_contact['billing_address_1'] : '',
			'city'       => isset( $billing_contact['billing_city'] ) ? $billing_contact['billing_city'] : '',
			'state'      => isset( $billing_contact['billing_state'] ) ? $billing_contact['billing_state'] : '',
			'postcode'   => isset( $billing_contact['billing_postcode'] ) ? $billing_contact['billing_postcode'] : '',
			'country'    => isset( $billing_contact['billing_country'] ) ? $billing_contact['billing_country'] : 'US',
		);
	}

	/**
	 * Gets the supported billing prefill field keys.
	 *
	 * @return array<int,string>
	 */
	private static function get_supported_billing_prefill_fields() {
		return array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
			'billing_address_1',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
		);
	}

	/**
	 * Gets a string value from registration meta.
	 *
	 * @param array<string,string> $meta Registration meta.
	 * @param string              $key  Meta key.
	 * @return string
	 */
	private static function get_meta_value( $meta, $key ) {
		return isset( $meta[ $key ] ) ? (string) $meta[ $key ] : '';
	}

	/**
	 * Stores registration billing prefill data in the WooCommerce session.
	 *
	 * @param int $registration_id Registration post ID.
	 * @return void
	 */
	private function store_registration_billing_prefill( $registration_id ) {
		$this->ensure_cart_is_loaded();

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$meta = array();

		foreach ( array( 'registration_type', 'main_contact_name', 'main_contact_email', 'main_contact_phone', 'main_contact_address', 'main_contact_city', 'main_contact_state', 'main_contact_zip', 'sponsor_contact_name', 'sponsor_email', 'sponsor_phone', 'sponsor_address', 'sponsor_city', 'sponsor_state', 'sponsor_zip', 'billing_country' ) as $key ) {
			$meta[ $key ] = (string) get_post_meta( $registration_id, $key, true );
		}

		WC()->session->set( self::BILLING_PREFILL_SESSION_KEY, self::get_checkout_billing_contact_from_meta( $meta ) );
	}

	/**
	 * Ensures the WooCommerce cart is available during admin-post processing.
	 *
	 * @return void
	 */
	private function ensure_cart_is_loaded() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		if ( ! WC()->session && method_exists( WC(), 'initialize_session' ) ) {
			WC()->initialize_session();
		}

		if ( WC()->session && method_exists( WC()->session, 'set_customer_session_cookie' ) && ( ! method_exists( WC()->session, 'has_session' ) || ! WC()->session->has_session() ) ) {
			WC()->session->set_customer_session_cookie( true );
		}

		if ( ! WC()->customer && class_exists( 'WC_Customer' ) ) {
			WC()->customer = new WC_Customer( get_current_user_id(), true );
		}
	}

	/**
	 * Clears golf checkout billing prefill session data.
	 *
	 * @return void
	 */
	private function clear_billing_prefill() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( self::BILLING_PREFILL_SESSION_KEY );
		}
	}

	/**
	 * Gets the first valid golf registration identifiers from the WooCommerce cart.
	 *
	 * @return array<string,int>
	 */
	private function get_golf_order_data_from_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! method_exists( WC()->cart, 'get_cart' ) ) {
			return array();
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$registration_id = isset( $cart_item['hfo_golf_registration_id'] ) ? absint( $cart_item['hfo_golf_registration_id'] ) : 0;

			if ( ! $this->is_valid_registration( $registration_id ) ) {
				continue;
			}

			return array(
				'registration_id' => $registration_id,
				'event_id'        => $this->get_event_id_from_cart_item( $cart_item, $registration_id ),
			);
		}

		return array();
	}

	/**
	 * Gets golf registration identifiers from WooCommerce order meta or order item meta.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return array<string,int>
	 */
	private function get_golf_order_data_from_order( $order ) {
		$registration_id = 0;
		$event_id        = 0;

		if ( $order && method_exists( $order, 'get_meta' ) ) {
			$registration_id = absint( $order->get_meta( 'hfo_golf_registration_id', true ) );
			$event_id        = absint( $order->get_meta( 'hfo_golf_event_id', true ) );
		}

		if ( $this->is_valid_registration( $registration_id ) ) {
			return array(
				'registration_id' => $registration_id,
				'event_id'        => $event_id,
			);
		}

		if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
			return array();
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
				continue;
			}

			$registration_id = absint( $item->get_meta( 'hfo_golf_registration_id', true ) );

			if ( ! $this->is_valid_registration( $registration_id ) ) {
				continue;
			}

			return array(
				'registration_id' => $registration_id,
				'event_id'        => absint( $item->get_meta( 'hfo_golf_event_id', true ) ),
			);
		}

		return array();
	}

	/**
	 * Gets a valid golf registration ID stored on a WooCommerce order.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return int
	 */
	private function get_registration_id_from_order( $order ) {
		$golf_order_data = $this->get_golf_order_data_from_order( $order );

		return ! empty( $golf_order_data['registration_id'] ) ? absint( $golf_order_data['registration_id'] ) : 0;
	}

	/**
	 * Syncs the Golf Registration order link and statuses for a WooCommerce order status.
	 *
	 * @param int    $registration_id Registration post ID.
	 * @param int    $order_id        WooCommerce order ID.
	 * @param string $order_status    WooCommerce order status.
	 * @return void
	 */
	private function sync_registration_meta_for_order_status( $registration_id, $order_id, $order_status ) {
		$mapped_statuses = $this->map_order_status_to_registration_statuses( $order_status );

		update_post_meta( $registration_id, self::REGISTRATION_ORDER_ID_META_KEY, absint( $order_id ) );
		HFO_Golf_Registration_Post_Type::append_order_id_to_title( $registration_id, $order_id );
		update_post_meta( $registration_id, self::REGISTRATION_PAYMENT_STATUS_META_KEY, $mapped_statuses['payment_status'] );
		update_post_meta( $registration_id, self::REGISTRATION_STATUS_META_KEY, $mapped_statuses['registration_status'] );
	}

	/**
	 * Maps a WooCommerce order status to Golf Registration status meta values.
	 *
	 * @param string $order_status WooCommerce order status without the wc- prefix.
	 * @return array<string,string>
	 */
	private function map_order_status_to_registration_statuses( $order_status ) {
		switch ( $order_status ) {
			case 'completed':
			case 'processing':
				return array(
					'payment_status'      => 'paid',
					'registration_status' => 'paid',
				);

			case 'failed':
			case 'cancelled':
				return array(
					'payment_status'      => 'failed',
					'registration_status' => 'cancelled',
				);

			case 'refunded':
				return array(
					'payment_status'      => 'refunded',
					'registration_status' => 'submitted',
				);

			case 'pending':
			case 'on-hold':
			default:
				return array(
					'payment_status'      => 'pending',
					'registration_status' => 'submitted',
				);
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
