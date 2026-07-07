<?php
namespace Alovio\Calculator\Tests\Unit\Import;

use Alovio\Calculator\Import\ImportController;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class ImportControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// FieldSchema::normalize runs inside FieldRepository::save.
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->alias( static fn( $c ) => preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : '' );
		Functions\when( 'sanitize_email' )->alias( static fn( $e ) => filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : '' );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'rest_ensure_response' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'is_wp_error' )->alias( static fn( $v ) => $v instanceof \stdClass && isset( $v->wp_error ) );
		Functions\when( 'get_post_field' )->justReturn( 'CCB Basic' );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] ); // don't leak the fake wpdb into other tests
		parent::tearDown();
	}

	/** One stored CCB calc (id 5): a quantity field. Raw format per fixtures. */
	private function stub_ccb_storage(): void {
		$GLOBALS['wpdb'] = new class() {
			public $posts = 'wp_posts';
			public function prepare( $sql, ...$args ) { return $sql; }
			public function get_var( $sql ) { return '5'; }
			public function get_results( $sql ) { return array( (object) array( 'ID' => 5, 'post_title' => 'CCB Basic' ) ); }
		};
		Functions\when( 'get_post_meta' )->alias( static function ( $id ) {
			return 5 === (int) $id ? array( array( 'alias' => 'quantity_field_id_0', 'label' => 'Windows', 'defaultValue' => 2 ) ) : '';
		} );
	}

	private function request( array $params ): object {
		return new class( $params ) {
			private $p;
			public function __construct( $p ) { $this->p = $p; }
			public function get_param( $k ) { return $this->p[ $k ] ?? null; }
		};
	}

	public function test_import_maps_then_creates_via_repository(): void {
		$this->stub_ccb_storage();
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 77 );
		Functions\expect( 'update_post_meta' )->once()->with( 77, '_alovio_calc_config', \Mockery::type( 'string' ) );
		$res = ( new ImportController() )->import( $this->request( array( 'ids' => array( 5 ) ) ) );
		$this->assertSame( 77, $res['results'][0]['created'] );
		$this->assertSame( 5, $res['results'][0]['ccbId'] );
		$this->assertSame( array(), $res['results'][0]['skipped'] );
	}

	public function test_unreadable_calculator_is_isolated_others_still_import(): void {
		$this->stub_ccb_storage(); // id 9 → get_post_meta returns '' → reader returns null
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 78 );
		Functions\expect( 'update_post_meta' )->once();
		$res = ( new ImportController() )->import( $this->request( array( 'ids' => array( 9, 5 ) ) ) );
		$this->assertNull( $res['results'][0]['created'] );
		$this->assertNotSame( '', $res['results'][0]['error'] );
		$this->assertSame( 78, $res['results'][1]['created'] );
	}

	public function test_failed_insert_reports_error_no_meta_written(): void {
		$this->stub_ccb_storage();
		$err           = new \stdClass();
		$err->wp_error = true;
		Functions\expect( 'wp_insert_post' )->once()->andReturn( $err );
		Functions\expect( 'update_post_meta' )->never();
		$res = ( new ImportController() )->import( $this->request( array( 'ids' => array( 5 ) ) ) );
		$this->assertNull( $res['results'][0]['created'] );
	}

	public function test_save_failure_rolls_back_created_post(): void {
		$this->stub_ccb_storage();
		Functions\expect( 'wp_insert_post' )->once()->andReturn( 79 );
		Functions\when( 'update_post_meta' )->alias( static function () {
			throw new \RuntimeException( 'meta write failed' ); // repo->save() blows up AFTER the post exists
		} );
		Functions\expect( 'wp_delete_post' )->once()->with( 79, true );
		$res = ( new ImportController() )->import( $this->request( array( 'ids' => array( 5 ) ) ) );
		$this->assertNull( $res['results'][0]['created'] );
		$this->assertNotSame( '', $res['results'][0]['error'] );
	}

	public function test_permission_is_manage_options(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		$this->assertFalse( ( new ImportController() )->can_manage() );
	}
}
