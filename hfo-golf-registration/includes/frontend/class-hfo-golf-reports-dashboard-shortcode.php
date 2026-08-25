<?php
/**
 * Secure frontend tournament reports dashboard shortcode.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers and renders [hfo_golf_reports_dashboard]. */
class HFO_Golf_Reports_Dashboard_Shortcode {

	const CAPABILITY = 'view_hfo_golf_reports';
	const ALLOWED_ROLES_OPTION = 'hfo_golf_reports_allowed_roles';
	const NONCE_ACTION = 'hfo_golf_reports_dashboard_filter';
	const NONCE_NAME = 'hfo_golf_reports_nonce';

	/** @var HFO_Golf_Registration_Lookup_Shortcode */
	private $lookup;

	/**
	 * @param HFO_Golf_Registration_Lookup_Shortcode $lookup Existing reporting data provider.
	 */
	public function __construct( HFO_Golf_Registration_Lookup_Shortcode $lookup ) {
		$this->lookup = $lookup;
	}

	/** Registers the shortcode. */
	public function register_hooks() {
		add_shortcode( 'hfo_golf_reports_dashboard', array( $this, 'render_shortcode' ) );
	}

	/** Renders the protected reports dashboard. */
	public function render_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p class="hfo-golf-registration-lookup-message hfo-golf-registration-lookup-message--error">' . esc_html__( 'Please log in to view golf reports.', 'hfo-golf-registration' ) . '</p>';
		}
		if ( ! $this->current_user_can_view() ) {
			return '<p class="hfo-golf-registration-lookup-message hfo-golf-registration-lookup-message--error">' . esc_html__( 'You do not have permission to view golf reports.', 'hfo-golf-registration' ) . '</p>';
		}

		$event_id = $this->get_selected_event_id();
		$rows     = $this->lookup->get_report_rows( $event_id );
		$report   = $this->build_report( $rows );

		wp_enqueue_style( 'hfo-golf-registration-lookup', plugins_url( 'assets/css/hfo-golf-registration-lookup.css', HFO_GOLF_REGISTRATION_FILE ), array(), HFO_GOLF_REGISTRATION_VERSION );

		ob_start();
		?>
		<div class="hfo-golf-reports-dashboard">
			<header class="hfo-golf-reports-dashboard__header">
				<h2><?php esc_html_e( 'Tournament Reports Dashboard', 'hfo-golf-registration' ); ?></h2>
				<?php $this->render_filter_form( $event_id ); ?>
			</header>

			<?php $this->render_cards( $report ); ?>

			<div class="hfo-golf-reports-dashboard__sections">
				<?php $this->render_section( __( 'Sponsor Breakdown', 'hfo-golf-registration' ), array( 'platinum' => __( 'Platinum Sponsors', 'hfo-golf-registration' ), 'gold' => __( 'Gold Sponsors', 'hfo-golf-registration' ), 'silver' => __( 'Silver Sponsors', 'hfo-golf-registration' ), 'tee' => __( 'Tee Sponsors', 'hfo-golf-registration' ), 'sponsor_revenue' => __( 'Sponsor Revenue', 'hfo-golf-registration' ) ), $report, array( 'sponsor_revenue' ) ); ?>
				<?php $this->render_section( __( 'Payment Status', 'hfo-golf-registration' ), array( 'processing' => __( 'Processing', 'hfo-golf-registration' ), 'completed' => __( 'Completed', 'hfo-golf-registration' ), 'pending' => __( 'Pending', 'hfo-golf-registration' ), 'failed' => __( 'Failed', 'hfo-golf-registration' ), 'cancelled' => __( 'Cancelled', 'hfo-golf-registration' ), 'refunded' => __( 'Refunded', 'hfo-golf-registration' ), 'on-hold' => __( 'On Hold', 'hfo-golf-registration' ) ), $report['statuses'] ); ?>
				<?php $this->render_section( __( 'Registration Type Breakdown', 'hfo-golf-registration' ), array( 'team' => __( 'Team', 'hfo-golf-registration' ), 'individual' => __( 'Individual', 'hfo-golf-registration' ), 'sponsor_only' => __( 'Sponsor Only', 'hfo-golf-registration' ), 'additional_guests' => __( 'Additional Guests', 'hfo-golf-registration' ) ), $report ); ?>
				<?php $this->render_section( __( 'Event Logistics', 'hfo-golf-registration' ), array( 'players' => __( 'Total Players', 'hfo-golf-registration' ), 'lunch' => __( 'Lunch Guests', 'hfo-golf-registration' ), 'dinner' => __( 'Dinner Guests', 'hfo-golf-registration' ) ), $report ); ?>
			</div>

			<?php $this->render_actions( $event_id ); ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Determines access by capability or an explicitly selected role. */
	private function current_user_can_view() {
		if ( current_user_can( self::CAPABILITY ) ) {
			return true;
		}
		$allowed = get_option( self::ALLOWED_ROLES_OPTION, array() );
		$user    = wp_get_current_user();
		return is_array( $allowed ) && (bool) array_intersect( array_map( 'sanitize_key', $allowed ), (array) $user->roles );
	}

	/** Gets a nonce-protected, published event selection. */
	private function get_selected_event_id() {
		if ( empty( $_GET[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return 0;
		}
		$event_id = isset( $_GET['hfo_reports_event'] ) ? absint( wp_unslash( $_GET['hfo_reports_event'] ) ) : 0;
		if ( ! $event_id ) {
			return 0;
		}
		return HFO_Golf_Event_Post_Type::POST_TYPE === get_post_type( $event_id ) && 'publish' === get_post_status( $event_id ) ? $event_id : 0;
	}

	/** Builds dashboard totals from lookup-normalized, positive-value rows. */
	private function build_report( $rows ) {
		$report = array(
			'total' => 0, 'paid' => 0.0, 'team' => 0, 'individual' => 0, 'sponsor_only' => 0, 'additional_guests' => 0,
			'players' => 0, 'lunch' => 0, 'dinner' => 0, 'sponsors' => 0, 'platinum' => 0, 'gold' => 0, 'silver' => 0, 'tee' => 0,
			'sponsor_revenue' => 0.0,
			'statuses' => array( 'processing' => 0, 'completed' => 0, 'pending' => 0, 'failed' => 0, 'cancelled' => 0, 'refunded' => 0, 'on-hold' => 0 ),
		);
		foreach ( $rows as $row ) {
			if ( (float) $row['total'] <= 0 ) {
				continue;
			}
			++$report['total'];
			$type = sanitize_key( $row['registration_type_key'] );
			if ( isset( $report[ $type ] ) ) {
				++$report[ $type ];
			}
			$status = sanitize_key( $row['payment_status_key'] );
			if ( isset( $report['statuses'][ $status ] ) ) {
				++$report['statuses'][ $status ];
			}
			$is_paid = in_array( $status, array( 'processing', 'completed' ), true );
			if ( $is_paid ) {
				$report['paid'] += (float) $row['total'];
			}
			$report['players'] += absint( $row['players'] );
			$report['lunch']   += absint( $row['lunch'] );
			$report['dinner']  += absint( $row['dinner'] );

			$level      = sanitize_key( $row['sponsor_level_key'] );
			$is_sponsor = 'none' !== $level || ! empty( $row['tee_sponsor'] );
			if ( $is_sponsor ) {
				++$report['sponsors'];
				// Mixed orders do not expose reliable line-level sponsor revenue here;
				// count the paid total only for rows identified as sponsor-related.
				if ( $is_paid ) {
					$report['sponsor_revenue'] += (float) $row['total'];
				}
			}
			if ( in_array( $level, array( 'platinum', 'gold', 'silver' ), true ) ) {
				++$report[ $level ];
			}
			if ( 'tee' === $level || ! empty( $row['tee_sponsor'] ) ) {
				++$report['tee'];
			}
		}
		return $report;
	}

	/** Renders the event filter. */
	private function render_filter_form( $event_id ) {
		$events = get_posts( array( 'post_type' => HFO_Golf_Event_Post_Type::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<form class="hfo-golf-reports-dashboard__filter" method="get">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<label for="hfo-reports-event"><?php esc_html_e( 'Event', 'hfo-golf-registration' ); ?></label>
			<select id="hfo-reports-event" name="hfo_reports_event">
				<option value="0"><?php esc_html_e( 'All Events', 'hfo-golf-registration' ); ?></option>
				<?php foreach ( $events as $event ) : ?>
					<option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $event_id, $event->ID ); ?>><?php echo esc_html( get_the_title( $event ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit"><?php esc_html_e( 'Apply', 'hfo-golf-registration' ); ?></button>
		</form>
		<?php
	}

	/** Renders the primary KPI cards. */
	private function render_cards( $report ) {
		$items = array( 'total' => __( 'Total Registrations', 'hfo-golf-registration' ), 'paid' => __( 'Total Paid', 'hfo-golf-registration' ), 'team' => __( 'Teams', 'hfo-golf-registration' ), 'individual' => __( 'Individual Registrations', 'hfo-golf-registration' ), 'sponsor_only' => __( 'Sponsor Only Registrations', 'hfo-golf-registration' ), 'additional_guests' => __( 'Additional Guests Registrations', 'hfo-golf-registration' ), 'players' => __( 'Players', 'hfo-golf-registration' ), 'lunch' => __( 'Lunch Guests', 'hfo-golf-registration' ), 'dinner' => __( 'Dinner Guests', 'hfo-golf-registration' ), 'sponsors' => __( 'Sponsors', 'hfo-golf-registration' ) );
		echo '<section class="hfo-golf-reports-dashboard__kpis" aria-label="' . esc_attr__( 'Tournament summary', 'hfo-golf-registration' ) . '">';
		foreach ( $items as $key => $label ) {
			$value = 'paid' === $key ? $this->format_price( $report[ $key ] ) : $report[ $key ];
			echo '<div class="hfo-golf-reports-dashboard__kpi"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
		}
		echo '</section>';
	}

	/** Renders a compact report breakdown. */
	private function render_section( $title, $items, $values, $money_keys = array() ) {
		echo '<section class="hfo-golf-reports-dashboard__section"><h3>' . esc_html( $title ) . '</h3><dl>';
		foreach ( $items as $key => $label ) {
			$value = isset( $values[ $key ] ) ? $values[ $key ] : 0;
			$value = in_array( $key, $money_keys, true ) ? $this->format_price( $value ) : $value;
			echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
		}
		echo '</dl></section>';
	}

	/** Renders optional lookup navigation and the capability-protected export. */
	private function render_actions( $event_id ) {
		$lookup_url = $this->find_lookup_page_url();
		if ( ! $lookup_url && ! current_user_can( HFO_Golf_Registration_Lookup_Shortcode::EXPORT_CAPABILITY ) ) {
			return;
		}
		echo '<div class="hfo-golf-reports-dashboard__actions">';
		if ( $lookup_url ) {
			$lookup_url = add_query_arg( array( 'hfo_lookup_event' => $event_id, HFO_Golf_Registration_Lookup_Shortcode::NONCE_NAME => wp_create_nonce( HFO_Golf_Registration_Lookup_Shortcode::NONCE_ACTION ) ), $lookup_url );
			echo '<a class="hfo-golf-reports-dashboard__button" href="' . esc_url( $lookup_url ) . '">' . esc_html__( 'Open Registration Lookup', 'hfo-golf-registration' ) . '</a>';
		}
		if ( current_user_can( HFO_Golf_Registration_Lookup_Shortcode::EXPORT_CAPABILITY ) ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( HFO_Golf_Registration_Lookup_Shortcode::EXPORT_ACTION ); ?>" />
				<input type="hidden" name="hfo_lookup_event" value="<?php echo esc_attr( $event_id ); ?>" />
				<?php wp_nonce_field( HFO_Golf_Registration_Lookup_Shortcode::EXPORT_NONCE_ACTION, HFO_Golf_Registration_Lookup_Shortcode::EXPORT_NONCE_NAME ); ?>
				<button class="hfo-golf-reports-dashboard__button" type="submit"><?php esc_html_e( 'Export Full CSV', 'hfo-golf-registration' ); ?></button>
			</form>
			<?php
		}
		echo '</div>';
	}

	/** Finds a published page containing the existing lookup shortcode. */
	private function find_lookup_page_url() {
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
		foreach ( $pages as $page_id ) {
			if ( has_shortcode( (string) get_post_field( 'post_content', $page_id ), 'hfo_golf_registration_lookup' ) ) {
				return get_permalink( $page_id );
			}
		}
		return '';
	}

	/** Formats currency as safe plain text. */
	private function format_price( $amount ) {
		return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amount ) ) : '$' . number_format_i18n( $amount, 2 );
	}
}
