<?php
declare( strict_types=1 );

namespace Alovio\Calculator\Frontend;

use Alovio\Calculator\Formula\DecimalMath;

defined( 'ABSPATH' ) || exit;

/** Settings-driven, locale-independent (separators come from config, never the browser/site locale). */
final class CurrencyFormatter {

	public static function format( int $scaled, array $currency ): string {
		$decimals = (int) $currency['decimals'];
		$rounded  = DecimalMath::roundToDecimals( $scaled, $decimals );
		$sign     = $rounded < 0 ? '-' : '';
		$abs      = abs( $rounded );
		$int      = intdiv( $abs, DecimalMath::SCALE );
		$fracPart = substr( str_pad( (string) ( $abs % DecimalMath::SCALE ), 4, '0', STR_PAD_LEFT ), 0, $decimals );
		$intStr   = number_format( $int, 0, '', $currency['thousandSep'] );
		$number   = $intStr . ( $decimals > 0 ? $currency['decimalSep'] . $fracPart : '' );
		return 'after' === $currency['position']
			? $sign . $number . $currency['symbol']
			: $sign . $currency['symbol'] . $number;
	}
}
