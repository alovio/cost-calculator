<?php
namespace Alovio\Calculator\Tests\Unit\Fields;

use Alovio\Calculator\Fields\FieldTypes;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Filters;

class FieldTypesTest extends TestCase {

	public function test_free_list_matches_spec_section_6(): void {
		Filters\expectApplied( 'alovio_calc_field_types' )->andReturnFirstArg();
		$this->assertSame(
			[ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text', 'heading', 'html', 'formula' ],
			FieldTypes::all()
		);
	}

	public function test_classifiers(): void {
		$this->assertTrue( FieldTypes::is_input( 'number' ) );
		$this->assertTrue( FieldTypes::is_choice( 'radio' ) );
		$this->assertFalse( FieldTypes::is_input( 'formula' ) );
		$this->assertFalse( FieldTypes::is_input( 'heading' ) );
		$this->assertTrue( FieldTypes::is_referenceable( 'toggle' ) );
		$this->assertFalse( FieldTypes::is_referenceable( 'text' ) );
		$this->assertTrue( FieldTypes::is_condition_controller( 'text' ) );
		$this->assertFalse( FieldTypes::is_condition_controller( 'formula' ) ); // spec §6/§7
	}
}
