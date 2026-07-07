<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Analytics;

use Alovio\Calculator\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Anonymous funnel beacon (spec §5.3), free-side support for Pro analytics.
 *
 * GDPR by construction: no cookies, no PII, no user agents; REMOTE_ADDR is
 * only md5'd into a transient key for rate limiting and expires within a
 * minute — never stored. Counts are approximate under full-page caching
 * (documented in readme). Buckets older than 90 days are pruned during the
 * increment write — no cron, storage stays bounded.
 */
final class Counter {

	public const META_VIEWS        = '_alovio_calc_views';
	public const META_INTERACTIONS = '_alovio_calc_interactions';
	private const EVENTS           = array( 'view', 'interact' );
	private const RATE_LIMIT       = 20; // events / minute / IP — several calculators per page still fit
	private const RETENTION_DAYS   = 90;

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'alovio-calc/v1',
			'/track',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public + anonymous by design (see class docblock; same cache-safe reasoning as /quote).
				'callback'            => array( $this, 'handle' ),
			)
		);
	}

	/** @param \WP_REST_Request $request */
	public function handle( $request ) {
		if ( ! $this->within_rate_limit() ) {
			return new \WP_REST_Response( array( 'ok' => false ), 429 );
		}
		$calc  = absint( $request->get_param( 'calc' ) );
		$event = (string) $request->get_param( 'event' );
		if ( ! in_array( $event, self::EVENTS, true ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}
		if ( $calc <= 0 || FieldRepository::POST_TYPE !== get_post_type( $calc ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}
		$this->record( $calc, $event );
		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Increment today's bucket, prune old ones in the same write.
	 *
	 * @param string|null $today Y-m-d override for tests (defaults to gmdate).
	 */
	public function record( int $calc_id, string $event, ?string $today = null ): void {
		$meta_key = 'view' === $event ? self::META_VIEWS : self::META_INTERACTIONS;
		$today    = null !== $today ? $today : gmdate( 'Y-m-d' );

		$buckets = get_post_meta( $calc_id, $meta_key, true );
		$buckets = is_array( $buckets ) ? $buckets : array();

		$buckets[ $today ] = (int) ( $buckets[ $today ] ?? 0 ) + 1;
		$buckets           = self::prune( $buckets, $today );

		update_post_meta( $calc_id, $meta_key, $buckets );
		do_action( 'alovio_calc_event_recorded', $calc_id, $event, $today );
	}

	/**
	 * Pure: drop buckets older than RETENTION_DAYS relative to $today.
	 * Y-m-d strings compare correctly lexicographically. No WP constants —
	 * unit-testable without the WP runtime.
	 *
	 * @param array<string,int> $buckets
	 * @return array<string,int>
	 */
	public static function prune( array $buckets, string $today ): array {
		$cutoff = gmdate( 'Y-m-d', (int) strtotime( $today . ' UTC -' . self::RETENTION_DAYS . ' days' ) );
		foreach ( array_keys( $buckets ) as $day ) {
			if ( ! is_string( $day ) || $day < $cutoff ) {
				unset( $buckets[ $day ] );
			}
		}
		return $buckets;
	}

	private function within_rate_limit(): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // REMOTE_ADDR only — X-Forwarded-For is spoofable (matches QuoteController).
		// The rl_ prefix keeps these inside uninstall.php's existing transient sweep (LIKE '%alovio_calc_rl_%').
		$key   = 'alovio_calc_rl_trk_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
