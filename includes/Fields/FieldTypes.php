<?php
namespace Alovio\Calculator\Fields;

/**
 * Type-token conventions (canonical): the spec's "heading/divider" is the single
 * `heading` type (a divider is a heading with an empty label); the spec's
 * "checkbox-group" is the token `checkbox_group`.
 */
final class FieldTypes {

	public const FREE = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text', 'heading', 'html', 'formula' ];

	private const CHOICE = [ 'select', 'radio', 'checkbox_group' ];

	/** Fields a visitor types/picks values into. */
	private const INPUT = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text' ];

	/** Fields usable as {refs} in formulas (spec §6 formula value map). */
	private const REFERENCEABLE = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'formula' ];

	public static function all(): array {
		return apply_filters( 'alovio_calc_field_types', self::FREE );
	}

	public static function is_choice( string $type ): bool {
		return in_array( $type, self::CHOICE, true );
	}

	public static function is_input( string $type ): bool {
		return in_array( $type, self::INPUT, true );
	}

	public static function is_referenceable( string $type ): bool {
		return in_array( $type, self::REFERENCEABLE, true );
	}

	/** Conditions may reference input fields and formula results (e.g. the running total) — never heading/html. */
	public static function is_condition_controller( string $type ): bool {
		return self::is_input( $type ) || 'formula' === $type;
	}
}
