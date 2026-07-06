<?php
namespace Alovio\Calculator\Tests\Unit\Frontend;

use Alovio\Calculator\Fields\FieldSchema;
use Alovio\Calculator\Frontend\CalculatorRenderer;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class CalculatorRendererTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'sanitize_key' )->alias( static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( (string) $s ) ); // keep <b> for the escaping assertion
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
		Functions\when( 'esc_attr__' )->alias( static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES ) );
		Functions\when( 'wp_get_attachment_image' )->justReturn( '<img src="thumb.jpg" alt="" />' );
	}

	private function config( bool $quotes = true ): array {
		return FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'area', 'type' => 'slider', 'label' => 'Area (m²)', 'min' => 10, 'max' => 500, 'default' => 50, 'showInSummary' => true ],
			[ 'id' => 'service', 'type' => 'radio', 'label' => '<b>Bold</b> service', 'showInSummary' => true, 'options' => [
				[ 'value' => 'opt_std', 'label' => 'Standard', 'price' => 2.5 ],
				[ 'value' => 'opt_deep', 'label' => 'Deep', 'price' => 4, 'image' => 9 ],
			] ],
			[ 'id' => 'express', 'type' => 'toggle', 'label' => 'Express', 'price' => 50 ],
			[ 'id' => 'discount_note', 'type' => 'heading', 'label' => 'Discount!', 'conditions' => [
				[ 'field' => 'area', 'operator' => 'gt', 'value' => '100' ],
			], 'conditionMatch' => 'all', 'conditionAction' => 'show' ],
			[ 'id' => 'total', 'type' => 'formula', 'label' => 'Estimated price', 'showInSummary' => true,
				'expression' => '{area} * {service} + {express}' ],
		], 'settings' => [ 'quoteForm' => [ 'enabled' => $quotes, 'fields' => [ 'name', 'email', 'phone' ], 'notifyEmail' => 'secret@owner.test' ] ] ] );
	}

	private function payload( string $html ): array {
		$this->assertSame( 1, preg_match( '/<script type="application\/json" class="alc-config">(.*?)<\/script>/s', $html, $m ) );
		return json_decode( $m[1], true );
	}

	public function test_wrapper_payload_and_no_secret_leaks(): void {
		$html = CalculatorRenderer::render( 7, $this->config() );
		$this->assertStringContainsString( 'data-alc-id="7"', $html );
		$payload = $this->payload( $html );
		$this->assertSame( 7, $payload['calculatorId'] );
		$this->assertSame( 'https://example.test/wp-json/alovio-calc/v1/quote', $payload['quoteEndpoint'] );
		$this->assertArrayHasKey( 'currency', $payload['settings'] );
		$this->assertSame( [ 'name', 'email', 'phone' ], $payload['settings']['quoteForm']['fields'] );
		$this->assertNotSame( '', $payload['settings']['quoteForm']['successMessage'] ); // default resolved (no wp-i18n in the frontend bundle)
		$this->assertStringNotContainsString( 'secret@owner.test', $html ); // notifyEmail never ships
		$this->assertCount( 5, $payload['fields'] );
	}

	public function test_labels_are_escaped(): void {
		$html = CalculatorRenderer::render( 7, $this->config() );
		$this->assertStringContainsString( '&lt;b&gt;Bold&lt;/b&gt; service', $html );
		$this->assertStringNotContainsString( '<b>Bold</b>', $html );
	}

	public function test_inactive_fields_render_hidden(): void {
		$html = CalculatorRenderer::render( 7, $this->config() );
		$this->assertSame( 1, preg_match( '/<div[^>]*data-alc-field="discount_note"[^>]*>/', $html, $m ) );
		$this->assertStringContainsString( ' hidden', $m[0] );
		$this->assertSame( 1, preg_match( '/<div[^>]*data-alc-field="area"[^>]*>/', $html, $m2 ) );
		$this->assertStringNotContainsString( ' hidden', $m2[0] );
	}

	public function test_summary_rows_total_and_aria_live(): void {
		$html = CalculatorRenderer::render( 7, $this->config() );
		$this->assertStringContainsString( 'data-alc-line="area"', $html );
		$this->assertStringContainsString( 'data-alc-line="total"', $html );
		$this->assertSame( 1, preg_match( '/<p[^>]*class="alc-total"[^>]*>/', $html, $m ) );
		$this->assertStringContainsString( 'aria-live="polite"', $m[0] );
		$this->assertStringContainsString( 'data-alc-total', $m[0] );
		// Defaults: area=50, no service selected, toggle off → total 50*0 + 0 = 0 → "$0.00".
		$this->assertStringContainsString( '$0.00', $html );
	}

	public function test_quote_form_honeypot_and_enabled_fields_only(): void {
		$html = CalculatorRenderer::render( 7, $this->config() );
		$this->assertStringContainsString( 'name="alc_website"', $html );
		$this->assertStringContainsString( 'name="alc_contact_name"', $html );
		$this->assertStringContainsString( 'name="alc_contact_phone"', $html );
		$this->assertStringNotContainsString( 'name="alc_contact_message"', $html );
		$this->assertStringContainsString( 'alc-quote-feedback', $html );
	}

	public function test_no_form_when_quotes_disabled(): void {
		$html = CalculatorRenderer::render( 7, $this->config( false ) );
		$this->assertStringNotContainsString( '<form', $html );
	}

	public function test_option_defaults_render_selected_and_checked(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'size', 'type' => 'select', 'label' => 'Size', 'options' => [
				[ 'value' => 'opt_s', 'label' => 'S' ],
				[ 'value' => 'opt_m', 'label' => 'M', 'default' => true ],
			] ],
			[ 'id' => 'extras', 'type' => 'checkbox_group', 'label' => 'Extras', 'options' => [
				[ 'value' => 'opt_x', 'label' => 'X', 'default' => true ],
			] ],
		], 'settings' => [] ] );
		$html = CalculatorRenderer::render( 7, $config );
		$this->assertMatchesRegularExpression( '/<option value="opt_m"[^>]*selected>/', $html );
		$this->assertMatchesRegularExpression( '/<option value="opt_s">/', $html );
		$this->assertMatchesRegularExpression( '/<input type="checkbox"[^>]*value="opt_x"[^>]*checked>/', $html );
	}

	public function test_repeater_renders_rows_template_and_controls(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms <b>x</b>', 'minRows' => 2, 'maxRows' => 5,
				'rowLabel' => 'Room {n}', 'addLabel' => 'Add room', 'rowExpression' => '', 'fields' => [
				[ 'id' => 'r_rate', 'type' => 'radio', 'label' => 'Rate', 'options' => [
					[ 'value' => 'opt_a', 'label' => 'A', 'price' => 1 ],
				] ],
				[ 'id' => 'r_area', 'type' => 'number', 'label' => 'Area', 'default' => 3 ],
			] ],
		] ] );
		$html = CalculatorRenderer::render( 7, $config );

		// 2 server-rendered initial rows (minRows) + 1 inert copy inside <template>.
		$this->assertSame( 3, substr_count( $html, '<div class="alc-repeater__row" data-alc-row>' ) );
		$this->assertStringContainsString( '<template data-alc-row-template>', $html );
		$this->assertStringContainsString( 'name="alc_rooms_r_rate_1"', $html );      // row-scoped radio names
		$this->assertStringContainsString( 'name="alc_rooms_r_rate_2"', $html );
		$this->assertStringContainsString( 'name="alc_rooms_r_rate___ROW__"', $html ); // template placeholder
		$this->assertStringContainsString( 'data-alc-child="r_area"', $html );
		$this->assertStringContainsString( 'data-alc-add', $html );
		$this->assertStringContainsString( 'Add room', $html );
		$this->assertStringContainsString( 'data-alc-row-label>Room 1<', $html );
		$this->assertStringContainsString( 'Rooms &lt;b&gt;x&lt;/b&gt;', $html );      // legend escaped
		$this->assertStringNotContainsString( '<b>x</b>', $html );
	}

	public function test_new_types_render_native_controls_and_display_summary(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'visit', 'type' => 'date', 'label' => 'Visit date' ],
			[ 'id' => 'mail', 'type' => 'email', 'label' => 'Email', 'placeholder' => 'you@example.com' ],
			[ 'id' => 'cell', 'type' => 'phone', 'label' => 'Phone' ],
			[ 'id' => 'site', 'type' => 'url', 'label' => 'Site' ],
			[ 'id' => 'notes', 'type' => 'textarea', 'label' => 'Notes', 'placeholder' => 'Tell us more' ],
		] ] );
		$html = CalculatorRenderer::render( 7, $config );
		$this->assertStringContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'type="tel"', $html );
		$this->assertStringContainsString( 'type="url"', $html );
		$this->assertStringContainsString( '<textarea id="alc-notes" rows="3" placeholder="Tell us more">', $html );
		$this->assertStringContainsString( 'placeholder="you@example.com"', $html );
	}

	public function test_slider_bubble_scale_and_unit(): void {
		$config = FieldSchema::normalize( [ 'fields' => [
			[ 'id' => 'area', 'type' => 'slider', 'label' => 'Area', 'min' => 10, 'max' => 110, 'default' => 35, 'unit' => 'm2' ],
		] ] );
		$html = CalculatorRenderer::render( 7, $config );
		$this->assertStringContainsString( 'class="alc-slider" data-alc-unit="m2"', $html );
		$this->assertStringContainsString( '--alc-pos:25%', $html );                       // (35-10)/(110-10)
		$this->assertStringContainsString( 'class="alc-slider__bubble">35 m2<', $html );   // unit suffix
		$this->assertStringContainsString( '<div class="alc-slider__scale" aria-hidden="true"><span>10</span><span>110</span></div>', $html );
	}
}
