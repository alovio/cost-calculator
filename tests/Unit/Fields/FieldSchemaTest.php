<?php
namespace Alovio\Calculator\Tests\Unit\Fields;

use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class FieldSchemaTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->alias( static fn( $c ) => preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : '' );
		Functions\when( 'sanitize_email' )->alias( static fn( $e ) => filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : '' );
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	private function config( array $fields, array $settings = [] ): array {
		return [ 'schemaVersion' => 1, 'fields' => $fields, 'settings' => $settings ];
	}

	public function test_generates_unique_opt_slugs_for_new_options(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'service', 'type' => 'radio', 'label' => 'Service', 'options' => [
				[ 'label' => 'Standard', 'price' => '2.5' ],
				[ 'label' => 'Deep', 'price' => 4 ],
			] ],
		] ) );
		$opts = $out['fields'][0]['options'];
		$this->assertMatchesRegularExpression( '/^opt_[a-z0-9]{4,8}$/', $opts[0]['value'] );
		$this->assertNotSame( $opts[0]['value'], $opts[1]['value'] );
		$this->assertSame( 2.5, $opts[0]['price'] );
	}

	public function test_preserves_existing_option_slugs(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'service', 'type' => 'radio', 'label' => 'S', 'options' => [
				[ 'value' => 'opt_7f3a', 'label' => 'Standard', 'price' => 2.5 ],
			] ],
		] ) );
		$this->assertSame( 'opt_7f3a', $out['fields'][0]['options'][0]['value'] );
	}

	public function test_rejects_unknown_types_and_duplicate_ids(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'a', 'type' => 'number', 'label' => 'A' ],
			[ 'id' => 'a', 'type' => 'number', 'label' => 'Dupe' ],
			[ 'id' => 'b', 'type' => 'launchcodes', 'label' => 'Bad' ],
		] ) );
		$this->assertCount( 1, $out['fields'] );
		$this->assertSame( 'a', $out['fields'][0]['id'] );
	}

	public function test_strips_conditions_with_formula_or_unknown_controllers(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'T', 'expression' => '1 + 1' ],
			[ 'id' => 'qty', 'type' => 'quantity', 'label' => 'Q' ],
			[ 'id' => 'x', 'type' => 'number', 'label' => 'X', 'conditions' => [
				[ 'field' => 'total', 'operator' => 'gt', 'value' => '10' ],
				[ 'field' => 'ghost', 'operator' => 'is', 'value' => '1' ],
				[ 'field' => 'qty', 'operator' => 'gt', 'value' => '2' ],
			], 'conditionMatch' => 'any', 'conditionAction' => 'require' ],
		] ) );
		$x = $out['fields'][2];
		$this->assertCount( 1, $x['conditions'] );                  // only qty survives
		$this->assertSame( 'qty', $x['conditions'][0]['field'] );
		$this->assertSame( 'show', $x['conditionAction'] );          // require coerced to show (spec §6)
	}

	public function test_expression_normalization(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'f1', 'type' => 'formula', 'label' => 'F', 'expression' => str_repeat( 'x', 2000 ) ],
		] ) );
		$this->assertSame( 1000, strlen( $out['fields'][0]['expression'] ) );
	}

	public function test_settings_defaults_and_sanitization(): void {
		$out = FieldSchema::normalize( $this->config( [], [
			'currency'  => [ 'symbol' => '<b>$</b>', 'position' => 'nonsense', 'decimals' => 9 ],
			'theme'     => [ 'accent' => 'javascript:alert(1)' ],
			'quoteForm' => [ 'enabled' => 1, 'fields' => [ 'name', 'email', 'bogus' ], 'notifyEmail' => 'not-an-email' ],
		] ) );
		$s = $out['settings'];
		$this->assertSame( '$', $s['currency']['symbol'] );
		$this->assertSame( 'before', $s['currency']['position'] ); // default on invalid
		$this->assertSame( 2, $s['currency']['decimals'] );        // default on out-of-range
		$this->assertSame( '#f97316', $s['theme']['accent'] );     // default on invalid
		$this->assertTrue( $s['quoteForm']['enabled'] );
		$this->assertSame( [ 'name', 'email' ], $s['quoteForm']['fields'] );
		$this->assertSame( '', $s['quoteForm']['notifyEmail'] );
	}

	public function test_empty_input_yields_valid_empty_config(): void {
		$out = FieldSchema::normalize( [] );
		$this->assertSame( 1, $out['schemaVersion'] );
		$this->assertSame( [], $out['fields'] );
		$this->assertArrayHasKey( 'currency', $out['settings'] );
	}
}
