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
}
