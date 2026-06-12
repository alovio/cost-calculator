<?php
namespace Alovio\Calculator\Tests\Unit\Frontend;

use Alovio\Calculator\Frontend\CurrencyFormatter;
use PHPUnit\Framework\TestCase;

class CurrencyFormatterTest extends TestCase {

	private const CUR = [ 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'thousandSep' => ',', 'decimalSep' => '.' ];

	public function test_formats_scaled_amounts(): void {
		$this->assertSame( '$1,234.50', CurrencyFormatter::format( 12345000, self::CUR ) );
		$this->assertSame( '$0.00', CurrencyFormatter::format( 0, self::CUR ) );
		$this->assertSame( '-$12.30', CurrencyFormatter::format( -123000, self::CUR ) );
	}

	public function test_position_after_and_custom_separators(): void {
		$cur = [ 'symbol' => '₼', 'position' => 'after', 'decimals' => 2, 'thousandSep' => ' ', 'decimalSep' => ',' ];
		$this->assertSame( '1 234,50₼', CurrencyFormatter::format( 12345000, $cur ) );
	}

	public function test_decimals_zero(): void {
		$cur = array_merge( self::CUR, [ 'decimals' => 0 ] );
		$this->assertSame( '$1,235', CurrencyFormatter::format( 12345000, $cur ) ); // rounds half-away
	}
}
