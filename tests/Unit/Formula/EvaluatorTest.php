<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Formula\Evaluator;
use Alovio\Calculator\Formula\FormulaError;
use Alovio\Calculator\Formula\Functions;
use Alovio\Calculator\Formula\Lexer;
use Alovio\Calculator\Formula\Parser;
use PHPUnit\Framework\TestCase;

class EvaluatorTest extends TestCase {

	private function evaluate( string $expr, array $values = [] ): string {
		$scaled = [];
		foreach ( $values as $id => $v ) {
			$scaled[ $id ] = DecimalMath::toScaled( $v );
		}
		$ast = ( new Parser( Functions::SPECS ) )->parse( Lexer::tokenize( $expr ) );
		return DecimalMath::fromScaled( ( new Evaluator( Functions::SPECS ) )->evaluate( $ast, $scaled ) );
	}

	public function test_arithmetic_with_fields(): void {
		$this->assertSame( '12.3', $this->evaluate( '{a} * {b}', [ 'a' => '4.1', 'b' => '3' ] ) );
		$this->assertSame( '0.3', $this->evaluate( '{a} + {b}', [ 'a' => '0.1', 'b' => '0.2' ] ) );
	}

	public function test_if_with_comparisons_both_branches(): void {
		$this->assertSame( '5', $this->evaluate( 'if({qty} >= 10, 5, 0)', [ 'qty' => '10' ] ) );
		$this->assertSame( '0', $this->evaluate( 'if({qty} >= 10, 5, 0)', [ 'qty' => '9.9999' ] ) );
		$this->assertSame( '1', $this->evaluate( 'if({a} != {b}, 1, 2)', [ 'a' => '1', 'b' => '2' ] ) );
	}

	public function test_if_is_lazy_untaken_branch_not_evaluated(): void {
		// Untaken branch divides by zero — must NOT throw.
		$this->assertSame( '7', $this->evaluate( 'if(1 == 1, 7, 1 / 0)' ) );
	}

	public function test_functions(): void {
		$this->assertSame( '2', $this->evaluate( 'min(5, 2, 8)' ) );
		$this->assertSame( '8', $this->evaluate( 'max(5, 2, 8)' ) );
		$this->assertSame( '3', $this->evaluate( 'round(2.5)' ) );
		$this->assertSame( '-3', $this->evaluate( 'round(-2.5)' ) );
		$this->assertSame( '1.24', $this->evaluate( 'round(1.235, 2)' ) );
		$this->assertSame( '3', $this->evaluate( 'ceil(2.1)' ) );
		$this->assertSame( '2', $this->evaluate( 'floor(2.9)' ) );
		$this->assertSame( '2.5', $this->evaluate( 'abs(-2.5)' ) );
	}

	public function test_unknown_field_throws(): void {
		try {
			$this->evaluate( '{ghost} + 1' );
			$this->fail( 'Expected FormulaError' );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'unknown_field', $e->getErrorCode() );
		}
	}

	public function test_division_by_zero_propagates(): void {
		$this->expectException( FormulaError::class );
		$this->evaluate( '1 / {z}', [ 'z' => '0' ] );
	}

	public function test_comparison_result_is_numeric_one_or_zero(): void {
		$this->assertSame( '1', $this->evaluate( '2 > 1' ) );
		$this->assertSame( '0', $this->evaluate( '2 < 1' ) );
	}

	public function test_round_second_arg_is_truncated_to_int_decimals(): void {
		$this->assertSame( '1.24', $this->evaluate( 'round(1.235, 2.9)' ) ); // n = 2
	}
}
