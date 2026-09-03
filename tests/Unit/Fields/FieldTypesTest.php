<?php
namespace Alovio\Calculator\Tests\Unit\Fields;

use Alovio\Calculator\Fields\FieldTypes;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Filters;

class FieldTypesTest extends TestCase {

	public function test_free_list_matches_spec_section_6(): void {
		Filters\expectApplied( 'alovio_calc_field_types' )->andReturnFirstArg();
		$this->assertSame(
			[ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'price_matrix', 'text', 'heading', 'html', 'formula', 'step', 'repeater', 'date', 'email', 'phone', 'url', 'textarea' ],
			FieldTypes::all()
		);
	}

	public function test_price_matrix_is_priced_referenceable_but_not_a_controller(): void {
		// It contributes a price and can be used in formulas, but is NOT a condition
		// source in v1 (not INPUT), and is not a choice or a repeater child.
		$this->assertContains( 'price_matrix', FieldTypes::all() );
		$this->assertTrue( FieldTypes::is_referenceable( 'price_matrix' ) );
		$this->assertFalse( FieldTypes::is_input( 'price_matrix' ) );
		$this->assertFalse( FieldTypes::is_condition_controller( 'price_matrix' ) );
		$this->assertFalse( FieldTypes::is_choice( 'price_matrix' ) );
		$this->assertFalse( FieldTypes::is_repeater_child( 'price_matrix' ) );
	}

	public function test_text_like_types_are_inputs_and_controllers_not_referenceable(): void {
		foreach ( [ 'date', 'email', 'phone', 'url', 'textarea' ] as $type ) {
			$this->assertContains( $type, FieldTypes::all(), $type );
			$this->assertTrue( FieldTypes::is_input( $type ), $type );
			$this->assertTrue( FieldTypes::is_condition_controller( $type ), $type );
			$this->assertFalse( FieldTypes::is_referenceable( $type ), $type );
			$this->assertFalse( FieldTypes::is_repeater_child( $type ), $type );
		}
	}

	public function test_repeater_type_flags(): void {
		$this->assertContains( 'repeater', FieldTypes::all() );
		$this->assertTrue( FieldTypes::is_referenceable( 'repeater' ) );
		$this->assertTrue( FieldTypes::is_condition_controller( 'repeater' ) );
		$this->assertFalse( FieldTypes::is_input( 'repeater' ) );
		$this->assertFalse( FieldTypes::is_choice( 'repeater' ) );
	}

	public function test_repeater_child_type_allowlist(): void {
		foreach ( [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity' ] as $type ) {
			$this->assertTrue( FieldTypes::is_repeater_child( $type ), $type );
		}
		foreach ( [ 'text', 'heading', 'html', 'formula', 'step', 'repeater' ] as $type ) {
			$this->assertFalse( FieldTypes::is_repeater_child( $type ), $type );
		}
	}

	public function test_classifiers(): void {
		$this->assertTrue( FieldTypes::is_input( 'number' ) );
		$this->assertTrue( FieldTypes::is_choice( 'radio' ) );
		$this->assertFalse( FieldTypes::is_input( 'formula' ) );
		$this->assertFalse( FieldTypes::is_input( 'heading' ) );
		$this->assertTrue( FieldTypes::is_referenceable( 'toggle' ) );
		$this->assertFalse( FieldTypes::is_referenceable( 'text' ) );
		$this->assertTrue( FieldTypes::is_condition_controller( 'text' ) );
		$this->assertTrue( FieldTypes::is_condition_controller( 'formula' ) );  // formula/total now drives conditions
		$this->assertFalse( FieldTypes::is_condition_controller( 'heading' ) ); // headings still cannot
		// Step dividers are pure layout — never input/referenceable/controller.
		$this->assertFalse( FieldTypes::is_input( 'step' ) );
		$this->assertFalse( FieldTypes::is_referenceable( 'step' ) );
		$this->assertFalse( FieldTypes::is_condition_controller( 'step' ) );
	}
}
