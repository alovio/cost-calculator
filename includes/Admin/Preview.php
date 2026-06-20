<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Frontend\CalculatorRenderer;
use Alovio\Calculator\Frontend\FrontendAssets;

defined( 'ABSPATH' ) || exit;

/**
 * Live builder preview. The builder POSTs the unsaved config to /preview, which
 * normalizes it into a per-user transient and returns a URL; the builder loads
 * that URL in an iframe. The URL renders a minimal, noindex, capability-gated
 * front-end page with the calculator + the real front-end bundle, so totals,
 * conditional logic, themes and the wizard all behave exactly as on the site.
 */
final class Preview {

	private const QUERY = 'alovio_calc_preview';
	private const NONCE = 'alovio_calc_preview';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'route' ) );
		add_action( 'template_redirect', array( $this, 'render_preview' ), 0 );
	}

	public function route(): void {
		register_rest_route(
			'alovio-calc/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'store' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/** @param \WP_REST_Request $request */
	public function store( $request ) {
		$config = FieldSchema::normalize(
			array(
				'fields'   => (array) $request->get_param( 'fields' ),
				'settings' => (array) $request->get_param( 'settings' ),
			)
		);
		$uid    = get_current_user_id();
		set_transient( 'alovio_calc_preview_' . $uid, $config, HOUR_IN_SECONDS );

		return rest_ensure_response(
			array(
				'url' => add_query_arg(
					array(
						self::QUERY => $uid,
						'_wpnonce'  => wp_create_nonce( self::NONCE ),
					),
					home_url( '/' )
				),
			)
		);
	}

	public function render_preview(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		$uid = isset( $_GET[ self::QUERY ] ) ? absint( $_GET[ self::QUERY ] ) : 0;
		if ( ! $uid ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! current_user_can( 'manage_options' ) || get_current_user_id() !== $uid || ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_die( esc_html__( 'Preview is not available.', 'alovio-calculator' ), '', array( 'response' => 403 ) );
		}

		$config = get_transient( 'alovio_calc_preview_' . $uid );
		if ( ! is_array( $config ) ) {
			wp_die( esc_html__( 'Nothing to preview yet — edit the calculator first.', 'alovio-calculator' ), '', array( 'response' => 404 ) );
		}

		// Register + enqueue only our front-end bundle, then print just those handles:
		// a clean, theme-free preview page with no wp_head() bloat from the theme or other plugins.
		( new FrontendAssets() )->register_assets();
		wp_enqueue_style( 'alovio-calc-frontend' );
		wp_enqueue_script( 'alovio-calc-frontend' );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="utf-8">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_language_attributes is safe.
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<meta name="robots" content="noindex">';
		wp_print_styles( 'alovio-calc-frontend' );
		echo '<style>body{margin:0;padding:24px;background:#fff;color:#1e1e1e;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}</style>';
		echo '</head><body>';
		echo CalculatorRenderer::render( 0, $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes internally.
		wp_print_scripts( 'alovio-calc-frontend' );
		echo '</body></html>';
		exit;
	}
}
