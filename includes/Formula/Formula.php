<?php
namespace Alovio\Calculator\Formula;

final class Formula {

	public static function compile( string $expr ): array {
		return ( new Parser( self::functions() ) )->parse( Lexer::tokenize( $expr ) );
	}

	public static function evaluate( array $ast, array $scaledValues ): int {
		return ( new Evaluator( self::functions() ) )->evaluate( $ast, $scaledValues );
	}

	/** @return string[] Unique field ids in first-seen order. */
	public static function references( array $ast ): array {
		$refs = [];
		self::walk( $ast, $refs );
		return array_values( array_unique( $refs ) );
	}

	/**
	 * Filterable in WP context (spec §15: alc_formula_functions); plain default in unit tests.
	 * NOTE for the future Pro add-on: this filter extends what the PARSER accepts; the
	 * Evaluator has no dispatch for unknown names (safe-0 via unknown_function) — Pro
	 * will need an evaluation-callback mechanism, not just this filter.
	 */
	public static function functions(): array {
		$fns = Functions::SPECS;
		if ( function_exists( 'apply_filters' ) ) {
			$fns = apply_filters( 'alovio_calc_formula_functions', $fns );
		}
		return $fns;
	}

	private static function walk( array $node, array &$refs ): void {
		switch ( $node['type'] ) {
			case 'field':
				$refs[] = $node['id'];
				return;
			case 'neg':
				self::walk( $node['operand'], $refs );
				return;
			case 'bin':
			case 'cmp':
				self::walk( $node['left'], $refs );
				self::walk( $node['right'], $refs );
				return;
			case 'call':
				foreach ( $node['args'] as $arg ) {
					self::walk( $arg, $refs );
				}
				return;
		}
	}
}
