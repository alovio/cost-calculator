<?php
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' ); // Satisfies the guard in class files; WP itself is never loaded in unit tests.
}
