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

	public function test_field_help_text_is_sanitized(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'qty', 'type' => 'number', 'label' => 'Quantity', 'help' => '  How many <b>units</b>?  ' ],
		] ) );
		$this->assertSame( 'How many units?', $out['fields'][0]['help'] );
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

	public function test_strips_unknown_controllers_keeps_formula(): void {
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
		$this->assertCount( 2, $x['conditions'] );                   // formula (total) + qty survive; ghost stripped
		$this->assertSame( 'total', $x['conditions'][0]['field'] );  // formula/total is a valid controller now
		$this->assertSame( 'qty', $x['conditions'][1]['field'] );
		$this->assertSame( 'require', $x['conditionAction'] );        // require is a real action now (THEN pass)
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
			'theme'     => [ 'accent' => 'javascript:alert(1)', 'preset' => 'hacker' ],
			'quoteForm' => [ 'enabled' => 1, 'fields' => [ 'name', 'email', 'bogus' ], 'notifyEmail' => 'not-an-email' ],
		] ) );
		$s = $out['settings'];
		$this->assertSame( '$', $s['currency']['symbol'] );
		$this->assertSame( 'before', $s['currency']['position'] ); // default on invalid
		$this->assertSame( 2, $s['currency']['decimals'] );        // default on out-of-range
		$this->assertSame( '#f97316', $s['theme']['accent'] );     // default on invalid
		$this->assertSame( 'classic', $s['theme']['preset'] );     // unknown theme ⇒ classic default

		$ok = FieldSchema::normalize( [ 'fields' => [], 'settings' => [ 'theme' => [ 'preset' => 'midnight' ] ] ] );
		$this->assertSame( 'midnight', $ok['settings']['theme']['preset'] ); // a valid theme survives
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

	public function test_step_divider_and_layout(): void {
		$out  = FieldSchema::normalize( $this->config(
			[ [ 'id' => 's1', 'type' => 'step', 'label' => 'Your details', 'description' => 'Tell us about you' ] ],
			[ 'theme' => [ 'layout' => 'wizard' ] ]
		) );
		$step = $out['fields'][0];
		$this->assertSame( 'step', $step['type'] );
		$this->assertSame( 'Your details', $step['label'] );
		$this->assertSame( 'Tell us about you', $step['description'] );
		$this->assertArrayNotHasKey( 'options', $step );                 // a divider, not a choice
		$this->assertSame( 'wizard', $out['settings']['theme']['layout'] );

		$bad = FieldSchema::normalize( $this->config( [], [ 'theme' => [ 'layout' => 'spaceship' ] ] ) );
		$this->assertSame( 'single', $bad['settings']['theme']['layout'] ); // invalid ⇒ single
		$this->assertSame( 'single', FieldSchema::normalize( [] )['settings']['theme']['layout'] ); // default
	}

	public function test_option_default_flag_is_stored(): void {
		$out = FieldSchema::normalize( [ 'fields' => [ [ 'id' => 'g', 'type' => 'checkbox_group', 'label' => 'G', 'options' => [
			[ 'value' => 'opt_a', 'label' => 'A', 'default' => true ],
			[ 'value' => 'opt_b', 'label' => 'B', 'default' => true ],
			[ 'value' => 'opt_c', 'label' => 'C' ],
		] ] ] ] );
		$this->assertSame( [ true, true, false ], array_column( $out['fields'][0]['options'], 'default' ) );
	}

	public function test_single_choice_types_keep_at_most_one_default(): void {
		foreach ( [ 'select', 'radio' ] as $type ) {
			$out = FieldSchema::normalize( [ 'fields' => [ [ 'id' => 'f', 'type' => $type, 'label' => 'F', 'options' => [
				[ 'value' => 'opt_a', 'label' => 'A', 'default' => true ],
				[ 'value' => 'opt_b', 'label' => 'B', 'default' => true ],
			] ] ] ] );
			$this->assertSame( [ true, false ], array_column( $out['fields'][0]['options'], 'default' ), $type );
		}
	}

	public function test_repeater_children_restricted_and_caps_enforced(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'minRows' => 0, 'maxRows' => 900,
				'rowLabel' => 'Room {n}', 'addLabel' => 'Add a room', 'fields' => [
				[ 'id' => 'area', 'type' => 'number', 'label' => 'Area', 'default' => 5 ],
				[ 'id' => 'notes', 'type' => 'text', 'label' => 'Not allowed inside' ],
				[ 'id' => 'nested', 'type' => 'repeater', 'label' => 'No nesting' ],
			] ],
		] ) );
		$rep = $out['fields'][0];
		$this->assertSame( 'repeater', $rep['type'] );
		$this->assertSame( [ 'area' ], array_column( $rep['fields'], 'id' ) );
		$this->assertSame( 1, $rep['minRows'] );      // clamped up to 1
		$this->assertSame( 50, $rep['maxRows'] );     // hard server cap
		$this->assertSame( 'Room {n}', $rep['rowLabel'] );
		$this->assertSame( 'Add a room', $rep['addLabel'] );
		$this->assertSame( '', $rep['rowExpression'] );
	}

	public function test_repeater_slug_uniqueness_across_levels_and_no_child_logic(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'area', 'type' => 'number', 'label' => 'Top-level area' ],
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'fields' => [
				[ 'id' => 'area', 'type' => 'number', 'label' => 'Duplicate of top level' ],
				[ 'id' => 'qty', 'type' => 'quantity', 'label' => 'Qty', 'conditions' => [
					[ 'field' => 'area', 'operator' => 'is', 'value' => '1' ],
				], 'conditionAction' => 'require' ],
			] ],
			[ 'id' => 'qty', 'type' => 'number', 'label' => 'Shadowed by child, dropped' ],
		] ) );
		$rep = $out['fields'][1];
		$this->assertSame( [ 'qty' ], array_column( $rep['fields'], 'id' ) ); // child 'area' dropped (dup)
		$this->assertSame( [], $rep['fields'][0]['conditions'] );             // v2.0: no child logic
		$this->assertSame( 'show', $rep['fields'][0]['conditionAction'] );
		$this->assertSame( [ 'area', 'rooms' ], array_column( $out['fields'], 'id' ) ); // later top-level dup dropped
	}

	public function test_row_expression_child_refs_only(): void {
		$fields = [
			[ 'id' => 'outside', 'type' => 'number', 'label' => 'Outside' ],
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'R', 'rowExpression' => '{area} * {outside}', 'fields' => [
				[ 'id' => 'area', 'type' => 'number', 'label' => 'Area' ],
			] ],
		];
		$out = FieldSchema::normalize( $this->config( $fields ) );
		$this->assertSame( '', $out['fields'][1]['rowExpression'] ); // cross-scope ref ⇒ blanked (price mode)

		$fields[1]['rowExpression'] = '{area} * 2';
		$ok = FieldSchema::normalize( $this->config( $fields ) );
		$this->assertSame( '{area} * 2', $ok['fields'][1]['rowExpression'] ); // child refs pass

		$fields[1]['rowExpression'] = '{area} * * 2';
		$bad = FieldSchema::normalize( $this->config( $fields ) );
		$this->assertSame( '{area} * * 2', $bad['fields'][1]['rowExpression'] ); // compile error kept (runtime badge, formula parity)
	}

	public function test_repeater_is_valid_controller_but_children_are_not(): void {
		$out = FieldSchema::normalize( $this->config( [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'R', 'fields' => [
				[ 'id' => 'a', 'type' => 'number', 'label' => 'A' ],
			] ],
			[ 'id' => 'note', 'type' => 'heading', 'label' => 'N', 'conditions' => [
				[ 'field' => 'rooms', 'operator' => 'gte', 'value' => '100' ],
			] ],
			[ 'id' => 'note2', 'type' => 'heading', 'label' => 'N2', 'conditions' => [
				[ 'field' => 'a', 'operator' => 'gte', 'value' => '1' ],
			] ],
		] ) );
		$this->assertCount( 1, $out['fields'][1]['conditions'] ); // repeater sum drives conditions (spec §3.1)
		$this->assertSame( [], $out['fields'][2]['conditions'] ); // child ids are row-scoped, never controllers
	}
}
