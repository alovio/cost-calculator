<?php
namespace Alovio\Calculator\Formula;

final class Parser {

	private const BP_CMP = 10;
	private const BP_ADD = 20;
	private const BP_MUL = 30;

	/** @var array<string, array{0:int,1:int}> */
	private $functions;

	/** @var array<int, array{type: string, value: string, pos: int}> */
	private $tokens = [];

	/** @var int */
	private $i = 0;

	public function __construct( array $functions ) {
		$this->functions = $functions;
	}

	public function parse( array $tokens ): array {
		$this->tokens = $tokens;
		$this->i      = 0;
		if ( empty( $tokens ) ) {
			throw new FormulaError( 'syntax', 'Empty expression' );
		}
		$ast = $this->expression( 0 );
		if ( null !== $this->peek() ) {
			throw new FormulaError( 'syntax', 'Unexpected token', $this->peek()['pos'] );
		}
		return $ast;
	}

	private function expression( int $minBp ): array {
		$left = $this->primary();

		while ( true ) {
			$t = $this->peek();
			if ( null === $t ) {
				break;
			}
			if ( 'op' === $t['type'] && ( '+' === $t['value'] || '-' === $t['value'] ) ) {
				$bp   = self::BP_ADD;
				$node = 'bin';
			} elseif ( 'op' === $t['type'] ) {
				$bp   = self::BP_MUL;
				$node = 'bin';
			} elseif ( 'cmp' === $t['type'] ) {
				$bp   = self::BP_CMP;
				$node = 'cmp';
			} else {
				break;
			}
			if ( $bp < $minBp ) {
				break;
			}
			$this->next();
			$right = $this->expression( $bp + 1 ); // Left-associative.
			$left  = [
				'type'  => $node,
				'op'    => $t['value'],
				'left'  => $left,
				'right' => $right,
			];
		}

		return $left;
	}

	private function primary(): array {
		$t = $this->next();
		if ( null === $t ) {
			throw new FormulaError( 'syntax', 'Unexpected end of expression' );
		}

		switch ( $t['type'] ) {
			case 'num':
				return [
					'type'  => 'num',
					'value' => DecimalMath::toScaled( $t['value'] ),
				];

			case 'field':
				return [
					'type' => 'field',
					'id'   => $t['value'],
				];

			case 'op':
				if ( '-' === $t['value'] ) {
					return [
						'type'    => 'neg',
						'operand' => $this->expression( self::BP_MUL + 1 ),
					];
				}
				break;

			case 'lparen':
				$inner = $this->expression( 0 );
				$this->expect( 'rparen', $t['pos'] );
				return $inner;

			case 'ident':
				if ( ! isset( $this->functions[ $t['value'] ] ) ) {
					throw new FormulaError( 'unknown_function', 'Unknown function: ' . $t['value'], $t['pos'] );
				}
				$this->expect( 'lparen', $t['pos'] );
				$args = [ $this->expression( 0 ) ];
				while ( null !== $this->peek() && 'comma' === $this->peek()['type'] ) {
					$this->next();
					$args[] = $this->expression( 0 );
				}
				$this->expect( 'rparen', $t['pos'] );
				[ $min, $max ] = $this->functions[ $t['value'] ];
				if ( count( $args ) < $min || count( $args ) > $max ) {
					throw new FormulaError( 'arity', sprintf( '%s() expects %d-%d arguments', $t['value'], $min, $max ), $t['pos'] );
				}
				return [
					'type' => 'call',
					'name' => $t['value'],
					'args' => $args,
				];
		}

		throw new FormulaError( 'syntax', 'Unexpected token', $t['pos'] );
	}

	private function peek(): ?array {
		return $this->tokens[ $this->i ] ?? null;
	}

	private function next(): ?array {
		$t = $this->peek();
		if ( null !== $t ) {
			++$this->i;
		}
		return $t;
	}

	private function expect( string $type, int $contextPos ): void {
		$t = $this->next();
		if ( null === $t || $type !== $t['type'] ) {
			throw new FormulaError( 'syntax', 'Expected ' . $type, null === $t ? $contextPos : $t['pos'] );
		}
	}
}
