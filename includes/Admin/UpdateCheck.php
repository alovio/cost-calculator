<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * "Check for updates" convenience for the Plugins screen.
 *
 * Updates themselves are handled entirely by WordPress.org — this only forces
 * core's own check to run NOW (instead of waiting for the ~12h cron) by clearing
 * the update transient and re-querying. It adds no custom update source, so it
 * stays within the plugin guidelines.
 */
final class UpdateCheck {

	private const ACTION = 'alovio_calc_check_update';

	public function register(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( ALOVIO_CALC_FILE ), array( $this, 'action_link' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'check' ) );
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
	}

	/**
	 * Add the link to our row on the Plugins page.
	 *
	 * @param array<int|string,string> $links
	 * @return array<int|string,string>
	 */
	public function action_link( array $links ): array {
		$url     = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
		$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Check for updates', 'alovio-calculator' ) );
		return $links;
	}

	/** Force WordPress to re-check WordPress.org for a newer version, then report back. */
	public function check(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'alovio-calculator' ) );
		}
		check_admin_referer( self::ACTION );

		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		delete_site_transient( 'update_plugins' );
		wp_clean_plugins_cache( true );
		wp_update_plugins();

		$updates = get_site_transient( 'update_plugins' );
		$has     = isset( $updates->response[ plugin_basename( ALOVIO_CALC_FILE ) ] );

		wp_safe_redirect( add_query_arg( self::ACTION, $has ? 'available' : 'current', admin_url( 'plugins.php' ) ) );
		exit;
	}

	/** Show the outcome after a manual check. */
	public function maybe_notice(): void {
		if ( empty( $_GET[ self::ACTION ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
			return;
		}
		$state = sanitize_key( (string) wp_unslash( $_GET[ self::ACTION ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'available' === $state ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html__( 'Alovio Calculator: a new version is available — use “Update now” in the plugin row below.', 'alovio-calculator' )
			);
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			sprintf(
				/* translators: %s: current plugin version */
				esc_html__( 'Alovio Calculator is up to date (version %s).', 'alovio-calculator' ),
				esc_html( ALOVIO_CALC_VERSION )
			)
		);
	}
}
