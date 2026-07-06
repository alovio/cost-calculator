<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

use Alovio\Calculator\Fields\FieldTypes;
use Alovio\Calculator\Templates\Presets;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the React builder bundle on the Calculator admin page and injects
 * the ALOVIO_CALC_BUILDER global.
 */
final class BuilderAssets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
	}

	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . AdminPage::SLUG !== $hook ) {
			return;
		}

		$asset_file = ALOVIO_CALC_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_media(); // Option image picker (wp.media frame).
		wp_enqueue_script( 'alovio-calc-builder', ALOVIO_CALC_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'alovio-calc-builder', 'alovio-calculator', ALOVIO_CALC_DIR . 'languages' );

		if ( file_exists( ALOVIO_CALC_DIR . 'build/index.css' ) ) {
			wp_enqueue_style( 'alovio-calc-builder', ALOVIO_CALC_URL . 'build/index.css', array(), $asset['version'] );
			wp_style_add_data( 'alovio-calc-builder', 'rtl', 'replace' );
		}

		$presets   = Presets::all();
		$templates = array();
		foreach ( $presets as $key => $preset ) {
			$templates[] = array(
				'key'         => $key,
				'title'       => $preset['title'],
				'description' => $preset['description'],
			);
		}

		wp_localize_script(
			'alovio-calc-builder',
			'ALOVIO_CALC_BUILDER',
			array(
				'root'        => esc_url_raw( rest_url( '/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'fieldTypes'  => FieldTypes::all(),
				'isPro'       => (bool) apply_filters( 'alovio_calc_is_pro', false ),
				'templates'   => $templates,
				'exportNonce' => wp_create_nonce( 'alovio_calc_export_entries' ),
				'adminPost'   => esc_url_raw( admin_url( 'admin-post.php' ) ),
			)
		);
	}

	/**
	 * Marks the builder screen so builder.scss can go full-bleed (spec §2.1).
	 */
	public function body_class( string $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'toplevel_page_' . AdminPage::SLUG === $screen->id ) {
			$classes .= ' alcb-builder-page';
		}
		return $classes;
	}
}
