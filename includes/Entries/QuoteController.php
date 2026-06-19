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
		foreach ( $rawValues as $k => $v ) {
			if ( is_string( $v ) && strlen( $v ) > self::VALUE_LIMIT ) {
				$rawValues[ $k ] = substr( $v, 0, self::VALUE_LIMIT );
			}
			if ( is_array( $v ) ) {
				$rawValues[ $k ] = array_map( 'strval', array_slice( $v, 0, 50 ) );
			}
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
			'totalScaled' => $result['totalScaled'] ?? 0,
			'currency'    => $config['settings']['currency'],
		);

		$repo = new EntriesRepository();
		$repo->insert( EntriesRepository::row_from_submission( $calculator_id, $validated['contact'], $snapshot ) );

		update_option( 'alovio_calc_entry_count', (int) get_option( 'alovio_calc_entry_count', 0 ) + 1 ); // Review nudge counter (§10).
		( new EntryMailer() )->notify( $post, $config, $validated['contact'], $snapshot );

		return new \WP_REST_Response( array( 'ok' => true ), 201 );
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
