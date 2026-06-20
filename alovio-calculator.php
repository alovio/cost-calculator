<?php
/**
 * Plugin Name: Alovio Calculator – Cost, Price & Quote Calculator Builder
 * Plugin URI: https://alovio.org/calculator
 * Description: Build cost, price and quote calculators with live totals, free conditional logic, and lead capture.
 * Version: 1.2.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Alovio
 * Author URI: https://alovio.org
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: alovio-calculator
 */

defined( 'ABSPATH' ) || exit;

define( 'ALOVIO_CALC_VERSION', '1.2.0' );
define( 'ALOVIO_CALC_FILE', __FILE__ );
define( 'ALOVIO_CALC_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALOVIO_CALC_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'Alovio\\Calculator\\' ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( 'Alovio\\Calculator\\' ) );
		$path     = ALOVIO_CALC_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

Alovio\Calculator\Plugin::instance()->boot();
