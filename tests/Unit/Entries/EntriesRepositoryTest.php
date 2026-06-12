<?php
namespace Alovio\Calculator\Tests\Unit\Entries;

use Alovio\Calculator\Entries\EntriesRepository;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class EntriesRepositoryTest extends TestCase {

	public function test_row_from_submission_shapes_and_clips(): void {
		Functions\when( 'current_time' )->justReturn( '2026-06-12 10:00:00' );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$row = EntriesRepository::row_from_submission(
			7,
			[ 'name' => str_repeat( 'n', 300 ), 'email' => 'a@b.co', 'phone' => '+99450', 'message' => 'hi' ],
			[ 'lineItems' => [ [ 'id' => 'total', 'label' => 'Price', 'amount' => 1750000 ] ], 'totalScaled' => 1750000, 'totalDisplay' => '$175.00', 'values' => [ 'area' => '50' ] ]
		);
		$this->assertSame( 7, $row['calculator_id'] );
		$this->assertSame( 190, strlen( $row['name'] ) );
		$this->assertSame( 'a@b.co', $row['email'] );
		$this->assertSame( '175.0000', $row['total'] );
		$this->assertSame( 'new', $row['status'] );
		$this->assertSame( '2026-06-12 10:00:00', $row['created_at'] );
		$this->assertIsString( $row['snapshot'] );
		$this->assertSame( 1750000, json_decode( $row['snapshot'], true )['totalScaled'] );
	}
}
