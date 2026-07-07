<?php
namespace Alovio\Calculator\Tests\Unit\Logic;

use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Logic\Evaluation;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class EvaluationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_hex_color' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	private function config(): array {
		return FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'area', 'type' => 'slider', 'label' => 'Area', 'min' => 10, 'max' => 500, 'default' => 50, 'showInSummary' => true ],
			[ 'id' => 'service', 'type' => 'radio', 'label' => 'Service', 'showInSummary' => true, 'options' => [
				[ 'value' => 'opt_std', 'label' => 'Standard', 'price' => 2.5 ],
				[ 'value' => 'opt_deep', 'label' => 'Deep', 'price' => 4 ],
			] ],
			[ 'id' => 'express', 'type' => 'toggle', 'label' => 'Express', 'price' => 50 ],
			[ 'id' => 'discount_note', 'type' => 'heading', 'label' => 'Discount!', 'conditions' => [
				[ 'field' => 'area', 'operator' => 'gt', 'value' => '100' ],
			], 'conditionMatch' => 'all', 'conditionAction' => 'show' ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'Total', 'showInSummary' => true,
				'expression' => '{area} * {service} + {express}' ],
		] ] );
	}

	public function test_happy_path_total_and_line_items(): void {
		$r = Evaluation::run( $this->config(), [ 'area' => '50', 'service' => 'opt_deep', 'express' => '1' ] );
		$this->assertSame( 2500000, $r['totalScaled'] ); // 50*4 + 50 = 250
		$this->assertSame( [], $r['errors'] );
		$ids = array_column( $r['lineItems'], 'id' );
		$this->assertSame( [ 'area', 'service', 'total' ], $ids );
		$this->assertFalse( $r['active']['discount_note'] );
	}

	public function test_condition_values_follow_spec_table(): void {
		$r = Evaluation::run( $this->config(), [ 'area' => '150', 'service' => 'opt_std', 'express' => '' ] );
		$this->assertSame( '150', $r['conditionValues']['area'] );
		$this->assertSame( 'opt_std', $r['conditionValues']['service'] ); // slug, not price
		$this->assertSame( '', $r['conditionValues']['express'] );
		$this->assertTrue( $r['active']['discount_note'] ); // area > 100
	}

	public function test_invalid_inputs_are_coerced_not_trusted(): void {
		$r = Evaluation::run( $this->config(), [ 'area' => '999999', 'service' => 'opt_hax', 'express' => 'yes' ] );
		$this->assertSame( 5000000, $r['values']['area'] );   // clamped to max 500
		$this->assertSame( 0, $r['values']['service'] );      // unknown slug ⇒ no selection ⇒ 0
		$this->assertSame( 500000, $r['values']['express'] ); // any truthy raw ⇒ on (price 50)
		$r2 = Evaluation::run( $this->config(), [ 'express' => 0 ] );
		$this->assertSame( 0, $r2['values']['express'] );      // JSON numeric zero ⇒ off (not the string-strict trap)
	}

	public function test_default_values_used_when_missing(): void {
		$r = Evaluation::run( $this->config(), [] );
		$this->assertSame( 500000, $r['values']['area'] ); // default 50
	}

	public function test_broken_formula_yields_zero_and_error(): void {
		$config = $this->config();
		$config['fields'][4]['expression'] = '{area} / 0';
		$r = Evaluation::run( $config, [ 'area' => '50' ] );
		$this->assertSame( 0, $r['totalScaled'] );
		$this->assertSame( 'div_zero', $r['errors']['total'] );
	}

	public function test_inactive_field_contributes_zero(): void {
		$config = $this->config();
		// Hide express unless area > 100.
		$config['fields'][2]['conditions']      = [ [ 'field' => 'area', 'operator' => 'gt', 'value' => '100' ] ];
		$config['fields'][2]['conditionMatch']  = 'all';
		$config['fields'][2]['conditionAction'] = 'show';
		$r = Evaluation::run( $config, [ 'area' => '50', 'service' => 'opt_std', 'express' => '1' ] );
		$this->assertSame( 0, $r['values']['express'] );
		$this->assertSame( 1250000, $r['totalScaled'] ); // 50*2.5 only
	}

	public function test_checkbox_group_sums_and_joins(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'extras', 'type' => 'checkbox_group', 'label' => 'Extras', 'options' => [
				[ 'value' => 'opt_a', 'label' => 'A', 'price' => 10 ],
				[ 'value' => 'opt_b', 'label' => 'B', 'price' => 5 ],
			] ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'T', 'expression' => '{extras}', 'showInSummary' => true ],
		] ] );
		$r = Evaluation::run( $config, [ 'extras' => [ 'opt_a', 'opt_b', 'opt_zzz' ] ] );
		$this->assertSame( 150000, $r['totalScaled'] );
		$this->assertSame( 'opt_a,opt_b', $r['conditionValues']['extras'] );
	}

	/** IF pass: a formula result (the running total) drives a condition via the fixed-point. */
	public function test_formula_total_drives_a_condition(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'area', 'type' => 'slider', 'label' => 'Area', 'min' => 10, 'max' => 1000, 'default' => 50 ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'Total', 'showInSummary' => true, 'expression' => '{area} * 10' ],
			[ 'id' => 'bulk_note', 'type' => 'heading', 'label' => 'Bulk discount applies', 'conditions' => [
				[ 'field' => 'total', 'operator' => 'gte', 'value' => '1000' ],
			], 'conditionMatch' => 'all', 'conditionAction' => 'show' ],
		] ] );

		$below = Evaluation::run( $config, [ 'area' => '50' ] );   // total 500 < 1000
		$this->assertSame( 5000000, $below['totalScaled'] );
		$this->assertSame( '500', $below['conditionValues']['total'] ); // unscaled formula value exposed to conditions
		$this->assertFalse( $below['active']['bulk_note'] );

		$above = Evaluation::run( $config, [ 'area' => '120' ] );  // total 1200 ≥ 1000
		$this->assertSame( 12000000, $above['totalScaled'] );
		$this->assertSame( '1200', $above['conditionValues']['total'] );
		$this->assertTrue( $above['active']['bulk_note'] );
	}

	private function repeater_config( string $rowExpression = '{r_area} * {r_rate}' ): array {
		return FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'rowLabel' => 'Room {n}',
				'minRows' => 1, 'maxRows' => 5, 'showInSummary' => true, 'rowExpression' => $rowExpression, 'fields' => [
				[ 'id' => 'r_area', 'type' => 'number', 'label' => 'Area', 'default' => 0 ],
				[ 'id' => 'r_rate', 'type' => 'select', 'label' => 'Rate', 'options' => [
					[ 'value' => 'opt_std', 'label' => 'Standard', 'price' => 6 ],
					[ 'value' => 'opt_dlx', 'label' => 'Deluxe', 'price' => 9 ],
				] ],
			] ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'Total', 'showInSummary' => true, 'expression' => '{rooms}' ],
		] ] );
	}

	public function test_repeater_rows_sum_and_per_row_summary(): void {
		$r = Evaluation::run( $this->repeater_config(), [ 'rooms' => [
			[ 'r_area' => '20', 'r_rate' => 'opt_std' ],
			[ 'r_area' => '10', 'r_rate' => 'opt_dlx' ],
		] ] );
		$this->assertSame( 2100000, $r['values']['rooms'] );   // 120 + 90
		$this->assertSame( 2100000, $r['totalScaled'] );
		$this->assertSame( [ 'rooms__1', 'rooms__2', 'total' ], array_column( $r['lineItems'], 'id' ) );
		$this->assertSame( 'Room 1', $r['lineItems'][0]['label'] );
		$this->assertSame( 'rooms', $r['lineItems'][0]['repeaterId'] );
		$rows = $r['repeaters']['rooms']['rows'];
		$this->assertSame( 'Standard', $rows[0]['values']['r_rate'] ); // display label, not slug
		$this->assertSame( '20', $rows[0]['values']['r_area'] );
	}

	public function test_repeater_absent_value_yields_min_rows_defaults_and_empty_array_zero(): void {
		$config = $this->repeater_config( '{r_area} * 2 + 3' );
		$absent = Evaluation::run( $config, [] );                       // 1 default row: 0*2+3
		$this->assertSame( 30000, $absent['values']['rooms'] );
		$empty = Evaluation::run( $config, [ 'rooms' => [] ] );          // zero rows
		$this->assertSame( 0, $empty['values']['rooms'] );
		$this->assertSame( [ 'total' ], array_column( $empty['lineItems'], 'id' ) );
	}

	public function test_repeater_runtime_and_compile_errors_zero_the_sum(): void {
		$runtime = Evaluation::run( $this->repeater_config( '{r_area} / 0' ), [ 'rooms' => [ [ 'r_area' => '5' ] ] ] );
		$this->assertSame( 0, $runtime['values']['rooms'] );
		$this->assertSame( 'div_zero', $runtime['errors']['rooms'] );

		$compile = Evaluation::run( $this->repeater_config( '{r_area} * * 2' ), [ 'rooms' => [ [ 'r_area' => '5' ] ] ] );
		$this->assertSame( 0, $compile['values']['rooms'] );
		$this->assertSame( 'syntax', $compile['errors']['rooms'] );
	}

	public function test_hidden_repeater_contributes_zero_and_can_drive_conditions(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'gate', 'type' => 'toggle', 'label' => 'Gate', 'price' => 0 ],
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'rowExpression' => '{r_qty} * 60',
				'conditions' => [ [ 'field' => 'gate', 'operator' => 'is', 'value' => '1' ] ], 'fields' => [
				[ 'id' => 'r_qty', 'type' => 'quantity', 'label' => 'Qty', 'default' => 1 ],
			] ],
			[ 'id' => 'bulk', 'type' => 'heading', 'label' => 'Bulk!', 'conditions' => [
				[ 'field' => 'rooms', 'operator' => 'gte', 'value' => '100' ],
			] ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'T', 'expression' => '{rooms} + 1' ],
		] ] );
		$off = Evaluation::run( $config, [ 'gate' => '', 'rooms' => [ [ 'r_qty' => '2' ] ] ] );
		$this->assertFalse( $off['active']['rooms'] );
		$this->assertSame( 0, $off['values']['rooms'] );
		$this->assertSame( 10000, $off['totalScaled'] );
		$this->assertFalse( $off['active']['bulk'] );

		$on = Evaluation::run( $config, [ 'gate' => '1', 'rooms' => [ [ 'r_qty' => '2' ] ] ] );
		$this->assertSame( 1200000, $on['values']['rooms'] );
		$this->assertTrue( $on['active']['bulk'] ); // 120 ≥ 100, via the fixed-point
	}

	public function test_text_like_fields_feed_conditions_and_summary_display(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'visit', 'type' => 'date', 'label' => 'Visit date', 'showInSummary' => true ],
			[ 'id' => 'mail', 'type' => 'email', 'label' => 'Email' ],
			[ 'id' => 'note', 'type' => 'heading', 'label' => 'Thanks!', 'conditions' => [
				[ 'field' => 'mail', 'operator' => 'is_not_empty', 'value' => '' ],
			] ],
		] ] );
		$r = Evaluation::run( $config, [ 'visit' => ' 2026-08-01 ', 'mail' => 'a@b.co' ] );
		$this->assertSame( '2026-08-01', $r['conditionValues']['visit'] ); // trimmed, text semantics
		$this->assertTrue( $r['active']['note'] );
		$this->assertSame(
			[ [ 'id' => 'visit', 'label' => 'Visit date', 'amount' => 0, 'isCurrency' => false, 'display' => '2026-08-01' ] ],
			$r['lineItems']
		);
		$empty = Evaluation::run( $config, [] );
		$this->assertSame( [], $empty['lineItems'] ); // empty text-like values emit no line
	}
}
