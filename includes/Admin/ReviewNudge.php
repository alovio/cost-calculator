<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Review nudge (spec §10): shown on OUR admin page only, while the site has
 * collected ≥ 3 quote entries and the notice was never dismissed. Dismissing
 * is permanent. Not an upsell — Guideline 11 untouched.
 */
final class ReviewNudge {

	private const THRESHOLD = 3;

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'admin_post_alc_dismiss_review', array( $this, 'dismiss' ) );
	}

	public function maybe_render(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'toplevel_page_' . AdminPage::SLUG !== $screen->id ) {
			return;
		}
		if ( get_option( 'alc_review_dismissed' ) || (int) get_option( 'alc_entry_count', 0 ) < self::THRESHOLD ) {
			return;
		}
		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=alc_dismiss_review' ), 'alc_dismiss_review' );
		printf(
			'<div class="notice notice-info"><p>%s</p><p><a class="button button-primary" href="%s" target="_blank" rel="noopener noreferrer">%s</a> <a href="%s">%s</a></p></div>',
			esc_html__( 'Your calculators have collected 3 quote requests 🎉 If Alovio Calculator is working for you, a review on WordPress.org helps a lot.', 'alovio-calculator' ),
			'https://wordpress.org/support/plugin/alovio-calculator/reviews/#new-post',
			esc_html__( 'Leave a review', 'alovio-calculator' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss forever', 'alovio-calculator' )
		);
	}

	public function dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'alovio-calculator' ) );
		}
		check_admin_referer( 'alc_dismiss_review' );
		update_option( 'alc_review_dismissed', 1 );
		wp_safe_redirect( admin_url( 'admin.php?page=' . AdminPage::SLUG ) );
		exit;
	}
}
