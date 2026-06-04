<?php
/**
 * GitHub release updater integration.
 *
 * @package HFO_Golf_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds WordPress plugin update support backed by GitHub Releases.
 */
class HFO_Golf_Registration_GitHub_Updater {

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
	const REPOSITORY_NAME = 'hfo-golf-registration-plugin';

	/**
	 * GitHub latest release API endpoint.
	 *
	 * @var string
	 */
	const LATEST_RELEASE_ENDPOINT = 'https://api.github.com/repos/Cleversupport/hfo-golf-registration-plugin/releases/latest';

	/**
	 * GitHub repository tags API endpoint.
	 *
	 * @var string
	 */
	const TAGS_ENDPOINT = 'https://api.github.com/repos/Cleversupport/hfo-golf-registration-plugin/tags';

	/**
	 * Option key for the optional GitHub personal access token.
	 *
	 * @var string
	 */
	const TOKEN_OPTION = 'hfo_golf_registration_github_token';

	/**
	 * Plugin slug/folder.
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'hfo-golf-registration';

	/**
	 * User agent sent to GitHub.
	 *
	 * @var string
	 */
	const USER_AGENT = 'HFO-Golf-Registration-Updater';

	/**
	 * Cached release data for the current request.
	 *
	 * @var array<string,mixed>|null
	 */
	private $latest_release;

	/**
	 * Registers WordPress hooks used by the updater.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_update_plugins_transient' ) );
		add_filter( 'plugins_api', array( $this, 'filter_plugins_api' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'filter_upgrader_source_selection' ), 10, 4 );
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

		if ( '' === $remote_version || ! version_compare( $remote_version, HFO_GOLF_REGISTRATION_VERSION, '>' ) ) {
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

		$remote_version = $this->get_release_version( $release );
		$release_notes  = ! empty( $release['body'] ) ? (string) $release['body'] : __( 'No release notes were provided for this release.', 'hfo-golf-registration' );

		return (object) array(
			'name'          => 'HFO Golf Registration',
			'slug'          => self::PLUGIN_SLUG,
			'version'       => $remote_version,
			'author'        => 'HFO',
			'homepage'      => $this->get_repository_url(),
			'last_updated'  => $this->get_release_date( $release ),
			'download_link' => $this->get_download_url( $release ),
			'sections'      => array(
				'description' => __( 'HFO golf event and registration management for WordPress.', 'hfo-golf-registration' ),
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
	 * Selects the hfo-golf-registration plugin directory from GitHub archives.
	 *
	 * GitHub zipball archives unpack to repository-specific directories. This method makes sure WordPress
	 * installs the plugin from a directory named hfo-golf-registration, even when the archive top-level
	 * directory uses a generated GitHub name.
	 *
	 * @param string|WP_Error $source        The source as passed by WordPress.
	 * @param string          $remote_source Remote source directory.
	 * @param WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array           $hook_extra    Extra arguments passed to hooked filters.
	 * @return string|WP_Error
	 */
	public function filter_upgrader_source_selection( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( is_wp_error( $source ) || empty( $hook_extra['plugin'] ) || $this->get_plugin_basename() !== $hook_extra['plugin'] ) {
			return $source;
		}

		$source = trailingslashit( $source );

		if ( is_dir( $source . self::PLUGIN_SLUG ) && file_exists( $source . self::PLUGIN_SLUG . '/hfo-golf-registration.php' ) ) {
			return trailingslashit( $source . self::PLUGIN_SLUG );
		}

		if ( file_exists( $source . 'hfo-golf-registration.php' ) && self::PLUGIN_SLUG === basename( untrailingslashit( $source ) ) ) {
			return $source;
		}

		if ( file_exists( $source . 'hfo-golf-registration.php' ) ) {
			$renamed_source = trailingslashit( $remote_source ) . self::PLUGIN_SLUG;

			if ( is_dir( $renamed_source ) ) {
				$this->delete_directory( $renamed_source );
			}

			if ( @rename( untrailingslashit( $source ), $renamed_source ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Rename failures are handled below.
				return trailingslashit( $renamed_source );
			}
		}

		return $source;
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

		$cached_release = get_site_transient( 'hfo_golf_registration_github_latest_release' );

		if ( is_array( $cached_release ) ) {
			$this->latest_release = $cached_release;
			return $this->latest_release;
		}

		$release = $this->request_latest_release();

		if ( empty( $release ) ) {
			$release = $this->request_latest_tag();
		}

		if ( empty( $release ) ) {
			$this->latest_release = array();
			return $this->latest_release;
		}

		$this->latest_release = $release;
		set_site_transient( 'hfo_golf_registration_github_latest_release', $release, 15 * MINUTE_IN_SECONDS );

		return $this->latest_release;
	}


	/**
	 * Requests the latest GitHub Release.
	 *
	 * @return array<string,mixed>
	 */
	private function request_latest_release() {
		$response = wp_remote_get(
			self::LATEST_RELEASE_ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => $this->get_github_headers(),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $release ) && ! empty( $release['tag_name'] ) ? $release : array();
	}

	/**
	 * Requests the latest GitHub tag when the repository does not have releases.
	 *
	 * @return array<string,mixed>
	 */
	private function request_latest_tag() {
		$response = wp_remote_get(
			self::TAGS_ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => $this->get_github_headers(),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		$tags = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $tags ) || ! is_array( $tags ) || empty( $tags[0]['name'] ) ) {
			return array();
		}

		$tag = $tags[0];

		return array(
			'tag_name'    => (string) $tag['name'],
			'zipball_url' => ! empty( $tag['zipball_url'] ) ? (string) $tag['zipball_url'] : '',
			'body'        => __( 'No GitHub Release notes were provided for this tag.', 'hfo-golf-registration' ),
		);
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
	 * Gets the latest release publication date.
	 *
	 * @param array<string,mixed> $release GitHub release data.
	 * @return string
	 */
	private function get_release_date( $release ) {
		if ( ! empty( $release['published_at'] ) ) {
			return (string) $release['published_at'];
		}

		return ! empty( $release['created_at'] ) ? (string) $release['created_at'] : '';
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
	 * Gets the optional GitHub personal access token.
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
		return plugin_basename( HFO_GOLF_REGISTRATION_FILE );
	}

	/**
	 * Gets the repository URL.
	 *
	 * @return string
	 */
	private function get_repository_url() {
		return 'https://github.com/' . self::REPOSITORY_OWNER . '/' . self::REPOSITORY_NAME;
	}

	/**
	 * Recursively deletes a directory before retrying a GitHub archive rename.
	 *
	 * @param string $directory Directory path.
	 * @return void
	 */
	private function delete_directory( $directory ) {
		global $wp_filesystem;

		if ( $wp_filesystem && method_exists( $wp_filesystem, 'delete' ) ) {
			$wp_filesystem->delete( $directory, true );
		}
	}
}
