<?php
namespace Alovio\Calculator\Formula;

final class Lexer {

	/** @return array<int, array{type: string, value: string, pos: int}> */
	public static function tokenize( string $expr ): array {
		$tokens = [];
		$len    = strlen( $expr );
		$i      = 0;

		while ( $i < $len ) {
			$c = $expr[ $i ];

			if ( ' ' === $c || "\t" === $c || "\n" === $c || "\r" === $c ) {
				$i++;
				continue;
			}

			if ( '{' === $c ) {
				if ( ! preg_match( '/\{([a-z0-9_]+)\}/A', $expr, $m, 0, $i ) ) {
					throw new FormulaError( 'syntax', 'Malformed field reference', $i );
				}
				$tokens[] = [ 'type' => 'field', 'value' => $m[1], 'pos' => $i ];
				$i       += strlen( $m[0] );
				continue;
			}

			if ( preg_match( '/[0-9]/', $c ) ) {
				preg_match( '/[0-9]+(\.[0-9]+)?/A', $expr, $m, 0, $i );
				$end = $i + strlen( $m[0] );
				if ( $end < $len && ( '.' === $expr[ $end ] || preg_match( '/[0-9a-z_]/i', $expr[ $end ] ) ) ) {
					throw new FormulaError( 'syntax', 'Malformed number', $i );
				}
				$tokens[] = [ 'type' => 'num', 'value' => $m[0], 'pos' => $i ];
				$i        = $end;
				continue;
			}

			if ( preg_match( '/[a-z_]/i', $c ) ) {
				preg_match( '/[a-z_][a-z0-9_]*/Ai', $expr, $m, 0, $i );
				$tokens[] = [ 'type' => 'ident', 'value' => strtolower( $m[0] ), 'pos' => $i ];
				$i       += strlen( $m[0] );
				continue;
			}

			$two = substr( $expr, $i, 2 );
			if ( in_array( $two, [ '>=', '<=', '==', '!=' ], true ) ) {
				$tokens[] = [ 'type' => 'cmp', 'value' => $two, 'pos' => $i ];
				$i       += 2;
				continue;
			}
			if ( '>' === $c || '<' === $c ) {
				$tokens[] = [ 'type' => 'cmp', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}
			if ( '+' === $c || '-' === $c || '*' === $c || '/' === $c ) {
				$tokens[] = [ 'type' => 'op', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}
			if ( '(' === $c ) {
				$tokens[] = [ 'type' => 'lparen', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}
			if ( ')' === $c ) {
				$tokens[] = [ 'type' => 'rparen', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}
			if ( ',' === $c ) {
				$tokens[] = [ 'type' => 'comma', 'value' => $c, 'pos' => $i ];
				$i++;
				continue;
			}

			throw new FormulaError( 'syntax', 'Unexpected character: ' . $c, $i );
		}

		return $tokens;
	}
}
