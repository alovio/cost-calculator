<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Entries;

defined( 'ABSPATH' ) || exit;

/** Admin REST routes for entries (spec §4). */
final class EntriesRestController {

	/** @var EntriesRepository */
	private $repo;

	public function __construct( ?EntriesRepository $repo = null ) {
		$this->repo = $repo ?? new EntriesRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'alc/v1',
			'/entries',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_entries' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'calculator' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'page'       => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 1,
					),
					'per_page'   => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 20,
					),
				),
			)
		);
		register_rest_route(
			'alc/v1',
			'/entries/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_entry' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'status' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_entry' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/** @param \WP_REST_Request $request */
	public function list_entries( $request ) {
		$per_page       = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$result         = $this->repo->paginate(
			(int) $request->get_param( 'calculator' ),
			max( 1, (int) $request->get_param( 'page' ) ),
			$per_page
		);
		$result['rows'] = array_map(
			static function ( $row ) {
				$row['snapshot'] = json_decode( (string) $row['snapshot'], true );
				return $row;
			},
			$result['rows']
		);
		return rest_ensure_response( $result );
	}

	/** @param \WP_REST_Request $request */
	public function update_entry( $request ) {
		$id = (int) $request['id'];
		if ( null === $this->repo->find( $id ) ) {
			return $this->not_found();
		}
		$this->repo->set_status( $id, (string) $request->get_param( 'status' ) );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/** @param \WP_REST_Request $request */
	public function delete_entry( $request ) {
		$id = (int) $request['id'];
		if ( null === $this->repo->find( $id ) ) {
			return $this->not_found();
		}
		$this->repo->delete( $id );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	private function not_found(): \WP_Error {
		return new \WP_Error( 'alc_not_found', __( 'Entry not found.', 'alovio-calculator' ), array( 'status' => 404 ) );
	}
}
