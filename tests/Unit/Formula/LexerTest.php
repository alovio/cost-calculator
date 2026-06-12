<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\FormulaError;
use Alovio\Calculator\Formula\Lexer;
use PHPUnit\Framework\TestCase;

class LexerTest extends TestCase {

	private function types( string $expr ): array {
		return array_map( static fn( $t ) => $t['type'], Lexer::tokenize( $expr ) );
	}

	public function test_numbers_fields_ops(): void {
		$tokens = Lexer::tokenize( '{area} * 2.5 + 10' );
		$this->assertSame(
			[ 'field', 'op', 'num', 'op', 'num' ],
			array_map( static fn( $t ) => $t['type'], $tokens )
		);
		$this->assertSame( 'area', $tokens[0]['value'] );
		$this->assertSame( '2.5', $tokens[2]['value'] );
		$this->assertSame( 0, $tokens[0]['pos'] );
	}

	public function test_function_call_tokens(): void {
		$this->assertSame(
			[ 'ident', 'lparen', 'field', 'cmp', 'num', 'comma', 'num', 'comma', 'num', 'rparen' ],
			$this->types( 'if({qty} >= 10, 5, 0)' )
		);
	}

	public function test_all_comparison_operators(): void {
		foreach ( [ '>', '<', '>=', '<=', '==', '!=' ] as $cmp ) {
			$tokens = Lexer::tokenize( "1 {$cmp} 2" );
			$this->assertSame( 'cmp', $tokens[1]['type'] );
			$this->assertSame( $cmp, $tokens[1]['value'] );
		}
	}

	public function test_field_id_charset(): void {
		$tokens = Lexer::tokenize( '{opt_7f3a}' );
		$this->assertSame( 'opt_7f3a', $tokens[0]['value'] );
	}

	public function test_unknown_char_throws_with_position(): void {
		try {
			Lexer::tokenize( '1 + $x' );
			$this->fail( 'Expected FormulaError' );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'syntax', $e->getErrorCode() );
			$this->assertSame( 4, $e->getPosition() );
		}
	}

	public function test_unterminated_field_throws(): void {
		$this->expectException( FormulaError::class );
		Lexer::tokenize( '{area' );
	}

	public function test_malformed_number_throws(): void {
		$this->expectException( FormulaError::class );
		Lexer::tokenize( '1.2.3' );
	}
}
