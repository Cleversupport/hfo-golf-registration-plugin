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
		add_action( 'woocommerce_checkout_order_created', array( $this, 'link_created_order_to_registration' ), 10, 1 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_link_golf_order_to_customer_user' ), 10, 3 );
		add_action( 'woocommerce_thankyou', array( $this, 'maybe_link_golf_order_to_customer_user' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'maybe_link_golf_order_to_customer_user' ), 10, 1 );
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

		if ( ! WC()->cart ) {
			return new WP_Error( 'hfo_golf_registration_cart_unavailable', __( 'WooCommerce cart is unavailable. Please try again.', 'hfo-golf-registration' ) );
		}

		if ( WC()->session && method_exists( WC()->session, 'set' ) ) {
			WC()->session->set( 'hfo_golf_registration_id', $registration_id );
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

		if ( empty( $golf_order_data ) ) {
			$session_registration_id = $this->get_registration_id_from_session();

			if ( $this->is_valid_registration( $session_registration_id ) ) {
				$golf_order_data = array(
					'registration_id' => $session_registration_id,
					'event_id'        => $this->get_registration_event_id( $session_registration_id ),
				);
			}
		}

		if ( empty( $golf_order_data ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		$order->update_meta_data( 'hfo_golf_registration_id', $golf_order_data['registration_id'] );
		$order->update_meta_data( 'hfo_golf_event_id', $golf_order_data['event_id'] );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'HFO registration_id: ' . $golf_order_data['registration_id'] );
			error_log( 'HFO order meta registration_id: ' . $order->get_meta( 'hfo_golf_registration_id' ) );
		}

		if ( method_exists( $order, 'save' ) ) {
			$order->save();
		}
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
		try {
			if ( ! $order || ! method_exists( $order, 'get_id' ) || ! method_exists( $order, 'get_status' ) ) {
				return;
			}

			$registration_id = method_exists( $order, 'get_meta' ) ? absint( $order->get_meta( 'hfo_golf_registration_id', true ) ) : 0;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'HFO registration_id: ' . $registration_id );
				error_log( 'HFO order meta registration_id: ' . ( method_exists( $order, 'get_meta' ) ? $order->get_meta( 'hfo_golf_registration_id' ) : '' ) );
			}

			$golf_order_data = array();

			if ( $this->is_valid_registration( $registration_id ) ) {
				$golf_order_data = $this->get_golf_order_data_from_order( $order );
			} else {
				$session_registration_id = $this->get_registration_id_from_session();

				if ( $this->is_valid_registration( $session_registration_id ) ) {
					$registration_id = $session_registration_id;
				} else {
					$golf_order_data = $this->get_golf_order_data_from_order( $order );
					$registration_id  = ! empty( $golf_order_data['registration_id'] ) ? absint( $golf_order_data['registration_id'] ) : 0;
				}
			}

			if ( ! $this->is_valid_registration( $registration_id ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'HFO registration_id missing for WooCommerce order ' . $order->get_id() );
				}
				return;
			}

			if ( empty( $golf_order_data ) ) {
				$golf_order_data = array(
					'registration_id' => $registration_id,
					'event_id'        => $this->get_registration_event_id( $registration_id ),
				);
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
			$this->maybe_link_golf_order_to_customer_user( $order );

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
		} catch ( Throwable $e ) {
			$this->track_customer_user_link_error( $order, 'fatal_error', $e->getMessage() );
		}
	}

	/**
	 * Links a golf registration order to a customer user after checkout data is available.
	 *
	 * @param WC_Order|int $order_or_order_id WooCommerce order object or order ID.
	 * @return void
	 */
	public function maybe_link_golf_order_to_customer_user( $order_or_order_id = 0 ) {
		$order = $order_or_order_id;

		try {
			if ( is_numeric( $order_or_order_id ) && function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( absint( $order_or_order_id ) );
			}

			if ( ! $order || ! method_exists( $order, 'get_meta' ) || ! method_exists( $order, 'get_id' ) ) {
				return;
			}

			if ( '1' === (string) $order->get_meta( '_hfo_golf_customer_user_linked', true ) ) {
				return;
			}

			$registration_id = absint( $order->get_meta( 'hfo_golf_registration_id', true ) );

			if ( 0 === $registration_id ) {
				$registration_id = $this->get_registration_id_from_session();
			}

			if ( 0 === $registration_id ) {
				$golf_order_data = $this->get_golf_order_data_from_order( $order );
				$registration_id  = ! empty( $golf_order_data['registration_id'] ) ? absint( $golf_order_data['registration_id'] ) : 0;
			}

			if ( 0 === $registration_id ) {
				$this->track_customer_user_link_error( $order, 'missing_registration_id' );
				return;
			}

			if ( ! $this->is_valid_registration( $registration_id ) ) {
				$this->track_customer_user_link_error( $order, 'invalid_registration_id' );
				return;
			}

			if ( method_exists( $order, 'update_meta_data' ) ) {
				$order->update_meta_data( 'hfo_golf_registration_id', $registration_id );
			}

			if ( method_exists( $order, 'save_meta_data' ) ) {
				$order->save_meta_data();
			}

			$this->link_order_to_customer_user( $order, $registration_id );
		} catch ( Throwable $e ) {
			$this->track_customer_user_link_error( $order, 'fatal_error', $e->getMessage() );
		}
	}

	/**
	 * Links a guest golf registration order to a customer user.
	 *
	 * @param WC_Order $order           WooCommerce order object.
	 * @param int      $registration_id Registration post ID.
	 * @return void
	 */
	private function link_order_to_customer_user( $order, $registration_id ) {
		try {
			if ( ! $order || ! method_exists( $order, 'get_customer_id' ) || ! method_exists( $order, 'set_customer_id' ) ) {
				return;
			}

			if ( method_exists( $order, 'get_meta' ) && '1' === (string) $order->get_meta( '_hfo_golf_customer_user_linked', true ) ) {
				return;
			}

			$user_id            = absint( $order->get_customer_id() );
			$customer_email     = $this->get_customer_email_for_registration_order( $order, $registration_id );
			$created_user       = false;
			$generated_password = '';

			if ( 0 === $user_id ) {
				if ( '' === $customer_email ) {
					$this->track_customer_user_link_error( $order, 'missing_billing_email' );
					return;
				}

				if ( ! is_email( $customer_email ) ) {
					$this->track_customer_user_link_error( $order, 'invalid_billing_email' );
					return;
				}

				$existing_user_id = email_exists( $customer_email );

				if ( $existing_user_id ) {
					$user_id = absint( $existing_user_id );
				} else {
					$generated_password = wp_generate_password( 16, true, true );
					$user_id            = wp_insert_user(
						array(
							'user_login' => $this->generate_unique_customer_username( $customer_email ),
							'user_email' => $customer_email,
							'user_pass'  => $generated_password,
							'first_name' => $this->get_order_billing_field( $order, 'get_billing_first_name' ),
							'last_name'  => $this->get_order_billing_field( $order, 'get_billing_last_name' ),
							'role'       => 'customer',
						)
					);

					if ( is_wp_error( $user_id ) ) {
						$this->track_customer_user_link_error( $order, 'user_creation_failed', $user_id->get_error_message() );
						return;
					}

					$created_user = true;
				}
			}

			if ( 0 === $user_id ) {
				return;
			}

			if ( ! is_email( $customer_email ) ) {
				$user = get_user_by( 'id', $user_id );

				if ( $user && is_email( $user->user_email ) ) {
					$customer_email = $user->user_email;
				}
			}

			$order->set_customer_id( $user_id );
			$order->update_meta_data( '_hfo_golf_customer_user_linked', '1' );

			if ( method_exists( $order, 'save' ) ) {
				$order->save();
			}

			update_post_meta( $registration_id, 'hfo_golf_customer_user_id', $user_id );
			update_post_meta( $registration_id, 'hfo_golf_customer_email', $customer_email );

			if ( $created_user ) {
				$email_sent = $this->send_customer_welcome_email( absint( $user_id ), $customer_email, $generated_password );
				$order->update_meta_data( '_hfo_golf_customer_welcome_email_sent', $email_sent ? '1' : '0' );

				if ( ! $email_sent ) {
					$this->track_customer_user_link_error( $order, 'email_send_failed' );
				}

				if ( method_exists( $order, 'save' ) ) {
					$order->save();
				}
			}
		} catch ( Throwable $e ) {
			$this->track_customer_user_link_error( $order, 'fatal_error', $e->getMessage() );
		}
	}

	/**
	 * Gets the best customer email for a golf registration order.
	 *
	 * @param WC_Order $order           WooCommerce order object.
	 * @param int      $registration_id Registration post ID.
	 * @return string
	 */
	private function get_customer_email_for_registration_order( $order, $registration_id ) {
		$customer_email = $this->get_order_billing_field( $order, 'get_billing_email' );

		if ( ! is_email( $customer_email ) ) {
			$customer_email = sanitize_email( get_post_meta( $registration_id, 'main_contact_email', true ) );
		}

		if ( ! is_email( $customer_email ) ) {
			$customer_email = sanitize_email( get_post_meta( $registration_id, 'sponsor_email', true ) );
		}

		return $customer_email;
	}

	/**
	 * Gets a sanitized billing field from an order when the getter exists.
	 *
	 * @param WC_Order $order  WooCommerce order object.
	 * @param string   $getter Getter method name.
	 * @return string
	 */
	private function get_order_billing_field( $order, $getter ) {
		if ( ! method_exists( $order, $getter ) ) {
			return '';
		}

		$value = $order->{$getter}();

		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Generates a unique username for a new customer account.
	 *
	 * @param string $customer_email Customer email address.
	 * @return string
	 */
	private function generate_unique_customer_username( $customer_email ) {
		$username = sanitize_user( current( explode( '@', $customer_email ) ), true );

		if ( '' === $username ) {
			$username = 'customer';
		}

		$unique_username = $username;
		$suffix          = 1;

		while ( username_exists( $unique_username ) ) {
			$unique_username = $username . $suffix;
			++$suffix;
		}

		return $unique_username;
	}

	/**
	 * Sends a custom welcome email with the generated usable password.
	 *
	 * @param int    $user_id            Customer user ID.
	 * @param string $customer_email     Customer email address.
	 * @param string $generated_password Generated customer password.
	 * @return bool Whether the email was sent successfully.
	 */
	private function send_customer_welcome_email( $user_id, $customer_email, $generated_password ) {
		try {
			$user = get_user_by( 'id', $user_id );

			if ( ! $user || ! is_email( $customer_email ) ) {
				return false;
			}

			$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$login_url = wp_login_url();
			$subject   = sprintf(
				/* translators: %s: site name. */
				__( 'Welcome to %s', 'hfo-golf-registration' ),
				$site_name
			);
			$message   = sprintf(
				/* translators: 1: site name, 2: login URL, 3: username or email, 4: generated password. */
				__( "Welcome to %1$s.

Your customer account has been created for your golf registration order.

Login URL: %2$s
Username/Email: %3$s
Password: %4$s

You can log in with this password now. You may change it later from your account if you want, but it is not required.", 'hfo-golf-registration' ),
				$site_name,
				$login_url,
				$user->user_login ? $user->user_login : $customer_email,
				$generated_password
			);

			return (bool) wp_mail( $customer_email, $subject, $message );
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'HFO golf customer welcome email failed: ' . $e->getMessage() );
			}

			return false;
		}
	}

	/**
	 * Tracks a customer user linking failure on an order.
	 *
	 * @param WC_Order $order         WooCommerce order object.
	 * @param string   $reason        Failure reason.
	 * @param string   $error_message Optional detailed error message.
	 * @return void
	 */
	private function track_customer_user_link_error( $order, $reason, $error_message = '' ) {
		try {
			$sanitized_reason  = sanitize_key( $reason );
			$sanitized_message = sanitize_text_field( (string) $error_message );

			if ( $order && method_exists( $order, 'update_meta_data' ) ) {
				$order->update_meta_data( '_hfo_golf_customer_user_link_error', $sanitized_reason );

				if ( '' !== $sanitized_message ) {
					$order->update_meta_data( '_hfo_golf_customer_user_link_error_message', $sanitized_message );
				}

				if ( method_exists( $order, 'save_meta_data' ) ) {
					$order->save_meta_data();
				} elseif ( method_exists( $order, 'save' ) ) {
					$order->save();
				}
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$order_id      = $order && method_exists( $order, 'get_id' ) ? absint( $order->get_id() ) : 0;
				$debug_message = sprintf( 'HFO golf customer user linking failed for order %d: %s', $order_id, $sanitized_reason );

				if ( '' !== $error_message ) {
					$debug_message .= ' - ' . $error_message;
				}

				error_log( $debug_message );
			}
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'HFO golf customer user link error tracking failed: ' . $e->getMessage() );
			}
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
	 * Gets a valid golf registration ID stored in the WooCommerce session.
	 *
	 * @return int
	 */
	private function get_registration_id_from_session() {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! method_exists( WC()->session, 'get' ) ) {
			return 0;
		}

		return absint( WC()->session->get( 'hfo_golf_registration_id' ) );
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
