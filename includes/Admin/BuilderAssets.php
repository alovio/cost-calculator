<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

use Alovio\Calculator\Fields\FieldTypes;
use Alovio\Calculator\Templates\Presets;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the React builder bundle on the Calculator admin page and injects
 * the ALC_BUILDER global.
 */
final class BuilderAssets {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_' . AdminPage::SLUG !== $hook ) {
			return;
		}

		$asset_file = ALC_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_media(); // Option image picker (wp.media frame).
		wp_enqueue_script( 'alc-builder', ALC_URL . 'build/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'alc-builder', 'alovio-calculator', ALC_DIR . 'languages' );

		if ( file_exists( ALC_DIR . 'build/index.css' ) ) {
			wp_enqueue_style( 'alc-builder', ALC_URL . 'build/index.css', array(), $asset['version'] );
			wp_style_add_data( 'alc-builder', 'rtl', 'replace' );
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
			'alc-builder',
			'ALC_BUILDER',
			array(
				'root'        => esc_url_raw( rest_url( '/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'fieldTypes'  => FieldTypes::all(),
				'isPro'       => (bool) apply_filters( 'alc_is_pro', false ),
				'templates'   => $templates,
				'exportNonce' => wp_create_nonce( 'alc_export_entries' ),
				'adminPost'   => esc_url_raw( admin_url( 'admin-post.php' ) ),
			)
		);
	}
}
