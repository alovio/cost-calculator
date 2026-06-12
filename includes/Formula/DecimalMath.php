<?php
namespace Alovio\Calculator\Formula;

/**
 * Exact scale-4 fixed-point arithmetic on integers.
 * 12.3 is represented as 123000. Range guard: ±999,999,999.9999 (±10⁹),
 * chosen so every intermediate below fits PHP int64 AND mirrors safely
 * into JS (the JS twin uses BigInt for the same decompositions).
 * Rounding everywhere: half away from zero.
 */
final class DecimalMath {

	public const SCALE = 10000;

	/** 999,999,999.9999 scaled. */
	public const MAX_SCALED = 9999999999999;

	/** @param int|float|string $v */
	public static function toScaled( $v ): int {
		if ( ! is_numeric( $v ) ) {
			throw new FormulaError( 'bad_number', 'Not a number: ' . (string) $v );
		}
		$f = (float) $v;
		if ( ! is_finite( $f ) ) {
			throw new FormulaError( 'bad_number', 'Not a finite number' );
		}
		$sign   = $f < 0 ? -1 : 1;
		$scaled = (int) round( abs( $f ) * self::SCALE ); // Round AT the boundary (§7).
		self::guard( $scaled );
		return $sign * $scaled;
	}

	public static function fromScaled( int $x ): string {
		$sign = $x < 0 ? '-' : '';
		$x    = abs( $x );
		$int  = intdiv( $x, self::SCALE );
		$frac = rtrim( str_pad( (string) ( $x % self::SCALE ), 4, '0', STR_PAD_LEFT ), '0' );
		return $sign . $int . ( '' === $frac ? '' : '.' . $frac );
	}

	public static function add( int $a, int $b ): int {
		$r = $a + $b;
		self::guard( abs( $r ) );
		return $r;
	}

	public static function sub( int $a, int $b ): int {
		return self::add( $a, -$b );
	}

	public static function mul( int $a, int $b ): int {
		// Magnitude pre-check in float space (floats are reliable for order-of-magnitude checks).
		$approx = ( $a / self::SCALE ) * ( $b / self::SCALE );
		if ( abs( $approx ) > ( self::MAX_SCALED / self::SCALE ) + 1 ) {
			throw new FormulaError( 'overflow', 'Multiplication overflow' );
		}
		$sign = ( ( $a < 0 ) xor ( $b < 0 ) ) ? -1 : 1;
		$a    = abs( $a );
		$b    = abs( $b );
		// a*b/SCALE decomposed so no intermediate exceeds ~1e17 (< PHP_INT_MAX 9.2e18):
		// b = q*SCALE + r  ⇒  a*b/SCALE = a*q + a*r/SCALE.
		$q      = intdiv( $b, self::SCALE );
		$r      = $b % self::SCALE;
		$result = $a * $q + self::divRound( $a * $r, self::SCALE );
		self::guard( $result );
		return $sign * $result;
	}

	public static function div( int $a, int $b ): int {
		if ( 0 === $b ) {
			throw new FormulaError( 'div_zero', 'Division by zero' );
		}
		$approx = $a / $b; // Unscaled ratio == scaled-result/SCALE ratio.
		if ( abs( $approx ) > ( self::MAX_SCALED / self::SCALE ) + 1 ) {
			throw new FormulaError( 'overflow', 'Division overflow' );
		}
		$sign = ( ( $a < 0 ) xor ( $b < 0 ) ) ? -1 : 1;
		// a*SCALE ≤ 1e13*1e4 = 1e17 — safe in int64.
		$result = self::divRound( abs( $a ) * self::SCALE, abs( $b ) );
		self::guard( $result );
		return $sign * $result;
	}

	/** @param int $n decimal places 0..4 (clamped). */
	public static function roundToDecimals( int $x, int $n ): int {
		$n    = max( 0, min( 4, $n ) );
		$f    = (int) ( 10 ** ( 4 - $n ) );
		$sign = $x < 0 ? -1 : 1;
		return $sign * self::divRound( abs( $x ), $f ) * $f;
	}

	public static function ceilToInt( int $x ): int {
		$q = intdiv( $x, self::SCALE );
		$r = $x - $q * self::SCALE;
		if ( $r > 0 ) {
			++$q;
		}
		$result = $q * self::SCALE;
		self::guard( abs( $result ) );
		return $result;
	}

	public static function floorToInt( int $x ): int {
		$q = intdiv( $x, self::SCALE );
		$r = $x - $q * self::SCALE;
		if ( $r < 0 ) {
			--$q;
		}
		$result = $q * self::SCALE;
		self::guard( abs( $result ) );
		return $result;
	}

	/** Integer division n/d (n ≥ 0, d > 0), half away from zero. */
	private static function divRound( int $n, int $d ): int {
		$q = intdiv( $n, $d );
		$r = $n - $q * $d;
		if ( 2 * $r >= $d ) {
			++$q;
		}
		return $q;
	}

	private static function guard( int $absScaled ): void {
		if ( $absScaled > self::MAX_SCALED ) {
			throw new FormulaError( 'overflow', 'Value exceeds supported range (±999,999,999.9999)' );
		}
	}
}
