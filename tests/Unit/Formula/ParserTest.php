<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\FormulaError;
use Alovio\Calculator\Formula\Functions;
use Alovio\Calculator\Formula\Lexer;
use Alovio\Calculator\Formula\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase {

	private function parse( string $expr ): array {
		return ( new Parser( Functions::SPECS ) )->parse( Lexer::tokenize( $expr ) );
	}

	public function test_precedence_mul_over_add(): void {
		$ast = $this->parse( '1 + 2 * 3' );
		$this->assertSame( 'bin', $ast['type'] );
		$this->assertSame( '+', $ast['op'] );
		$this->assertSame( '*', $ast['right']['op'] );
	}

	public function test_parens_override_precedence(): void {
		$ast = $this->parse( '(1 + 2) * 3' );
		$this->assertSame( '*', $ast['op'] );
		$this->assertSame( '+', $ast['left']['op'] );
	}

	public function test_unary_minus_binds_tighter_than_mul(): void {
		$ast = $this->parse( '-2 * 3' );
		$this->assertSame( '*', $ast['op'] );
		$this->assertSame( 'neg', $ast['left']['type'] );
	}

	public function test_numbers_are_scaled_at_parse_time(): void {
		$ast = $this->parse( '4.1' );
		$this->assertSame( [ 'type' => 'num', 'value' => 41000 ], $ast );
	}

	public function test_call_with_comparison_arg(): void {
		$ast = $this->parse( 'if({qty} >= 10, 5, 0)' );
		$this->assertSame( 'call', $ast['type'] );
		$this->assertSame( 'if', $ast['name'] );
		$this->assertCount( 3, $ast['args'] );
		$this->assertSame( 'cmp', $ast['args'][0]['type'] );
	}

	public function test_unknown_function_throws(): void {
		try {
			$this->parse( 'sqrt(4)' );
			$this->fail( 'Expected FormulaError' );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'unknown_function', $e->getErrorCode() );
		}
	}

	public function test_arity_violation_throws(): void {
		try {
			$this->parse( 'if(1, 2)' );
			$this->fail( 'Expected FormulaError' );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'arity', $e->getErrorCode() );
		}
	}

	public function test_trailing_garbage_throws(): void {
		$this->expectException( FormulaError::class );
		$this->parse( '1 2' );
	}

	public function test_empty_expression_throws(): void {
		$this->expectException( FormulaError::class );
		$this->parse( '   ' );
	}

	public function test_bare_ident_without_call_throws(): void {
		$this->expectException( FormulaError::class );
		$this->parse( 'min' );
	}
}
