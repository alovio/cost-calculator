<?php
namespace Alovio\Calculator\Logic;

use Alovio\Calculator\Fields\FieldTypes;
use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Formula\Formula;
use Alovio\Calculator\Formula\FormulaError;
use Alovio\Calculator\Formula\FormulaGraph;

/**
 * The server-side calculation authority (spec §6 value maps + §8 runtime order).
 * Consumed by CalculatorRenderer (initial render) and QuoteController (recompute).
 * Grand-total convention: the LAST active formula field in field order.
 */
final class Evaluation {

	/** Fixed-point cap: bounds the formula↔condition feedback loop (cycle safety). */
	private const MAX_PASSES = 8;

	/** Informational fields: string value maps + display-only summary lines (never priced). */
	private const TEXT_LIKE = [ 'text', 'date', 'email', 'phone', 'url', 'textarea' ];

	private static function is_text_like( string $type ): bool {
		return in_array( $type, self::TEXT_LIKE, true );
	}

	public static function run( array $config, array $rawValues ): array {
		$fields = $config['fields'];

		// Compile formulas once (compile/cycle errors + order are pass-invariant).
		$errors   = [];
		$formulas = [];
		$asts     = [];
		foreach ( $fields as $field ) {
			if ( 'formula' !== $field['type'] ) {
				continue;
			}
			try {
				$ast                      = Formula::compile( $field['expression'] );
				$asts[ $field['id'] ]     = $ast;
				$formulas[ $field['id'] ] = Formula::references( $ast );
			} catch ( FormulaError $e ) {
				$errors[ $field['id'] ]   = $e->getErrorCode();
				$formulas[ $field['id'] ] = [];
			}
		}
		$graph = FormulaGraph::order( $formulas );
		foreach ( $graph['cycles'] as $id ) {
			$errors[ $id ] = 'cycle';
		}

		// Repeater pre-pass (spec §3.1): children carry no logic, so row math is
		// pass-invariant — computed ONCE here. Visibility gating happens inside the
		// loop below, exactly like formula fields.
		$repeaters = [];
		foreach ( $fields as $field ) {
			if ( 'repeater' !== $field['type'] ) {
				continue;
			}
			$repeaters[ $field['id'] ] = self::repeater_result( $field, $rawValues[ $field['id'] ] ?? null );
			if ( '' !== $repeaters[ $field['id'] ]['error'] ) {
				$errors[ $field['id'] ] = $repeaters[ $field['id'] ]['error'];
			}
		}

		// Fixed-point: a formula result can drive a condition, which changes the active
		// map, which changes the result. Re-derive the input map each pass and feed the
		// formula results back in until the active map stabilises (capped for safety).
		// With no formula-driven condition this converges on the first pass — identical
		// to the previous single-pass behaviour.
		$baseCond        = self::condition_values( $fields, $rawValues );
		$conditionValues = $baseCond;
		$active          = ConditionalLogic::active_map( [ 'fields' => $fields ], $conditionValues );
		$values          = [];

		for ( $pass = 0; $pass < self::MAX_PASSES; $pass++ ) {
			$tmpErrors = [];
			$values    = self::compute_values( $fields, $active, $asts, $errors, $graph['order'], $rawValues, $tmpErrors, $repeaters );
			$nextCond  = $baseCond;
			foreach ( $fields as $field ) {
				if ( 'formula' === $field['type'] ) {
					$nextCond[ $field['id'] ] = ( $active[ $field['id'] ] ?? true )
						? DecimalMath::fromScaled( (int) ( $values[ $field['id'] ] ?? 0 ) )
						: '';
				}
				if ( 'repeater' === $field['type'] ) {
					$nextCond[ $field['id'] ] = ( $active[ $field['id'] ] ?? true )
						? DecimalMath::fromScaled( $repeaters[ $field['id'] ]['sum'] )
						: '';
				}
			}
			$nextActive      = ConditionalLogic::active_map( [ 'fields' => $fields ], $nextCond );
			$conditionValues = $nextCond;
			if ( $nextActive === $active ) {
				break;
			}
			$active = $nextActive;
		}

		// Final values + runtime errors, consistent with the settled active map.
		$evalErrors = [];
		$values     = self::compute_values( $fields, $active, $asts, $errors, $graph['order'], $rawValues, $evalErrors, $repeaters );
		$errors     = $errors + $evalErrors;

		// Summary line items + grand total (= last active formula in field order).
		$lineItems   = [];
		$totalScaled = null;
		foreach ( $fields as $field ) {
			$id = $field['id'];
			if ( 'formula' === $field['type'] && ( $active[ $id ] ?? true ) ) {
				$totalScaled = $values[ $id ];
			}
			if ( empty( $field['showInSummary'] ) || false === ( $active[ $id ] ?? true ) ) {
				continue;
			}
			if ( 'repeater' === $field['type'] ) {
				foreach ( $repeaters[ $id ]['rows'] as $n => $row ) {
					$lineItems[] = [
						'id'         => $id . '__' . ( $n + 1 ),
						'label'      => $row['label'],
						'amount'     => $row['total'],
						'isCurrency' => true,
						'repeaterId' => $id,
					];
				}
				continue;
			}
			if ( self::is_text_like( $field['type'] ) ) {
				$text = (string) ( $conditionValues[ $id ] ?? '' );
				if ( '' !== $text ) {
					$lineItems[] = [
						'id'         => $id,
						'label'      => $field['label'],
						'amount'     => 0,
						'isCurrency' => false,
						'display'    => $text,
					];
				}
				continue;
			}
			if ( ! isset( $values[ $id ] ) ) {
				continue;
			}
			$isCurrency  = 'formula' === $field['type'] || self::is_priced( $field );
			$lineItems[] = [
				'id'         => $id,
				'label'      => $field['label'],
				'amount'     => $values[ $id ],
				'isCurrency' => $isCurrency,
			];
		}

		return [
			'conditionValues' => $conditionValues,
			'active'          => $active,
			'values'          => $values,
			'lineItems'       => $lineItems,
			'totalScaled'     => $totalScaled,
			'errors'          => $errors,
			'repeaters'       => $repeaters,
		];
	}

