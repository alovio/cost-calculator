<?php
namespace Alovio\Calculator\Fields;

final class FieldRepository {

	public const META_KEY  = '_alc_config';
	public const POST_TYPE = 'alc_calculator';

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'          => [ 'name' => __( 'Calculators', 'alovio-calculator' ) ],
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => [ 'title' ],
				'capability_type' => 'page',
				'map_meta_cap'    => true,
			]
		);
	}

	public function get( int $post_id ): array {
		$raw     = get_post_meta( $post_id, self::META_KEY, true );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			return FieldSchema::defaults();
		}
		return FieldSchema::normalize( $decoded );
	}

	public function save( int $post_id, array $config ): array {
		$normalized = FieldSchema::normalize( $config );
		// wp_slash is mandatory: update_post_meta unslashes its value, which would
		// eat the backslashes of JSON unicode escapes (e.g. "m²" → ² → u00b2).
		update_post_meta( $post_id, self::META_KEY, wp_slash( wp_json_encode( $normalized ) ) );
		return $normalized;
	}
}
