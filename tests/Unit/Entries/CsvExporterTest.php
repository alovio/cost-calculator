<?php
namespace Alovio\Calculator\Tests\Unit\Entries;

use Alovio\Calculator\Entries\CsvExporter;
use Alovio\Calculator\Tests\TestCase;

class CsvExporterTest extends TestCase {

	public function test_csv_line_escapes_and_orders_columns(): void {
		$line = CsvExporter::csv_row(
			[
				'id'            => 3,
				'created_at'    => '2026-06-12 10:00:00',
				'name'          => 'A "B"',
				'email'         => 'a@b.co',
				'phone'         => '',
				'message'       => "multi\nline",
				'total'         => '175.0000',
				'status'        => 'new',
				'snapshot'      => '{"values":{"area":"50"}}',
				'calculator_id' => 7,
			]
		);
		$this->assertSame( '3,7,"2026-06-12 10:00:00","A ""B""",a@b.co,,"multi line",175.0000,new,,"{""values"":{""area"":""50""}}"', $line );
	}

	public function test_repeater_cell_flattens_rows_per_spec(): void {
		$snapshot = [
			'currency'  => [ 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousandSep' => ',', 'decimalSep' => '.' ],
			'repeaters' => [ [
				'id' => 'rooms', 'label' => 'Rooms',
				'children' => [ 'r_area' => 'Area', 'r_rate' => 'Rate' ],
				'types'    => [ 'r_area' => 'number', 'r_rate' => 'select' ],
				'rows'     => [
					[ 'label' => 'Room 1', 'total' => 1200000, 'values' => [ 'r_area' => '20', 'r_rate' => 'Standard' ] ],
					[ 'label' => 'Room 2', 'total' => 900000, 'values' => [ 'r_area' => '10', 'r_rate' => '' ] ],
				],
			] ],
		];
		$this->assertSame(
			'Room 1: r_area=20, r_rate=Standard ($120.00) | Room 2: r_area=10 ($90.00)',
			CsvExporter::repeater_cell( $snapshot )
		);
		$this->assertSame( '', CsvExporter::repeater_cell( [] ) );
	}

	public function test_formula_injection_guard(): void {
		$line = CsvExporter::csv_row(
			[
				'id'            => 1,
				'created_at'    => 'x',
				'name'          => '=cmd()',
				'email'         => '+SUM(A1)',
				'phone'         => '@sum',
				'message'       => '-2+cmd',
				'total'         => '0.0000',
				'status'        => 'new',
				'snapshot'      => '{}',
				'calculator_id' => 1,
			]
		);
		$this->assertStringContainsString( "'=cmd()", $line );
		$this->assertStringContainsString( "'+SUM(A1)", $line );
		$this->assertStringContainsString( "'@sum", $line );
		$this->assertStringContainsString( "'-2+cmd", $line );
	}

	public function test_purely_numeric_cells_stay_unguarded(): void {
		$line = CsvExporter::csv_row(
			[
				'id'            => 1,
				'created_at'    => 'x',
				'name'          => 'T',
				'email'         => 'a@b.co',
				'phone'         => '+994501234567', // is_numeric ⇒ Excel reads it as a number — no injection vector, keep clean.
				'message'       => '',
				'total'         => '-175.0000',     // negative totals must not get an apostrophe.
				'status'        => 'new',
				'snapshot'      => '{}',
				'calculator_id' => 1,
			]
		);
		$this->assertStringContainsString( ',+994501234567,', $line );
		$this->assertStringContainsString( ',-175.0000,', $line );
	}
}
