<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Detects whether Cost Calculator Builder data is present on this site —
 * either their plugin is active or their stored calculators exist (a site
 * that deactivated CCB can still import).
 *
 * Storage facts are pinned by tests/fixtures/ccb/README.md (Task 8.1); the
 * two constants below are the ONLY place they touch this class.
 */
final class CcbDetector {

	/** CCB's calculator post type (verified against the recorded fixtures). */
	public const POST_TYPE = 'cost-calc';

	/** CCB's plugin basename, for the is-active check. */
	public const PLUGIN = 'cost-calculator-builder/cost-calculator-builder.php';

	public function is_present(): bool {
		return $this->is_plugin_active() || $this->has_stored_calculators();
	}

	public function is_plugin_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::PLUGIN );
	}

	/** Direct query: their post type is unregistered while CCB is inactive, so WP_Query 'any' is unreliable. */
	public function has_stored_calculators(): bool {
		global $wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb.
				self::POST_TYPE
			)
		);
		return null !== $found && '' !== (string) $found;
	}
}
