<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only access to Cost Calculator Builder storage → the CcbCalc struct
 * (shape documented in tests/fixtures/ccb/README.md and the v2 plan). The
 * mapper and UI depend on CcbCalc only, never on CCB's raw format: ALL format
 * knowledge is confined to parse()/type_from_alias()/the small extractors,
 * verified against tests/fixtures/ccb/ (Task 8.1).
 */
final class CcbReader {

	/** Meta key CCB stores its field list under (pinned by the fixtures). */
	public const META_FIELDS = 'stm-fields';

	/** @return array<int, array{id:int, title:string, fieldCount:int}> */
	public function list(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft' ) ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb.
				CcbDetector::POST_TYPE
			)
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$calc = $this->read( (int) $row->ID );
			if ( null !== $calc ) {
				$out[] = array(
					'id'         => $calc['id'],
					'title'      => $calc['title'],
					'fieldCount' => count( $calc['fields'] ),
				);
			}
		}
		return $out;
	}

	/** @return array|null CcbCalc, or null when the stored data is unreadable. */
	public function read( int $id ): ?array {
		$raw   = get_post_meta( $id, self::META_FIELDS, true ); // WP unserializes stored arrays for us.
		$title = get_post_field( 'post_title', $id );
		return self::parse( $id, is_string( $title ) ? $title : '', $raw );
	}

	/**
	 * Pure parse: raw stored value → CcbCalc. Unit-tested against the fixtures.
	 *
	 * @param mixed $raw_fields Raw meta value (expected: array of field arrays).
	 */
	public static function parse( int $id, string $title, $raw_fields ): ?array {
		if ( ! is_array( $raw_fields ) || array() === $raw_fields ) {
			return null;
		}
		$fields = array();
		foreach ( self::flatten( $raw_fields ) as $f ) {
			$fields[] = self::parse_field( $f );
		}
		if ( array() === $fields ) {
			return null;
		}
		return array(
			'id'     => $id,
			'title'  => sanitize_text_field( $title ),
			'fields' => $fields,
		);
	}

	/**
	 * CCB 4.x nests real fields inside page-break → groupElements[] → section →
	 * fields[] (see fixtures). Structural containers are unwrapped, never emitted;
	 * flat lists (older majors) pass through unchanged.
	 *
	 * @param array $raw Raw field arrays (possibly nested).
	 * @return array<int, array> Flat list of raw field arrays.
	 */
	private static function flatten( array $raw ): array {
		$out = array();
		foreach ( $raw as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			if ( isset( $f['groupElements'] ) && is_array( $f['groupElements'] ) ) {
				foreach ( $f['groupElements'] as $group ) {
					if ( is_array( $group ) && isset( $group['fields'] ) && is_array( $group['fields'] ) ) {
						$out = array_merge( $out, self::flatten( $group['fields'] ) );
					}
				}
				continue; // the page-break container itself is structural
			}
			$type = self::type_from_alias( sanitize_key( (string) ( $f['alias'] ?? '' ) ) );
			if ( in_array( $type, array( 'section', 'group' ), true ) && isset( $f['fields'] ) && is_array( $f['fields'] ) ) {
				$out = array_merge( $out, self::flatten( $f['fields'] ) );
				continue;
			}
			$out[] = $f;
		}
		return $out;
	}

	/** @param array $f Raw CCB field array. */
	private static function parse_field( array $f ): array {
		$alias = sanitize_key( (string) ( $f['alias'] ?? '' ) );
		if ( '' === $alias ) {
			return array(
				'type'               => 'unknown',
				'alias'              => '',
				'label'              => sanitize_text_field( (string) ( $f['label'] ?? '' ) ),
				'unsupported_reason' => 'unrecognized structure (no alias)',
			);
		}
		$type = self::type_from_alias( $alias );
		$out  = array(
			'type'  => $type,
			'alias' => $alias,
			'label' => sanitize_text_field( (string) ( $f['label'] ?? $alias ) ),
		);
		if ( isset( $f['options'] ) && is_array( $f['options'] ) ) {
			$out['options'] = self::parse_options( $f['options'] );
		}
		// 4.0.14 uses 'default'; older majors used 'defaultValue' — accept both.
		foreach ( array(
			'min'     => array( 'minValue' ),
			'max'     => array( 'maxValue' ),
			'step'    => array( 'step' ),
			'default' => array( 'defaultValue', 'default' ),
		) as $ours => $theirs ) {
			foreach ( $theirs as $key ) {
				if ( isset( $f[ $key ] ) && is_numeric( $f[ $key ] ) ) {
					$out[ $ours ] = (float) $f[ $key ];
					break;
				}
			}
		}
		if ( isset( $f['checkedPrice'] ) && is_numeric( $f['checkedPrice'] ) ) {
			$out['price'] = (float) $f['checkedPrice']; // legacy single-switch toggles only (4.0.14 toggles are options-based).
		}
		if ( 'total' === $type ) {
			$out['formula'] = trim( (string) ( $f['costCalcFormula'] ?? '' ) );
		}
		return $out;
	}

	/**
	 * CCB aliases encode the type as the prefix before "_field_id_". The alias
	 * is sanitize_key()-lowercased first, so camelCase prefixes normalize
	 * (dropDown_field_id_1 → "dropdown").
	 */
	private static function type_from_alias( string $alias ): string {
		$pos = strpos( $alias, '_field_id_' );
		if ( false === $pos ) {
			return 'unknown';
		}
		return substr( $alias, 0, $pos );
	}

	/**
	 * CCB option encoding (per fixtures): [{optionText, optionValue}] where
	 * optionValue is the PLAIN price as a decimal string ("2.5"), possibly ""
	 * for unpriced options. strtok also tolerates the legacy "<price>_<index>"
	 * encoding from older CCB majors.
	 */
	private static function parse_options( array $raw ): array {
		$out = array();
		foreach ( $raw as $opt ) {
			if ( ! is_array( $opt ) ) {
				continue;
			}
			$value = (string) ( $opt['optionValue'] ?? '' );
			$out[] = array(
				'label' => sanitize_text_field( (string) ( $opt['optionText'] ?? '' ) ),
				'price' => (float) strtok( $value, '_' ),
			);
		}
		return $out;
	}
}
