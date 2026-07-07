<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Import;

defined( 'ABSPATH' ) || exit;

/**
 * CcbCalc → our calculator config (spec §4.1). Pure: no DB, no writes.
 * Output feeds FieldRepository::save() → FieldSchema::normalize(), which
 * regenerates option slugs and re-validates everything — the mapper only has
 * to produce FieldSchema-compatible structures.
 *
 * An import NEVER hard-fails on content: unmappable fields land in skipped[],
 * untranslatable formulas import as an empty expression + warnings[] entry.
 */
final class CcbMapper {

	/**
	 * Raw CCB type token → our field type. KEYS follow Task 8.1's fixture
	 * reconciliation (tokens are lowercased alias prefixes; 4.0.14 emits
	 * "dropdown"); values are our canonical FieldTypes tokens. Deliberately
	 * absent (skipped with a report line): page_break, section, html, line,
	 * file_upload, geolocation, validated_form, multi_range, repeater, group.
	 */
	public const TYPE_MAP = array(
		'range'       => 'slider',
		'drop_down'   => 'select',
		'dropdown'    => 'select',
		'checkbox'    => 'checkbox_group',
		'radio'       => 'radio',
		'toggle'      => 'toggle',
		'switch'      => 'toggle',
		'quantity'    => 'quantity',
		'text'        => 'text',
		'date_picker' => 'date',
		'datepicker'  => 'date',
		'total'       => 'formula',
	);

	/**
	 * Value-bearing types: legal inside translated formulas (mirror of
	 * FieldTypes::REFERENCEABLE minus 'number', which we never produce) AND
	 * shown in the summary by default; informational types are neither.
	 */
	private const VALUE_TYPES = array( 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'formula' );

	/**
	 * @param array $ccb CcbCalc (see CcbReader docblock).
	 * @return array{title:string, config:array, skipped:array<int,string>, warnings:array<int,string>}
	 */
	public static function map( array $ccb ): array {
		$fields   = array();
		$skipped  = array();
		$warnings = array();
		$ref_ids  = array(); // ids legal inside translated formulas
		$used_ids = array();
		$totals   = array();

		foreach ( (array) ( $ccb['fields'] ?? array() ) as $f ) {
			$label = (string) ( '' !== (string) ( $f['label'] ?? '' ) ? $f['label'] : ( $f['alias'] ?? '' ) );
			if ( ! empty( $f['unsupported_reason'] ) ) {
				/* translators: 1: field label, 2: technical reason */
				$skipped[] = sprintf( __( '“%1$s” skipped — %2$s.', 'alovio-calculator' ), $label, $f['unsupported_reason'] );
				continue;
			}
			$our_type = self::TYPE_MAP[ strtolower( (string) ( $f['type'] ?? '' ) ) ] ?? null;
			if ( null === $our_type ) {
				/* translators: 1: field label, 2: CCB field type token */
				$skipped[] = sprintf( __( '“%1$s” skipped — the “%2$s” field type has no free equivalent in Alovio Calculator.', 'alovio-calculator' ), $label, (string) ( $f['type'] ?? '' ) );
				continue;
			}
			if ( 'formula' === $our_type ) {
				$totals[] = $f;
				continue; // second pass — all referenceable ids must be known first
			}
			if ( 'toggle' === $our_type && ! empty( $f['options'] ) ) {
				// 4.0.14 toggles are a LIST of priced switches (fixtures) — that IS our checkbox group.
				$our_type = 'checkbox_group';
			}
			$mapped                    = self::map_simple( $f, $our_type, $label, $used_ids );
			$used_ids[ $mapped['id'] ] = true;
			$fields[]                  = $mapped;
			if ( in_array( $our_type, self::VALUE_TYPES, true ) ) {
				$ref_ids[] = $mapped['id'];
			}
		}

		foreach ( $totals as $f ) {
			$label      = (string) ( '' !== (string) ( $f['label'] ?? '' ) ? $f['label'] : ( $f['alias'] ?? '' ) );
			$raw        = (string) ( $f['formula'] ?? '' );
			$translated = self::translate_formula( $raw, $ref_ids );
			if ( ! $translated['ok'] ) {
				/* translators: 1: formula field label, 2: the original CCB formula */
				$warnings[] = sprintf( __( 'The formula for “%1$s” could not be translated automatically and was imported empty — rebuild it in the builder. Original: %2$s', 'alovio-calculator' ), $label, $raw );
			}
			$id              = self::unique_id( (string) ( $f['alias'] ?? '' ), $used_ids );
			$used_ids[ $id ] = true;
			$fields[]        = array(
				'id'            => $id,
				'type'          => 'formula',
				'label'         => $label,
				'expression'    => $translated['expression'],
				'showInSummary' => true,
			);
			$ref_ids[]       = $id; // later totals may reference earlier ones
		}

		return array(
			'title'    => (string) ( $ccb['title'] ?? '' ),
			'config'   => array(
				'schemaVersion' => 1,
				'fields'        => $fields,
				'settings'      => array(), // FieldSchema::normalize fills every default
			),
			'skipped'  => $skipped,
			'warnings' => $warnings,
		);
	}

