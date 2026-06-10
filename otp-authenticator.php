<?php
/**
 * Plugin Name: Clever OTP Authenticator
 * Plugin URI:  https://github.com/Cleversupport/clever-otp-authenticator
 * Description: Adds OTP authentication tools, a configurable subscribe button, and GitHub release updates.
 * Version:     1.0.1
 * Author:      Clever Support
 * Text Domain: clever-otp-authenticator
 * Domain Path: /languages
 *
 * @package Clever_OTP_Authenticator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLEVER_OTP_AUTHENTICATOR_VERSION', '1.0.1' );
define( 'CLEVER_OTP_AUTHENTICATOR_FILE', __FILE__ );
define( 'CLEVER_OTP_AUTHENTICATOR_PATH', plugin_dir_path( __FILE__ ) );

require_once CLEVER_OTP_AUTHENTICATOR_PATH . 'includes/class-clever-otp-authenticator-github-updater.php';

/**
 * Registers and renders the Clever OTP Authenticator settings screen.
 */
class Clever_OTP_Authenticator_Admin_Settings {

	/**
	 * Unified settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'clever-otp-authenticator';

	/**
	 * Settings group name shared by all tabs.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'clever_otp_authenticator_settings';

	/**
	 * Subscribe button text option key.
	 *
	 * @var string
	 */
	const SUBSCRIBE_TEXT_OPTION = 'otpa_subscribe_text';

	/**
	 * Subscribe button URL option key.
	 *
	 * @var string
	 */
	const SUBSCRIBE_URL_OPTION = 'otpa_subscribe_url';

