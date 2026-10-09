<?php
declare( strict_types=1 );

namespace Alovio\Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * Self-hosted updates.
 *
 * The plugin header carries an `Update URI` pointing at our own server, which
 * is WordPress's supported way of saying "do not ask the plugin directory about
 * this one". WordPress then fires `update_plugins_<host>` and takes the answer
 * from here.
 */
final class Updater {

	/**
	 * The `Update URI` host. The filter name is built from it, so if one is ever
	 * changed the other has to change with it or updates go quiet.
	 */
	private const HOST = 'licenses.alovio.org';

	private const ENDPOINT = 'https://licenses.alovio.org/';

	/** What the release server knows this plugin as. */
	private const PRODUCT = 'alovio-calculator';

	private const CACHE_KEY = 'alovio_calc_update_check';

	/**
	 * Long enough that a run of update checks costs one request, short enough
	 * that a release is never held back for a whole day.
	 */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public function register(): void {
		// An opt-out counts for more than the statistics do. A site that would
		// rather tell us nothing defines this and still updates by hand from
		// alovio.org; nothing else about the plugin changes.
		if ( defined( 'ALOVIO_NO_UPDATE_CHECK' ) && ALOVIO_NO_UPDATE_CHECK ) {
			return;
		}

		add_filter( 'update_plugins_' . self::HOST, array( $this, 'offer' ), 10, 3 );
	}

	/**
	 * Answer for this plugin, and only this plugin.
	 *
	 * The filter is keyed by host, not by plugin, so every Alovio plugin on the
	 * site is offered this same hook. Anything that is not us is handed straight
	 * back — otherwise whichever plugin registered first would answer for all of
	 * them and offer its own zip as their update.
	 *
	 * @param array<string,mixed>|false $update      The offer made so far.
	 * @param array<string,string>      $plugin_data The plugin's own headers.
	 * @param string                    $plugin_file Plugin basename, e.g. `foo/foo.php`.
	 * @return array<string,mixed>|false
	 */
	public function offer( $update, array $plugin_data, string $plugin_file ) {
		if ( plugin_basename( ALOVIO_CALC_FILE ) !== $plugin_file ) {
			return $update;
		}

		$latest = $this->latest();

		if ( null === $latest || version_compare( $latest['version'], ALOVIO_CALC_VERSION, '<=' ) ) {
			return $update;
		}

		return array(
			'id'           => self::HOST . '/' . self::PRODUCT,
			'slug'         => self::PRODUCT,
			'plugin'       => $plugin_file,
			'version'      => $latest['version'],
			'url'          => $plugin_data['PluginURI'] ?? '',
			'package'      => $latest['package'],
			'requires'     => $latest['requires'],
			'requires_php' => $latest['requires_php'],
		);
	}

	/**
	 * The newest published release, or null if we could not find out.
	 *
	 * Not knowing is not the same as there being nothing, so every failure path
	 * returns null and the caller leaves the existing offer alone. A server that
	 * is unreachable must never read as "you are up to date", and must never
	 * break the Plugins screen either.
	 *
	 * @return array{version:string,package:string,requires:string,requires_php:string}|null
	 */
	private function latest(): ?array {
		$cached = get_site_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'action'           => 'update-check',
						'product'          => self::PRODUCT,
						'domain'           => $this->domain(),
						'installedVersion' => ALOVIO_CALC_VERSION,
					)
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['latestVersion'] ) || empty( $body['package'] ) ) {
			return null;
		}

		$latest = array(
			'version'      => (string) $body['latestVersion'],
			'package'      => (string) $body['package'],
			'requires'     => (string) ( $body['requires'] ?? '' ),
			'requires_php' => (string) ( $body['requiresPhp'] ?? '' ),
		);

		set_site_transient( self::CACHE_KEY, $latest, self::CACHE_TTL );

		return $latest;
	}

	/** The host the server records the download against. */
	private function domain(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return is_string( $host ) && '' !== $host ? strtolower( $host ) : 'unknown';
	}
}
