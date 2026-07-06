<?php
namespace Alovio\Calculator\Tests\Unit\Import;

use Alovio\Calculator\Import\CcbDetector;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class CcbDetectorTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] ); // don't leak the fake wpdb into other tests
		parent::tearDown();
	}

	private function wpdb_returning( $var ): object {
		return new class( $var ) {
			public $posts = 'wp_posts';
			private $var;
			public function __construct( $var ) { $this->var = $var; }
			public function prepare( $sql, ...$args ) { return $sql; }
			public function get_var( $sql ) { return $this->var; }
		};
	}

	public function test_present_when_plugin_active(): void {
		Functions\when( 'is_plugin_active' )->justReturn( true );
		$GLOBALS['wpdb'] = $this->wpdb_returning( null );
		$this->assertTrue( ( new CcbDetector() )->is_present() );
	}

	public function test_present_when_inactive_but_storage_found(): void {
		Functions\when( 'is_plugin_active' )->justReturn( false );
		$GLOBALS['wpdb'] = $this->wpdb_returning( '42' ); // one stored CCB calculator
		$this->assertTrue( ( new CcbDetector() )->is_present() );
	}

	public function test_absent_when_no_plugin_and_no_storage(): void {
		Functions\when( 'is_plugin_active' )->justReturn( false );
		$GLOBALS['wpdb'] = $this->wpdb_returning( null );
		$this->assertFalse( ( new CcbDetector() )->is_present() );
	}
}
