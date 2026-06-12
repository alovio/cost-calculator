<?php
namespace Alovio\Calculator\Fields;

final class FieldSchema {

	public const SCHEMA_VERSION   = 1;
	public const EXPRESSION_LIMIT = 1000;
	private const OPERATORS       = [ 'is', 'is_not', 'contains', 'gt', 'lt' ];

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
				'theme'     => [ 'accent' => '#0a66ff' ],
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
			$fields[]    = self::normalize_field( $field, $id, $type );
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

	private static function normalize_field( array $raw, string $id, string $type ): array {
		$field = [
			'id'              => $id,
			'type'            => $type,
			'label'           => sanitize_text_field( (string) ( $raw['label'] ?? '' ) ),
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
				// Deliberately NO price on numeric fields — formulas do the multiplying (§6 value maps stay unambiguous).
				break;

			case 'toggle':
				$field['price']   = isset( $raw['price'] ) && is_numeric( $raw['price'] ) ? (float) $raw['price'] : 0.0;
				$field['default'] = ! empty( $raw['default'] );
				break;

			case 'select':
			case 'radio':
			case 'checkbox_group':
				$field['options'] = self::normalize_options( (array) ( $raw['options'] ?? [] ) );
				break;

			case 'formula':
				$field['expression'] = substr( trim( (string) ( $raw['expression'] ?? '' ) ), 0, self::EXPRESSION_LIMIT );
				break;

			case 'html':
				$field['content'] = wp_kses_post( (string) ( $raw['content'] ?? '' ) );
				break;

			case 'text':
			case 'heading':
				$field['placeholder'] = sanitize_text_field( (string) ( $raw['placeholder'] ?? '' ) );
				break;
		}

		return $field;
	}

	private static function normalize_options( array $rawOptions ): array {
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
				'value' => $value,
				'label' => sanitize_text_field( (string) ( $opt['label'] ?? '' ) ),
				'price' => isset( $opt['price'] ) && is_numeric( $opt['price'] ) ? (float) $opt['price'] : 0.0,
				'image' => isset( $opt['image'] ) ? max( 0, (int) $opt['image'] ) : 0,
			];
		}
		return $options;
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
		$field['conditionAction'] = 'hide' === ( $field['conditionAction'] ?? '' ) ? 'hide' : 'show'; // require coerced (spec §6)
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

		return [
			'currency'  => [
				'symbol'      => '' !== $symbol ? $symbol : $d['currency']['symbol'],
				'position'    => in_array( $currency['position'] ?? '', [ 'before', 'after' ], true ) ? $currency['position'] : $d['currency']['position'],
				'decimals'    => is_numeric( $decimals ) && (int) $decimals >= 0 && (int) $decimals <= 4 ? (int) $decimals : $d['currency']['decimals'],
				'thousandSep' => sanitize_text_field( (string) ( $currency['thousandSep'] ?? $d['currency']['thousandSep'] ) ),
				'decimalSep'  => sanitize_text_field( (string) ( $currency['decimalSep'] ?? $d['currency']['decimalSep'] ) ),
			],
			'theme'     => [ 'accent' => '' !== (string) $accent ? $accent : $d['theme']['accent'] ],
			'quoteForm' => [
				'enabled'        => ! empty( $quote['enabled'] ),
				'fields'         => $fields,
				'notifyEmail'    => sanitize_email( (string) ( $quote['notifyEmail'] ?? '' ) ),
				'successMessage' => sanitize_text_field( (string) ( $quote['successMessage'] ?? '' ) ),
			],
		];
	}
}
