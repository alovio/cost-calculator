<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Swaps the plugin's generic "Visit plugin site" meta link (on the Plugins
 * screen) for a branded "Upgrade to Pro" link. Hidden automatically once the
 * Pro add-on flips the alovio_calc_is_pro filter.
 */
final class ProLink {

	private const URL = 'https://alovio.org/calculator';

	public function register(): void {
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/**
	 * @param array<int,string> $links Row meta links.
	 * @param string            $file  Plugin file the row belongs to.
	 * @return array<int,string>
	 */
	public function row_meta( array $links, string $file ): array {
		if ( plugin_basename( ALOVIO_CALC_FILE ) !== $file || apply_filters( 'alovio_calc_is_pro', false ) ) {
			return $links;
		}

		// Drop core's "Visit plugin site" entry (it is built from the Plugin URI).
		$links = array_values(
			array_filter(
				$links,
				static function ( $link ) {
					return false === strpos( (string) $link, self::URL );
				}
			)
		);

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#f97316;font-weight:600;">%s</a>',
			esc_url( self::URL . '#pro' ),
			esc_html__( 'Upgrade to Pro', 'alovio-calculator' )
		);

		return $links;
	}
}
