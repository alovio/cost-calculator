<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Entries;

use Alovio\Calculator\Fields\FieldRepository;
use Alovio\Calculator\Logic\ConditionalLogic;
use Alovio\Calculator\Logic\Evaluation;

defined( 'ABSPATH' ) || exit;

/**
 * Public quote-submission endpoint (spec §10). Deliberately NO nonce: REST nonces
 * expire and get baked into cached HTML, guaranteeing 403s on cached pages — this
 * is a contact-form-class endpoint with no auth context or privileged side effect.
 */
final class QuoteController {

	private const RATE_LIMIT  = 5;   // per minute per IP
	private const VALUE_LIMIT = 500; // chars per submitted field value
	private const VALUES_MAX  = 200; // submitted field count cap

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'alovio-calc/v1',
			'/quote',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public by design — spec §10 (no nonce; cache-safe).
				'callback'            => array( $this, 'handle' ),
			)
		);
	}

	/** @param \WP_REST_Request $request */
	public function handle( $request ) {
		if ( '' !== (string) $request->get_param( 'alc_website' ) ) { // Honeypot.
			return new \WP_REST_Response( array( 'ok' => true ), 201 ); // Pretend success to bots.
		}

		if ( ! $this->within_rate_limit() ) {
			return new \WP_REST_Response(
				array(
					'ok'          => false,
					'code'        => 'rate_limited',
					'message'     => __( 'Too many requests. Please try again in a minute.', 'alovio-calculator' ),
					'fieldErrors' => array(),
				),
				429
			);
		}

		$calculator_id = absint( $request->get_param( 'calculatorId' ) );
		$post          = get_post( $calculator_id );
		if ( ! $post || FieldRepository::POST_TYPE !== get_post_type( $post ) ) {
			return $this->bad_request( 'not_found', __( 'Calculator not found.', 'alovio-calculator' ) );
		}

		$config = ( new FieldRepository() )->get( $calculator_id );
		if ( empty( $config['settings']['quoteForm']['enabled'] ) ) {
			return $this->bad_request( 'quotes_disabled', __( 'Quote requests are not enabled.', 'alovio-calculator' ) );
		}

		$rawValues = $request->get_param( 'values' );
		$rawValues = is_array( $rawValues ) ? array_slice( $rawValues, 0, self::VALUES_MAX, true ) : array();

		$repeater_ids = array();
		foreach ( $config['fields'] as $field ) {
			if ( 'repeater' !== $field['type'] || ! array_key_exists( $field['id'], $rawValues ) ) {
				continue;
			}
			$rows = self::sanitize_repeater_rows( $field, $rawValues[ $field['id'] ] );
			if ( null === $rows ) {
				return $this->bad_request( 'too_many_rows', __( 'Too many rows submitted.', 'alovio-calculator' ) );
			}
			$rawValues[ $field['id'] ]    = $rows;
			$repeater_ids[ $field['id'] ] = true;
		}
		foreach ( $rawValues as $k => $v ) {
			if ( isset( $repeater_ids[ $k ] ) ) {
				continue; // Already row-sanitized above.
			}
			if ( is_string( $v ) && strlen( $v ) > self::VALUE_LIMIT ) {
				$rawValues[ $k ] = substr( $v, 0, self::VALUE_LIMIT );
			}
			if ( is_array( $v ) ) {
				$rawValues[ $k ] = array_map( 'strval', array_slice( $v, 0, 50 ) );
			}
		}
		if ( strlen( (string) wp_json_encode( $rawValues ) ) > 65535 ) {
			return $this->bad_request( 'too_large', __( 'The submitted data is too large.', 'alovio-calculator' ) );
		}

		$validated = self::validate_contact( (array) $request->get_param( 'contact' ), $config['settings']['quoteForm'] );
		if ( ! empty( $validated['fieldErrors'] ) ) {
			return new \WP_REST_Response(
				array(
					'ok'          => false,
					'code'        => 'invalid',
					'message'     => __( 'Please correct the highlighted fields.', 'alovio-calculator' ),
					'fieldErrors' => $validated['fieldErrors'],
				),
				400
			);
		}

		// Authoritative recompute — the client's total is ignored (spec §10).
		$result = Evaluation::run( $config, $rawValues );

		// THEN=require: an active, mandatory field left empty blocks the quote.
		$requiredErrors = self::validate_required( $config['fields'], $result['conditionValues'], $rawValues );
		if ( ! empty( $requiredErrors ) ) {
			return new \WP_REST_Response(
				array(
					'ok'          => false,
					'code'        => 'invalid',
					'message'     => __( 'Please correct the highlighted fields.', 'alovio-calculator' ),
					'fieldErrors' => $requiredErrors,
				),
				400
			);
		}

		$snapshot = array(
			'values'      => array_map( 'sanitize_text_field', $result['conditionValues'] ), // §12: text fields carry raw visitor input — sanitize at the storage boundary.
			'lineItems'   => $result['lineItems'],
			'repeaters'   => self::repeater_snapshot( $config['fields'], $result ),
			'totalScaled' => $result['totalScaled'] ?? 0,
			'currency'    => $config['settings']['currency'],
		);

		$repo    = new EntriesRepository();
		$entryId = $repo->insert( EntriesRepository::row_from_submission( $calculator_id, $validated['contact'], $snapshot ) );

		update_option( 'alovio_calc_entry_count', (int) get_option( 'alovio_calc_entry_count', 0 ) + 1 ); // Review nudge counter (§10).

		/**
		 * Fires after a quote entry is stored. The free plugin does nothing with it;
		 * the Pro add-on hooks this to generate a PDF, push a webhook, etc.
		 *
		 * @param int      $entryId  Stored entry id.
		 * @param array    $snapshot Quote snapshot: values, lineItems, totalScaled, currency.
		 * @param array    $contact  Sanitized contact fields.
		 * @param \WP_Post $post     Calculator post.
		 * @param array    $config   Calculator config.
		 */
		do_action( 'alovio_calc_quote_stored', $entryId, $snapshot, $validated['contact'], $post, $config );

		( new EntryMailer() )->notify( $post, $config, $validated['contact'], $snapshot, $entryId );

		/**
		 * Filters the quote success response body. The free plugin returns just
		 * `array( 'ok' => true )`; the Pro add-on injects e.g. a `pdfUrl`.
		 *
		 * @param array $response The response body.
		 * @param int   $entryId  Stored entry id.
		 * @param array $snapshot Quote snapshot.
		 * @param array $config   Calculator config.
		 */
		$response = apply_filters( 'alovio_calc_quote_response', array( 'ok' => true ), $entryId, $snapshot, $config );

		return new \WP_REST_Response( $response, 201 );
	}

	/**
	 * THEN=require: collect field errors for active, mandatory fields the visitor left empty.
	 * Pure + unit-tested; the conditionValues map is the authoritative server one.
	 *
	 * @param array<int,array<string,mixed>> $fields
	 * @param array<string,string>           $conditionValues
	 * @param array<string,mixed>            $rawValues
	 * @return array<string,string> field id => message
	 */
	public static function validate_required( array $fields, array $conditionValues, array $rawValues ): array {
		$errors = array();
		foreach ( $fields as $field ) {
			if ( ! ConditionalLogic::requires( $field, $conditionValues ) ) {
				continue;
			}
			$v     = $rawValues[ $field['id'] ] ?? '';
			$empty = is_array( $v ) ? 0 === count( $v ) : '' === trim( (string) $v );
			if ( $empty ) {
				/* translators: %s: field label */
				$errors[ $field['id'] ] = sprintf( __( '%s is required.', 'alovio-calculator' ), (string) ( $field['label'] ?? $field['id'] ) );
			}
		}
		return $errors;
	}

	/** Pure, unit-tested. */
	public static function validate_contact( array $contact, array $quoteForm ): array {
		$enabled = $quoteForm['fields'];
		$out     = array();
		$errors  = array();

		foreach ( $contact as $k => $v ) {
			if ( is_string( $v ) && strlen( $v ) > 2000 ) {
				$errors[ $k ] = __( 'Too long.', 'alovio-calculator' );
			}
		}

		if ( in_array( 'name', $enabled, true ) ) {
			$out['name'] = sanitize_text_field( (string) ( $contact['name'] ?? '' ) );
			if ( '' === $out['name'] && ! isset( $errors['name'] ) ) {
				$errors['name'] = __( 'Name is required.', 'alovio-calculator' );
			}
		}
		if ( in_array( 'email', $enabled, true ) ) {
			$out['email'] = sanitize_email( (string) ( $contact['email'] ?? '' ) );
			if ( '' === $out['email'] && ! isset( $errors['email'] ) ) {
				$errors['email'] = __( 'A valid email is required.', 'alovio-calculator' );
			}
		}
		if ( in_array( 'phone', $enabled, true ) ) {
			$out['phone'] = sanitize_text_field( (string) ( $contact['phone'] ?? '' ) );
		}
		if ( in_array( 'message', $enabled, true ) ) {
			$out['message'] = sanitize_textarea_field( (string) ( $contact['message'] ?? '' ) );
		}

		$errors = array_intersect_key( $errors, array_flip( $enabled ) );
		return array(
			'contact'     => $out,
			'fieldErrors' => $errors,
		);
	}

	/**
	 * Sanitize one repeater's submitted rows. Pure, unit-tested.
	 *
	 * @param array $field Normalized repeater field.
	 * @param mixed $raw   Client-submitted rows.
	 * @return array|null Cleaned rows, or NULL when the row count exceeds maxRows (⇒ 400).
	 */
	public static function sanitize_repeater_rows( array $field, $raw ): ?array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$rows = array_values( $raw );
		$cap  = min( (int) ( $field['maxRows'] ?? 50 ), 50 );
		if ( count( $rows ) > $cap ) {
			return null;
		}
		$child_ids = array_column( (array) ( $field['fields'] ?? array() ), 'id' );
		$out       = array();
		foreach ( $rows as $row ) {
			$clean = array();
			if ( is_array( $row ) ) {
				foreach ( $child_ids as $cid ) {
					if ( ! array_key_exists( $cid, $row ) ) {
						continue;
					}
					$v = $row[ $cid ];
					if ( is_array( $v ) ) {
						$clean[ $cid ] = array_map( 'strval', array_slice( $v, 0, 50 ) );
					} elseif ( is_scalar( $v ) ) {
						$clean[ $cid ] = substr( (string) $v, 0, self::VALUE_LIMIT );
					}
				}
			}
			$out[] = $clean;
		}
		return $out;
	}

	/**
	 * Repeater block for the entry snapshot (spec §3.1 entries surfaces): one entry
	 * per ACTIVE repeater in field order, child id => label/type legends included so
	 * surfaces never re-read the calculator config.
	 *
	 * @return array[]
	 */
	public static function repeater_snapshot( array $fields, array $result ): array {
		$out = array();
		foreach ( $fields as $field ) {
			if ( 'repeater' !== $field['type'] || false === ( $result['active'][ $field['id'] ] ?? true ) ) {
				continue;
			}
			$children = array();
			$types    = array();
			foreach ( (array) ( $field['fields'] ?? array() ) as $child ) {
				$children[ $child['id'] ] = $child['label'];
				$types[ $child['id'] ]    = $child['type'];
			}
			$rows = array();
			foreach ( (array) ( $result['repeaters'][ $field['id'] ]['rows'] ?? array() ) as $row ) {
				$rows[] = array(
					'label'  => sanitize_text_field( (string) $row['label'] ),
					'total'  => (int) $row['total'],
					'values' => array_map( 'sanitize_text_field', $row['values'] ),
				);
			}
			$out[] = array(
				'id'       => $field['id'],
				'label'    => $field['label'],
				'children' => $children,
				'types'    => $types,
				'rows'     => $rows,
			);
		}
		return $out;
	}

	private function within_rate_limit(): bool {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // REMOTE_ADDR only — spec §10 (X-Forwarded-For is spoofable).
		$key   = 'alovio_calc_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private function bad_request( string $code, string $message ): \WP_REST_Response {
		return new \WP_REST_Response(
			array(
				'ok'          => false,
				'code'        => $code,
				'message'     => $message,
				'fieldErrors' => array(),
			),
			400
		);
	}
}
