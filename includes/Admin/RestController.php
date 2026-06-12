<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Admin;

use Alovio\Calculator\Fields\FieldRepository;
use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Templates\Presets;

defined( 'ABSPATH' ) || exit;

/**
 * REST API for the builder app: calculators CRUD (spec §4).
 * All routes capability-gated to `manage_options`.
 */
final class RestController {

	/** @var FieldRepository */
	private $repo;

	public function __construct( ?FieldRepository $repo = null ) {
		$this->repo = $repo ?? new FieldRepository();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'alc/v1',
			'/calculators',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_calculators' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_calculator' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'title'       => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
						'template'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
						'duplicateOf' => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					),
				),
			)
		);
		register_rest_route(
			'alc/v1',
			'/calculators/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_calculator' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_calculator' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'title'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
						'config' => array( 'type' => 'object' ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_calculator' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			'alc/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'deleteOnUninstall' => array( 'type' => 'boolean' ),
					),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function list_calculators() {
		$posts = get_posts(
			array(
				'post_type'   => FieldRepository::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'orderby'     => 'modified',
				'order'       => 'DESC',
			)
		);
		$items = array_map(
			static fn( $post ) => array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'updated'   => $post->post_modified,
				'shortcode' => sprintf( '[alovio_calculator id="%d"]', $post->ID ),
			),
			$posts
		);
		return rest_ensure_response( $items );
	}

	/** @param \WP_REST_Request $request */
	public function create_calculator( $request ) {
		$title  = (string) $request->get_param( 'title' );
		$config = FieldSchema::defaults();

		$template = (string) $request->get_param( 'template' );
		if ( '' !== $template ) {
			$presets = Presets::all();
			if ( ! isset( $presets[ $template ] ) ) {
				return new \WP_Error( 'alc_bad_template', __( 'Unknown template.', 'alovio-calculator' ), array( 'status' => 400 ) );
			}
			$config = $presets[ $template ]['config'];
		}

		$duplicate_of = (int) $request->get_param( 'duplicateOf' );
		if ( $duplicate_of > 0 ) {
			$source = $this->find( $duplicate_of );
			if ( null === $source ) {
				return $this->not_found();
			}
			$config = $this->repo->get( $duplicate_of ); // Copy the CONFIG only; title comes from the request as-is.
		}

		$id = wp_insert_post(
			array(
				'post_type'   => FieldRepository::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$this->repo->save( (int) $id, $config );

		return rest_ensure_response( array( 'id' => (int) $id ) );
	}

	/** @param \WP_REST_Request $request */
	public function get_calculator( $request ) {
		$post = $this->find( (int) $request['id'] );
		if ( null === $post ) {
			return $this->not_found();
		}
		return rest_ensure_response(
			array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'config' => $this->repo->get( $post->ID ),
			)
		);
	}

	/** @param \WP_REST_Request $request */
	public function update_calculator( $request ) {
		$post = $this->find( (int) $request['id'] );
		if ( null === $post ) {
			return $this->not_found();
		}

		$title = $request->get_param( 'title' );
		if ( is_string( $title ) && '' !== $title ) {
			wp_update_post(
				array(
					'ID'         => $post->ID,
					'post_title' => $title,
				)
			);
		}

		$config = $request->get_param( 'config' );
		$saved  = is_array( $config ) ? $this->repo->save( $post->ID, $config ) : $this->repo->get( $post->ID );

		return rest_ensure_response(
			array(
				'id'     => $post->ID,
				'title'  => is_string( $title ) && '' !== $title ? $title : $post->post_title,
				'config' => $saved, // Normalized — the builder re-hydrates from this (server may rewrite option slugs).
			)
		);
	}

	/** @param \WP_REST_Request $request */
	public function delete_calculator( $request ) {
		$post = $this->find( (int) $request['id'] );
		if ( null === $post ) {
			return $this->not_found();
		}
		wp_delete_post( $post->ID, true );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public function get_settings() {
		return rest_ensure_response( array( 'deleteOnUninstall' => (bool) get_option( 'alc_delete_on_uninstall' ) ) );
	}

	/** @param \WP_REST_Request $request */
	public function update_settings( $request ) {
		update_option( 'alc_delete_on_uninstall', (bool) $request->get_param( 'deleteOnUninstall' ) );
		return $this->get_settings();
	}

	private function find( int $id ): ?\WP_Post {
		$post = get_post( $id );
		if ( ! $post || FieldRepository::POST_TYPE !== get_post_type( $post ) ) {
			return null; // Prevents reading/writing arbitrary posts' meta through our routes.
		}
		return $post;
	}

	private function not_found(): \WP_Error {
		return new \WP_Error( 'alc_not_found', __( 'Calculator not found.', 'alovio-calculator' ), array( 'status' => 404 ) );
	}
}