	/**
	 * One evaluation pass: input value map (active-gated) then formulas in dependency
	 * order. Runtime formula errors are collected into $evalErrors (kept separate from
	 * compile/cycle errors so transient passes don't pollute the final error map).
	 *
	 * @param array<string,mixed>  $structural Compile + cycle errors (read-only here).
	 * @param string[]             $order      Formula evaluation order.
	 * @param array<string,string> $evalErrors Out param: runtime errors hit this pass.
	 * @return array<string,int> field id => scaled value
	 */
	private static function compute_values( array $fields, array $active, array $asts, array $structural, array $order, array $rawValues, array &$evalErrors, array $repeaters = [] ): array {
		$values = [];
		foreach ( $fields as $field ) {
			if ( 'repeater' === $field['type'] ) {
				$values[ $field['id'] ] = ( $active[ $field['id'] ] ?? true ) ? $repeaters[ $field['id'] ]['sum'] : 0;
				continue;
			}
			if ( ! FieldTypes::is_referenceable( $field['type'] ) || 'formula' === $field['type'] ) {
				continue;
			}
			$values[ $field['id'] ] = ( $active[ $field['id'] ] ?? true )
				? self::input_amount( $field, $rawValues[ $field['id'] ] ?? null )
				: 0;
		}
		foreach ( $order as $id ) {
			if ( isset( $structural[ $id ] ) || ! isset( $asts[ $id ] ) || false === ( $active[ $id ] ?? true ) ) {
				$values[ $id ] = 0; // unparsable, cyclic, or inactive ⇒ 0 (§6).
				continue;
			}
			try {
				$values[ $id ] = Formula::evaluate( $asts[ $id ], $values );
			} catch ( FormulaError $e ) {
				$evalErrors[ $id ] = $e->getErrorCode();
				$values[ $id ]     = 0;
			}
		}
		return $values;
	}

