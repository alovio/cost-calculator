<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

defined( 'ABSPATH' ) || exit;

final class AdminPage {

	public const SLUG = 'alovio-calculator';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'Alovio Calculator', 'alovio-calculator' ),
			__( 'Calculator', 'alovio-calculator' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-calculator',
			58
		);
	}

	public function render(): void {
		echo '<div id="alc-builder-root"></div>';
	}
}
