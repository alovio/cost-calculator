<?php
namespace Alovio\Calculator\Tests\Unit\Formula;

use Alovio\Calculator\Formula\FormulaGraph;
use PHPUnit\Framework\TestCase;

class FormulaGraphTest extends TestCase {

	public function test_orders_dependencies_before_dependents(): void {
		$result = FormulaGraph::order(
			[
				'total'    => [ 'subtotal' ],
				'subtotal' => [],
				'tax'      => [ 'subtotal' ],
				'grand'    => [ 'total', 'tax' ],
			]
		);
		$this->assertSame( [], $result['cycles'] );
		$order = $result['order'];
		$this->assertLessThan( array_search( 'total', $order, true ), array_search( 'subtotal', $order, true ) );
		$this->assertLessThan( array_search( 'grand', $order, true ), array_search( 'tax', $order, true ) );
		$this->assertCount( 4, $order );
	}

	public function test_detects_cycles(): void {
		$result = FormulaGraph::order(
			[
				'a' => [ 'b' ],
				'b' => [ 'a' ],
				'c' => [],
			]
		);
		$this->assertSame( [ 'c' ], $result['order'] );
		$this->assertEqualsCanonicalizing( [ 'a', 'b' ], $result['cycles'] );
	}

	public function test_ignores_refs_to_non_formula_fields(): void {
		$result = FormulaGraph::order( [ 'total' => [ 'qty', 'price' ] ] ); // qty/price are inputs, not keys.
		$this->assertSame( [ 'total' ], $result['order'] );
		$this->assertSame( [], $result['cycles'] );
	}
}
