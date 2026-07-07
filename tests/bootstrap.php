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

if ( ! class_exists( 'WP_REST_Response' ) ) {
	// Minimal stub — enough for callback-level endpoint tests (data + status only).
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function get_status() {
			return $this->status;
		}
		public function get_data() {
			return $this->data;
		}
	}
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
