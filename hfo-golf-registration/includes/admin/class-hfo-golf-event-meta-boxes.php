<?php
/**
 * Golf Event meta boxes.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and saves meta boxes for golf_event posts.
 */
class HFO_Golf_Event_Meta_Boxes {

	/**
	 * Meta box nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'hfo_golf_event_meta_boxes_save';

	/**
	 * Meta box nonce field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'hfo_golf_event_meta_boxes_nonce';

	/**
	 * Valid registration status values.
	 *
	 * @var array<string,string>
	 */
	private $registration_statuses = array(
		'draft'  => 'Draft',
		'open'   => 'Open',
		'closed' => 'Closed',
	);

	/**
	 * Registers WordPress hooks used by the event meta boxes.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_notices', array( $this, 'show_missing_title_notice' ) );
		add_action( 'save_post_' . HFO_Golf_Event_Post_Type::POST_TYPE, array( $this, 'save_meta_boxes' ) );
		add_filter( 'enter_title_here', array( $this, 'change_title_placeholder' ), 10, 2 );
		add_filter( 'manage_' . HFO_Golf_Event_Post_Type::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . HFO_Golf_Event_Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
	}

	/**
	 * Changes the title field placeholder for golf events.
	 *
	 * @param string  $placeholder Current title placeholder text.
	 * @param WP_Post $post        Current post object.
	 * @return string
	 */
	public function change_title_placeholder( $placeholder, $post ) {
		if ( ! $post instanceof WP_Post || HFO_Golf_Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return $placeholder;
		}

		return __( 'Enter event name, e.g. 2026 Golf Tournament for the Orphans', 'hfo-golf-registration' );
	}

