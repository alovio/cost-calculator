<?php
namespace Alovio\Calculator\Tests\Unit\Logic;

use Alovio\Calculator\Formula\DecimalMath;
use Alovio\Calculator\Logic\Evaluation;
use Alovio\Calculator\Tests\TestCase;
use Brain\Monkey\Functions;

class RepeaterCasesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	/** @dataProvider casesProvider */
	public function test_fixture_case( array $case ): void {
		$r = Evaluation::run( [ 'fields' => $case['fields'] ], $case['values'] );

		foreach ( $case['expected']['values'] as $id => $want ) {
			$this->assertSame( $want, DecimalMath::fromScaled( (int) $r['values'][ $id ] ), "value {$id}" );
		}
		if ( array_key_exists( 'total', $case['expected'] ) ) {
			$this->assertSame( $case['expected']['total'], DecimalMath::fromScaled( (int) $r['totalScaled'] ) );
		}
		foreach ( ( $case['expected']['active'] ?? [] ) as $id => $want ) {
			$this->assertSame( $want, $r['active'][ $id ], "active {$id}" );
		}
		if ( isset( $case['expected']['lineItems'] ) ) {
			$got = [];
			foreach ( $r['lineItems'] as $item ) {
				$g = [
					'id'         => $item['id'],
					'label'      => $item['label'],
					'amount'     => DecimalMath::fromScaled( $item['amount'] ),
					'isCurrency' => $item['isCurrency'],
				];
				if ( isset( $item['repeaterId'] ) ) {
					$g['repeaterId'] = $item['repeaterId'];
				}
				$got[] = $g;
			}
			$this->assertSame( $case['expected']['lineItems'], $got );
		}

		// Row-level block: raw (pre-visibility) sum + rows.
		$rep = $r['repeaters'][ $case['repeater']['id'] ];
		$this->assertSame( $case['repeater']['sum'], DecimalMath::fromScaled( $rep['sum'] ) );
		$rows = [];
		foreach ( $rep['rows'] as $row ) {
			$rows[] = [
				'label' => $row['label'],
				'total' => DecimalMath::fromScaled( $row['total'] ),
			];
		}
		$this->assertSame( $case['repeater']['rows'], $rows );
		if ( isset( $case['repeater']['error'] ) ) {
			$this->assertSame( $case['repeater']['error'], $rep['error'] );
			$this->assertSame( $case['repeater']['error'], $r['errors'][ $case['repeater']['id'] ] );
		}
	}

	public function casesProvider(): iterable {
		$json = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/repeater-cases.json' ), true );
		foreach ( $json['cases'] as $case ) {
			yield $case['name'] => [ $case ];
		}
	}
}
