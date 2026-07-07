<?php
namespace Alovio\Calculator\Tests\Unit\Entries;

use Alovio\Calculator\Entries\QuoteController;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class QuoteControllerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( 'sanitize_email' )->alias( static fn( $e ) => filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : '' );
		Functions\when( 'sanitize_textarea_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		Functions\when( '__' )->returnArg();
	}

	private function quoteForm( array $fields = [ 'name', 'email', 'phone', 'message' ] ): array {
		return [ 'enabled' => true, 'fields' => $fields, 'notifyEmail' => '', 'successMessage' => '' ];
	}

	public function test_requires_name_and_email(): void {
		$r = QuoteController::validate_contact( [ 'name' => '', 'email' => 'nope' ], $this->quoteForm() );
		$this->assertArrayHasKey( 'name', $r['fieldErrors'] );
		$this->assertArrayHasKey( 'email', $r['fieldErrors'] );
	}

	public function test_valid_contact_passes_and_is_sanitized(): void {
		$r = QuoteController::validate_contact(
			[ 'name' => ' <b>Tahir</b> ', 'email' => 'a@b.co', 'phone' => '+994', 'message' => 'hi' ],
			$this->quoteForm()
		);
		$this->assertSame( [], $r['fieldErrors'] );
		$this->assertSame( 'Tahir', $r['contact']['name'] );
	}

	public function test_fields_not_in_quote_form_are_dropped(): void {
		$r = QuoteController::validate_contact(
			[ 'name' => 'T', 'email' => 'a@b.co', 'phone' => 'x', 'message' => 'y' ],
			$this->quoteForm( [ 'name', 'email' ] )
		);
		$this->assertArrayNotHasKey( 'phone', $r['contact'] );
		$this->assertArrayNotHasKey( 'message', $r['contact'] );
	}

	public function test_oversize_values_rejected(): void {
		$r = QuoteController::validate_contact(
			[ 'name' => str_repeat( 'a', 5000 ), 'email' => 'a@b.co' ],
			$this->quoteForm()
		);
		$this->assertArrayHasKey( 'name', $r['fieldErrors'] );
	}

	public function test_validate_required_blocks_empty_mandatory_fields(): void {
		$fields = [
			[ 'id' => 'a', 'type' => 'toggle' ],
			[
				'id'              => 'ref',
				'type'            => 'text',
				'label'           => 'Reference',
				'conditions'      => [ [ 'field' => 'a', 'operator' => 'is', 'value' => '1' ] ],
				'conditionMatch'  => 'all',
				'conditionAction' => 'require',
			],
		];
		// toggle on ⇒ ref mandatory ⇒ empty ref errors
		$this->assertArrayHasKey( 'ref', QuoteController::validate_required( $fields, [ 'a' => '1' ], [ 'a' => '1', 'ref' => '' ] ) );
		// ref filled ⇒ passes
		$this->assertSame( [], QuoteController::validate_required( $fields, [ 'a' => '1' ], [ 'a' => '1', 'ref' => 'PO-42' ] ) );
		// toggle off ⇒ ref not mandatory ⇒ empty ref OK
		$this->assertSame( [], QuoteController::validate_required( $fields, [ 'a' => '' ], [ 'a' => '', 'ref' => '' ] ) );
	}

	private function repeater_field(): array {
		return [
			'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'maxRows' => 2,
			'fields' => [
				[ 'id' => 'r_area', 'type' => 'number', 'label' => 'Area' ],
				[ 'id' => 'r_extras', 'type' => 'checkbox_group', 'label' => 'Extras', 'options' => [] ],
			],
		];
	}

	public function test_sanitize_repeater_rows_caps_and_filters(): void {
		$field = $this->repeater_field();
		// Over the cap ⇒ null (the endpoint answers 400, spec §3.1 server guards).
		$this->assertNull( QuoteController::sanitize_repeater_rows( $field, [ [], [], [] ] ) );
		// Unknown child keys dropped, scalars truncated, checkbox arrays stringified.
		$rows = QuoteController::sanitize_repeater_rows( $field, [
			[ 'r_area' => str_repeat( '9', 600 ), 'ghost' => 'x', 'r_extras' => [ 'opt_a', 7 ] ],
			'not-a-row',
		] );
		$this->assertSame( 500, strlen( $rows[0]['r_area'] ) );
		$this->assertArrayNotHasKey( 'ghost', $rows[0] );
		$this->assertSame( [ 'opt_a', '7' ], $rows[0]['r_extras'] );
		$this->assertSame( [], $rows[1] ); // garbage row ⇒ empty row object
		// Garbage instead of an array ⇒ zero rows (never trusted).
		$this->assertSame( [], QuoteController::sanitize_repeater_rows( $field, 'hax' ) );
	}

	public function test_repeater_snapshot_keeps_active_repeaters_with_labels(): void {
		Functions\when( 'sanitize_text_field' )->alias( static fn( $s ) => trim( strip_tags( (string) $s ) ) );
		$fields = [
			[ 'id' => 'rooms', 'type' => 'repeater', 'label' => 'Rooms', 'fields' => [
				[ 'id' => 'r_area', 'type' => 'number', 'label' => 'Area' ],
				[ 'id' => 'r_rate', 'type' => 'select', 'label' => 'Rate', 'options' => [] ],
			] ],
			[ 'id' => 'hidden_rep', 'type' => 'repeater', 'label' => 'Hidden', 'fields' => [] ],
		];
		$result = [
			'active'    => [ 'rooms' => true, 'hidden_rep' => false ],
			'repeaters' => [
				'rooms'      => [ 'sum' => 1200000, 'rows' => [ [ 'label' => 'Room 1', 'total' => 1200000, 'values' => [ 'r_area' => '20', 'r_rate' => 'Standard' ] ] ], 'error' => '' ],
				'hidden_rep' => [ 'sum' => 0, 'rows' => [], 'error' => '' ],
			],
		];
		$snap = QuoteController::repeater_snapshot( $fields, $result );
		$this->assertCount( 1, $snap );
		$this->assertSame( 'rooms', $snap[0]['id'] );
		$this->assertSame( [ 'r_area' => 'Area', 'r_rate' => 'Rate' ], $snap[0]['children'] );
		$this->assertSame( [ 'r_area' => 'number', 'r_rate' => 'select' ], $snap[0]['types'] );
		$this->assertSame( 'Room 1', $snap[0]['rows'][0]['label'] );
		$this->assertSame( 1200000, $snap[0]['rows'][0]['total'] );
		$this->assertSame( '20', $snap[0]['rows'][0]['values']['r_area'] );
	}

	public function test_resolve_file_gates_tokens(): void {
		$cfg_on = [ 'settings' => [ 'quoteForm' => [ 'file' => [ 'enabled' => true ] ] ] ];
		// Feature off or no token sent ⇒ null (no file — not an error).
		$this->assertNull( QuoteController::resolve_file( [ 'settings' => [ 'quoteForm' => [] ] ], str_repeat( 'a', 32 ) ) );
		$this->assertNull( QuoteController::resolve_file( $cfg_on, '' ) );
		// Malformed / unknown tokens ⇒ false (handle() maps this to 400 file_invalid).
		$this->assertFalse( QuoteController::resolve_file( $cfg_on, 'not-a-token' ) );
		Functions\when( 'get_option' )->justReturn( false );
		$this->assertFalse( QuoteController::resolve_file( $cfg_on, str_repeat( 'a', 32 ) ) );
	}

	public function test_resolve_file_returns_consumed_meta(): void {
		$cfg_on = [ 'settings' => [ 'quoteForm' => [ 'file' => [ 'enabled' => true ] ] ] ];
		Functions\when( 'get_option' )->justReturn( [ 'name' => 'roof.jpg', 'stored' => '3f2ab.jpg' ] );
		Functions\expect( 'delete_option' )->once(); // one-time token
		$this->assertSame(
			[ 'name' => 'roof.jpg', 'stored' => '3f2ab.jpg' ],
			QuoteController::resolve_file( $cfg_on, str_repeat( 'a', 32 ) )
		);
	}
}
