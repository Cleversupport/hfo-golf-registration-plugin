<?php
/**
 * WooCommerce checkout handling for golf registrations.
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
	 * Admin-post action used by the Send to Checkout action.
	 *
	 * @var string
	 */
	const ACTION = 'hfo_golf_registration_send_to_checkout';

	/**
	 * Nonce action used by the Send to Checkout action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'hfo_golf_registration_send_to_checkout';

	/**
	 * Nonce field/query arg used by the Send to Checkout action.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'hfo_golf_registration_send_to_checkout_nonce';

	/**
	 * Query arg used to identify checkout notices.
	 *
	 * @var string
	 */
	const NOTICE_QUERY_ARG = 'hfo_golf_registration_checkout_notice';

	/**
	 * Query arg used to carry missing-price labels back to the edit screen.
	 *
	 * @var string
	 */
	const MISSING_PRICES_QUERY_ARG = 'hfo_golf_registration_missing_prices';

	/**
	 * Registers WordPress and WooCommerce hooks used by the checkout handler.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_send_to_checkout' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_cart_item_prices' ) );
	}

	/**
	 * Handles the admin Send to Checkout request.
	 *
	 * @return void
	 */
	public function handle_send_to_checkout() {
		$registration_id = $this->get_request_absint( 'registration_id' );

		if ( ! $registration_id || ! current_user_can( 'edit_post', $registration_id ) ) {
			wp_die( esc_html__( 'Invalid registration.', 'hfo-golf-registration' ) );
		}

		$nonce = $this->get_request_text( self::NONCE_NAME );

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid request.', 'hfo-golf-registration' ) );
		}

		$cart_items = $this->get_cart_items( $registration_id );

		if ( ! empty( $cart_items['missing_prices'] ) ) {
			$this->redirect_to_registration_edit_screen(
				$registration_id,
				array(
					self::NOTICE_QUERY_ARG         => 'missing_prices',
					self::MISSING_PRICES_QUERY_ARG => implode( ',', $cart_items['missing_prices'] ),
				)
			);
		}

		if ( empty( $cart_items['items'] ) ) {
			$this->redirect_to_registration_edit_screen(
				$registration_id,
				array(
					self::NOTICE_QUERY_ARG => 'empty_cart',
				)
			);
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			$this->redirect_to_registration_edit_screen(
				$registration_id,
				array(
					self::NOTICE_QUERY_ARG => 'woocommerce_unavailable',
				)
			);
		}

		WC()->cart->empty_cart();

		foreach ( $cart_items['items'] as $cart_item ) {
			WC()->cart->add_to_cart(
				$cart_item['product_id'],
				$cart_item['quantity'],
				0,
				array(),
				array(
					'hfo_golf_registration_id' => $registration_id,
					'hfo_golf_event_id'        => $cart_item['event_id'],
					'hfo_golf_item_type'       => $cart_item['type'],
					'hfo_golf_item_price'      => $cart_item['price'],
				)
			);
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Renders checkout-related admin notices on the registration edit screen.
	 *
	 * @return void
	 */
	public function render_admin_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || HFO_Golf_Registration_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$notice = isset( $_GET[ self::NOTICE_QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_QUERY_ARG ] ) ) : '';
		if ( '' === $notice ) {
			return;
		}

		$message = '';
		if ( 'missing_prices' === $notice ) {
			$missing_prices = isset( $_GET[ self::MISSING_PRICES_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::MISSING_PRICES_QUERY_ARG ] ) ) : '';
			$labels         = $this->sanitize_missing_price_labels( explode( ',', $missing_prices ) );

			if ( ! empty( $labels ) ) {
				$message = sprintf(
					/* translators: %s: comma-separated list of registration items missing prices. */
					esc_html__( 'The related golf event is missing a valid price for: %s.', 'hfo-golf-registration' ),
					esc_html( implode( ', ', $labels ) )
				);
			}
		} elseif ( 'empty_cart' === $notice ) {
			$message = esc_html__( 'There are no registration items with a quantity greater than zero to send to checkout.', 'hfo-golf-registration' );
		} elseif ( 'woocommerce_unavailable' === $notice ) {
			$message = esc_html__( 'WooCommerce is unavailable. Please activate WooCommerce before sending this registration to checkout.', 'hfo-golf-registration' );
		}

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
			wp_kses_post( $message )
		);
	}

	/**
	 * Applies event prices to checkout cart items created by this plugin.
	 *
	 * @param WC_Cart $cart WooCommerce cart instance.
	 * @return void
	 */
	public function apply_cart_item_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['hfo_golf_item_price'] ) || empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$cart_item['data']->set_price( (float) $cart_item['hfo_golf_item_price'] );
		}
	}

	/**
	 * Generates checkout cart items and records selected items missing valid event prices.
	 *
	 * @param int $registration_id Registration post ID.
	 * @return array{items:array<int,array<string,mixed>>,missing_prices:array<int,string>}
	 */
	private function get_cart_items( $registration_id ) {
		$event_id       = absint( get_post_meta( $registration_id, 'related_event', true ) );
		$items          = array();
		$missing_prices = array();

		if ( ! $event_id ) {
			return array(
				'items'          => $items,
				'missing_prices' => $missing_prices,
			);
		}

		foreach ( $this->get_checkout_item_definitions() as $definition ) {
			$quantity = max( 0, absint( get_post_meta( $registration_id, $definition['quantity_meta_key'], true ) ) );

			if ( 0 >= $quantity ) {
				continue;
			}

			$raw_price = get_post_meta( $event_id, $definition['price_meta_key'], true );
			$price     = (float) $raw_price;

			if ( '' === trim( (string) $raw_price ) || 0 >= $price ) {
				$missing_prices[] = $definition['label'];
				continue;
			}

			$product_id = absint( get_option( $definition['product_option_name'], 0 ) );
			if ( ! $product_id ) {
				continue;
			}

			$items[] = array(
				'event_id'    => $event_id,
				'type'        => $definition['type'],
				'product_id'  => $product_id,
				'quantity'    => $quantity,
				'price'       => $price,
			);
		}

		return array(
			'items'          => $items,
			'missing_prices' => $missing_prices,
		);
	}


	/**
	 * Gets a sanitized integer from the current request.
	 *
	 * @param string $key Request key.
	 * @return int
	 */
	private function get_request_absint( $key ) {
		if ( ! isset( $_REQUEST[ $key ] ) ) {
			return 0;
		}

		return absint( wp_unslash( $_REQUEST[ $key ] ) );
	}

	/**
	 * Gets sanitized text from the current request.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private function get_request_text( $key ) {
		if ( ! isset( $_REQUEST[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
	}

	/**
	 * Redirects back to the registration edit screen with query args.
	 *
	 * @param int   $registration_id Registration post ID.
	 * @param array $query_args      Query args to add to the edit URL.
	 * @return void
	 */
	private function redirect_to_registration_edit_screen( $registration_id, $query_args ) {
		wp_safe_redirect( add_query_arg( $query_args, get_edit_post_link( $registration_id, 'url' ) ) );
		exit;
	}

	/**
	 * Sanitizes missing-price labels passed through query args.
	 *
	 * @param array<int,string> $labels Raw labels.
	 * @return array<int,string>
	 */
	private function sanitize_missing_price_labels( $labels ) {
		$allowed_labels = wp_list_pluck( $this->get_checkout_item_definitions(), 'label' );
		$clean_labels   = array();

		foreach ( $labels as $label ) {
			$label = sanitize_text_field( $label );
			if ( in_array( $label, $allowed_labels, true ) ) {
				$clean_labels[] = $label;
			}
		}

		return array_values( array_unique( $clean_labels ) );
	}

	/**
	 * Gets checkout item definitions.
	 *
	 * @return array<int,array{type:string,label:string,quantity_meta_key:string,price_meta_key:string,product_option_name:string}>
	 */
	private function get_checkout_item_definitions() {
		return array(
			array(
				'type'                => 'golf',
				'label'               => 'Golf',
				'quantity_meta_key'   => 'golf_qty',
				'price_meta_key'      => 'golf_price',
				'product_option_name' => 'hfo_golf_registration_golf_product_id',
			),
			array(
				'type'                => 'lunch',
				'label'               => 'Lunch',
				'quantity_meta_key'   => 'lunch_qty',
				'price_meta_key'      => 'lunch_price',
				'product_option_name' => 'hfo_golf_registration_lunch_product_id',
			),
			array(
				'type'                => 'dinner',
				'label'               => 'Dinner',
				'quantity_meta_key'   => 'dinner_qty',
				'price_meta_key'      => 'dinner_price',
				'product_option_name' => 'hfo_golf_registration_dinner_product_id',
			),
			array(
				'type'                => 'platinum_sponsor',
				'label'               => 'Platinum Sponsor',
				'quantity_meta_key'   => 'platinum_sponsor_qty',
				'price_meta_key'      => 'platinum_sponsor_price',
				'product_option_name' => 'hfo_golf_registration_platinum_sponsor_product_id',
			),
			array(
				'type'                => 'gold_sponsor',
				'label'               => 'Gold Sponsor',
				'quantity_meta_key'   => 'gold_sponsor_qty',
				'price_meta_key'      => 'gold_sponsor_price',
				'product_option_name' => 'hfo_golf_registration_gold_sponsor_product_id',
			),
			array(
				'type'                => 'silver_sponsor',
				'label'               => 'Silver Sponsor',
				'quantity_meta_key'   => 'silver_sponsor_qty',
				'price_meta_key'      => 'silver_sponsor_price',
				'product_option_name' => 'hfo_golf_registration_silver_sponsor_product_id',
			),
			array(
				'type'                => 'tee_sponsor',
				'label'               => 'Tee Sponsor',
				'quantity_meta_key'   => 'tee_sponsor_qty',
				'price_meta_key'      => 'tee_sponsor_price',
				'product_option_name' => 'hfo_golf_registration_tee_sponsor_product_id',
			),
		);
	}
}
