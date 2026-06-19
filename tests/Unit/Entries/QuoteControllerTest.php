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
}
