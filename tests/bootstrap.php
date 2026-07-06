<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' ); // Satisfies the guard in class files; WP itself is never loaded in unit tests.
}

if ( ! class_exists( 'WP_Post' ) ) {
	// Minimal stub so classes type-hinting \WP_Post are unit-testable without loading WP.
	class WP_Post {
		public $ID                = 0;
		public $post_title        = '';
		public $post_modified_gmt = '';
	}
}
