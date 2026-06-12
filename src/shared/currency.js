import { SCALE, roundToDecimals } from './formula/decimal';

// Mirror of includes/Frontend/CurrencyFormatter.php — settings-driven separators,
// NOT toLocaleString (the browser locale is irrelevant; config decides).
export function formatCurrency( scaled, currency ) {
	const decimals = Number( currency.decimals ) || 0;
	const rounded = roundToDecimals( scaled, decimals );
	const sign = rounded < 0 ? '-' : '';
	const abs = Math.abs( rounded );
	const int = Math.trunc( abs / SCALE );
	const fracPart = String( abs % SCALE ).padStart( 4, '0' ).slice( 0, decimals );

	let intStr = String( int );
	const groups = [];
	while ( intStr.length > 3 ) {
		groups.unshift( intStr.slice( -3 ) );
		intStr = intStr.slice( 0, -3 );
	}
	groups.unshift( intStr );
	const grouped = groups.join( currency.thousandSep ?? ',' );

	const number = grouped + ( decimals > 0 ? ( currency.decimalSep ?? '.' ) + fracPart : '' );
	return currency.position === 'after'
		? sign + number + ( currency.symbol ?? '' )
		: sign + ( currency.symbol ?? '' ) + number;
}