	/**
	 * Adds event details columns to the golf events admin list table.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function add_admin_columns( $columns ) {
		$custom_columns = array(
			'event_year'          => __( 'Event Year', 'hfo-golf-registration' ),
			'event_date'          => __( 'Event Date', 'hfo-golf-registration' ),
			'registration_status' => __( 'Registration Status', 'hfo-golf-registration' ),
		);

		if ( isset( $columns['date'] ) ) {
			$date_column = array( 'date' => $columns['date'] );
			unset( $columns['date'] );

			return array_merge( $columns, $custom_columns, $date_column );
		}

		return array_merge( $columns, $custom_columns );
	}

	/**
	 * Renders custom event detail columns in the golf events admin list table.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_admin_column( $column, $post_id ) {
		if ( ! in_array( $column, array( 'event_year', 'event_date', 'registration_status' ), true ) ) {
			return;
		}

		$value = get_post_meta( $post_id, $column, true );

		if ( 'registration_status' === $column ) {
			$value = $this->get_registration_status_label( $value );
		}

		if ( '' === $value ) {
			echo '&mdash;';
			return;
		}

		echo esc_html( $value );
	}

	/**
	 * Shows an admin notice when a saved golf event does not have a title.
	 *
	 * @return void
	 */
	public function show_missing_title_notice() {
		$screen = get_current_screen();

		if ( ! $screen || HFO_Golf_Event_Post_Type::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		if ( 0 === $post_id ) {
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post || HFO_Golf_Event_Post_Type::POST_TYPE !== $post->post_type || '' !== trim( $post->post_title ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Please add an event title before using this event for registrations.', 'hfo-golf-registration' )
		);
	}

	/**
	 * Adds meta boxes to the golf_event edit screen.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'hfo_golf_event_details',
			esc_html__( 'Event Details', 'hfo-golf-registration' ),
			array( $this, 'render_event_details_meta_box' ),
			HFO_Golf_Event_Post_Type::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'hfo_golf_event_pricing',
			esc_html__( 'Pricing', 'hfo-golf-registration' ),
			array( $this, 'render_pricing_meta_box' ),
			HFO_Golf_Event_Post_Type::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'hfo_golf_event_discounts',
			esc_html__( 'Discounts', 'hfo-golf-registration' ),
			array( $this, 'render_discounts_meta_box' ),
			HFO_Golf_Event_Post_Type::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'hfo_golf_event_notifications',
			esc_html__( 'Notifications', 'hfo-golf-registration' ),
			array( $this, 'render_notifications_meta_box' ),
			HFO_Golf_Event_Post_Type::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Renders the Event Details meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_event_details_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$this->render_number_field(
			'event_year',
			esc_html__( 'Event Year', 'hfo-golf-registration' ),
			$post->ID,
			array(
				'min'  => '2000',
				'max'  => '2100',
				'step' => '1',
			)
		);
		$this->render_input_field( 'event_date', esc_html__( 'Event Date', 'hfo-golf-registration' ), $post->ID, 'date' );
		$this->render_input_field( 'event_location', esc_html__( 'Event Location', 'hfo-golf-registration' ), $post->ID, 'text' );
		$this->render_registration_status_field( $post->ID );
	}

	/**
	 * Renders the Pricing meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_pricing_meta_box( $post ) {
		$fields = array(
			'golf_price'             => esc_html__( 'Golf Price', 'hfo-golf-registration' ),
			'lunch_price'            => esc_html__( 'Lunch Price', 'hfo-golf-registration' ),
			'dinner_price'           => esc_html__( 'Dinner Price', 'hfo-golf-registration' ),
			'platinum_sponsor_price' => esc_html__( 'Platinum Sponsor Price', 'hfo-golf-registration' ),
			'gold_sponsor_price'     => esc_html__( 'Gold Sponsor Price', 'hfo-golf-registration' ),
			'silver_sponsor_price'   => esc_html__( 'Silver Sponsor Price', 'hfo-golf-registration' ),
			'tee_sponsor_price'      => esc_html__( 'Tee Sponsor Price', 'hfo-golf-registration' ),
		);

		foreach ( $fields as $key => $label ) {
			$this->render_number_field(
				$key,
				$label,
				$post->ID,
				array(
					'min'  => '0',
					'step' => '0.01',
				)
			);
		}
	}

	/**
	 * Renders the Discounts meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_discounts_meta_box( $post ) {
		$this->render_input_field( 'discount_code_15', esc_html__( '15% Discount Code', 'hfo-golf-registration' ), $post->ID, 'text' );
		$this->render_input_field( 'discount_code_30', esc_html__( '30% Discount Code', 'hfo-golf-registration' ), $post->ID, 'text' );
	}

	/**
	 * Renders the Notifications meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_notifications_meta_box( $post ) {
		$this->render_textarea_field(
			'notification_emails',
			esc_html__( 'Notification Emails', 'hfo-golf-registration' ),
			$post->ID,
			esc_html__( 'Separate multiple email addresses with commas.', 'hfo-golf-registration' )
		);
		$this->render_textarea_field( 'thank_you_message', esc_html__( 'Thank You Message', 'hfo-golf-registration' ), $post->ID );
	}

	/**
	 * Saves golf_event meta box values.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return void
	 */
	public function save_meta_boxes( $post_id ) {
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}

		$this->save_meta_value( $post_id, 'event_year', 'year' );
		$this->save_meta_value( $post_id, 'event_date', 'text' );
		$this->save_meta_value( $post_id, 'event_location', 'text' );
		$this->save_meta_value( $post_id, 'registration_status', 'registration_status' );

		foreach ( $this->get_price_fields() as $field ) {
			$this->save_meta_value( $post_id, $field, 'price' );
		}

		$this->save_meta_value( $post_id, 'discount_code_15', 'text' );
		$this->save_meta_value( $post_id, 'discount_code_30', 'text' );
		$this->save_meta_value( $post_id, 'notification_emails', 'emails' );
		$this->save_meta_value( $post_id, 'thank_you_message', 'textarea' );
	}

	/**
	 * Checks whether the current request can save meta box values.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return bool
	 */
	private function can_save( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Saves one meta value after sanitizing it by type.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $type    Sanitization type.
	 * @return void
	 */
	private function save_meta_value( $post_id, $key, $type ) {
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		$value = $this->sanitize_meta_value( $value, $type );

		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Sanitizes a meta value by type.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Sanitization type.
	 * @return string
	 */
	private function sanitize_meta_value( $value, $type ) {
		switch ( $type ) {
			case 'year':
				$year = absint( $value );
				return ( $year >= 2000 && $year <= 2100 ) ? (string) $year : '';

			case 'registration_status':
				$status = sanitize_key( $value );
				return array_key_exists( $status, $this->registration_statuses ) ? $status : 'draft';

			case 'price':
				$price = is_scalar( $value ) ? preg_replace( '/[^0-9.]/', '', (string) $value ) : '';
				return '' === $price ? '' : number_format( (float) $price, 2, '.', '' );

			case 'emails':
				return $this->sanitize_email_list( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Sanitizes a comma-separated list of email addresses.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_email_list( $value ) {
		$emails = is_scalar( $value ) ? explode( ',', (string) $value ) : array();
		$valid  = array();

		foreach ( $emails as $email ) {
			$email = sanitize_email( trim( $email ) );

			if ( is_email( $email ) ) {
				$valid[] = $email;
			}
		}

		return implode( ', ', $valid );
	}

	/**
	 * Renders a text-like input field.
	 *
	 * @param string $key     Meta key.
	 * @param string $label   Field label.
	 * @param int    $post_id Post ID.
	 * @param string $type    Input type.
	 * @return void
	 */
	private function render_input_field( $key, $label, $post_id, $type ) {
		$value = get_post_meta( $post_id, $key, true );
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="widefat" />
		</p>
		<?php
	}

	/**
	 * Renders a number input field.
	 *
	 * @param string $key        Meta key.
	 * @param string $label      Field label.
	 * @param int    $post_id    Post ID.
	 * @param array  $attributes Optional number input attributes.
	 * @return void
	 */
	private function render_number_field( $key, $label, $post_id, $attributes = array() ) {
		$value = get_post_meta( $post_id, $key, true );
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<input
				type="number"
				id="<?php echo esc_attr( $key ); ?>"
				name="<?php echo esc_attr( $key ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="widefat"
				<?php foreach ( $attributes as $attribute => $attribute_value ) : ?>
					<?php echo esc_attr( $attribute ); ?>="<?php echo esc_attr( $attribute_value ); ?>"
				<?php endforeach; ?>
			/>
		</p>
		<?php
	}

	/**
	 * Renders the registration status select field.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function render_registration_status_field( $post_id ) {
		$current = get_post_meta( $post_id, 'registration_status', true );
		$current = array_key_exists( $current, $this->registration_statuses ) ? $current : 'draft';
		?>
		<p>
			<label for="registration_status"><strong><?php echo esc_html__( 'Registration Status', 'hfo-golf-registration' ); ?></strong></label><br />
			<select id="registration_status" name="registration_status" class="widefat">
				<?php foreach ( $this->registration_statuses as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Gets the display label for a registration status value.
	 *
	 * @param string $status Registration status meta value.
	 * @return string
	 */
	private function get_registration_status_label( $status ) {
		$status = sanitize_key( $status );

		return array_key_exists( $status, $this->registration_statuses ) ? $this->registration_statuses[ $status ] : $status;
	}

	/**
	 * Renders a textarea field.
	 *
	 * @param string $key         Meta key.
	 * @param string $label       Field label.
	 * @param int    $post_id     Post ID.
	 * @param string $description Optional field description.
	 * @return void
	 */
	private function render_textarea_field( $key, $label, $post_id, $description = '' ) {
		$value = get_post_meta( $post_id, $key, true );
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat" rows="4"><?php echo esc_textarea( $value ); ?></textarea>
			<?php if ( '' !== $description ) : ?>
				<br /><span class="description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Gets pricing meta fields.
	 *
	 * @return array<int,string>
	 */
	private function get_price_fields() {
		return array(
			'golf_price',
			'lunch_price',
			'dinner_price',
			'platinum_sponsor_price',
			'gold_sponsor_price',
			'silver_sponsor_price',
			'tee_sponsor_price',
		);
	}
}
