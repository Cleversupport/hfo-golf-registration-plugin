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
	 * Default WooCommerce products section ID.
	 *
	 * @var string
	 */
	const DEFAULT_PRODUCTS_SECTION = 'hfo_golf_registration_default_products';

	/**
	 * Frontend styling section ID.
	 *
	 * @var string
	 */
	const FRONTEND_STYLING_SECTION = 'hfo_golf_registration_frontend_styling';

	/**
	 * reCAPTCHA v3 section ID.
	 *
	 * @var string
	 */
	const RECAPTCHA_SECTION = 'hfo_golf_registration_recaptcha';

	/**
	 * GitHub updater section ID.
	 *
	 * @var string
	 */
	const GITHUB_UPDATES_SECTION = 'hfo_golf_registration_github_updates';

	/**
	 * Option key for custom frontend CSS.
	 *
	 * @var string
	 */
	const CUSTOM_FRONTEND_CSS_OPTION = 'hfo_golf_registration_custom_frontend_css';

	/**
	 * Option key for the optional GitHub personal access token.
	 *
	 * @var string
	 */
	const GITHUB_TOKEN_OPTION = 'hfo_golf_registration_github_token';

	/**
	 * Option key for the Google reCAPTCHA v3 site key.
	 *
	 * @var string
	 */
	const RECAPTCHA_SITE_KEY_OPTION = 'hfo_golf_registration_recaptcha_site_key';

	/**
	 * Option key for the Google reCAPTCHA v3 secret key.
	 *
	 * @var string
	 */
	const RECAPTCHA_SECRET_KEY_OPTION = 'hfo_golf_registration_recaptcha_secret_key';

	/**
	 * Option key for the minimum accepted Google reCAPTCHA v3 score.
	 *
	 * @var string
	 */
	const RECAPTCHA_MINIMUM_SCORE_OPTION = 'hfo_golf_registration_recaptcha_minimum_score';

	/**
	 * Action name used to create default products.
	 *
	 * @var string
	 */
	const CREATE_DEFAULT_PRODUCTS_ACTION = 'hfo_golf_registration_create_default_products';

	/**
	 * Nonce action used to create default products.
	 *
	 * @var string
	 */
	const CREATE_DEFAULT_PRODUCTS_NONCE_ACTION = 'hfo_golf_registration_create_default_products_nonce';

	/**
	 * Product meta key used to identify products created by this plugin.
	 *
	 * @var string
	 */
	const PRODUCT_TYPE_META_KEY = '_hfo_golf_registration_product_type';

	/**
	 * Registers WordPress hooks used by the settings page.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'render_woocommerce_required_notice' ) );
		add_action( 'admin_notices', array( $this, 'render_default_products_notice' ) );
		add_action( 'admin_post_' . self::CREATE_DEFAULT_PRODUCTS_ACTION, array( $this, 'handle_create_default_products' ) );
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

		register_setting(
			self::SETTINGS_GROUP,
			self::CUSTOM_FRONTEND_CSS_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_custom_frontend_css' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::GITHUB_TOKEN_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_github_token' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::RECAPTCHA_SITE_KEY_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_recaptcha_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::RECAPTCHA_SECRET_KEY_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_recaptcha_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::RECAPTCHA_MINIMUM_SCORE_OPTION,
			array(
				'type'              => 'number',
				'sanitize_callback' => array( $this, 'sanitize_recaptcha_minimum_score' ),
				'default'           => 0.5,
			)
		);

		add_settings_section(
			self::DEFAULT_PRODUCTS_SECTION,
			esc_html__( 'Default WooCommerce Products', 'hfo-golf-registration' ),
			array( $this, 'render_default_products_section' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			self::PRODUCT_MAPPING_SECTION,
			esc_html__( 'WooCommerce Product Mapping', 'hfo-golf-registration' ),
			array( $this, 'render_product_mapping_section' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			self::FRONTEND_STYLING_SECTION,
			esc_html__( 'Frontend Styling', 'hfo-golf-registration' ),
			array( $this, 'render_frontend_styling_section' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			self::RECAPTCHA_SECTION,
			esc_html__( 'reCAPTCHA v3', 'hfo-golf-registration' ),
			array( $this, 'render_recaptcha_section' ),
			self::PAGE_SLUG
		);

		add_settings_section(
			self::GITHUB_UPDATES_SECTION,
			esc_html__( 'GitHub Updates', 'hfo-golf-registration' ),
			array( $this, 'render_github_updates_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			self::CUSTOM_FRONTEND_CSS_OPTION,
			esc_html__( 'Custom Frontend CSS', 'hfo-golf-registration' ),
			array( $this, 'render_custom_frontend_css_field' ),
			self::PAGE_SLUG,
			self::FRONTEND_STYLING_SECTION
		);

		add_settings_field(
			self::GITHUB_TOKEN_OPTION,
			esc_html__( 'GitHub Personal Access Token', 'hfo-golf-registration' ),
			array( $this, 'render_github_token_field' ),
			self::PAGE_SLUG,
			self::GITHUB_UPDATES_SECTION
		);

		add_settings_field(
			self::RECAPTCHA_SITE_KEY_OPTION,
			esc_html__( 'reCAPTCHA v3 Site Key', 'hfo-golf-registration' ),
			array( $this, 'render_recaptcha_site_key_field' ),
			self::PAGE_SLUG,
			self::RECAPTCHA_SECTION
		);

		add_settings_field(
			self::RECAPTCHA_SECRET_KEY_OPTION,
			esc_html__( 'reCAPTCHA v3 Secret Key', 'hfo-golf-registration' ),
			array( $this, 'render_recaptcha_secret_key_field' ),
			self::PAGE_SLUG,
			self::RECAPTCHA_SECTION
		);

		add_settings_field(
			self::RECAPTCHA_MINIMUM_SCORE_OPTION,
			esc_html__( 'Minimum Score', 'hfo-golf-registration' ),
			array( $this, 'render_recaptcha_minimum_score_field' ),
			self::PAGE_SLUG,
			self::RECAPTCHA_SECTION
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
			<form id="hfo-golf-registration-create-default-products-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<?php wp_nonce_field( self::CREATE_DEFAULT_PRODUCTS_NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CREATE_DEFAULT_PRODUCTS_ACTION ); ?>" />
			</form>

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
	 * Renders the default products section description.
	 *
	 * @return void
	 */
	public function render_default_products_section() {
		printf(
			'<p>%s</p>',
			esc_html__( 'Create the default WooCommerce checkout container products for golf registrations, guests, and sponsorships. Final cart prices may be overridden later from the selected golf event.', 'hfo-golf-registration' )
		);

		submit_button(
			esc_html__( 'Create Default Products', 'hfo-golf-registration' ),
			'primary',
			'submit',
			true,
			array(
				'form' => 'hfo-golf-registration-create-default-products-form',
			)
		);
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
	 * Renders the frontend styling section description.
	 *
	 * @return void
	 */
	public function render_frontend_styling_section() {
		printf(
			'<p>%s</p>',
			esc_html__( 'Edit the CSS used by the golf registration frontend form and event shortcodes. Leave blank to use the plugin default CSS.', 'hfo-golf-registration' )
		);
	}

	/**
	 * Renders the reCAPTCHA v3 section description.
	 *
	 * @return void
	 */
	public function render_recaptcha_section() {
		printf(
			'<p>%s</p>',
			esc_html__( 'Google reCAPTCHA v3 is mandatory for the public golf registration form. Submissions are blocked until both keys are configured.', 'hfo-golf-registration' )
		);
	}

	/**
	 * Renders the GitHub updates section description.
	 *
	 * @return void
	 */
	public function render_github_updates_section() {
		printf(
			'<p>%s</p>',
			esc_html__( 'WordPress checks GitHub Releases for plugin updates. A token is optional for public repositories and required if the GitHub repository is private.', 'hfo-golf-registration' )
		);
	}

	/**
	 * Renders the custom frontend CSS textarea field.
	 *
	 * @return void
	 */
	public function render_custom_frontend_css_field() {
		$saved_css = (string) get_option( self::CUSTOM_FRONTEND_CSS_OPTION, '' );
		$css       = '' === trim( $saved_css ) ? $this->get_default_frontend_css() : $saved_css;

		printf(
			'<textarea id="%1$s" name="%1$s" rows="24" class="large-text code" style="width: 100%%; font-family: Consolas, Monaco, monospace;" spellcheck="false">%2$s</textarea>',
			esc_attr( self::CUSTOM_FRONTEND_CSS_OPTION ),
			esc_textarea( $css )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'To restore default styling, clear this field and save settings.', 'hfo-golf-registration' )
		);
	}

	/**
	 * Renders the optional GitHub personal access token field.
	 *
	 * @return void
	 */
	public function render_github_token_field() {
		$has_token = '' !== (string) get_option( self::GITHUB_TOKEN_OPTION, '' );

		printf(
			'<input id="%1$s" name="%1$s" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="%2$s" />',
			esc_attr( self::GITHUB_TOKEN_OPTION ),
			esc_attr( $has_token ? __( 'Token saved; leave blank to keep it', 'hfo-golf-registration' ) : __( 'Optional for public repositories', 'hfo-golf-registration' ) )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Used only for GitHub API and release download requests. The saved token is never displayed back in this field.', 'hfo-golf-registration' )
		);
	}

	/**
	 * Renders the Google reCAPTCHA v3 site key field.
	 *
	 * @return void
	 */
	public function render_recaptcha_site_key_field() {
		$value = (string) get_option( self::RECAPTCHA_SITE_KEY_OPTION, '' );

		printf(
			'<input id="%1$s" name="%1$s" type="text" class="regular-text" value="%2$s" autocomplete="off" />',
			esc_attr( self::RECAPTCHA_SITE_KEY_OPTION ),
			esc_attr( $value )
		);
	}

	/**
	 * Renders the Google reCAPTCHA v3 secret key field.
	 *
	 * @return void
	 */
	public function render_recaptcha_secret_key_field() {
		$has_key = '' !== (string) get_option( self::RECAPTCHA_SECRET_KEY_OPTION, '' );

		printf(
			'<input id="%1$s" name="%1$s" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="%2$s" />',
			esc_attr( self::RECAPTCHA_SECRET_KEY_OPTION ),
			esc_attr( $has_key ? __( 'Secret key saved; leave blank to keep it', 'hfo-golf-registration' ) : __( 'Required', 'hfo-golf-registration' ) )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'The saved secret key is never displayed and is used only for server-side verification.', 'hfo-golf-registration' )
		);
	}

	/**
	 * Renders the Google reCAPTCHA v3 minimum score field.
	 *
	 * @return void
	 */
	public function render_recaptcha_minimum_score_field() {
		$value = (float) get_option( self::RECAPTCHA_MINIMUM_SCORE_OPTION, 0.5 );

		printf(
			'<input id="%1$s" name="%1$s" type="number" class="small-text" value="%2$s" min="0" max="1" step="0.1" />',
			esc_attr( self::RECAPTCHA_MINIMUM_SCORE_OPTION ),
			esc_attr( (string) $value )
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
	 * Displays the result notice after attempting to create default products.
	 *
	 * @return void
	 */
	public function render_default_products_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_transient( $this->get_default_products_notice_transient_key() );

		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( $this->get_default_products_notice_transient_key() );

		$type    = isset( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'success';
		$message = isset( $notice['message'] ) ? (string) $notice['message'] : '';

		if ( '' === $message ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Handles the default product creation action.
	 *
	 * @return void
	 */
	public function handle_create_default_products() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to create default products.', 'hfo-golf-registration' ) );
		}

		check_admin_referer( self::CREATE_DEFAULT_PRODUCTS_NONCE_ACTION );

		if ( ! $this->is_woocommerce_active() ) {
			$this->set_default_products_notice(
				'error',
				esc_html__( 'WooCommerce must be active before default products can be created.', 'hfo-golf-registration' )
			);
			$this->redirect_to_settings_page();
		}

		$created = 0;
		$reused  = 0;

		foreach ( $this->get_default_product_definitions() as $definition ) {
			$mapped_product_id = absint( get_option( $definition['option_name'], 0 ) );

			if ( $mapped_product_id && $this->is_valid_published_product_id( $mapped_product_id ) ) {
				continue;
			}

			$existing_product_id = $this->get_plugin_product_id_by_type( $definition['type'] );

			if ( $existing_product_id ) {
				update_option( $definition['option_name'], $existing_product_id );
				++$reused;
				continue;
			}

			$product_id = $this->create_default_product( $definition );

			if ( $product_id ) {
				update_option( $definition['option_name'], $product_id );
				++$created;
			}
		}

		$this->set_default_products_notice(
			'success',
			sprintf(
				/* translators: 1: number of created products, 2: number of reused products. */
				esc_html__( 'Default WooCommerce products processed. Created: %1$d. Reused: %2$d.', 'hfo-golf-registration' ),
				$created,
				$reused
			)
		);

		$this->redirect_to_settings_page();
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
	 * Sanitizes custom frontend CSS before saving.
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_custom_frontend_css( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$css = wp_unslash( $value );
		$css = preg_replace( '#<script[^>]*>.*?</script>#is', '', $css );
		$css = preg_replace( '#<\?(?:php)?[\s\S]*?\?>#i', '', $css );
		$css = preg_replace( '#</?style[^>]*>#i', '', $css );
		$css = wp_strip_all_tags( $css );

		return trim( $css );
	}

	/**
	 * Sanitizes the optional GitHub personal access token before saving.
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_github_token( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$token = trim( sanitize_text_field( wp_unslash( $value ) ) );

		if ( '' === $token ) {
			$existing_token = get_option( self::GITHUB_TOKEN_OPTION, '' );

			return is_string( $existing_token ) ? $existing_token : '';
		}

		return $token;
	}

	/**
	 * Sanitizes a Google reCAPTCHA v3 key before saving.
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	public function sanitize_recaptcha_key( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$key = trim( sanitize_text_field( wp_unslash( $value ) ) );

		if ( '' === $key && 'sanitize_option_' . self::RECAPTCHA_SECRET_KEY_OPTION === current_filter() ) {
			$existing_key = get_option( self::RECAPTCHA_SECRET_KEY_OPTION, '' );

			return is_string( $existing_key ) ? $existing_key : '';
		}

		return $key;
	}

	/**
	 * Sanitizes the minimum Google reCAPTCHA v3 score.
	 *
	 * @param mixed $value Raw option value.
	 * @return float
	 */
	public function sanitize_recaptcha_minimum_score( $value ) {
		$score = is_numeric( $value ) ? (float) $value : 0.5;

		return min( 1, max( 0, $score ) );
	}

	/**
	 * Gets the default frontend CSS file contents for the settings textarea.
	 *
	 * @return string
	 */
	private function get_default_frontend_css() {
		$css_file = HFO_GOLF_REGISTRATION_PATH . 'assets/css/hfo-golf-registration-form.css';

		if ( ! is_readable( $css_file ) ) {
			return '';
		}

		$css = file_get_contents( $css_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading bundled plugin CSS for settings preview.

		return false === $css ? '' : (string) $css;
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
	 * Redirects back to the settings page.
	 *
	 * @return void
	 */
	private function redirect_to_settings_page() {
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Stores a default products admin notice for the next page load.
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_default_products_notice( $type, $message ) {
		set_transient(
			$this->get_default_products_notice_transient_key(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Gets the transient key used for default products notices.
	 *
	 * @return string
	 */
	private function get_default_products_notice_transient_key() {
		return 'hfo_golf_registration_default_products_notice_' . get_current_user_id();
	}

	/**
	 * Creates a default WooCommerce product.
	 *
	 * @param array<string,string|int> $definition Product definition.
	 * @return int
	 */
	private function create_default_product( $definition ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return 0;
		}

		$product = new WC_Product_Simple();
		$product->set_name( $definition['name'] );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_regular_price( (string) $definition['price'] );
		$product->set_price( (string) $definition['price'] );

		if ( method_exists( $product, 'set_tax_status' ) ) {
			$product->set_tax_status( 'none' );
		}

		$product_id = $product->save();

		if ( $product_id ) {
			update_post_meta( $product_id, self::PRODUCT_TYPE_META_KEY, $definition['type'] );
			$this->hide_product_from_catalog_and_search( $product_id );
		}

		return absint( $product_id );
	}

	/**
	 * Hides a WooCommerce product from catalog and search listings when visibility terms are available.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function hide_product_from_catalog_and_search( $product_id ) {
		if ( ! taxonomy_exists( 'product_visibility' ) ) {
			return;
		}

		if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			$visibility_terms = wc_get_product_visibility_term_ids();
			$term_ids         = array();

			foreach ( array( 'exclude-from-catalog', 'exclude-from-search' ) as $term_key ) {
				if ( ! empty( $visibility_terms[ $term_key ] ) ) {
					$term_ids[] = absint( $visibility_terms[ $term_key ] );
				}
			}

			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $product_id, $term_ids, 'product_visibility', false );
			}

			return;
		}

		wp_set_object_terms( $product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility', false );
	}

	/**
	 * Finds an existing product created by this plugin for the requested product type.
	 *
	 * @param string $product_type Plugin product type.
	 * @return int
	 */
	private function get_plugin_product_id_by_type( $product_type ) {
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::PRODUCT_TYPE_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required one-time admin lookup for plugin-created products.
				'meta_value'     => $product_type, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required one-time admin lookup for plugin-created products.
			)
		);

		if ( empty( $products ) ) {
			return 0;
		}

		return absint( $products[0] );
	}

	/**
	 * Gets default WooCommerce checkout container product definitions.
	 *
	 * @return array<int,array{name:string,price:int,type:string,option_name:string}>
	 */
	private function get_default_product_definitions() {
		return array(
			array(
				'name'        => esc_html__( 'Golf Player Registration', 'hfo-golf-registration' ),
				'price'       => 150,
				'type'        => 'golf',
				'option_name' => 'hfo_golf_registration_golf_product_id',
			),
			array(
				'name'        => esc_html__( 'Lunch Only Guest', 'hfo-golf-registration' ),
				'price'       => 40,
				'type'        => 'lunch',
				'option_name' => 'hfo_golf_registration_lunch_product_id',
			),
			array(
				'name'        => esc_html__( 'Dinner Only Guest', 'hfo-golf-registration' ),
				'price'       => 40,
				'type'        => 'dinner',
				'option_name' => 'hfo_golf_registration_dinner_product_id',
			),
			array(
				'name'        => esc_html__( 'Platinum Sponsor', 'hfo-golf-registration' ),
				'price'       => 1000,
				'type'        => 'platinum_sponsor',
				'option_name' => 'hfo_golf_registration_platinum_sponsor_product_id',
			),
			array(
				'name'        => esc_html__( 'Gold Sponsor', 'hfo-golf-registration' ),
				'price'       => 500,
				'type'        => 'gold_sponsor',
				'option_name' => 'hfo_golf_registration_gold_sponsor_product_id',
			),
			array(
				'name'        => esc_html__( 'Silver Sponsor', 'hfo-golf-registration' ),
				'price'       => 250,
				'type'        => 'silver_sponsor',
				'option_name' => 'hfo_golf_registration_silver_sponsor_product_id',
			),
			array(
				'name'        => esc_html__( 'Tee Sponsor', 'hfo-golf-registration' ),
				'price'       => 100,
				'type'        => 'tee_sponsor',
				'option_name' => 'hfo_golf_registration_tee_sponsor_product_id',
			),
		);
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
