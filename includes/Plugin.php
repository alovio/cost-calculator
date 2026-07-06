<?php
namespace Alovio\Calculator;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		register_activation_hook( ALOVIO_CALC_FILE, [ $this, 'activate' ] );
		add_action( 'init', [ $this, 'init' ] );
		add_action(
			'wp_initialize_site',
			static function ( $new_site ) {
				if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				if ( is_plugin_active_for_network( plugin_basename( ALOVIO_CALC_FILE ) ) ) {
					switch_to_blog( (int) $new_site->blog_id );
					Entries\EntriesTable::install();
					restore_current_blog();
				}
			},
			10,
			1
		);
		( new Admin\RestController() )->register();
		( new Admin\Preview() )->register();
		( new Entries\QuoteController() )->register();
		( new Entries\FileUploads() )->register();
		( new Entries\EntriesRestController() )->register();
		( new Entries\CsvExporter() )->register();
		( new Entries\Privacy() )->register();
		( new Admin\AdminPage() )->register();
		( new Admin\BuilderAssets() )->register();
		( new Frontend\Shortcode() )->register();
		( new Frontend\FrontendAssets() )->register();
		( new Admin\ReviewNudge() )->register();
		( new Admin\UpdateCheck() )->register();
		( new Admin\ProLink() )->register();
		Pro\ProModule::register();
	}

	public function activate( bool $network_wide = false ): void {
		Entries\EntriesTable::install_for_network( $network_wide );
		update_option( 'alovio_calc_version', ALOVIO_CALC_VERSION );
	}

	public function init(): void {
		// No load_plugin_textdomain(): WordPress.org auto-loads translations since 4.6 (we require 6.2+).
		Fields\FieldRepository::register_post_type();
		if ( file_exists( ALOVIO_CALC_DIR . 'build/block/block.json' ) ) {
			register_block_type(
				ALOVIO_CALC_DIR . 'build/block',
				array(
					'render_callback' => static fn( $attributes ) => Frontend\Shortcode::render_calculator( absint( $attributes['calculatorId'] ?? 0 ) ),
				)
			);
		}
	}
}
