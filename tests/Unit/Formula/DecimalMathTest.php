<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Formula\FormulaError;
use PHPUnit\Framework\TestCase;

class DecimalMathTest extends TestCase {

	public function test_to_scaled_rounds_at_conversion_boundary(): void {
		// Naive (int) (4.1 * 10000) yields 40999 — the artifact class the engine kills.
		$this->assertSame( 41000, DecimalMath::toScaled( 4.1 ) );
		$this->assertSame( 41000, DecimalMath::toScaled( '4.1' ) );
		$this->assertSame( -41000, DecimalMath::toScaled( -4.1 ) );
		$this->assertSame( 0, DecimalMath::toScaled( 0 ) );
	}

	public function test_to_scaled_rejects_non_numeric_and_non_finite(): void {
		$this->expectException( FormulaError::class );
		DecimalMath::toScaled( 'abc' );
	}

	public function test_add_sub_are_exact(): void {
		// 0.1 + 0.2 === 0.3 exactly — the marquee case.
		$a = DecimalMath::toScaled( '0.1' );
		$b = DecimalMath::toScaled( '0.2' );
		$this->assertSame( '0.3', DecimalMath::fromScaled( DecimalMath::add( $a, $b ) ) );
		$this->assertSame( '-0.1', DecimalMath::fromScaled( DecimalMath::sub( $a, $b ) ) );
	}

	public function test_mul_rescales_with_half_away_rounding(): void {
		$this->assertSame( '0.02', DecimalMath::fromScaled( DecimalMath::mul( 1000, 2000 ) ) );      // 0.1*0.2
		$this->assertSame( '12.3', DecimalMath::fromScaled( DecimalMath::mul( 41000, 30000 ) ) );    // 4.1*3
		$this->assertSame( '-12.3', DecimalMath::fromScaled( DecimalMath::mul( -41000, 30000 ) ) );
		// Half-away at the 4th decimal: 0.0001 * 0.5 = 0.00005 → rounds to 0.0001 (away from zero).
		$this->assertSame( 1, DecimalMath::mul( 1, 5000 ) );
		$this->assertSame( -1, DecimalMath::mul( -1, 5000 ) );
	}

	public function test_div_rescales_with_half_away_rounding(): void {
		$this->assertSame( '3.3333', DecimalMath::fromScaled( DecimalMath::div( 100000, 30000 ) ) ); // 10/3
		$this->assertSame( '-3.3333', DecimalMath::fromScaled( DecimalMath::div( -100000, 30000 ) ) );
		$this->assertSame( '0.5', DecimalMath::fromScaled( DecimalMath::div( 10000, 20000 ) ) );
	}

	public function test_div_by_zero_throws(): void {
		$this->expectException( FormulaError::class );
		try {
			DecimalMath::div( 10000, 0 );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'div_zero', $e->getErrorCode() );
			throw $e;
		}
	}

	public function test_overflow_guard(): void {
		$big = DecimalMath::toScaled( '999999999' );
		$this->expectException( FormulaError::class );
		try {
			DecimalMath::mul( $big, $big );
		} catch ( FormulaError $e ) {
			$this->assertSame( 'overflow', $e->getErrorCode() );
			throw $e;
		}
	}

	public function test_round_to_decimals_half_away_from_zero(): void {
		$this->assertSame( 30000, DecimalMath::roundToDecimals( 25000, 0 ) );   // 2.5  → 3
		$this->assertSame( -30000, DecimalMath::roundToDecimals( -25000, 0 ) ); // -2.5 → -3 (NOT -2)
		$this->assertSame( 12300, DecimalMath::roundToDecimals( 12345, 2 ) );   // 1.2345 → 1.23 (remainder 45 < 50)
	}

	public function test_round_examples_pinned(): void {
		$this->assertSame( '1.23', DecimalMath::fromScaled( DecimalMath::roundToDecimals( DecimalMath::toScaled( '1.2345' ), 2 ) ) );
		$this->assertSame( '1.24', DecimalMath::fromScaled( DecimalMath::roundToDecimals( DecimalMath::toScaled( '1.235' ), 2 ) ) );
	}

	public function test_ceil_floor_to_int(): void {
		$this->assertSame( '3', DecimalMath::fromScaled( DecimalMath::ceilToInt( DecimalMath::toScaled( '2.1' ) ) ) );
		$this->assertSame( '2', DecimalMath::fromScaled( DecimalMath::floorToInt( DecimalMath::toScaled( '2.9' ) ) ) );
		$this->assertSame( '-2', DecimalMath::fromScaled( DecimalMath::ceilToInt( DecimalMath::toScaled( '-2.5' ) ) ) );
		$this->assertSame( '-3', DecimalMath::fromScaled( DecimalMath::floorToInt( DecimalMath::toScaled( '-2.5' ) ) ) );
	}

	public function test_from_scaled_trims_trailing_zeros(): void {
		$this->assertSame( '12', DecimalMath::fromScaled( 120000 ) );
		$this->assertSame( '12.5', DecimalMath::fromScaled( 125000 ) );
		$this->assertSame( '0.0001', DecimalMath::fromScaled( 1 ) );
		$this->assertSame( '-12.5', DecimalMath::fromScaled( -125000 ) );
	}
}
