<?php
namespace Alovio\Calculator\Tests\Unit\Import;

use Alovio\Calculator\Import\CcbReader;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class CcbReaderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
	}

	private function fixture( string $name ): array {
		$path = dirname( __DIR__, 2 ) . '/fixtures/ccb/' . $name;
		return json_decode( (string) file_get_contents( $path ), true );
	}

	private function parse_fixture( string $name ): ?array {
		$fx = $this->fixture( $name );
		return CcbReader::parse( (int) $fx['id'], (string) $fx['title'], $fx['meta'][ CcbReader::META_FIELDS ] ?? null );
	}

	private function by_type( array $calc, string $type ): ?array {
		foreach ( $calc['fields'] as $f ) {
			if ( $type === $f['type'] ) {
				return $f;
			}
		}
		return null;
	}

	public function test_parses_the_basic_sample_into_ccbcalc(): void {
		$calc = $this->parse_fixture( 'sample-basic.json' );
		$this->assertNotNull( $calc );
		$this->assertSame( 'CCB Basic', $calc['title'] );
		$this->assertGreaterThanOrEqual( 4, count( $calc['fields'] ) ); // range + dropdown + checkbox + total
		foreach ( $calc['fields'] as $f ) {
			$this->assertNotSame( '', $f['alias'] );
			$this->assertNotSame( '', $f['type'] );
		}
		// Range carries bounds (page-break/section nesting flattened away):
		$range = $this->by_type( $calc, 'range' );
		$this->assertSame( 10.0, $range['min'] );
		$this->assertSame( 500.0, $range['max'] );
		$this->assertSame( 50.0, $range['default'] );
		// Dropdown options carry labels + prices (optionValue = plain decimal string per fixtures):
		$drop = $this->by_type( $calc, 'dropdown' );
		$this->assertSame( 'Standard', $drop['options'][0]['label'] );
		$this->assertSame( 2.5, $drop['options'][0]['price'] );
		// Total carries the raw formula:
		$total = $this->by_type( $calc, 'total' );
		$this->assertNotSame( '', (string) $total['formula'] );
	}

	public function test_parses_the_edge_sample_unpriced_options_and_if_formula(): void {
		$calc = $this->parse_fixture( 'sample-edge.json' );
		$this->assertNotNull( $calc );
		$drop = $this->by_type( $calc, 'dropdown' );
		$this->assertSame( 0.0, $drop['options'][0]['price'] ); // unpriced option ("")
		$this->assertNotNull( $this->by_type( $calc, 'html' ) ); // free content type, mapper skips it
		$this->assertNotNull( $this->by_type( $calc, 'line' ) );
		$total = $this->by_type( $calc, 'total' );
		$this->assertStringStartsWith( 'if(', $total['formula'] ); // stored lowercase — untranslatable path
	}

	public function test_unparseable_raw_field_gets_unsupported_reason_not_dropped(): void {
		$calc = CcbReader::parse( 9, 'X', array( array( 'label' => 'no alias here' ) ) );
		$this->assertCount( 1, $calc['fields'] );
		$this->assertArrayHasKey( 'unsupported_reason', $calc['fields'][0] );
	}

	public function test_returns_null_for_garbage_meta(): void {
		$this->assertNull( CcbReader::parse( 9, 'X', 'not-an-array' ) );
		$this->assertNull( CcbReader::parse( 9, 'X', array() ) );
	}
}
