<?php
namespace Alovio\Calculator\Tests\Unit\Fields;

use Alovio\Calculator\Fields\FieldRepository;
use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class FieldRepositoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->justReturn( '#0a66ff' );
		Functions\when( 'sanitize_email' )->justReturn( '' );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	public function test_get_normalizes_stored_json(): void {
		$stored = json_encode( [ 'fields' => [
			[ 'id' => 'a', 'type' => 'number', 'label' => 'A' ],
			[ 'id' => 'evil', 'type' => 'launchcodes', 'label' => 'X' ],
		] ] );
		Functions\when( 'get_post_meta' )->justReturn( $stored );
		$config = ( new FieldRepository() )->get( 7 );
		$this->assertCount( 1, $config['fields'] );
	}

	public function test_get_returns_defaults_on_garbage(): void {
		Functions\when( 'get_post_meta' )->justReturn( '{not json' );
		$this->assertSame( FieldSchema::defaults(), ( new FieldRepository() )->get( 7 ) );
	}

	public function test_save_writes_normalized_json(): void {
		$captured = null;
		Functions\when( 'update_post_meta' )->alias( static function ( $id, $key, $value ) use ( &$captured ) {
			$captured = [ $id, $key, $value ];
			return true;
		} );
		( new FieldRepository() )->save( 7, [ 'fields' => [ [ 'id' => 'a', 'type' => 'number', 'label' => 'A' ] ] ] );
		$this->assertSame( 7, $captured[0] );
		$this->assertSame( '_alc_config', $captured[1] );
		$decoded = json_decode( $captured[2], true );
		$this->assertSame( 1, $decoded['schemaVersion'] );
	}
}
