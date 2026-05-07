<?php
/**
 * Golf Registration admin settings.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders plugin settings pages.
 */
class HFO_Golf_Registration_Settings {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'hfo-golf-registration-settings';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'hfo_golf_registration_settings';

	/**
	 * WooCommerce mapping section ID.
	 *
	 * @var string
	 */
	const PRODUCT_MAPPING_SECTION = 'hfo_golf_registration_product_mapping';

	/**
	 * Registers WordPress hooks used by the settings page.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'render_woocommerce_required_notice' ) );
	}

	/**
	 * Adds the settings page under the WordPress Settings menu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			esc_html__( 'Golf Registration Settings', 'hfo-golf-registration' ),
			esc_html__( 'Golf Registration Settings', 'hfo-golf-registration' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers settings, sections, and fields with the WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		foreach ( $this->get_product_mapping_fields() as $field_key => $field ) {
			register_setting(
				self::SETTINGS_GROUP,
				$field['option_name'],
				array(
					'type'              => 'integer',
					'sanitize_callback' => array( $this, 'sanitize_product_id' ),
					'default'           => 0,
				)
			);
		}

		add_settings_section(
			self::PRODUCT_MAPPING_SECTION,
			esc_html__( 'WooCommerce Product Mapping', 'hfo-golf-registration' ),
			array( $this, 'render_product_mapping_section' ),
			self::PAGE_SLUG
		);

		foreach ( $this->get_product_mapping_fields() as $field_key => $field ) {
			add_settings_field(
				$field_key,
				$field['label'],
				array( $this, 'render_product_select_field' ),
				self::PAGE_SLUG,
				self::PRODUCT_MAPPING_SECTION,
				array(
					'field_key'   => $field_key,
					'label'       => $field['label'],
					'option_name' => $field['option_name'],
				)
			);
		}
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Golf Registration Settings', 'hfo-golf-registration' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( esc_html__( 'Save Settings', 'hfo-golf-registration' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the product mapping section description.
	 *
	 * @return void
	 */
	public function render_product_mapping_section() {
		if ( ! $this->is_woocommerce_active() ) {
			printf(
				'<p>%s</p>',
				esc_html__( 'WooCommerce must be active before products can be selected for these mappings.', 'hfo-golf-registration' )
			);
			return;
		}

		printf(
			'<p>%s</p>',
			esc_html__( 'Select the published WooCommerce products that correspond to each registration item.', 'hfo-golf-registration' )
		);
	}

	/**
	 * Renders a WooCommerce product select field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_product_select_field( $args ) {
		$option_name = isset( $args['option_name'] ) ? sanitize_key( $args['option_name'] ) : '';
		$label       = isset( $args['label'] ) ? $args['label'] : '';
		$value       = absint( get_option( $option_name, 0 ) );
		$products    = $this->get_published_products();
		$disabled    = $this->is_woocommerce_active() ? '' : ' disabled="disabled"';

		printf(
			'<select id="%1$s" name="%1$s"%2$s>',
			esc_attr( $option_name ),
			$disabled // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static disabled attribute controlled by boolean state.
		);

		printf(
			'<option value="0">%s</option>',
			esc_html__( 'Select a product', 'hfo-golf-registration' )
		);

		foreach ( $products as $product ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $product->ID ),
				selected( $value, $product->ID, false ),
				esc_html( $product->post_title )
			);
		}

		echo '</select>';

		if ( ! empty( $label ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( sprintf( __( 'Maps the %s value to a WooCommerce product.', 'hfo-golf-registration' ), $label ) )
			);
		}
	}

	/**
	 * Displays an admin notice when WooCommerce is unavailable.
	 *
	 * @return void
	 */
	public function render_woocommerce_required_notice() {
		if ( ! current_user_can( 'manage_options' ) || $this->is_woocommerce_active() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'WooCommerce is required to configure the HFO Golf Registration product mappings.', 'hfo-golf-registration' )
		);
	}

	/**
	 * Sanitizes and validates a product ID before saving it as an option.
	 *
	 * @param mixed $value Raw option value.
	 * @return int
	 */
	public function sanitize_product_id( $value ) {
		$product_id = absint( $value );

		if ( 0 === $product_id ) {
			return 0;
		}

		if ( $this->is_valid_published_product_id( $product_id ) ) {
			return $product_id;
		}

		add_settings_error(
			self::SETTINGS_GROUP,
			'invalid_product_id',
			esc_html__( 'One or more selected products were not saved because they are not published WooCommerce products.', 'hfo-golf-registration' ),
			'error'
		);

		return 0;
	}

	/**
	 * Checks whether WooCommerce is active enough for product mappings.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && post_type_exists( 'product' );
	}

	/**
	 * Gets published WooCommerce products for select fields.
	 *
	 * @return WP_Post[]
	 */
	private function get_published_products() {
		if ( ! $this->is_woocommerce_active() ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'all',
			)
		);
	}

	/**
	 * Validates that a post ID belongs to a published WooCommerce product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	private function is_valid_published_product_id( $product_id ) {
		if ( ! $this->is_woocommerce_active() ) {
			return false;
		}

		$product = get_post( $product_id );

		return $product instanceof WP_Post && 'product' === $product->post_type && 'publish' === $product->post_status;
	}

	/**
	 * Gets all product mapping fields.
	 *
	 * @return array<string,array{label:string,option_name:string}>
	 */
	private function get_product_mapping_fields() {
		return array(
			'golf_product_id'             => array(
				'label'       => esc_html__( 'Golf Product', 'hfo-golf-registration' ),
				'option_name' => 'hfo_golf_registration_golf_product_id',
			),
			'lunch_product_id'            => array(
				'label'       => esc_html__( 'Lunch Product', 'hfo-golf-registration' ),
				'option_name' => 'hfo_golf_registration_lunch_product_id',
			),
			'dinner_product_id'           => array(
				'label'       => esc_html__( 'Dinner Product', 'hfo-golf-registration' ),
				'option_name' => 'hfo_golf_registration_dinner_product_id',
			),
			'platinum_sponsor_product_id' => array(
				'label'       => esc_html__( 'Platinum Sponsor Product', 'hfo-golf-registration' ),
				'option_name' => 'hfo_golf_registration_platinum_sponsor_product_id',
			),
			'gold_sponsor_product_id'     => array(
				'label'       => esc_html__( 'Gold Sponsor Product', 'hfo-golf-registration' ),
				'option_name' => 'hfo_golf_registration_gold_sponsor_product_id',
			),
			'silver_sponsor_product_id'   => array(
				'label'       => esc_html__( 'Silver Sponsor Product', 'hfo-golf-registration' ),
				'option_name' => 'hfo_golf_registration_silver_sponsor_product_id',
			),
			'tee_sponsor_product_id'      => array(
				'label'       => esc_html__( 'Tee Sponsor Product', 'hfo-golf-registration' ),
				'option_name' => 'hfo_golf_registration_tee_sponsor_product_id',
			),
		);
	}
}
