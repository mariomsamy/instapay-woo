<?php
/**
 * Offers new GitHub releases of this plugin through the normal WordPress update screens.
 *
 * Works with the "Update URI: https://github.com/mariomsamy/instapay-woo" header: WordPress
 * then asks the `update_plugins_github.com` filter (WordPress 5.8+) instead of WordPress.org,
 * so a WordPress.org plugin with the same folder name can never replace this one.
 *
 * - The latest release is fetched at most every 12 hours (1 hour after a failure).
 * - The package must be an https URL on github.com.
 * - The package is the release's `instapay*.zip` asset (e.g. instapay-gateway-for-egypt-1.3.0.zip),
 *   and the release must also publish `<asset>.sha256`; the download is refused if it does not match.
 *   Releases without a checksum are not offered.
 *
 * @package Instapay_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Instapay_Woo_GitHub_Updater {

	const REPOSITORY = 'mariomsamy/instapay-woo';
	const CACHE_KEY  = 'instapay_woo_github_release';

	/** @var string Plugin basename, e.g. instapay-woo/instapay-woo.php. */
	private $basename;

	/** @var string Installed version. */
	private $version;

	public function __construct( $plugin_file, $version ) {
		$this->basename = plugin_basename( $plugin_file );
		$this->version  = $version;
	}

	public function register() {
		add_filter( 'update_plugins_github.com', array( $this, 'check_update' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'verified_download' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_folder' ), 10, 4 );
		add_action( 'load-update-core.php', array( $this, 'clear_cache_on_force_check' ) );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );
	}

	private function slug() {
		return dirname( $this->basename );
	}

	/**
	 * Latest release, normalized and cached.
	 *
	 * @return array|null { version, package, sha256, url, notes, published }
	 */
	private function latest_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return empty( $cached['version'] ) ? null : $cached;
		}

		$release = $this->fetch_release();
		// Cache failures too, so an outage or rate limit never causes a request per page load.
		set_site_transient( self::CACHE_KEY, $release ? $release : array(), $release ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );

		return $release;
	}

	private function fetch_release() {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			instapay_woo_log( 'warning', 'GitHub release check failed.', array( 'code' => is_wp_error( $response ) ? $response->get_error_code() : wp_remote_retrieve_response_code( $response ) ) );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}

		$version = ltrim( (string) $data['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+\.\d+(?:\.\d+)?$/', $version ) ) {
			return null;
		}

		$assets = array();
		foreach ( isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : array() as $asset ) {
			if ( isset( $asset['name'], $asset['browser_download_url'] ) ) {
				$assets[ (string) $asset['name'] ] = (string) $asset['browser_download_url'];
			}
		}

		$package  = '';
		$checksum = '';
		foreach ( $assets as $name => $url ) {
			if ( preg_match( '/^instapay[\w.\-]*\.zip$/i', $name ) ) {
				$package  = $url;
				$checksum = isset( $assets[ $name . '.sha256' ] ) ? $assets[ $name . '.sha256' ] : '';
				break;
			}
		}
		// Only built release packages with a published checksum are offered. Right after a release
		// is published the workflow is still attaching them; the short failure cache retries soon.
		if ( ! $this->is_github_url( $package ) || ! $this->is_github_url( $checksum ) ) {
			return null;
		}

		$sum = wp_remote_get( $checksum, array( 'timeout' => 10 ) );
		if ( is_wp_error( $sum ) || 200 !== (int) wp_remote_retrieve_response_code( $sum ) || ! preg_match( '/\b([a-f0-9]{64})\b/i', wp_remote_retrieve_body( $sum ), $match ) ) {
			return null;
		}
		$sha256 = strtolower( $match[1] );

		return array(
			'version'   => $version,
			'package'   => $package,
			'sha256'    => $sha256,
			'url'       => isset( $data['html_url'] ) && $this->is_github_url( $data['html_url'] ) ? (string) $data['html_url'] : 'https://github.com/' . self::REPOSITORY . '/releases',
			'notes'     => isset( $data['body'] ) ? (string) $data['body'] : '',
			'published' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
		);
	}

	private function is_github_url( $url ) {
		$parts = wp_parse_url( (string) $url );

		return ! empty( $parts['scheme'] ) && 'https' === $parts['scheme']
			&& ! empty( $parts['host'] ) && in_array( strtolower( $parts['host'] ), array( 'github.com', 'api.github.com' ), true );
	}

	/**
	 * `update_plugins_github.com`: tell WordPress whether a newer release exists.
	 */
	public function check_update( $update, $plugin_data, $plugin_file ) {
		if ( $plugin_file !== $this->basename ) {
			return $update;
		}

		$release = $this->latest_release();
		if ( ! $release ) {
			return $update;
		}

		return array(
			'id'           => 'https://github.com/' . self::REPOSITORY,
			'slug'         => $this->slug(),
			'plugin'       => $this->basename,
			'version'      => $release['version'],
			'new_version'  => $release['version'],
			'url'          => $release['url'],
			'package'      => version_compare( $release['version'], $this->version, '>' ) ? $release['package'] : '',
			'requires'     => '6.3',
			'requires_php' => '7.4',
			'icons'        => array( 'default' => INSTAPAY_WOO_PLUGIN_URL . 'img/InstaPay.webp' ),
		);
	}

	/**
	 * "View details" pop-up on the Plugins and Updates screens.
	 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug() ) {
			return $result;
		}

		$release = $this->latest_release();
		if ( ! $release ) {
			return $result;
		}

		$notes = '' !== trim( $release['notes'] ) ? '<pre style="white-space:pre-wrap">' . esc_html( $release['notes'] ) . '</pre>' : '';

		return (object) array(
			'name'          => 'Instapay Gateway for Egypt',
			'slug'          => $this->slug(),
			'version'       => $release['version'],
			'author'        => '<a href="https://recipe.codes">Recipe Codes</a>',
			'homepage'      => 'https://github.com/' . self::REPOSITORY,
			'requires'      => '6.3',
			'requires_php'  => '7.4',
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => esc_html__( 'Instapay payment gateway for WooCommerce with secure receipt upload and manager verification.', 'instapay-gateway-for-egypt' ),
				'changelog'   => $notes . '<p><a href="' . esc_url( $release['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Release notes on GitHub', 'instapay-gateway-for-egypt' ) . '</a></p>',
			),
		);
	}

	/**
	 * Download the package ourselves and refuse it unless it matches the published checksum.
	 */
	public function verified_download( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( false !== $reply || empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $reply;
		}

		$release = $this->latest_release();
		if ( ! $release || $package !== $release['package'] || '' === $release['sha256'] ) {
			return new WP_Error( 'instapay_unverified_package', __( 'The update package could not be verified and was not installed.', 'instapay-gateway-for-egypt' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = download_url( $package, 300 );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		if ( ! hash_equals( $release['sha256'], strtolower( (string) hash_file( 'sha256', $file ) ) ) ) {
			wp_delete_file( $file );
			instapay_woo_log( 'error', 'GitHub update package failed checksum verification.', array( 'version' => $release['version'] ) );
			return new WP_Error( 'instapay_checksum_mismatch', __( 'The downloaded update did not match its published checksum and was not installed.', 'instapay-gateway-for-egypt' ) );
		}

		return $file;
	}

	/**
	 * GitHub archives unpack to a folder like "mariomsamy-instapay-woo-1a2b3c" (or to no folder
	 * at all); move the files into the folder this plugin is installed in, so it stays active.
	 */
	public function fix_source_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( is_wp_error( $source ) || empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename || ! $wp_filesystem ) {
			return $source;
		}

		$source  = trailingslashit( $source );
		$desired = trailingslashit( $remote_source ) . $this->slug() . '/';
		if ( $source === $desired ) {
			return $source;
		}

		if ( ! $wp_filesystem->exists( $source . basename( $this->basename ) ) ) {
			return new WP_Error( 'instapay_bad_package', __( 'The update package does not contain the Instapay plugin.', 'instapay-gateway-for-egypt' ) );
		}

		if ( trailingslashit( $remote_source ) === $source ) {
			// Files sit at the archive root: gather them into the plugin folder.
			$wp_filesystem->mkdir( $desired );
			foreach ( array_keys( (array) $wp_filesystem->dirlist( $source ) ) as $name ) {
				if ( $this->slug() !== $name && ! $wp_filesystem->move( $source . $name, $desired . $name ) ) {
					return new WP_Error( 'instapay_move_failed', __( 'Could not prepare the update package.', 'instapay-gateway-for-egypt' ) );
				}
			}
			return $desired;
		}

		if ( ! $wp_filesystem->move( $source, $desired ) ) {
			return new WP_Error( 'instapay_move_failed', __( 'Could not prepare the update package.', 'instapay-gateway-for-egypt' ) );
		}

		return $desired;
	}

	public function clear_cache_on_force_check() {
		if ( isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			delete_site_transient( self::CACHE_KEY );
		}
	}

	public function clear_cache_after_update( $upgrader, $options ) {
		if ( isset( $options['type'], $options['plugins'] ) && 'plugin' === $options['type'] && in_array( $this->basename, (array) $options['plugins'], true ) ) {
			delete_site_transient( self::CACHE_KEY );
		}
	}
}
