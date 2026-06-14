<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Frontend;

defined( 'ABSPATH' ) || exit;

/** Registers the front-end bundle; enqueues only when a calculator renders (spec §8). */
final class FrontendAssets {

	/** @var bool */
	private static $registered = false;

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets(): void {
		$asset_file = ALOVIO_CALC_DIR . 'build/frontend.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_register_script( 'alovio-calc-frontend', ALOVIO_CALC_URL . 'build/frontend.js', $asset['dependencies'], $asset['version'], true );
		if ( file_exists( ALOVIO_CALC_DIR . 'build/frontend.css' ) ) {
			wp_register_style( 'alovio-calc-frontend', ALOVIO_CALC_URL . 'build/frontend.css', array(), $asset['version'] );
			wp_style_add_data( 'alovio-calc-frontend', 'rtl', 'replace' );
		}
		self::$registered = true;
	}

	/** Called by the renderer entry points; safe before or after wp_enqueue_scripts fired. */
	public static function mark_needed(): void {
		if ( ! self::$registered && ! did_action( 'wp_enqueue_scripts' ) ) {
			// Too early (e.g. REST/admin render) — registration will run and late enqueue happens below.
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'mark_needed' ), 20 );
			return;
		}
		wp_enqueue_script( 'alovio-calc-frontend' );
		wp_enqueue_style( 'alovio-calc-frontend' );
	}
}
