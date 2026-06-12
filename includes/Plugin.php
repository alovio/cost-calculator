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
		register_activation_hook( ALC_FILE, [ $this, 'activate' ] );
		add_action( 'init', [ $this, 'init' ] );
		add_action(
			'wp_initialize_site',
			static function ( $new_site ) {
				if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				if ( is_plugin_active_for_network( plugin_basename( ALC_FILE ) ) ) {
					switch_to_blog( (int) $new_site->blog_id );
					Entries\EntriesTable::install();
					restore_current_blog();
				}
			},
			10,
			1
		);
		( new Admin\RestController() )->register();
		( new Entries\QuoteController() )->register();
		( new Entries\EntriesRestController() )->register();
		( new Entries\CsvExporter() )->register();
		( new Entries\Privacy() )->register();
		( new Admin\AdminPage() )->register();
		( new Admin\BuilderAssets() )->register();
		// Services register themselves here as later tasks add them.
	}

	public function activate( bool $network_wide = false ): void {
		Entries\EntriesTable::install_for_network( $network_wide );
		update_option( 'alc_version', ALC_VERSION );
	}

	public function init(): void {
		load_plugin_textdomain( 'alovio-calculator' );
		Fields\FieldRepository::register_post_type();
	}
}
