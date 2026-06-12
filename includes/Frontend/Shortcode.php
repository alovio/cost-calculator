<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Frontend;

use Alovio\Calculator\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

final class Shortcode {

	public function register(): void {
		add_shortcode( 'alovio_calculator', array( $this, 'render' ) );
	}

	/** Shared by the shortcode and the block render_callback — single rendering path (spec §9). */
	public static function render_calculator( int $id ): string {
		$post = get_post( $id );
		if ( ! $post || FieldRepository::POST_TYPE !== get_post_type( $post ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p class="alc-admin-notice">' . esc_html__( 'Alovio Calculator: calculator not found (visible to admins only).', 'alovio-calculator' ) . '</p>';
			}
			return '';
		}
		FrontendAssets::mark_needed();
		$config = ( new FieldRepository() )->get( $id );
		return CalculatorRenderer::render( $id, $config );
	}

	/** @param array|string $atts */
	public function render( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'alovio_calculator' );
		return self::render_calculator( absint( $atts['id'] ) );
	}
}
