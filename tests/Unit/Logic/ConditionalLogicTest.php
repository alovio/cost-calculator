<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Tests\Unit\Logic;

use Alovio\Calculator\Logic\ConditionalLogic;
use Alovio\Calculator\Tests\TestCase;

class ConditionalLogicTest extends TestCase {

	/**
	 * Parity contract shared with the JS evaluator.
	 *
	 * @dataProvider fixtureCases
	 */
	public function test_is_active_matches_fixture( string $name, $condition, array $values, bool $expected ): void {
		$field = array( 'condition' => $condition );
		$this->assertSame( $expected, ConditionalLogic::is_active( $field, $values ), $name );
	}

	/** @return array<int,array{0:string,1:mixed,2:array,3:bool}> */
	public function fixtureCases(): array {
		$path  = dirname( __DIR__, 2 ) . '/fixtures/conditional-cases.json';
		$cases = json_decode( (string) file_get_contents( $path ), true );
		return array_map(
			static fn( $c ) => array( $c['name'], $c['condition'], $c['values'], $c['expectedActive'] ),
			$cases
		);
	}

	/** Calculator-specific guard: the spec §6 toggle convention ('1'/'') drives the untouched engine. */
	public function test_toggle_convention_drives_visibility(): void {
		$group = array(
			'fields' => array(
				array( 'id' => 'express', 'type' => 'toggle' ),
				array(
					'id'              => 'note',
					'type'            => 'text',
					'conditions'      => array( array( 'field' => 'express', 'operator' => 'is', 'value' => '1' ) ),
					'conditionMatch'  => 'all',
					'conditionAction' => 'show',
				),
			),
		);
		$on  = ConditionalLogic::active_map( $group, array( 'express' => '1', 'note' => '' ) );
		$off = ConditionalLogic::active_map( $group, array( 'express' => '', 'note' => '' ) );
		$this->assertTrue( $on['note'] );
		$this->assertFalse( $off['note'] );
	}

	/** THEN=require: requires() reports mandatory state without affecting visibility. */
	public function test_requires_reports_mandatory_state(): void {
		$field = array(
			'conditions'      => array( array( 'field' => 'a', 'operator' => 'is', 'value' => 'yes' ) ),
			'conditionMatch'  => 'all',
			'conditionAction' => 'require',
		);
		$this->assertTrue( ConditionalLogic::requires( $field, array( 'a' => 'yes' ) ) );  // condition met ⇒ mandatory
		$this->assertFalse( ConditionalLogic::requires( $field, array( 'a' => 'no' ) ) );  // not met ⇒ optional
		$this->assertTrue( ConditionalLogic::is_active( $field, array( 'a' => 'no' ) ) );  // require never hides

		$show = array_merge( $field, array( 'conditionAction' => 'show' ) );
		$this->assertFalse( ConditionalLogic::requires( $show, array( 'a' => 'yes' ) ) );  // show-action is never "required"
	}
}