	/** Spec §6 condition value map. Untrusted raw input is coerced here. */
	private static function condition_values( array $fields, array $raw ): array {
		$out = [];
		foreach ( $fields as $field ) {
			if ( ! FieldTypes::is_condition_controller( $field['type'] ) ) {
				continue;
			}
			$id = $field['id'];
			$v  = $raw[ $id ] ?? null;
			switch ( $field['type'] ) {
				case 'number':
				case 'slider':
				case 'quantity':
					$out[ $id ] = (string) self::clamped_number( $field, $v );
					break;
				case 'select':
				case 'radio':
					$out[ $id ] = self::valid_slug( $field, is_string( $v ) ? $v : '' );
					break;
				case 'checkbox_group':
					$out[ $id ] = implode( ',', self::valid_slugs( $field, is_array( $v ) ? $v : [] ) );
					break;
				case 'toggle':
					$out[ $id ] = self::toggle_on( $field, $v ) ? '1' : '';
					break;
				case 'text':
				case 'date':
				case 'email':
				case 'phone':
				case 'url':
				case 'textarea':
					$out[ $id ] = is_string( $v ) ? trim( $v ) : '';
					break;
			}
		}
		return $out;
	}

	/** Spec §6 formula value map for a single input field (scaled). */
	private static function input_amount( array $field, $v ): int {
		switch ( $field['type'] ) {
			case 'number':
			case 'slider':
			case 'quantity':
				return DecimalMath::toScaled( self::clamped_number( $field, $v ) );
			case 'select':
			case 'radio':
				$slug = self::valid_slug( $field, is_string( $v ) ? $v : '' );
				foreach ( $field['options'] as $opt ) {
					if ( $opt['value'] === $slug ) {
						return DecimalMath::toScaled( $opt['price'] );
					}
				}
				return 0;
			case 'checkbox_group':
				$sum      = 0;
				$selected = self::valid_slugs( $field, is_array( $v ) ? $v : [] );
				foreach ( $field['options'] as $opt ) {
					if ( in_array( $opt['value'], $selected, true ) ) {
						$sum = DecimalMath::add( $sum, DecimalMath::toScaled( $opt['price'] ) );
					}
				}
				return $sum;
			case 'toggle':
				return self::toggle_on( $field, $v ) ? DecimalMath::toScaled( $field['price'] ) : 0;
		}
		return 0;
	}

	private static function clamped_number( array $field, $v ): float {
		$n = is_numeric( $v ) ? (float) $v : (float) ( $field['default'] ?? 0 );
		if ( isset( $field['min'] ) && null !== $field['min'] ) {
			$n = max( (float) $field['min'], $n );
		}
		if ( isset( $field['max'] ) && null !== $field['max'] ) {
			$n = min( (float) $field['max'], $n );
		}
		return $n;
	}

	private static function valid_slug( array $field, string $v ): string {
		foreach ( $field['options'] as $opt ) {
			if ( $opt['value'] === $v ) {
				return $v;
			}
		}
		return '';
	}

	private static function valid_slugs( array $field, array $vs ): array {
		$valid = array_column( $field['options'], 'value' );
		return array_values( array_intersect( array_map( 'strval', $vs ), $valid ) );
	}

	/** Currency line items = choice/toggle (their amounts are money); numeric inputs display as plain counts. */
	private static function is_priced( array $field ): bool {
		return FieldTypes::is_choice( $field['type'] ) || 'toggle' === $field['type'];
	}

	/** JSON-tolerant on/off: null ⇒ field default; 0, 0.0, '', '0', false, [] ⇒ off; anything else ⇒ on. Single source — used by BOTH value maps so they cannot drift. */
	private static function toggle_on( array $field, $v ): bool {
		if ( null === $v ) {
			return ! empty( $field['default'] );
		}
		if ( false === $v ) {
			return false;
		}
		$s = is_scalar( $v ) ? (string) $v : '';
		return '' !== $s && '0' !== $s;
	}

