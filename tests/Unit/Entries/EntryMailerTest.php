<?php
namespace Alovio\Calculator\Tests\Unit\Entries;

use Alovio\Calculator\Entries\EntryMailer;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class EntryMailerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_option' )->justReturn( 'admin@site.test' );
		Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
		Functions\when( 'wp_specialchars_decode' )->returnArg();
	}

	private function post(): \WP_Post {
		$p             = new \WP_Post();
		$p->post_title = 'Roof Quote';
		return $p;
	}

	private function config(): array {
		// notifyEmail '' forces the get_option( admin_email ) fallback.
		return array( 'settings' => array( 'quoteForm' => array( 'notifyEmail' => '' ) ) );
	}

	private function snapshot(): array {
		return array(
			'lineItems'   => array( array( 'id' => 'a', 'label' => 'Base', 'amount' => 1000000, 'isCurrency' => true ) ),
			'totalScaled' => 1000000,
			'currency'    => array(),
		);
	}

	/** A Pro-style filter that adds an attachment + customer recipient must reach wp_mail(). */
	public function test_quote_email_filter_attachments_reach_wp_mail(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				if ( 'alovio_calc_quote_email' === $hook ) {
					$value['attachments'][] = '/tmp/quote-7.pdf';
					$value['to']            = 'lead@customer.test';
				}
				return $value;
			}
		);

		$captured = null;
		Functions\when( 'wp_mail' )->alias(
			static function ( $to, $subject, $message, $headers, $attachments ) use ( &$captured ) {
				$captured = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
				return true;
			}
		);

		( new EntryMailer() )->notify( $this->post(), $this->config(), array( 'name' => 'T', 'email' => 'a@b.co' ), $this->snapshot(), 7 );

		$this->assertSame( 'lead@customer.test', $captured['to'] );
		$this->assertSame( array( '/tmp/quote-7.pdf' ), $captured['attachments'] );
		$this->assertStringContainsString( 'Roof Quote', $captured['message'] );
	}

	/** With no Pro filter, the admin notice goes out with no attachments/headers. */
	public function test_default_email_has_no_attachments(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 ); // Passthrough: return $mail ($value) unchanged.

		$captured = null;
		Functions\when( 'wp_mail' )->alias(
			static function ( $to, $subject, $message, $headers, $attachments ) use ( &$captured ) {
				$captured = compact( 'to', 'subject', 'message', 'headers', 'attachments' );
				return true;
			}
		);

		( new EntryMailer() )->notify( $this->post(), $this->config(), array( 'name' => 'T' ), $this->snapshot(), 0 );

		$this->assertSame( 'admin@site.test', $captured['to'] );
		$this->assertSame( array(), $captured['attachments'] );
		$this->assertSame( array(), $captured['headers'] );
	}

	public function test_repeater_lines_use_child_labels_and_currency(): void {
		$snapshot = [
			'currency'  => [ 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousandSep' => ',', 'decimalSep' => '.' ],
			'repeaters' => [ [
				'id' => 'rooms', 'label' => 'Rooms',
				'children' => [ 'r_area' => 'Area', 'r_express' => 'Express' ],
				'types'    => [ 'r_area' => 'number', 'r_express' => 'toggle' ],
				'rows'     => [ [ 'label' => 'Room 1', 'total' => 1200000, 'values' => [ 'r_area' => '20', 'r_express' => '1' ] ] ],
			] ],
		];
		$this->assertSame(
			[ 'Room 1: Area 20, Express — $120.00' ],
			EntryMailer::repeater_lines( $snapshot, 'rooms' )
		);
		$this->assertSame( [], EntryMailer::repeater_lines( $snapshot, 'ghost' ) );
	}
}
