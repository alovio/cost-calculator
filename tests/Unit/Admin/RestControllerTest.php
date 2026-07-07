<?php
namespace Alovio\Calculator\Tests\Unit\Admin;

use Alovio\Calculator\Admin\RestController;
use Alovio\Calculator\Fields\FieldRepository;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

/** Duck-typed WP_REST_Request stand-in (route callbacks type-hint nothing). */
class FakeRequest implements \ArrayAccess {
	private $params;
	public function __construct( array $params ) {
		$this->params = $params;
	}
	public function get_param( $key ) {
		return $this->params[ $key ] ?? null;
	}
	#[\ReturnTypeWillChange]
	public function offsetExists( $key ): bool {
		return isset( $this->params[ $key ] );
	}
	#[\ReturnTypeWillChange]
	public function offsetGet( $key ) {
		return $this->params[ $key ] ?? null;
	}
	#[\ReturnTypeWillChange]
	public function offsetSet( $key, $value ): void {
		$this->params[ $key ] = $value;
	}
	#[\ReturnTypeWillChange]
	public function offsetUnset( $key ): void {
		unset( $this->params[ $key ] );
	}
}

class RestControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Renderer + schema stub set — mirrors CalculatorRendererTest.
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( (string) $s ) );
		Functions\when( 'sanitize_hex_color' )->returnArg();
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'esc_attr' )->alias( static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ) );
		Functions\when( 'esc_html' )->alias( static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'rest_url' )->alias( static fn( $path = '' ) => 'https://example.test/wp-json/' . $path );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data, $flags = 0 ) => json_encode( $data, $flags ) );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->alias( static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ) );
		Functions\when( 'wp_get_attachment_image' )->justReturn( '<img src="thumb.jpg" alt="" />' );
		Functions\when( 'rest_ensure_response' )->returnArg();
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
	}

	public function test_render_fragment_returns_canonical_renderer_html(): void {
		$res = ( new RestController() )->render_fragment(
			new FakeRequest(
				array(
					'calculatorId' => 7,
					'fields'       => array(
						array( 'id' => 'area', 'type' => 'number', 'label' => 'Area' ),
						array( 'id' => 'evil', 'type' => 'nope', 'label' => 'Dropped' ),
					),
					'settings'     => array(),
				)
			)
		);
		$this->assertArrayHasKey( 'html', $res );
		$this->assertStringContainsString( 'class="alc-calculator', $res['html'] );
		$this->assertStringContainsString( 'data-alc-id="7"', $res['html'] );
		$this->assertStringContainsString( 'data-alc-field="area"', $res['html'] );
		$this->assertStringNotContainsString( 'evil', $res['html'] ); // unknown type dropped by FieldSchema::normalize
		$this->assertStringContainsString( 'class="alc-config"', $res['html'] ); // embedded payload — init() parses it
	}

	public function test_render_fragment_survives_garbage_body(): void {
		$res = ( new RestController() )->render_fragment( new FakeRequest( array( 'fields' => 'not-an-array' ) ) );
		$this->assertStringContainsString( 'class="alc-calculator', $res['html'] ); // empty-but-valid fragment
	}

	public function test_can_manage_gates_on_manage_options(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->assertFalse( ( new RestController() )->can_manage() );
	}

	public function test_get_calculator_includes_modified(): void {
		$post                    = new \WP_Post();
		$post->ID                = 12;
		$post->post_title        = 'Roof quote';
		$post->post_modified_gmt = '2026-07-05 09:30:00';
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_type' )->justReturn( FieldRepository::POST_TYPE );
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$res = ( new RestController() )->get_calculator( new FakeRequest( array( 'id' => 12 ) ) );
		$this->assertSame( '2026-07-05 09:30:00', $res['modified'] );
		$this->assertSame( 'Roof quote', $res['title'] );
	}

	public function test_update_calculator_always_bumps_and_returns_modified(): void {
		$post             = new \WP_Post();
		$post->ID         = 12;
		$post->post_title = 'Roof quote';
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_type' )->justReturn( FieldRepository::POST_TYPE );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'get_post_field' )->justReturn( '2026-07-05 10:00:00' );
		// The essential contract: post_modified moves even on a config-only save.
		Functions\expect( 'wp_update_post' )->once()->with( array( 'ID' => 12 ) );

		$res = ( new RestController() )->update_calculator(
			new FakeRequest( array( 'id' => 12, 'config' => array( 'fields' => array(), 'settings' => array() ) ) )
		);
		$this->assertSame( '2026-07-05 10:00:00', $res['modified'] );
	}
}
