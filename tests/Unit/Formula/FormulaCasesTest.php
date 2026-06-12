<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Formula\Formula;
use Alovio\Calculator\Formula\FormulaError;
use PHPUnit\Framework\TestCase;

class FormulaCasesTest extends TestCase {

	/** @dataProvider casesProvider */
	public function test_fixture_case( string $expression, array $values, $expected ): void {
		$scaled = [];
		foreach ( $values as $id => $v ) {
			$scaled[ $id ] = DecimalMath::toScaled( $v );
		}

		if ( is_array( $expected ) ) {
			try {
				Formula::evaluate( Formula::compile( $expression ), $scaled );
				$this->fail( 'Expected FormulaError ' . $expected['error'] );
			} catch ( FormulaError $e ) {
				$this->assertSame( $expected['error'], $e->getErrorCode() );
			}
			return;
		}

		$result = Formula::evaluate( Formula::compile( $expression ), $scaled );
		$this->assertSame( $expected, DecimalMath::fromScaled( $result ) );
	}

	public function casesProvider(): iterable {
		$json = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/formula-cases.json' ), true );
		foreach ( $json['cases'] as $case ) {
			yield $case['name'] => [ $case['expression'], $case['values'], $case['expected'] ];
		}
	}
}
