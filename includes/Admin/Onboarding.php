<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Light onboarding (spec §4.2, guideline-safe): NO activation redirect. Two
 * one-time dismissible notices — a post-activation welcome (fresh installs)
 * and a "What's new in 2.0" for updaters. Opening the builder clears both.
 */
final class Onboarding {

	public const OPTION_WELCOME  = 'alovio_calc_welcome_notice';
	public const OPTION_WHATSNEW = 'alovio_calc_whatsnew_notice';
	private const DISMISS_ACTION = 'alovio_calc_dismiss_onboarding';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'detect_update' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'dismiss' ) );
	}

	/** Updates never fire activation hooks — catch the version change here. */
	public function detect_update(): void {
		$stored = (string) get_option( 'alovio_calc_version', '' );
		if ( '' === $stored || ALOVIO_CALC_VERSION === $stored ) {
			return; // fresh installs are handled by the activation hook
		}
		if ( self::should_flag_whatsnew( $stored, ALOVIO_CALC_VERSION ) ) {
			update_option( self::OPTION_WHATSNEW, 1 );
		}
		update_option( 'alovio_calc_version', ALOVIO_CALC_VERSION );
	}

	/** Pure: the what's-new notice fires only when crossing the 2.0.0 line. */
	public static function should_flag_whatsnew( string $from, string $to ): bool {
		return version_compare( $from, '2.0.0', '<' ) && version_compare( $to, '2.0.0', '>=' );
	}

	/** Pure: which notice (if any) to show; a fresh install outranks an update. */
	public static function notice_to_show( bool $welcome_flag, bool $whatsnew_flag ): ?string {
		if ( $welcome_flag ) {
			return 'welcome';
		}
		if ( $whatsnew_flag ) {
			return 'whatsnew';
		}
		return null;
	}

	public function maybe_render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'toplevel_page_' . AdminPage::SLUG === $screen->id ) {
			// They reached the builder — the notices did their job.
			delete_option( self::OPTION_WELCOME );
			delete_option( self::OPTION_WHATSNEW );
			return;
		}
		$which = self::notice_to_show( (bool) get_option( self::OPTION_WELCOME ), (bool) get_option( self::OPTION_WHATSNEW ) );
		if ( null === $which ) {
			return;
		}
		$builder_url = admin_url( 'admin.php?page=' . AdminPage::SLUG );
		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION . '&which=' . $which ), self::DISMISS_ACTION );
		$message     = 'welcome' === $which
			? __( 'Alovio Calculator is ready. Start from a template — you can have a working price calculator with a quote form in about ten minutes.', 'alovio-calculator' )
			: __( 'Alovio Calculator 2.0 is here: the new Builder Studio, the free repeater field and 18 field types. Your existing calculators work unchanged.', 'alovio-calculator' );
		$cta         = 'welcome' === $which
			? __( 'Create your first calculator', 'alovio-calculator' )
			: __( 'Open the new Studio', 'alovio-calculator' );
		printf(
			'<div class="notice notice-info"><p>%s</p><p><a class="button button-primary" href="%s">%s</a> <a href="%s">%s</a></p></div>',
			esc_html( $message ),
			esc_url( $builder_url ),
			esc_html( $cta ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'alovio-calculator' )
		);
	}

	public function dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'alovio-calculator' ) );
		}
		check_admin_referer( self::DISMISS_ACTION );
		$which = isset( $_GET['which'] ) ? sanitize_key( (string) wp_unslash( $_GET['which'] ) ) : '';
		delete_option( 'welcome' === $which ? self::OPTION_WELCOME : self::OPTION_WHATSNEW );
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}
}
