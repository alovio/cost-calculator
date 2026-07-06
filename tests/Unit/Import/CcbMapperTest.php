<?php
namespace Alovio\Calculator\Tests\Unit\Import;

use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Import\CcbMapper;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class CcbMapperTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
	}

	private function calc( array $fields ): array {
		return array( 'id' => 7, 'title' => 'From CCB', 'fields' => $fields );
	}

	public function test_maps_range_to_slider_with_bounds(): void {
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'range', 'alias' => 'range_field_id_0', 'label' => 'Area', 'min' => 10.0, 'max' => 500.0, 'step' => 5.0, 'default' => 50.0 ),
		) ) );
		$f = $r['config']['fields'][0];
		$this->assertSame( 'slider', $f['type'] );
		$this->assertSame( 'range_field_id_0', $f['id'] );
		$this->assertSame( 10.0, $f['min'] );
		$this->assertSame( 500.0, $f['max'] );
		$this->assertSame( array(), $r['skipped'] );
	}

	public function test_maps_dropdown_options_with_prices(): void {
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'dropdown', 'alias' => 'dropdown_field_id_1', 'label' => 'Service', 'options' => array(
				array( 'label' => 'Standard', 'price' => 2.5 ),
				array( 'label' => 'Deep', 'price' => 4.0 ),
			) ),
		) ) );
		$f = $r['config']['fields'][0];
		$this->assertSame( 'select', $f['type'] );
		$this->assertSame( 2.5, $f['options'][0]['price'] );
		$this->assertArrayNotHasKey( 'value', $f['options'][0] ); // FieldSchema::normalize generates opt_ slugs on save
	}

	public function test_translates_total_formula_to_ref_syntax(): void {
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'range', 'alias' => 'range_field_id_0', 'label' => 'Area' ),
			array( 'type' => 'dropdown', 'alias' => 'dropdown_field_id_1', 'label' => 'Rate', 'options' => array( array( 'label' => 'A', 'price' => 2.0 ) ) ),
			array( 'type' => 'total', 'alias' => 'total_field_id_2', 'label' => 'Total', 'formula' => 'range_field_id_0 * dropdown_field_id_1 + 10' ),
		) ) );
		$total = $r['config']['fields'][2];
		$this->assertSame( 'formula', $total['type'] );
		$this->assertSame( '{range_field_id_0} * {dropdown_field_id_1} + 10', $total['expression'] );
		$this->assertSame( array(), $r['warnings'] );
	}

	public function test_untranslatable_or_skipped_ref_formula_imports_empty_with_warning(): void {
		// (a) their function syntax; (b) a reference to a field we skipped.
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'range', 'alias' => 'range_field_id_0', 'label' => 'Area' ),
			array( 'type' => 'geolocation', 'alias' => 'geolocation_field_id_1', 'label' => 'Where' ),
			array( 'type' => 'total', 'alias' => 'total_field_id_2', 'label' => 'T1', 'formula' => 'their_func(range_field_id_0)' ),
			array( 'type' => 'total', 'alias' => 'total_field_id_3', 'label' => 'T2', 'formula' => 'geolocation_field_id_1 * 2' ),
		) ) );
		$this->assertCount( 1, $r['skipped'] ); // geolocation
		$this->assertSame( '', $r['config']['fields'][1]['expression'] );
		$this->assertSame( '', $r['config']['fields'][2]['expression'] );
		$this->assertCount( 2, $r['warnings'] );
	}

	public function test_unsupported_fields_reported_and_duplicate_aliases_suffixed(): void {
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'unknown', 'alias' => '', 'label' => 'Mystery', 'unsupported_reason' => 'unrecognized structure (no alias)' ),
			array( 'type' => 'file_upload', 'alias' => 'file_upload_field_id_0', 'label' => 'Plans' ),
			array( 'type' => 'toggle', 'alias' => 'toggle_field_id_1', 'label' => 'Express', 'price' => 30.0 ),
			array( 'type' => 'toggle', 'alias' => 'toggle_field_id_1', 'label' => 'Dup', 'price' => 5.0 ),
		) ) );
		$this->assertCount( 2, $r['skipped'] );
		$this->assertSame( 'toggle', $r['config']['fields'][0]['type'] );
		$this->assertSame( 30.0, $r['config']['fields'][0]['price'] );
		$ids = array_column( $r['config']['fields'], 'id' );
		$this->assertCount( 2, array_unique( $ids ) ); // second toggle got a _2 suffix, not dropped
	}

	public function test_options_based_toggle_maps_to_checkbox_group(): void {
		// 4.0.14 toggles are a LIST of priced switches (fixtures) — semantically our checkbox group.
		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'toggle', 'alias' => 'toggle_field_id_0', 'label' => 'Add-ons', 'options' => array(
				array( 'label' => 'Express service', 'price' => 35.0 ),
				array( 'label' => 'Insurance', 'price' => 12.0 ),
			) ),
		) ) );
		$f = $r['config']['fields'][0];
		$this->assertSame( 'checkbox_group', $f['type'] );
		$this->assertSame( 35.0, $f['options'][0]['price'] );
		$this->assertSame( array(), $r['skipped'] );
	}

	public function test_mapper_output_survives_field_schema_normalization(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->alias( static fn( $c ) => preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : '' );
		Functions\when( 'sanitize_email' )->alias( static fn( $e ) => filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : '' );
		Functions\when( 'wp_kses_post' )->returnArg();

		$r = CcbMapper::map( $this->calc( array(
			array( 'type' => 'dropdown', 'alias' => 'dropdown_field_id_1', 'label' => 'Service', 'options' => array(
				array( 'label' => 'Standard', 'price' => 2.5 ),
				array( 'label' => 'Deep', 'price' => 4.0 ),
			) ),
		) ) );
		$normalized = FieldSchema::normalize( $r['config'] );
		$this->assertCount( count( $r['config']['fields'] ), $normalized['fields'] );
		$this->assertMatchesRegularExpression( '/^opt_/', $normalized['fields'][0]['options'][0]['value'] );
	}
}
