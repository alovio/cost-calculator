<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\Formula;
use PHPUnit\Framework\TestCase;

class FormulaFacadeTest extends TestCase {

	public function test_compile_evaluate_roundtrip(): void {
		$ast = Formula::compile( '{a} * 2 + if({b} > 0, 1, 0)' );
		$this->assertSame( 90000 + 10000, Formula::evaluate( $ast, [ 'a' => 45000, 'b' => 10000 ] ) );
	}

	public function test_references_collects_unique_field_ids(): void {
		$ast = Formula::compile( '{a} + {b} * if({a} > 1, {c}, 2)' );
		$this->assertSame( [ 'a', 'b', 'c' ], Formula::references( $ast ) );
	}
}
