<?php
namespace Alovio\Calculator\Tests\Unit\Templates;

use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Formula\Formula;
use Alovio\Calculator\Templates\Presets;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class PresetsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	public function test_six_presets_exist(): void {
		$this->assertSame(
			[ 'cleaning-price', 'moving-cost', 'print-quote', 'agency-estimate', 'salon-pricing', 'rental-cost' ],
			array_keys( Presets::all() )
		);
	}

	public function test_every_preset_is_normalization_stable_and_compiles(): void {
		foreach ( Presets::all() as $key => $preset ) {
			$normalized = FieldSchema::normalize( $preset['config'] );
			$this->assertSame( $normalized, FieldSchema::normalize( $normalized ), "Preset {$key} must be normalization-idempotent" );
			$formulas = array_filter( $normalized['fields'], static fn( $f ) => 'formula' === $f['type'] );
			$this->assertNotEmpty( $formulas, "Preset {$key} needs at least one formula" );
			foreach ( $formulas as $f ) {
				Formula::compile( $f['expression'] ); // Throws on invalid syntax.
			}
			$summary = array_filter( $normalized['fields'], static fn( $f ) => ! empty( $f['showInSummary'] ) );
			$this->assertNotEmpty( $summary, "Preset {$key} needs a summary line" );
			$conditional = array_filter( $normalized['fields'], static fn( $f ) => ! empty( $f['conditions'] ) );
			$this->assertNotEmpty( $conditional, "Preset {$key} should demo conditional logic" );
		}
	}

	public function test_every_formula_reference_resolves_to_a_field(): void {
		foreach ( Presets::all() as $key => $preset ) {
			$normalized = FieldSchema::normalize( $preset['config'] );
			$ids        = array_column( $normalized['fields'], 'id' );
			foreach ( $normalized['fields'] as $f ) {
				if ( 'formula' !== $f['type'] ) {
					continue;
				}
				foreach ( Formula::references( Formula::compile( $f['expression'] ) ) as $ref ) {
					$this->assertContains( $ref, $ids, "Preset {$key}: {{$ref}} must exist" );
				}
			}
		}
	}
}
