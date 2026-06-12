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
		// Services register themselves here as later tasks add them.
	}

	public function activate(): void {
		update_option( 'alc_version', ALC_VERSION );
	}

	public function init(): void {
		load_plugin_textdomain( 'alovio-calculator' );
	}
}
