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

	public static function run( array $config, array $rawValues ): array {
		$fields = $config['fields'];

		$conditionValues = self::condition_values( $fields, $rawValues );
		$active          = ConditionalLogic::active_map( [ 'fields' => $fields ], $conditionValues );

		// §6 formula value map for inputs; inactive ⇒ 0.
		$values = [];
		foreach ( $fields as $field ) {
			if ( ! FieldTypes::is_referenceable( $field['type'] ) || 'formula' === $field['type'] ) {
				continue;
			}
			$values[ $field['id'] ] = ( $active[ $field['id'] ] ?? true )
				? self::input_amount( $field, $rawValues[ $field['id'] ] ?? null )
				: 0;
		}

		// Formulas in dependency order (§7/§8).
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
			$values[ $id ] = 0;
		}
		foreach ( $graph['order'] as $id ) {
			if ( isset( $errors[ $id ] ) || ! isset( $asts[ $id ] ) ) {
				$values[ $id ] = 0;
				continue;
			}
			if ( false === ( $active[ $id ] ?? true ) ) {
				$values[ $id ] = 0; // Inactive formulas contribute 0, skip evaluation (§6).
				continue;
			}
			try {
				$values[ $id ] = Formula::evaluate( $asts[ $id ], $values );
			} catch ( FormulaError $e ) {
				$errors[ $id ] = $e->getErrorCode();
				$values[ $id ] = 0;
			}
		}

		// Summary line items + grand total (= last active formula in field order).
		$lineItems   = [];
		$totalScaled = null;
		foreach ( $fields as $field ) {
			$id = $field['id'];
			if ( 'formula' === $field['type'] && ( $active[ $id ] ?? true ) ) {
				$totalScaled = $values[ $id ];
			}
			if ( empty( $field['showInSummary'] ) || false === ( $active[ $id ] ?? true ) || ! isset( $values[ $id ] ) ) {
				continue;
			}
			$isCurrency  = 'formula' === $field['type'] || self::is_priced( $field );
			$lineItems[] = [ 'id' => $id, 'label' => $field['label'], 'amount' => $values[ $id ], 'isCurrency' => $isCurrency ];
		}

		return [
			'conditionValues' => $conditionValues,
			'active'          => $active,
			'values'          => $values,
			'lineItems'       => $lineItems,
			'totalScaled'     => $totalScaled,
			'errors'          => $errors,
		];
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
}
