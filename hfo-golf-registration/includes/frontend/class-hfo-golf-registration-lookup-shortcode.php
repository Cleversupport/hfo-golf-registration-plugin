<?php
/**
 * Secure frontend registration lookup shortcode.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers and renders [hfo_golf_registration_lookup]. */
class HFO_Golf_Registration_Lookup_Shortcode {

	const CAPABILITY = 'view_hfo_golf_registrations';
	const ALLOWED_ROLES_OPTION = 'hfo_golf_registration_lookup_allowed_roles';
	const NONCE_ACTION = 'hfo_golf_registration_lookup';
	const NONCE_NAME = 'hfo_registration_lookup_nonce';
	const PER_PAGE = 25;

	/** Registers shortcode hooks. */
	public function register_hooks() {
		add_shortcode( 'hfo_golf_registration_lookup', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Renders the protected lookup.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p class="hfo-golf-registration-lookup-message hfo-golf-registration-lookup-message--error">' . esc_html__( 'Please log in to view golf registrations.', 'hfo-golf-registration' ) . '</p>';
		}
		if ( ! $this->current_user_can_view() ) {
			return '<p class="hfo-golf-registration-lookup-message hfo-golf-registration-lookup-message--error">' . esc_html__( 'You do not have permission to view golf registrations.', 'hfo-golf-registration' ) . '</p>';
		}

		$filters = $this->get_filters();
		$rows    = $this->get_matching_rows( $filters );
		$page    = max( 1, $filters['page'] );
		$pages   = max( 1, (int) ceil( count( $rows ) / self::PER_PAGE ) );
		$page    = min( $page, $pages );
		$rows    = array_slice( $rows, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );

		wp_enqueue_style(
			'hfo-golf-registration-lookup',
			plugins_url( 'assets/css/hfo-golf-registration-lookup.css', HFO_GOLF_REGISTRATION_FILE ),
			array(),
			HFO_GOLF_REGISTRATION_VERSION
		);

		ob_start();
		?>
		<div class="hfo-golf-registration-lookup">
			<h2><?php esc_html_e( 'Registration Lookup', 'hfo-golf-registration' ); ?></h2>
			<?php $this->render_search_form( $filters ); ?>
			<?php if ( empty( $rows ) ) : ?>
				<p class="hfo-golf-registration-lookup-message"><?php esc_html_e( 'No registrations found for the selected filters.', 'hfo-golf-registration' ); ?></p>
			<?php else : ?>
				<?php $this->render_results_table( $rows ); ?>
				<?php $this->render_pagination( $page, $pages ); ?>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** Determines access by explicit capability or a currently selected role. */
	private function current_user_can_view() {
		if ( current_user_can( self::CAPABILITY ) ) {
			return true;
		}
		$allowed = get_option( self::ALLOWED_ROLES_OPTION, array() );
		$user    = wp_get_current_user();
		return is_array( $allowed ) && (bool) array_intersect( array_map( 'sanitize_key', $allowed ), (array) $user->roles );
	}

	/** Gets sanitized filters, accepting them only with a valid nonce. */
	private function get_filters() {
		$filters = array(
			'keyword'          => '',
			'event'            => 0,
			'registration_type' => '',
			'payment_status'   => '',
			'sponsor_level'    => '',
			'page'             => 1,
		);
		if ( empty( $_GET[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return $filters;
		}

		$filters['keyword']          = isset( $_GET['hfo_lookup_keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['hfo_lookup_keyword'] ) ) : '';
		$filters['event']            = isset( $_GET['hfo_lookup_event'] ) ? absint( wp_unslash( $_GET['hfo_lookup_event'] ) ) : 0;
		$filters['registration_type'] = isset( $_GET['hfo_lookup_type'] ) ? sanitize_key( wp_unslash( $_GET['hfo_lookup_type'] ) ) : '';
		$filters['payment_status']   = isset( $_GET['hfo_lookup_payment'] ) ? sanitize_key( wp_unslash( $_GET['hfo_lookup_payment'] ) ) : '';
		$filters['sponsor_level']    = isset( $_GET['hfo_lookup_sponsor'] ) ? sanitize_key( wp_unslash( $_GET['hfo_lookup_sponsor'] ) ) : '';
		$filters['page']             = isset( $_GET['hfo_lookup_page'] ) ? max( 1, absint( wp_unslash( $_GET['hfo_lookup_page'] ) ) ) : 1;

		$filters['registration_type'] = in_array( $filters['registration_type'], array( 'individual', 'team', 'sponsor_only', 'additional_guests' ), true ) ? $filters['registration_type'] : '';
		$filters['payment_status'] = in_array( $filters['payment_status'], array( 'pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded', 'on-hold' ), true ) ? $filters['payment_status'] : '';
		$filters['sponsor_level'] = in_array( $filters['sponsor_level'], array( 'platinum', 'gold', 'silver', 'tee', 'none' ), true ) ? $filters['sponsor_level'] : '';
		return $filters;
	}

	/** Queries registrations and builds only matching display rows. */
	private function get_matching_rows( $filters ) {
		$meta_query = array( 'relation' => 'AND' );
		if ( $filters['event'] ) {
			$meta_query[] = array( 'key' => 'related_event', 'value' => $filters['event'], 'compare' => '=' );
		}
		if ( $filters['registration_type'] ) {
			$meta_query[] = array( 'key' => 'registration_type', 'value' => $filters['registration_type'], 'compare' => '=' );
		}
		if ( $filters['keyword'] && ! ctype_digit( $filters['keyword'] ) ) {
			$keyword_query = array( 'relation' => 'OR' );
			foreach ( array( 'main_contact_name', 'main_contact_email', 'main_contact_phone', 'hfo_golf_team_name', 'woocommerce_order_id' ) as $key ) {
				$keyword_query[] = array( 'key' => $key, 'value' => $filters['keyword'], 'compare' => 'LIKE' );
			}
			$meta_query[] = $keyword_query;
		}

		$query = new WP_Query(
			array(
				'post_type'              => HFO_Golf_Registration_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => count( $meta_query ) > 1 ? $meta_query : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		$rows = array();
		foreach ( $query->posts as $registration_id ) {
			$row = $this->build_registration_lookup_row( $registration_id );
			if ( ! $this->row_matches_filters( $row, $filters ) ) {
				continue;
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/** Handles filters that depend on WooCommerce or combined stored values. */
	private function row_matches_filters( $row, $filters ) {
		if ( $filters['keyword'] && ctype_digit( $filters['keyword'] ) ) {
			$searchable = array( $row['id'], $row['order_number'], $row['contact'], $row['email'], $row['phone'], $row['team'] );
			$matched    = false;
			foreach ( $searchable as $value ) {
				if ( false !== stripos( (string) $value, $filters['keyword'] ) ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				return false;
			}
		}
		if ( $filters['payment_status'] && $filters['payment_status'] !== $row['payment_status_key'] ) {
			return false;
		}
		if ( $filters['sponsor_level'] && $filters['sponsor_level'] !== $row['sponsor_level_key'] && ! ( 'tee' === $filters['sponsor_level'] && $row['tee_sponsor'] ) ) {
			return false;
		}
		return true;
	}

	/** Builds a safely displayable row from established registration meta. */
	public function build_registration_lookup_row( $registration_id ) {
		$event_id   = absint( get_post_meta( $registration_id, 'related_event', true ) );
		$order_id   = absint( get_post_meta( $registration_id, 'woocommerce_order_id', true ) );
		$order      = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		$type       = sanitize_key( (string) get_post_meta( $registration_id, 'registration_type', true ) );
		$sponsor    = sanitize_key( (string) get_post_meta( $registration_id, 'sponsorship_level', true ) );
		$tee        = '1' === (string) get_post_meta( $registration_id, 'tee_sponsor_selected', true );
		$status     = $order ? sanitize_key( $order->get_status() ) : sanitize_key( (string) get_post_meta( $registration_id, 'payment_status', true ) );
		$status     = 'paid' === $status ? 'completed' : ( 'unpaid' === $status ? 'pending' : $status );
		$sponsor_key = $sponsor ? $sponsor : ( $tee ? 'tee' : 'none' );
		$event_date  = $event_id ? sanitize_text_field( get_post_meta( $event_id, 'event_date', true ) ) : '';
		if ( $event_date ) {
			$timestamp  = strtotime( $event_date );
			$event_date = $timestamp ? date_i18n( get_option( 'date_format' ), $timestamp ) : $event_date;
		}

		return array(
			'id'                 => absint( $registration_id ),
			'event'              => $event_id ? get_the_title( $event_id ) : '',
			'event_date'         => $event_date,
			'contact'            => sanitize_text_field( get_post_meta( $registration_id, 'main_contact_name', true ) ),
			'email'              => sanitize_email( get_post_meta( $registration_id, 'main_contact_email', true ) ),
			'phone'              => sanitize_text_field( get_post_meta( $registration_id, 'main_contact_phone', true ) ),
			'team'               => sanitize_text_field( get_post_meta( $registration_id, 'hfo_golf_team_name', true ) ),
			'type'               => $this->label( $type, array( 'individual' => 'Individual', 'team' => 'Team', 'sponsor_only' => 'Sponsor Only', 'additional_guests' => 'Additional Guests' ) ),
			'sponsor'            => $this->label( $sponsor_key, array( 'platinum' => 'Platinum Sponsor', 'gold' => 'Gold Sponsor', 'silver' => 'Silver Sponsor', 'tee' => 'Tee Sponsor', 'none' => 'None' ) ),
			'sponsor_level_key'  => $sponsor_key,
			'tee_sponsor'        => $tee,
			'players'            => absint( get_post_meta( $registration_id, 'golf_qty', true ) ),
			'lunch'              => absint( get_post_meta( $registration_id, 'additional_lunch_count', true ) ),
			'dinner'             => absint( get_post_meta( $registration_id, 'additional_dinner_count', true ) ),
			'order_id'           => $order_id,
			'order_number'       => $order ? $order->get_order_number() : ( $order_id ? $order_id : '' ),
			'payment_status_key' => $status,
			'payment_status'     => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : ucwords( str_replace( '-', ' ', $status ) ),
			'total'              => $order ? (float) $order->get_total() : 0.0,
			'date'               => get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $registration_id ),
		);
	}

	/** Gets display rows for registrations belonging to a WooCommerce customer. */
	public function get_customer_registration_rows( $user_id ) {
		$user_id   = absint( $user_id );
		$order_ids = array();
		if ( $user_id && function_exists( 'wc_get_orders' ) ) {
			$order_ids = array_map( 'absint', (array) wc_get_orders( array( 'customer_id' => $user_id, 'limit' => -1, 'return' => 'ids' ) ) );
		}
		$relationships = array(
			'relation' => 'OR',
			array( 'key' => 'hfo_golf_customer_user_id', 'value' => $user_id, 'compare' => '=' ),
		);
		if ( $order_ids ) {
			$relationships[] = array( 'key' => 'woocommerce_order_id', 'value' => $order_ids, 'compare' => 'IN', 'type' => 'NUMERIC' );
		}
		$query = new WP_Query(
			array(
				'post_type' => HFO_Golf_Registration_Post_Type::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1,
				'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'no_found_rows' => true,
				'update_post_term_cache' => false,
				'meta_query' => $relationships, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$rows = array();
		foreach ( $query->posts as $registration_id ) {
			$linked_user_id = absint( get_post_meta( $registration_id, 'hfo_golf_customer_user_id', true ) );
			$row            = $this->build_registration_lookup_row( $registration_id );
			$order_owner    = 0;
			if ( $row['order_id'] && function_exists( 'wc_get_order' ) ) {
				$order       = wc_get_order( $row['order_id'] );
				$order_owner = $order && is_callable( array( $order, 'get_customer_id' ) ) ? absint( $order->get_customer_id() ) : 0;
			}
			if ( $user_id === $linked_user_id || $user_id === $order_owner ) {
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/** Returns a translated label where known. */
	private function label( $key, $labels ) {
		return isset( $labels[ $key ] ) ? __( $labels[ $key ], 'hfo-golf-registration' ) : ucwords( str_replace( '_', ' ', $key ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
	}

	/** Renders the GET search form. */
	private function render_search_form( $filters ) {
		$events = get_posts( array( 'post_type' => HFO_Golf_Event_Post_Type::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<form class="hfo-golf-registration-lookup-form" method="get">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<label><?php esc_html_e( 'Keyword', 'hfo-golf-registration' ); ?><input type="search" name="hfo_lookup_keyword" value="<?php echo esc_attr( $filters['keyword'] ); ?>" /></label>
			<label><?php esc_html_e( 'Event', 'hfo-golf-registration' ); ?><select name="hfo_lookup_event"><option value="0"><?php esc_html_e( 'All Events', 'hfo-golf-registration' ); ?></option><?php foreach ( $events as $event ) : ?><option value="<?php echo esc_attr( $event->ID ); ?>" <?php selected( $filters['event'], $event->ID ); ?>><?php echo esc_html( get_the_title( $event ) ); ?></option><?php endforeach; ?></select></label>
			<?php $this->render_select( 'hfo_lookup_type', __( 'Registration Type', 'hfo-golf-registration' ), $filters['registration_type'], array( '' => __( 'All Types', 'hfo-golf-registration' ), 'individual' => __( 'Individual', 'hfo-golf-registration' ), 'team' => __( 'Team', 'hfo-golf-registration' ), 'sponsor_only' => __( 'Sponsor Only', 'hfo-golf-registration' ), 'additional_guests' => __( 'Additional Guests', 'hfo-golf-registration' ) ) ); ?>
			<?php $this->render_select( 'hfo_lookup_payment', __( 'Payment Status', 'hfo-golf-registration' ), $filters['payment_status'], array( '' => __( 'All Payment Statuses', 'hfo-golf-registration' ), 'pending' => __( 'Pending', 'hfo-golf-registration' ), 'processing' => __( 'Processing', 'hfo-golf-registration' ), 'completed' => __( 'Completed', 'hfo-golf-registration' ), 'failed' => __( 'Failed', 'hfo-golf-registration' ), 'cancelled' => __( 'Cancelled', 'hfo-golf-registration' ), 'refunded' => __( 'Refunded', 'hfo-golf-registration' ), 'on-hold' => __( 'On Hold', 'hfo-golf-registration' ) ) ); ?>
			<?php $this->render_select( 'hfo_lookup_sponsor', __( 'Sponsor Level', 'hfo-golf-registration' ), $filters['sponsor_level'], array( '' => __( 'All Sponsor Levels', 'hfo-golf-registration' ), 'platinum' => __( 'Platinum Sponsor', 'hfo-golf-registration' ), 'gold' => __( 'Gold Sponsor', 'hfo-golf-registration' ), 'silver' => __( 'Silver Sponsor', 'hfo-golf-registration' ), 'tee' => __( 'Tee Sponsor', 'hfo-golf-registration' ), 'none' => __( 'No Sponsor', 'hfo-golf-registration' ) ) ); ?>
			<button type="submit"><?php esc_html_e( 'Search Registrations', 'hfo-golf-registration' ); ?></button>
		</form>
		<?php
	}

	/** Renders one labelled select. */
	private function render_select( $name, $label, $current, $options ) {
		echo '<label>' . esc_html( $label ) . '<select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select></label>';
	}

	/** Renders the results table. */
	private function render_results_table( $rows ) {
		$headers = array( 'Registration ID', 'Event', 'Main Contact', 'Email', 'Phone', 'Team Name', 'Registration Type', 'Sponsor Level', 'Players', 'Lunch Guests', 'Dinner Guests', 'WooCommerce Order', 'Payment Status', 'Total Paid', 'Date Submitted' );
		?>
		<div class="hfo-golf-registration-lookup-table-wrap"><table><thead><tr><?php foreach ( $headers as $header ) : ?><th scope="col"><?php echo esc_html( $header ); ?></th><?php endforeach; ?></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : ?>
			<tr><td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['event'] ); ?></td><td><?php echo esc_html( $row['contact'] ); ?></td><td><?php echo esc_html( $row['email'] ); ?></td><td><?php echo esc_html( $row['phone'] ); ?></td><td><?php echo esc_html( $row['team'] ); ?></td><td><?php echo esc_html( $row['type'] ); ?></td><td><?php echo esc_html( $row['sponsor'] ); ?></td><td><?php echo esc_html( $row['players'] ); ?></td><td><?php echo esc_html( $row['lunch'] ); ?></td><td><?php echo esc_html( $row['dinner'] ); ?></td><td><?php $this->render_order( $row ); ?></td><td><?php echo esc_html( $row['payment_status'] ); ?></td><td><?php echo esc_html( $this->format_price( $row['total'] ) ); ?></td><td><?php echo esc_html( $row['date'] ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php
	}

	/** Renders an order number as plain text. */
	private function render_order( $row ) {
		if ( ! $row['order_number'] ) {
			echo '&mdash;';
			return;
		}
		echo '#' . esc_html( $row['order_number'] );
	}

	/** Formats currency without returning unescaped markup. */
	private function format_price( $amount ) {
		return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amount ) ) : '$' . number_format_i18n( $amount, 2 );
	}

	/** Renders pagination while retaining sanitized filters. */
	private function render_pagination( $page, $pages ) {
		if ( $pages < 2 ) {
			return;
		}
		$base = remove_query_arg( 'hfo_lookup_page' );
		echo '<nav class="hfo-golf-registration-lookup-pagination" aria-label="' . esc_attr__( 'Registration results pages', 'hfo-golf-registration' ) . '">';
		for ( $number = 1; $number <= $pages; $number++ ) {
			if ( $number === $page ) {
				echo '<span aria-current="page">' . esc_html( $number ) . '</span>';
			} else {
				echo '<a href="' . esc_url( add_query_arg( 'hfo_lookup_page', $number, $base ) ) . '">' . esc_html( $number ) . '</a>';
			}
		}
		echo '</nav>';
	}
}
