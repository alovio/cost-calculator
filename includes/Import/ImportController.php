<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Import;

use Alovio\Calculator\Fields\FieldRepository;

defined( 'ABSPATH' ) || exit;

/**
 * REST for the CCB importer (spec §4.1). manage_options only. Per-calculator
 * isolation: one bad calculator never aborts the batch, and we only write
 * after its map completed in full (map is pure; the write is the last step).
 */
final class ImportController {

	/** @var CcbDetector */
	private $detector;

	/** @var CcbReader */
	private $reader;

	/** @var FieldRepository */
	private $repo;

	public function __construct( ?CcbDetector $detector = null, ?CcbReader $reader = null, ?FieldRepository $repo = null ) {
		$this->detector = $detector ?? new CcbDetector();
		$this->reader   = $reader ?? new CcbReader();
		$this->repo     = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'alovio-calc/v1',
			'/import/ccb',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_available' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'import' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'ids' => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function list_available() {
		if ( ! $this->detector->is_present() ) {
			return rest_ensure_response(
				array(
					'present' => false,
					'items'   => array(),
				)
			);
		}
		return rest_ensure_response(
			array(
				'present' => true,
				'items'   => $this->reader->list(),
			)
		);
	}

	/** @param \WP_REST_Request $request */
	public function import( $request ) {
		$ids     = array_map( 'absint', (array) $request->get_param( 'ids' ) );
		$results = array();
		foreach ( array_filter( array_unique( $ids ) ) as $ccb_id ) {
			$results[] = $this->import_one( $ccb_id );
		}
		return rest_ensure_response( array( 'results' => $results ) );
	}

	/** Isolation boundary: everything for ONE calculator happens inside this try/catch. */
	private function import_one( int $ccb_id ): array {
		$post_id = 0;
		try {
			$calc = $this->reader->read( $ccb_id );
			if ( null === $calc ) {
				return $this->failure( $ccb_id, __( 'Could not read this calculator — its stored data is missing or in an unknown format.', 'alovio-calculator' ) );
			}
			$mapped = CcbMapper::map( $calc ); // pure; no writes until this succeeded in full
			$title  = '' !== $mapped['title'] ? $mapped['title'] : __( 'Imported calculator', 'alovio-calculator' );

			$post_id = wp_insert_post(
				array(
					'post_type'   => FieldRepository::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $title,
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				$post_id = 0;
				return $this->failure( $ccb_id, __( 'Could not create the calculator post.', 'alovio-calculator' ) );
			}
			$this->repo->save( (int) $post_id, $mapped['config'] );

			return array(
				'ccbId'    => $ccb_id,
				'title'    => $title,
				'created'  => (int) $post_id,
				'skipped'  => $mapped['skipped'],
				'warnings' => $mapped['warnings'],
			);
		} catch ( \Throwable $e ) {
			if ( $post_id ) {
				wp_delete_post( (int) $post_id, true ); // clean failure path — no orphan post (spec §4.1: no partial writes)
			}
			return $this->failure( $ccb_id, __( 'Import failed for this calculator.', 'alovio-calculator' ) );
		}
	}

	private function failure( int $ccb_id, string $message ): array {
		return array(
			'ccbId'    => $ccb_id,
			'title'    => '',
			'created'  => null,
			'error'    => $message,
			'skipped'  => array(),
			'warnings' => array(),
		);
	}
}