	/**
	 * Row math for one repeater (spec §3.1). Absent/non-array raw ⇒ minRows default
	 * rows (renderer parity); rows past maxRows sliced; rowExpression evaluated
	 * row-locally with the SAME Evaluator, or — when empty (price mode) — the sum of
	 * PRICED children only (option prices, toggle price, per spec §3.1 "ordinary price
	 * contributions"); number/slider/quantity children feed {refs} and contribute 0.
	 *
	 * @param mixed $raw Array of row objects { childId: value } or anything else.
	 * @return array{sum:int, rows: array<int, array{label:string, total:int, values:array<string,string>}>, error:string}
	 */
	private static function repeater_result( array $field, $raw ): array {
		$children = (array) ( $field['fields'] ?? [] );
		$maxRows  = min( (int) ( $field['maxRows'] ?? 50 ), 50 );
		$rowsRaw  = is_array( $raw )
			? array_slice( array_values( $raw ), 0, $maxRows )
			: array_fill( 0, (int) ( $field['minRows'] ?? 1 ), [] );

		$ast = null;
		if ( '' !== (string) ( $field['rowExpression'] ?? '' ) ) {
			try {
				$ast = Formula::compile( $field['rowExpression'] );
			} catch ( FormulaError $e ) {
				return [
					'sum'   => 0,
					'rows'  => [],
					'error' => $e->getErrorCode(),
				];
			}
		}

		$sum   = 0;
		$rows  = [];
		$error = '';
		foreach ( $rowsRaw as $i => $rowRaw ) {
			$rowRaw   = is_array( $rowRaw ) ? $rowRaw : [];
			$rowMap   = [];
			$display  = [];
			$priceSum = 0;
			foreach ( $children as $child ) {
				$cid             = $child['id'];
				$v               = $rowRaw[ $cid ] ?? null;
				$rowMap[ $cid ]  = self::input_amount( $child, $v );
				$display[ $cid ] = self::display_value( $child, $v );
				if ( self::is_priced( $child ) ) {
					// Price mode counts ONLY priced children — a number/slider/quantity
					// raw value is NOT currency (it is only a {ref} for rowExpression).
					$priceSum = DecimalMath::add( $priceSum, $rowMap[ $cid ] );
				}
			}
			if ( null !== $ast ) {
				try {
					$total = Formula::evaluate( $ast, $rowMap );
				} catch ( FormulaError $e ) {
					$total = 0;
					$error = $e->getErrorCode();
				}
			} else {
				$total = $priceSum;
			}
			$rows[] = [
				'label'  => self::row_label( $field, $i + 1 ),
				'total'  => $total,
				'values' => $display,
			];
			$sum    = DecimalMath::add( $sum, $total );
		}

		return [
			'sum'   => $sum,
			'rows'  => $rows,
			'error' => $error,
		];
	}

	/** "{n}" substitution; empty template falls back to "<label> <n>". Mirrored in compute.js. */
	private static function row_label( array $field, int $n ): string {
		$tpl = (string) ( $field['rowLabel'] ?? '' );
		if ( '' === $tpl ) {
			return trim( (string) ( $field['label'] ?? '' ) . ' ' . $n );
		}
		return str_replace( '{n}', (string) $n, $tpl );
	}

	/** Human-readable child value for entries surfaces (PHP-only; not part of JS parity). */
	private static function display_value( array $child, $v ): string {
		switch ( $child['type'] ) {
			case 'number':
			case 'slider':
			case 'quantity':
				return DecimalMath::fromScaled( DecimalMath::toScaled( self::clamped_number( $child, $v ) ) );
			case 'select':
			case 'radio':
				$slug = self::valid_slug( $child, is_string( $v ) ? $v : '' );
				foreach ( $child['options'] as $opt ) {
					if ( $opt['value'] === $slug ) {
						return $opt['label'];
					}
				}
				return '';
			case 'checkbox_group':
				$selected = self::valid_slugs( $child, is_array( $v ) ? $v : [] );
				$labels   = [];
				foreach ( $child['options'] as $opt ) {
					if ( in_array( $opt['value'], $selected, true ) ) {
						$labels[] = $opt['label'];
					}
				}
				return implode( ', ', $labels );
			case 'toggle':
				return self::toggle_on( $child, $v ) ? '1' : '';
		}
		return '';
	}
}
