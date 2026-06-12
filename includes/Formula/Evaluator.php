<?php
namespace Alovio\Calculator\Formula;

final class Evaluator {

	/** @var array<string, array{0:int,1:int}> */
	private $functions;

	public function __construct( array $functions ) {
		$this->functions = $functions;
	}

	/**
	 * @param array $ast    Node from Parser.
	 * @param array $values Map field-id => scaled int. Callers must pre-resolve
	 *                      inactive fields to 0 (spec §6/§8).
	 */
	public function evaluate( array $ast, array $values ): int {
		switch ( $ast['type'] ) {
			case 'num':
				return $ast['value'];

			case 'field':
				if ( ! array_key_exists( $ast['id'], $values ) ) {
					throw new FormulaError( 'unknown_field', 'Unknown field: ' . $ast['id'] );
				}
				return $values[ $ast['id'] ];

			case 'neg':
				return -$this->evaluate( $ast['operand'], $values );

			case 'bin':
				$l = $this->evaluate( $ast['left'], $values );
				$r = $this->evaluate( $ast['right'], $values );
				switch ( $ast['op'] ) {
					case '+':
						return DecimalMath::add( $l, $r );
					case '-':
						return DecimalMath::sub( $l, $r );
					case '*':
						return DecimalMath::mul( $l, $r );
					case '/':
						return DecimalMath::div( $l, $r );
				}
				break;

			case 'cmp':
				$l    = $this->evaluate( $ast['left'], $values );
				$r    = $this->evaluate( $ast['right'], $values );
				$bool = false;
				switch ( $ast['op'] ) {
					case '>':
						$bool = $l > $r;
						break;
					case '<':
						$bool = $l < $r;
						break;
					case '>=':
						$bool = $l >= $r;
						break;
					case '<=':
						$bool = $l <= $r;
						break;
					case '==':
						$bool = $l === $r;
						break;
					case '!=':
						$bool = $l !== $r;
						break;
				}
				return $bool ? DecimalMath::SCALE : 0;

			case 'call':
				return $this->call( $ast['name'], $ast['args'], $values );
		}

		throw new FormulaError( 'syntax', 'Malformed AST node' );
	}

	private function call( string $name, array $args, array $values ): int {
		if ( ! isset( $this->functions[ $name ] ) ) {
			throw new FormulaError( 'unknown_function', 'Unknown function: ' . $name );
		}

		if ( 'if' === $name ) { // Lazy: only the taken branch is evaluated.
			$cond = $this->evaluate( $args[0], $values );
			return $this->evaluate( 0 !== $cond ? $args[1] : $args[2], $values );
		}

		$vals = array_map( fn( $a ) => $this->evaluate( $a, $values ), $args );

		switch ( $name ) {
			case 'min':
				return min( $vals );
			case 'max':
				return max( $vals );
			case 'round':
				$n = isset( $vals[1] ) ? intdiv( $vals[1], DecimalMath::SCALE ) : 0;
				return DecimalMath::roundToDecimals( $vals[0], $n );
			case 'ceil':
				return DecimalMath::ceilToInt( $vals[0] );
			case 'floor':
				return DecimalMath::floorToInt( $vals[0] );
			case 'abs':
				return abs( $vals[0] );
		}

		throw new FormulaError( 'unknown_function', 'No evaluator for function: ' . $name );
	}
}
