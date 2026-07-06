<?php
namespace Alovio\Calculator\Fields;

use Alovio\Calculator\Formula\Formula;
use Alovio\Calculator\Formula\FormulaError;

final class FieldSchema {

	public const SCHEMA_VERSION    = 1;
	public const EXPRESSION_LIMIT  = 1000;
	public const REPEATER_MAX_ROWS = 50;
	private const OPERATORS        = [ 'is', 'is_not', 'contains', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty' ];
	private const THEMES           = [ 'classic', 'minimal', 'midnight', 'soft', 'bold', 'slate' ];

	public static function defaults(): array {
		return [
			'schemaVersion' => self::SCHEMA_VERSION,
			'fields'        => [],
			'settings'      => [
				'currency'  => [
					'symbol'      => '$',
					'position'    => 'before',
					'decimals'    => 2,
					'thousandSep' => ',',
					'decimalSep'  => '.',
				],
				'theme'     => [
					'accent' => '#f97316',
					'preset' => 'classic',
					'layout' => 'single',
				],
				'quoteForm' => [
					'enabled'        => false,
					'fields'         => [ 'name', 'email' ],
					'notifyEmail'    => '',
					'successMessage' => '',
				],
			],
		];
	}

	public static function normalize( array $raw ): array {
		$out    = self::defaults();
		$types  = FieldTypes::all();
		$seen   = [];
		$fields = [];

		foreach ( (array) ( $raw['fields'] ?? [] ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			$id   = sanitize_key( (string) ( $field['id'] ?? '' ) );
			if ( '' === $id || isset( $seen[ $id ] ) || ! in_array( $type, $types, true ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$fields[]    = self::normalize_field( $field, $id, $type, $seen );
		}

		// Conditions can only be validated once all ids are known.
		$controllers = [];
		foreach ( $fields as $field ) {
			if ( FieldTypes::is_condition_controller( $field['type'] ) ) {
				$controllers[ $field['id'] ] = true;
			}
		}
		foreach ( $fields as &$field ) {
			$field = self::normalize_conditions( $field, $controllers );
		}
		unset( $field );

		$out['fields']   = $fields;
		$out['settings'] = self::normalize_settings( (array) ( $raw['settings'] ?? [] ) );
		return $out;
	}

	private static function normalize_field( array $raw, string $id, string $type, array &$seen ): array {
		$field = [
			'id'              => $id,
			'type'            => $type,
			'label'           => sanitize_text_field( (string) ( $raw['label'] ?? '' ) ),
			'help'            => sanitize_text_field( (string) ( $raw['help'] ?? '' ) ),
			'showInSummary'   => ! empty( $raw['showInSummary'] ),
			// Carried through raw; validated in normalize_conditions() once all ids are known.
			'conditions'      => $raw['conditions'] ?? [],
			'conditionMatch'  => $raw['conditionMatch'] ?? 'all',
			'conditionAction' => $raw['conditionAction'] ?? 'show',
		];

		switch ( $type ) {
			case 'number':
			case 'slider':
			case 'quantity':
				foreach ( [ 'min', 'max', 'step', 'default' ] as $k ) {
					$field[ $k ] = isset( $raw[ $k ] ) && is_numeric( $raw[ $k ] ) ? (float) $raw[ $k ] : null;
				}
				if ( 'slider' === $type ) {
					$field['unit'] = sanitize_text_field( (string) ( $raw['unit'] ?? '' ) );
				}
				// Deliberately NO price on numeric fields — formulas do the multiplying (§6 value maps stay unambiguous).
				break;

			case 'toggle':
				$field['price']   = isset( $raw['price'] ) && is_numeric( $raw['price'] ) ? (float) $raw['price'] : 0.0;
				$field['default'] = ! empty( $raw['default'] );
				break;

			case 'select':
			case 'radio':
			case 'checkbox_group':
				$field['options'] = self::normalize_options( (array) ( $raw['options'] ?? [] ), $type );
				break;

			case 'formula':
				$field['expression'] = substr( trim( (string) ( $raw['expression'] ?? '' ) ), 0, self::EXPRESSION_LIMIT );
				break;

			case 'html':
				$field['content'] = wp_kses_post( (string) ( $raw['content'] ?? '' ) );
				break;

			case 'text':
			case 'heading':
			case 'date':
			case 'email':
			case 'phone':
			case 'url':
			case 'textarea':
				$field['placeholder'] = sanitize_text_field( (string) ( $raw['placeholder'] ?? '' ) );
				break;

			case 'step':
				$field['description'] = sanitize_text_field( (string) ( $raw['description'] ?? '' ) );
				break;

			case 'repeater':
				$field['fields']        = self::normalize_repeater_children( (array) ( $raw['fields'] ?? [] ), $seen );
				$min                    = isset( $raw['minRows'] ) && is_numeric( $raw['minRows'] ) ? (int) $raw['minRows'] : 1;
				$max                    = isset( $raw['maxRows'] ) && is_numeric( $raw['maxRows'] ) ? (int) $raw['maxRows'] : 10;
				$field['minRows']       = max( 1, min( $min, self::REPEATER_MAX_ROWS ) );
				$field['maxRows']       = max( $field['minRows'], min( $max, self::REPEATER_MAX_ROWS ) );
				$field['addLabel']      = sanitize_text_field( (string) ( $raw['addLabel'] ?? '' ) );
				$field['rowLabel']      = sanitize_text_field( (string) ( $raw['rowLabel'] ?? '' ) );
				$field['rowExpression'] = self::normalize_row_expression(
					substr( trim( (string) ( $raw['rowExpression'] ?? '' ) ), 0, self::EXPRESSION_LIMIT ),
					array_column( $field['fields'], 'id' )
				);
				break;
		}

		return $field;
	}

	private static function normalize_options( array $rawOptions, string $type ): array {
		$options = [];
		$used    = [];
		foreach ( $rawOptions as $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			$value = sanitize_key( (string) ( $opt['value'] ?? '' ) );
			if ( '' === $value || 0 !== strpos( $value, 'opt_' ) || isset( $used[ $value ] ) ) {
				$value = self::generate_slug( $used );
			}
			$used[ $value ] = true;
			$options[]      = [
				'value'   => $value,
				'label'   => sanitize_text_field( (string) ( $opt['label'] ?? '' ) ),
				'price'   => isset( $opt['price'] ) && is_numeric( $opt['price'] ) ? (float) $opt['price'] : 0.0,
				'image'   => isset( $opt['image'] ) ? max( 0, (int) $opt['image'] ) : 0,
				'default' => ! empty( $opt['default'] ),
			];
		}
		// Single-choice fields keep at most ONE default (first wins) — spec §2.4.
		if ( in_array( $type, [ 'select', 'radio' ], true ) ) {
			$found = false;
			foreach ( $options as &$o ) {
				if ( $o['default'] && $found ) {
					$o['default'] = false;
				}
				$found = $found || $o['default'];
			}
			unset( $o );
		}
		return $options;
	}

	/**
	 * Children are restricted to REPEATER_CHILD_TYPES (no nesting) and carry NO
	 * conditional logic in v2.0 (spec §3.1). $seen is the GLOBAL slug registry —
	 * uniqueness holds across all levels.
	 */
	private static function normalize_repeater_children( array $rawChildren, array &$seen ): array {
		$children = [];
		foreach ( $rawChildren as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$type = (string) ( $child['type'] ?? '' );
			$id   = sanitize_key( (string) ( $child['id'] ?? '' ) );
			if ( '' === $id || isset( $seen[ $id ] ) || ! FieldTypes::is_repeater_child( $type ) ) {
				continue;
			}
			$seen[ $id ]                   = true;
			$normalized                    = self::normalize_field( $child, $id, $type, $seen );
			$normalized['conditions']      = [];
			$normalized['conditionMatch']  = 'all';
			$normalized['conditionAction'] = 'show';
			$children[]                    = $normalized;
		}
		return $children;
	}

	/**
	 * A rowExpression may reference CHILD ids only (spec §3.1 graph rule). Refs are
	 * extracted with the real Lexer/Parser. Compile failures are KEPT — they surface
	 * at runtime exactly like broken formula fields (error badge, sum 0).
	 */
	private static function normalize_row_expression( string $expr, array $childIds ): string {
		if ( '' === $expr ) {
			return '';
		}
		try {
			$refs = Formula::references( Formula::compile( $expr ) );
		} catch ( FormulaError $e ) {
			return $expr;
		}
		return array_diff( $refs, $childIds ) ? '' : $expr;
	}

	private static function generate_slug( array $used ): string {
		do {
			$slug = 'opt_' . substr( bin2hex( random_bytes( 4 ) ), 0, 6 );
		} while ( isset( $used[ $slug ] ) );
		return $slug;
	}

	private static function normalize_conditions( array $field, array $controllers ): array {
		$conditions = [];
		foreach ( (array) ( $field['conditions'] ?? [] ) as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$controller = sanitize_key( (string) ( $rule['field'] ?? '' ) );
			$operator   = (string) ( $rule['operator'] ?? '' );
			if ( ! isset( $controllers[ $controller ] ) || $controller === $field['id'] || ! in_array( $operator, self::OPERATORS, true ) ) {
				continue; // Formula/unknown/self controllers rejected (spec §6/§7).
			}
			$conditions[] = [
				'field'    => $controller,
				'operator' => $operator,
				'value'    => sanitize_text_field( (string) ( $rule['value'] ?? '' ) ),
			];
		}
		$field['conditions']      = $conditions;
		$field['conditionMatch']  = 'any' === ( $field['conditionMatch'] ?? '' ) ? 'any' : 'all';
		$action                   = (string) ( $field['conditionAction'] ?? 'show' );
		$field['conditionAction'] = in_array( $action, [ 'show', 'hide', 'require' ], true ) ? $action : 'show';
		return $field;
	}

	private static function normalize_settings( array $raw ): array {
		$d = self::defaults()['settings'];

		$currency = (array) ( $raw['currency'] ?? [] );
		$symbol   = sanitize_text_field( (string) ( $currency['symbol'] ?? $d['currency']['symbol'] ) );
		$decimals = $currency['decimals'] ?? $d['currency']['decimals'];

		$quote  = (array) ( $raw['quoteForm'] ?? [] );
		$fields = array_values( array_intersect( [ 'name', 'email', 'phone', 'message' ], (array) ( $quote['fields'] ?? $d['quoteForm']['fields'] ) ) );
		if ( ! in_array( 'name', $fields, true ) ) {
			array_unshift( $fields, 'name' );
		}
		if ( ! in_array( 'email', $fields, true ) ) {
			array_splice( $fields, 1, 0, 'email' );
		}

		$accent = sanitize_hex_color( (string) ( $raw['theme']['accent'] ?? '' ) );
		$preset = (string) ( $raw['theme']['preset'] ?? '' );
		$preset = in_array( $preset, self::THEMES, true ) ? $preset : $d['theme']['preset'];
		$layout = (string) ( $raw['theme']['layout'] ?? '' );
		$layout = in_array( $layout, [ 'single', 'wizard' ], true ) ? $layout : $d['theme']['layout'];

		return [
			'currency'  => [
				'symbol'      => '' !== $symbol ? $symbol : $d['currency']['symbol'],
				'position'    => in_array( $currency['position'] ?? '', [ 'before', 'after' ], true ) ? $currency['position'] : $d['currency']['position'],
				'decimals'    => is_numeric( $decimals ) && (int) $decimals >= 0 && (int) $decimals <= 4 ? (int) $decimals : $d['currency']['decimals'],
				'thousandSep' => sanitize_text_field( (string) ( $currency['thousandSep'] ?? $d['currency']['thousandSep'] ) ),
				'decimalSep'  => sanitize_text_field( (string) ( $currency['decimalSep'] ?? $d['currency']['decimalSep'] ) ),
			],
			'theme'     => [
				'accent' => '' !== (string) $accent ? $accent : $d['theme']['accent'],
				'preset' => $preset,
				'layout' => $layout,
			],
			'quoteForm' => [
				'enabled'        => ! empty( $quote['enabled'] ),
				'fields'         => $fields,
				'notifyEmail'    => sanitize_email( (string) ( $quote['notifyEmail'] ?? '' ) ),
				'successMessage' => sanitize_text_field( (string) ( $quote['successMessage'] ?? '' ) ),
			],
		];
	}
}
