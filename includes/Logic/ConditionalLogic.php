<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Logic;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for evaluating a field's conditional rules.
 *
 * Reused verbatim from the CoreLabs Product Options engine. Context-agnostic:
 * it compares string values in a flat `$values` map and does not care whether a
 * rule's `field` key is a sibling field id or a checkout "source token"
 * (e.g. `user_role`, `cart_has_category:12`, `payment_method`). The checkout
 * value map is assembled by CheckoutConditionData.
 *
 * Mirrored in JS (src/frontend/conditional-logic.js); kept in lockstep by
 * tests/fixtures/conditional-cases.json.
 */
final class ConditionalLogic {

	/**
	 * Raw operator match for one rule (no action applied).
	 *
	 * @param array<string,mixed>|null $rule
	 * @param array<string,mixed>      $values
	 */
	public static function matches( ?array $rule, array $values ): bool {
		if ( ! is_array( $rule ) ) {
			return false;
		}
		$field = $rule['field'] ?? '';
		$left  = isset( $values[ $field ] ) ? (string) $values[ $field ] : '';
		$right = isset( $rule['value'] ) ? (string) $rule['value'] : '';

		switch ( $rule['operator'] ?? 'is' ) {
			case 'is_not':
				return $left !== $right;
			case 'contains':
				return '' !== $right && false !== strpos( $left, $right );
			case 'gt':
				return is_numeric( $left ) && is_numeric( $right ) && (float) $left > (float) $right;
			case 'gte':
				return is_numeric( $left ) && is_numeric( $right ) && (float) $left >= (float) $right;
			case 'lt':
				return is_numeric( $left ) && is_numeric( $right ) && (float) $left < (float) $right;
			case 'lte':
				return is_numeric( $left ) && is_numeric( $right ) && (float) $left <= (float) $right;
			case 'is_empty':
				return '' === $left;
			case 'is_not_empty':
				return '' !== $left;
			case 'is':
			default:
				return $left === $right;
		}
	}

	/**
	 * Combine a field's rules into a single boolean (before action).
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $values
	 * @return array{has:bool,combined:bool,action:string} has=false when the field has no rules
	 */
	private static function evaluate_rules( array $field, array $values ): array {
		// Multiple conditions (AND/OR).
		if ( ! empty( $field['conditions'] ) && is_array( $field['conditions'] ) ) {
			$results = array();
			foreach ( $field['conditions'] as $rule ) {
				$results[] = self::matches( $rule, $values );
			}
			$any      = ( 'any' === ( $field['conditionMatch'] ?? 'all' ) );
			$combined = $any ? in_array( true, $results, true ) : ! in_array( false, $results, true );
			return array(
				'has'      => true,
				'combined' => $combined,
				'action'   => (string) ( $field['conditionAction'] ?? 'show' ),
			);
		}
		// Single condition.
		$cond = $field['condition'] ?? null;
		if ( is_array( $cond ) ) {
			return array(
				'has'      => true,
				'combined' => self::matches( $cond, $values ),
				'action'   => (string) ( $cond['action'] ?? 'show' ),
			);
		}
		return array(
			'has'      => false,
			'combined' => true,
			'action'   => 'show',
		);
	}

	/**
	 * Whether the field is shown/active given submitted values.
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $values
	 */
	public static function is_active( array $field, array $values ): bool {
		$r = self::evaluate_rules( $field, $values );
		if ( ! $r['has'] || 'require' === $r['action'] ) {
			return true; // 'require' affects validation, not visibility.
		}
		return 'hide' === $r['action'] ? ! $r['combined'] : $r['combined'];
	}

	/**
	 * Whether a require-action rule is currently satisfied (field is mandatory now).
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $values
	 */
	public static function requires( array $field, array $values ): bool {
		$r = self::evaluate_rules( $field, $values );
		return $r['has'] && 'require' === $r['action'] && $r['combined'];
	}

	/**
	 * Field ids referenced by a field's rule(s).
	 *
	 * @param array<string,mixed> $field
	 * @return string[]
	 */
	private static function controllers( array $field ): array {
		$ids = array();
		if ( ! empty( $field['conditions'] ) && is_array( $field['conditions'] ) ) {
			foreach ( $field['conditions'] as $rule ) {
				if ( isset( $rule['field'] ) ) {
					$ids[] = (string) $rule['field'];
				}
			}
		} elseif ( is_array( $field['condition'] ?? null ) && isset( $field['condition']['field'] ) ) {
			$ids[] = (string) $field['condition']['field'];
		}
		return $ids;
	}

	/**
	 * Resolve active state for every field, transitively (a field is active only
	 * if its own rules pass AND every referenced controller is active). Cycle-safe.
	 *
	 * Note: controllers that are source tokens (not field ids) simply aren't in
	 * the field map, so resolve() returns true for them — correct, since context
	 * sources are always "present".
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $values
	 * @return array<string,bool> field id => active
	 */
	public static function active_map( array $group, array $values ): array {
		$by_id = array();
		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$by_id[ (string) ( $f['id'] ?? '' ) ] = $f;
		}

		$cache    = array();
		$in_stack = array();

		$resolve = function ( string $id ) use ( &$resolve, $by_id, $values, &$cache, &$in_stack ): bool {
			if ( array_key_exists( $id, $cache ) ) {
				return $cache[ $id ];
			}
			if ( ! isset( $by_id[ $id ] ) || isset( $in_stack[ $id ] ) ) {
				return true;
			}
			$in_stack[ $id ] = true;
			$field           = $by_id[ $id ];

			$active = self::is_active( $field, $values );
			foreach ( self::controllers( $field ) as $cid ) {
				$active = $active && $resolve( $cid );
			}

			unset( $in_stack[ $id ] );
			$cache[ $id ] = $active;
			return $active;
		};

		$map = array();
		foreach ( array_keys( $by_id ) as $id ) {
			$map[ $id ] = $resolve( $id );
		}
		return $map;
	}
}
