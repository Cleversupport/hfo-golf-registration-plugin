<?php
/**
 * Frontend current-user golf registrations shortcode.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the [hfo_golf_my_registration] shortcode.
 */
class HFO_Golf_My_Registration_Shortcode {

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'hfo_golf_my_registration', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Renders the current user's golf registration orders.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You must be logged in to view your registrations.', 'hfo-golf-registration' ) . '</p>';
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return '<p>' . esc_html__( 'WooCommerce must be active to view your registrations.', 'hfo-golf-registration' ) . '</p>';
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => get_current_user_id(),
				'limit'       => -1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'meta_query'  => array(
					array(
						'key'     => 'hfo_golf_registration_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		if ( empty( $orders ) ) {
			return '<p>' . esc_html__( 'No registrations found.', 'hfo-golf-registration' ) . '</p>';
		}

		ob_start();
		?>
		<div class="hfo-golf-my-registrations">
			<h2><?php esc_html_e( 'My Golf Registrations', 'hfo-golf-registration' ); ?></h2>

			<?php foreach ( $orders as $order ) : ?>
				<?php $this->render_registration_card( $order ); ?>
			<?php endforeach; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Renders a single registration order card.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return void
	 */
	private function render_registration_card( $order ) {
		if ( ! $order || ! method_exists( $order, 'get_id' ) ) {
			return;
		}

		$order_id        = $order->get_id();
		$event_id        = absint( $order->get_meta( 'hfo_golf_event_id', true ) );
		$registration_id = absint( $order->get_meta( 'hfo_golf_registration_id', true ) );
		$status          = method_exists( $order, 'get_status' ) ? $order->get_status() : '';
		$view_order_url  = method_exists( $order, 'get_view_order_url' ) ? $order->get_view_order_url() : '';
		?>
		<div class="hfo-golf-registration-card">
			<div class="hfo-golf-header">
				<strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: WooCommerce order ID. */
							__( 'Order #%d', 'hfo-golf-registration' ),
							$order_id
						)
					);
					?>
				</strong>
				<?php if ( $status ) : ?>
					<span class="status status-<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( wc_get_order_status_name( $status ) ); ?>
					</span>
				<?php endif; ?>
			</div>

			<div class="hfo-golf-body">
				<p><strong><?php esc_html_e( 'Registration Type:', 'hfo-golf-registration' ); ?></strong> <?php echo esc_html( $this->get_registration_type_label( $order ) ); ?></p>
				<p><strong><?php esc_html_e( 'Event ID:', 'hfo-golf-registration' ); ?></strong> <?php echo esc_html( $event_id ); ?></p>
				<p><strong><?php esc_html_e( 'Registration ID:', 'hfo-golf-registration' ); ?></strong> <?php echo esc_html( $registration_id ); ?></p>
				<p><strong><?php esc_html_e( 'Total:', 'hfo-golf-registration' ); ?></strong> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></p>
			</div>

			<?php if ( $view_order_url ) : ?>
				<div class="hfo-golf-footer">
					<a href="<?php echo esc_url( $view_order_url ); ?>"><?php esc_html_e( 'View Receipt', 'hfo-golf-registration' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Gets a friendly registration type label from order line item metadata.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return string
	 */
	private function get_registration_type_label( $order ) {
		if ( ! method_exists( $order, 'get_items' ) ) {
			return __( 'Golf Registration', 'hfo-golf-registration' );
		}

		$types = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) ) {
				continue;
			}

			$item_label = sanitize_text_field( $item->get_meta( 'hfo_golf_item_label', true ) );

			if ( $item_label ) {
				$types[] = $item_label;
			}
		}

		if ( empty( $types ) ) {
			return __( 'Golf Registration', 'hfo-golf-registration' );
		}

		return implode( ', ', array_unique( $types ) );
	}
}