	/** @param array<string,bool> $used_ids */
	private static function map_simple( array $f, string $our_type, string $label, array $used_ids ): array {
		$out = array(
			'id'            => self::unique_id( (string) $f['alias'], $used_ids ),
			'type'          => $our_type,
			'label'         => $label,
			'showInSummary' => in_array( $our_type, self::VALUE_TYPES, true ),
		);
		switch ( $our_type ) {
			case 'slider':
			case 'quantity':
				foreach ( array( 'min', 'max', 'step', 'default' ) as $k ) {
					if ( isset( $f[ $k ] ) ) {
						$out[ $k ] = (float) $f[ $k ];
					}
				}
				break;
			case 'select':
			case 'radio':
			case 'checkbox_group':
				$out['options'] = array();
				foreach ( (array) ( $f['options'] ?? array() ) as $opt ) {
					$out['options'][] = array(
						'label' => (string) ( $opt['label'] ?? '' ),
						'price' => (float) ( $opt['price'] ?? 0 ),
						// no 'value': FieldSchema::normalize_options generates fresh opt_ slugs
					);
				}
				break;
			case 'toggle':
				$out['price'] = (float) ( $f['price'] ?? 0 );
				break;
			// 'text' and 'date' carry label only.
		}
		return $out;
	}

	/** @param array<string,bool> $used_ids */
	private static function unique_id( string $alias, array $used_ids ): string {
		$id        = '' !== $alias ? $alias : 'ccb_field';
		$n         = 2;
		$candidate = $id;
		while ( isset( $used_ids[ $candidate ] ) ) {
			$candidate = $id . '_' . $n;
			++$n;
		}
		return $candidate;
	}

	/**
	 * CCB formula → our {ref} syntax. Grammar accepted: known aliases, decimal
	 * numbers, + - * / ( ) and whitespace. ANYTHING else (their functions,
	 * conditionals, unknown/skipped aliases) aborts to the empty-expression
	 * fallback — we never guess at semantics.
	 *
	 * @param string[] $known_ids
	 * @return array{expression:string, ok:bool}
	 */
	public static function translate_formula( string $raw, array $known_ids ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array(
				'expression' => '',
				'ok'         => false,
			);
		}
		$parts = preg_split( '/([a-zA-Z_][a-zA-Z0-9_]*)/', $raw, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = '';
		foreach ( (array) $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			if ( preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/', $part ) ) {
				$id = strtolower( $part );
				if ( ! in_array( $id, $known_ids, true ) ) {
					return array(
						'expression' => '',
						'ok'         => false,
					);
				}
				$out .= '{' . $id . '}';
				continue;
			}
			if ( ! preg_match( '/^[\s0-9+\-*\/().]*$/', $part ) ) {
				return array(
					'expression' => '',
					'ok'         => false,
				);
			}
			$out .= $part;
		}
		return array(
			'expression' => trim( $out ),
			'ok'         => true,
		);
	}
}
