<?php
/** WooCommerce My Account golf registrations endpoint. @package HFO_Golf_Registration */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Adds a protected customer registration history to My Account. */
class HFO_Golf_My_Account {
	const ENDPOINT = 'golf-registrations';
	const CAPABILITY = 'view_hfo_golf_registrations';

	/** @var HFO_Golf_Registration_Lookup_Shortcode */
	private $registration_lookup;

	/** @param HFO_Golf_Registration_Lookup_Shortcode $registration_lookup Shared lookup service. */
	public function __construct( $registration_lookup ) { $this->registration_lookup = $registration_lookup; }

	/** Registers WooCommerce endpoint hooks. */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_content' ) );
	}

	/** Registers the account rewrite endpoint and completes one-time upgrade flushing. */
	public function register_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
		if ( '1' === get_option( 'hfo_golf_registration_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'hfo_golf_registration_flush_rewrite_rules' );
		}
	}

	/** @param array $query_vars Account query variables. @return array */
	public function add_query_var( $query_vars ) { $query_vars[ self::ENDPOINT ] = self::ENDPOINT; return $query_vars; }

	/** Inserts the authorized-only link immediately after Orders. */
	public function add_menu_item( $items ) {
		if ( ! current_user_can( self::CAPABILITY ) ) { return $items; }
		$menu = array();
		foreach ( $items as $key => $label ) {
			$menu[ $key ] = $label;
			if ( 'orders' === $key ) { $menu[ self::ENDPOINT ] = __( 'Golf Registrations', 'hfo-golf-registration' ); }
		}
		return $menu;
	}

	/** Renders registrations belonging only to the current authorized user. */
	public function render_content() {
		if ( ! is_user_logged_in() || ! current_user_can( self::CAPABILITY ) ) { return; }
		$rows = $this->registration_lookup->get_customer_registration_rows( get_current_user_id() );
		wp_enqueue_style( 'hfo-golf-registration-lookup', plugins_url( 'assets/css/hfo-golf-registration-lookup.css', HFO_GOLF_REGISTRATION_FILE ), array(), HFO_GOLF_REGISTRATION_VERSION );
		?>
		<div class="hfo-golf-registration-lookup hfo-golf-registration-my-account">
			<h2><?php esc_html_e( 'Golf Registrations', 'hfo-golf-registration' ); ?></h2>
			<?php if ( empty( $rows ) ) : ?>
				<p class="hfo-golf-registration-lookup-message"><?php esc_html_e( 'You do not have any golf registrations yet.', 'hfo-golf-registration' ); ?></p>
			<?php else : $this->render_cards( $rows ); endif; ?>
		</div>
		<?php
	}

	/** Renders each registration as a vertically stacked card. */
	private function render_cards( $rows ) {
		$fields = array(
			'event'          => __( 'Event Name', 'hfo-golf-registration' ),
			'event_date'     => __( 'Event Date', 'hfo-golf-registration' ),
			'type'           => __( 'Registration Type', 'hfo-golf-registration' ),
			'team'           => __( 'Team Name', 'hfo-golf-registration' ),
			'sponsor'        => __( 'Sponsor Level', 'hfo-golf-registration' ),
			'payment_status' => __( 'Payment Status', 'hfo-golf-registration' ),
			'contact'        => __( 'Main Contact', 'hfo-golf-registration' ),
			'email'          => __( 'Email', 'hfo-golf-registration' ),
			'phone'          => __( 'Phone', 'hfo-golf-registration' ),
			'players'        => __( 'Players Count', 'hfo-golf-registration' ),
			'lunch'          => __( 'Lunch Guests', 'hfo-golf-registration' ),
			'dinner'         => __( 'Dinner Guests', 'hfo-golf-registration' ),
		);
		?>
		<div class="hfo-golf-registration-cards">
			<?php foreach ( $rows as $row ) : ?>
				<article class="hfo-golf-registration-card">
					<dl class="hfo-golf-registration-card__fields">
						<?php foreach ( $fields as $key => $label ) : ?>
							<?php $this->render_field( $label, $row[ $key ] ); ?>
						<?php endforeach; ?>
						<?php $this->render_field( __( 'Order Number', 'hfo-golf-registration' ), $row['order_number'] ? '#' . $row['order_number'] : '' ); ?>
						<?php $this->render_field( __( 'Total Paid', 'hfo-golf-registration' ), $this->format_price( $row['total'] ) ); ?>
					</dl>
					<?php $this->render_actions( $row ); ?>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/** Outputs one escaped label/value row. */
	private function render_field( $label, $value ) {
		?>
		<div class="hfo-golf-registration-card__field">
			<dt><?php echo esc_html( $label ); ?></dt>
			<dd><?php echo esc_html( '' === (string) $value ? '—' : $value ); ?></dd>
		</div>
		<?php
	}

	/** Outputs the permitted order action when an order URL is available. */
	private function render_actions( $row ) {
		if ( ! $row['order_number'] ) { return; }
		$url = '';
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) ) { $url = $row['order_edit_url']; }
		elseif ( current_user_can( 'view_order', $row['order_id'] ) ) { $url = $row['order_view_url']; }
		if ( ! $url ) { return; }
		?>
		<div class="hfo-golf-registration-card__actions">
			<a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View Order', 'hfo-golf-registration' ); ?></a>
		</div>
		<?php
	}

	/** Returns a plain-text localized currency value. */
	private function format_price( $amount ) { return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amount ) ) : '$' . number_format_i18n( $amount, 2 ); }
}
