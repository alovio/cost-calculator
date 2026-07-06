<?php
namespace Alovio\Calculator\Tests\Unit\Entries;

use Alovio\Calculator\Entries\FileUploads;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class FileUploadsTest extends TestCase {

	private function settings( array $over = [] ): array {
		return $over + [ 'enabled' => true, 'label' => '', 'types' => [ 'jpg', 'png', 'webp', 'pdf' ], 'maxMb' => 5 ];
	}

	public function test_validate_upload_enforces_size_and_type(): void {
		$ok = FileUploads::validate_upload( 'plan.PDF', 1024, 'application/pdf', $this->settings() );
		$this->assertTrue( $ok['ok'] );
		$this->assertSame( 'pdf', $ok['ext'] );

		$jpeg = FileUploads::validate_upload( 'photo.jpeg', 1024, 'image/jpeg', $this->settings() );
		$this->assertTrue( $jpeg['ok'] ); // jpeg alias maps to jpg

		$big = FileUploads::validate_upload( 'plan.pdf', 6 * 1048576, 'application/pdf', $this->settings() );
		$this->assertSame( 'too_large', $big['code'] );

		$lying = FileUploads::validate_upload( 'evil.pdf', 1024, 'application/x-httpd-php', $this->settings() );
		$this->assertSame( 'bad_type', $lying['code'] ); // finfo MIME must match the extension

		$narrow = FileUploads::validate_upload( 'pic.png', 1024, 'image/png', $this->settings( [ 'types' => [ 'pdf' ] ] ) );
		$this->assertSame( 'bad_type', $narrow['code'] ); // site narrowed the allowlist
	}

	public function test_consume_is_one_time_and_format_checked(): void {
		Functions\when( 'get_option' )->alias( static function ( $name ) {
			return 'alovio_calc_upload_' . str_repeat( 'a', 32 ) === $name
				? [ 'stored' => 'alc-x.pdf', 'name' => 'plan.pdf', 'time' => 1 ]
				: false;
		} );
		Functions\expect( 'delete_option' )->once()->with( 'alovio_calc_upload_' . str_repeat( 'a', 32 ) );

		$this->assertNull( FileUploads::consume( 'not-a-token' ) );
		$this->assertNull( FileUploads::consume( str_repeat( 'b', 32 ) ) );
		$this->assertSame(
			[ 'name' => 'plan.pdf', 'stored' => 'alc-x.pdf' ],
			FileUploads::consume( str_repeat( 'a', 32 ) )
		);
	}
}
