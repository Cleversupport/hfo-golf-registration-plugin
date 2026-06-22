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
			'event_date'          => __( 'Event Date', 'hfo-golf-registration' ),
			'event_time'          => __( 'Event Time', 'hfo-golf-registration' ),
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
		if ( ! in_array( $column, array( 'event_date', 'event_time', 'registration_status' ), true ) ) {
			return;
		}

		if ( 'event_time' === $column ) {
			$start_time = get_post_meta( $post_id, 'event_start_time', true );
			$end_time   = get_post_meta( $post_id, 'event_end_time', true );

			if ( '' !== $start_time && '' !== $end_time ) {
				echo esc_html( $start_time . ' - ' . $end_time );
				return;
			}

			if ( '' !== $start_time || '' !== $end_time ) {
				echo esc_html( '' !== $start_time ? $start_time : $end_time );
				return;
			}

			echo '&mdash;';
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
			'hfo_golf_event_email_configuration',
			esc_html__( 'Email Configuration', 'hfo-golf-registration' ),
			array( $this, 'render_email_configuration_meta_box' ),
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
		$this->render_input_field( 'event_start_time', esc_html__( 'Event Start Time', 'hfo-golf-registration' ), $post->ID, 'time' );
		$this->render_input_field( 'event_end_time', esc_html__( 'Event End Time', 'hfo-golf-registration' ), $post->ID, 'time' );
		$this->render_input_field( 'event_location', esc_html__( 'Event Location (Legacy)', 'hfo-golf-registration' ), $post->ID, 'text' );
		$this->render_input_field( 'event_venue', esc_html__( 'Venue', 'hfo-golf-registration' ), $post->ID, 'text' );
		$this->render_input_field( 'event_address', esc_html__( 'Address', 'hfo-golf-registration' ), $post->ID, 'text' );
		$this->render_input_field( 'event_city', esc_html__( 'City', 'hfo-golf-registration' ), $post->ID, 'text' );
		$this->render_input_field( 'event_state', esc_html__( 'State', 'hfo-golf-registration' ), $post->ID, 'text' );
		$this->render_input_field( 'event_zip', esc_html__( 'ZIP', 'hfo-golf-registration' ), $post->ID, 'text' );
		$this->render_textarea_field(
			'event_caption',
			esc_html__( 'Event Caption', 'hfo-golf-registration' ),
			$post->ID,
			esc_html__( 'Short public-facing summary used on event templates/cards.', 'hfo-golf-registration' ),
			2
		);
		$this->render_registration_status_field( $post->ID );
		$this->render_input_field( 'sponsor_packet_pdf_url', esc_html__( 'Sponsor Packet PDF URL', 'hfo-golf-registration' ), $post->ID, 'url' );
		$this->render_input_field( 'event_flyer_image_url', esc_html__( 'Event Flyer Image URL', 'hfo-golf-registration' ), $post->ID, 'url' );
		$this->render_wysiwyg_field(
			'why_this_tournament_matters',
			esc_html__( 'Why This Tournament Matters', 'hfo-golf-registration' ),
			$post->ID
		);
		$this->render_wysiwyg_field(
			'whats_included',
			esc_html__( 'What’s Included', 'hfo-golf-registration' ),
			$post->ID
		);
		$this->render_wysiwyg_field(
			'event_schedule',
			esc_html__( 'Event Schedule', 'hfo-golf-registration' ),
			$post->ID
		);
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
	 * Renders the Email Configuration meta box.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_email_configuration_meta_box( $post ) {
		$this->render_checkbox_field(
			'hfo_event_email_enabled',
			esc_html__( 'Enable event email for completed orders', 'hfo-golf-registration' ),
			$post->ID
		);
		$this->render_input_field( 'hfo_event_email_subject', esc_html__( 'Email Subject', 'hfo-golf-registration' ), $post->ID, 'text' );
		$this->render_wysiwyg_field( 'hfo_event_email_body', esc_html__( 'Email Body', 'hfo-golf-registration' ), $post->ID );
		printf(
			'<p class="description">%s <code>{first_name}</code> <code>{last_name}</code> <code>{email}</code> <code>{event_name}</code> <code>{event_location}</code> <code>{event_date}</code> <code>{order_id}</code></p>',
			esc_html__( 'Available placeholders:', 'hfo-golf-registration' )
		);
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
		$this->save_meta_value( $post_id, 'event_start_time', 'time' );
		$this->save_meta_value( $post_id, 'event_end_time', 'time' );
		$this->save_meta_value( $post_id, 'event_location', 'text' );
		$this->save_meta_value( $post_id, 'event_venue', 'text' );
		$this->save_meta_value( $post_id, 'event_address', 'text' );
		$this->save_meta_value( $post_id, 'event_city', 'text' );
		$this->save_meta_value( $post_id, 'event_state', 'text' );
		$this->save_meta_value( $post_id, 'event_zip', 'text' );
		$this->save_meta_value( $post_id, 'event_caption', 'textarea' );
		$this->save_meta_value( $post_id, 'registration_status', 'registration_status' );
		$this->save_meta_value( $post_id, 'sponsor_packet_pdf_url', 'url' );
		$this->save_meta_value( $post_id, 'event_flyer_image_url', 'url' );
		$this->save_meta_value( $post_id, 'why_this_tournament_matters', 'html' );
		$this->save_meta_value( $post_id, 'whats_included', 'html' );
		$this->save_meta_value( $post_id, 'event_schedule', 'html' );
		$this->save_meta_value( $post_id, 'hfo_event_email_enabled', 'checkbox' );
		$this->save_meta_value( $post_id, 'hfo_event_email_subject', 'text' );
		$this->save_meta_value( $post_id, 'hfo_event_email_body', 'html' );

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

			case 'url':
				return esc_url_raw( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'html':
				return is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';

			case 'checkbox':
				return ! empty( $value ) ? '1' : '0';

			case 'time':
				return $this->sanitize_time_value( $value );

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
	 * Sanitizes a time value in HH:MM format.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_time_value( $value ) {
		$time = sanitize_text_field( $value );

		if ( '' === $time ) {
			return '';
		}

		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '';
	}


	/**
	 * Renders a checkbox field.
	 *
	 * @param string $key     Meta key.
	 * @param string $label   Field label.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function render_checkbox_field( $key, $label, $post_id ) {
		$value = get_post_meta( $post_id, $key, true );
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( '1', (string) $value ); ?> />
				<strong><?php echo esc_html( $label ); ?></strong>
			</label>
		</p>
		<?php
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
	 * @param int    $rows        Number of textarea rows.
	 * @return void
	 */
	private function render_textarea_field( $key, $label, $post_id, $description = '', $rows = 4 ) {
		$value = get_post_meta( $post_id, $key, true );
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
			<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat" rows="<?php echo esc_attr( $rows ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<?php if ( '' !== $description ) : ?>
				<br /><span class="description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}


	/**
	 * Renders a compact WordPress editor field for formatted event content.
	 *
	 * @param string $key     Meta key.
	 * @param string $label   Field label.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	private function render_wysiwyg_field( $key, $label, $post_id ) {
		$value = get_post_meta( $post_id, $key, true );
		?>
		<div class="hfo-golf-event-editor-field">
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label>
			<?php
			wp_editor(
				$value,
				$key,
				array(
					'textarea_name' => $key,
					'media_buttons' => false,
					'textarea_rows' => 8,
					'teeny'         => true,
					'quicktags'     => true,
				)
			);
			?>
		</div>
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
