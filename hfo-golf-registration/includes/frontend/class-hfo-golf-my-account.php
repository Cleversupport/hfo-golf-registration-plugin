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
			<?php else : $this->render_table( $rows ); endif; ?>
		</div>
		<?php
	}

	/** Renders the shared registration data in a responsive table/card structure. */
	private function render_table( $rows ) {
		$headers = array( 'Event Name', 'Event Date', 'Registration Type', 'Team Name', 'Sponsor Level', 'Players Count', 'Lunch Guests', 'Dinner Guests', 'Order Number', 'Payment Status', 'Total Paid' );
		?>
		<div class="hfo-golf-registration-lookup-table-wrap"><table><thead><tr><?php foreach ( $headers as $header ) : ?><th scope="col"><?php echo esc_html( $header ); ?></th><?php endforeach; ?></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : ?><tr>
			<?php $this->cell( $headers[0], $row['event'] ); $this->cell( $headers[1], $row['event_date'] ); $this->cell( $headers[2], $row['type'] ); $this->cell( $headers[3], $row['team'] ); $this->cell( $headers[4], $row['sponsor'] ); $this->cell( $headers[5], $row['players'] ); $this->cell( $headers[6], $row['lunch'] ); $this->cell( $headers[7], $row['dinner'] ); ?>
			<td data-label="<?php echo esc_attr( $headers[8] ); ?>"><?php $this->render_order( $row ); ?></td>
			<?php $this->cell( $headers[9], $row['payment_status'] ); $this->cell( $headers[10], $this->format_price( $row['total'] ) ); ?>
		</tr><?php endforeach; ?></tbody></table></div>
		<?php
	}

	/** Outputs one escaped table value. */
	private function cell( $label, $value ) { echo '<td data-label="' . esc_attr( $label ) . '">' . esc_html( '' === (string) $value ? '—' : $value ) . '</td>'; }

	/** Outputs a permitted customer/admin order link, or an unlinked order number. */
	private function render_order( $row ) {
		if ( ! $row['order_number'] ) { echo esc_html( '—' ); return; }
		$url = '';
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) ) { $url = $row['order_edit_url']; }
		elseif ( current_user_can( 'view_order', $row['order_id'] ) ) { $url = $row['order_view_url']; }
		echo '#' . esc_html( $row['order_number'] );
		if ( $url ) { echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'View Order', 'hfo-golf-registration' ) . '</a>'; }
	}

	/** Returns a plain-text localized currency value. */
	private function format_price( $amount ) { return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amount ) ) : '$' . number_format_i18n( $amount, 2 ); }
}