	/**
	 * GitHub updater token option key.
	 *
	 * @var string
	 */
	const GITHUB_TOKEN_OPTION = 'clever_otp_authenticator_github_token';

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_shortcode( 'otp_subscribe_button', array( $this, 'render_subscribe_button_shortcode' ) );
	}

	/**
	 * Adds the single Settings > OTP Authenticator menu entry.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			esc_html__( 'OTP Authenticator', 'clever-otp-authenticator' ),
			esc_html__( 'OTP Authenticator', 'clever-otp-authenticator' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers tabbed settings sections and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::SUBSCRIBE_TEXT_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => __( 'Subscribe', 'clever-otp-authenticator' ),
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::SUBSCRIBE_URL_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
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

		add_settings_section(
			'clever_otp_authenticator_general',
			esc_html__( 'General Settings', 'clever-otp-authenticator' ),
			array( $this, 'render_general_section' ),
			$this->get_tab_page_slug( 'general' )
		);

		add_settings_section(
			'clever_otp_authenticator_subscribe_button',
			esc_html__( 'Subscribe Button', 'clever-otp-authenticator' ),
			array( $this, 'render_subscribe_section' ),
			$this->get_tab_page_slug( 'subscribe' )
		);

		add_settings_section(
			'clever_otp_authenticator_updates',
			esc_html__( 'Updates', 'clever-otp-authenticator' ),
			array( $this, 'render_updates_section' ),
			$this->get_tab_page_slug( 'updates' )
		);

		add_settings_field(
			self::SUBSCRIBE_TEXT_OPTION,
			esc_html__( 'Button Text', 'clever-otp-authenticator' ),
			array( $this, 'render_subscribe_text_field' ),
			$this->get_tab_page_slug( 'subscribe' ),
			'clever_otp_authenticator_subscribe_button'
		);

		add_settings_field(
			self::SUBSCRIBE_URL_OPTION,
			esc_html__( 'Button URL', 'clever-otp-authenticator' ),
			array( $this, 'render_subscribe_url_field' ),
			$this->get_tab_page_slug( 'subscribe' ),
			'clever_otp_authenticator_subscribe_button'
		);

		add_settings_field(
			self::GITHUB_TOKEN_OPTION,
			esc_html__( 'GitHub Personal Access Token', 'clever-otp-authenticator' ),
			array( $this, 'render_github_token_field' ),
			$this->get_tab_page_slug( 'updates' ),
			'clever_otp_authenticator_updates'
		);
	}

	/**
	 * Renders the tabbed settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = $this->get_active_tab();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'OTP Authenticator', 'clever-otp-authenticator' ); ?></h1>
			<?php $this->render_tabs( $active_tab ); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( $this->get_tab_page_slug( $active_tab ) );
				submit_button( esc_html__( 'Save Settings', 'clever-otp-authenticator' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the tab navigation.
	 *
	 * @param string $active_tab Active tab key.
	 * @return void
	 */
	private function render_tabs( $active_tab ) {
		$tabs = $this->get_tabs();
		?>
		<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__( 'OTP Authenticator settings tabs', 'clever-otp-authenticator' ); ?>">
			<?php foreach ( $tabs as $tab_key => $label ) : ?>
				<a class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $tab_key ), admin_url( 'options-general.php' ) ) ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Renders the General Settings tab content.
	 *
	 * @return void
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure Clever OTP Authenticator settings from the tabs above.', 'clever-otp-authenticator' ) . '</p>';
	}

	/**
	 * Renders the Subscribe Button tab description.
	 *
	 * @return void
	 */
	public function render_subscribe_section() {
		echo '<p>' . esc_html__( 'Configure the text and URL used by the OTP subscribe button output.', 'clever-otp-authenticator' ) . '</p>';
	}

	/**
	 * Renders the Updates tab description.
	 *
	 * @return void
	 */
	public function render_updates_section() {
		echo '<p>' . esc_html__( 'Add an optional GitHub token for private repository access or higher API rate limits when checking for plugin updates.', 'clever-otp-authenticator' ) . '</p>';
	}

	/**
	 * Renders the subscribe button text field.
	 *
	 * @return void
	 */
	public function render_subscribe_text_field() {
		$value = get_option( self::SUBSCRIBE_TEXT_OPTION, __( 'Subscribe', 'clever-otp-authenticator' ) );
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::SUBSCRIBE_TEXT_OPTION ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<?php
	}

	/**
	 * Renders the subscribe button URL field.
	 *
	 * @return void
	 */
	public function render_subscribe_url_field() {
		$value = get_option( self::SUBSCRIBE_URL_OPTION, '' );
		?>
		<input type="url" class="regular-text" name="<?php echo esc_attr( self::SUBSCRIBE_URL_OPTION ); ?>" value="<?php echo esc_url( $value ); ?>" />
		<p class="description"><?php echo esc_html__( 'Leave blank to hide the subscribe button.', 'clever-otp-authenticator' ); ?></p>
		<?php
	}

	/**
	 * Renders the GitHub token field.
	 *
	 * @return void
	 */
	public function render_github_token_field() {
		$value = get_option( self::GITHUB_TOKEN_OPTION, '' );
		?>
		<input type="password" class="regular-text" autocomplete="off" name="<?php echo esc_attr( self::GITHUB_TOKEN_OPTION ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<p class="description"><?php echo esc_html__( 'Stored in the existing clever_otp_authenticator_github_token option.', 'clever-otp-authenticator' ); ?></p>
		<?php
	}

	/**
	 * Renders the subscribe button shortcode output.
	 *
	 * @return string Button markup or an empty string when no URL is saved.
	 */
	public function render_subscribe_button_shortcode() {
		$url = get_option( self::SUBSCRIBE_URL_OPTION, '' );

		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return '';
		}

		$text = get_option( self::SUBSCRIBE_TEXT_OPTION, __( 'Subscribe', 'clever-otp-authenticator' ) );
		$text = is_string( $text ) && '' !== trim( $text ) ? $text : __( 'Subscribe', 'clever-otp-authenticator' );

		return sprintf(
			'<a class="otpa-subscribe-button" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $text )
		);
	}

	/**
	 * Sanitizes a GitHub token without changing the saved option key.
	 *
	 * @param string $value Submitted token value.
	 * @return string
	 */
	public function sanitize_github_token( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		return preg_replace( '/[^A-Za-z0-9_\.\-]/', '', $value );
	}

	/**
	 * Gets the active tab key.
	 *
	 * @return string
	 */
	private function get_active_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab routing.

		return array_key_exists( $tab, $this->get_tabs() ) ? $tab : 'general';
	}

	/**
	 * Gets available settings tabs.
	 *
	 * @return array<string,string>
	 */
	private function get_tabs() {
		return array(
			'general'   => __( 'General Settings', 'clever-otp-authenticator' ),
			'subscribe' => __( 'Subscribe Button', 'clever-otp-authenticator' ),
			'updates'   => __( 'Updates', 'clever-otp-authenticator' ),
		);
	}

	/**
	 * Gets a Settings API page slug for a tab.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	private function get_tab_page_slug( $tab ) {
		return self::PAGE_SLUG . '-' . $tab;
	}
}

$clever_otp_authenticator_admin_settings = new Clever_OTP_Authenticator_Admin_Settings();
$clever_otp_authenticator_admin_settings->register_hooks();

$clever_otp_authenticator_github_updater = new Clever_OTP_Authenticator_GitHub_Updater();
$clever_otp_authenticator_github_updater->register_hooks();
