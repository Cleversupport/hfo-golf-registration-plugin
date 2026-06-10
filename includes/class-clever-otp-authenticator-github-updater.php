<?php
/**
 * GitHub release updater integration for Clever OTP Authenticator.
 *
 * @package Clever_OTP_Authenticator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds WordPress plugin update support backed by GitHub Releases.
 */
class Clever_OTP_Authenticator_GitHub_Updater {

	/**
	 * GitHub repository owner.
	 *
	 * @var string
	 */
	const REPOSITORY_OWNER = 'Cleversupport';

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	const REPOSITORY_NAME = 'clever-otp-authenticator';

	/**
	 * Existing GitHub token option key.
	 *
	 * @var string
	 */
	const TOKEN_OPTION = 'clever_otp_authenticator_github_token';

	/**
	 * Plugin slug/folder.
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'otp-authenticator';

	/**
	 * User agent sent to GitHub.
	 *
	 * @var string
	 */
	const USER_AGENT = 'Clever-OTP-Authenticator-Updater';

	/**
	 * Cached release data for the current request.
	 *
	 * @var array<string,mixed>|null
	 */
	private $latest_release;

	/**
	 * Registers WordPress updater hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_update_plugins_transient' ) );
		add_filter( 'plugins_api', array( $this, 'filter_plugins_api' ), 10, 3 );
		add_filter( 'http_request_args', array( $this, 'filter_http_request_args' ), 10, 2 );
	}

	/**
	 * Adds update data to WordPress plugin update transient when a newer GitHub release exists.
	 *
	 * @param mixed $transient Plugin update transient.
	 * @return mixed
	 */
	public function filter_update_plugins_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( empty( $release ) ) {
			return $transient;
		}

		$remote_version = $this->get_release_version( $release );

		if ( '' === $remote_version || ! version_compare( $remote_version, CLEVER_OTP_AUTHENTICATOR_VERSION, '>' ) ) {
			if ( isset( $transient->response[ $this->get_plugin_basename() ] ) ) {
				unset( $transient->response[ $this->get_plugin_basename() ] );
			}

			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ $this->get_plugin_basename() ] = $this->build_update_response( $release, $remote_version );

		return $transient;
	}

	/**
	 * Supplies the plugin details modal content for the WordPress update screen.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Installation API.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object|array
	 */
	public function filter_plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( empty( $release ) ) {
			return $result;
		}

		$release_notes = ! empty( $release['body'] ) ? (string) $release['body'] : __( 'No release notes were provided for this release.', 'clever-otp-authenticator' );

		return (object) array(
			'name'          => 'Clever OTP Authenticator',
			'slug'          => self::PLUGIN_SLUG,
			'version'       => $this->get_release_version( $release ),
			'author'        => 'Clever Support',
			'homepage'      => $this->get_repository_url(),
			'last_updated'  => ! empty( $release['published_at'] ) ? (string) $release['published_at'] : '',
			'download_link' => $this->get_download_url( $release ),
			'sections'      => array(
				'description' => __( 'OTP authentication tools for WordPress.', 'clever-otp-authenticator' ),
				'changelog'   => wp_kses_post( wpautop( $release_notes ) ),
			),
		);
	}

	/**
	 * Adds GitHub API headers, including the optional token, to updater requests.
	 *
	 * @param array<string,mixed> $args HTTP request arguments.
	 * @param string              $url  Request URL.
	 * @return array<string,mixed>
	 */
	public function filter_http_request_args( $args, $url ) {
		if ( false === strpos( $url, 'api.github.com/repos/' . self::REPOSITORY_OWNER . '/' . self::REPOSITORY_NAME ) && false === strpos( $url, 'github.com/' . self::REPOSITORY_OWNER . '/' . self::REPOSITORY_NAME ) ) {
			return $args;
		}

		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}

		$args['headers']['Accept']     = 'application/vnd.github+json';
		$args['headers']['User-Agent'] = self::USER_AGENT;

		$token = $this->get_github_token();

		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		return $args;
	}

	/**
	 * Fetches the latest GitHub release.
	 *
	 * @return array<string,mixed>
	 */
	private function get_latest_release() {
		if ( null !== $this->latest_release ) {
			return $this->latest_release;
		}

		$cached_release = get_site_transient( 'clever_otp_authenticator_github_latest_release' );

		if ( is_array( $cached_release ) ) {
			$this->latest_release = $cached_release;
			return $this->latest_release;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPOSITORY_OWNER . '/' . self::REPOSITORY_NAME . '/releases/latest',
			array(
				'timeout' => 15,
				'headers' => $this->get_github_headers(),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->latest_release = array();
			return $this->latest_release;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			$this->latest_release = array();
			return $this->latest_release;
		}

		$this->latest_release = $release;
		set_site_transient( 'clever_otp_authenticator_github_latest_release', $release, 15 * MINUTE_IN_SECONDS );

		return $this->latest_release;
	}

	/**
	 * Builds the object WordPress expects in the plugin update transient.
	 *
	 * @param array<string,mixed> $release        GitHub release data.
	 * @param string              $remote_version Remote release version.
	 * @return object
	 */
	private function build_update_response( $release, $remote_version ) {
		return (object) array(
			'id'          => $this->get_repository_url(),
			'slug'        => self::PLUGIN_SLUG,
			'plugin'      => $this->get_plugin_basename(),
			'new_version' => $remote_version,
			'url'         => $this->get_repository_url(),
			'package'     => $this->get_download_url( $release ),
			'tested'      => '',
		);
	}

	/**
	 * Gets the release version from a GitHub release tag.
	 *
	 * @param array<string,mixed> $release GitHub release data.
	 * @return string
	 */
	private function get_release_version( $release ) {
		$tag_name = isset( $release['tag_name'] ) ? (string) $release['tag_name'] : '';

		return ltrim( trim( $tag_name ), 'vV' );
	}

	/**
	 * Gets the best available download URL from a GitHub release.
	 *
	 * @param array<string,mixed> $release GitHub release data.
	 * @return string
	 */
	private function get_download_url( $release ) {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( ! is_array( $asset ) || empty( $asset['browser_download_url'] ) ) {
					continue;
				}

				$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';

				if ( preg_match( '/\.zip$/i', $name ) ) {
					return (string) $asset['browser_download_url'];
				}
			}
		}

		return ! empty( $release['zipball_url'] ) ? (string) $release['zipball_url'] : '';
	}

	/**
	 * Gets GitHub request headers for API requests.
	 *
	 * @return array<string,string>
	 */
	private function get_github_headers() {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => self::USER_AGENT,
		);

		$token = $this->get_github_token();

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	/**
	 * Gets the optional GitHub personal access token from the existing option key.
	 *
	 * @return string
	 */
	private function get_github_token() {
		$token = get_option( self::TOKEN_OPTION, '' );

		return is_string( $token ) ? trim( $token ) : '';
	}

	/**
	 * Gets the plugin basename used by WordPress update APIs.
	 *
	 * @return string
	 */
	private function get_plugin_basename() {
		return plugin_basename( CLEVER_OTP_AUTHENTICATOR_FILE );
	}

	/**
	 * Gets the repository URL.
	 *
	 * @return string
	 */
	private function get_repository_url() {
		return 'https://github.com/' . self::REPOSITORY_OWNER . '/' . self::REPOSITORY_NAME;
	}
}
