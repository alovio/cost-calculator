export const SCALE = 10000;
export const MAX_SCALED = 9999999999999; // ±999,999,999.9999

export class FormulaError extends Error {
	constructor( code, message, pos = -1 ) {
		super( message );
		this.name = 'FormulaError';
		this.code = code;
		this.pos = pos;
	}
}

function guard( absScaled ) {
	if ( absScaled > MAX_SCALED ) {
		throw new FormulaError( 'overflow', 'Value exceeds supported range (±999,999,999.9999)' );
	}
}

// Callers must pass sanitized decimal strings/numbers (compute.js does) — JS Number()
// accepts a few exotic inputs PHP is_numeric rejects, and the PHP server stays authoritative.
export function toScaled( v ) {
	if ( typeof v === 'string' && v.trim() === '' ) {
		throw new FormulaError( 'bad_number', 'Not a number: empty string' );
	}
	const f = Number( v );
	if ( ! Number.isFinite( f ) ) {
		throw new FormulaError( 'bad_number', 'Not a number: ' + String( v ) );
	}
	const sign = f < 0 ? -1 : 1;
	// Sign-aware boundary rounding: Math.round alone rounds half toward +∞ (spec §7).
	const scaled = Math.round( Math.abs( f ) * SCALE );
	guard( scaled );
	return sign * scaled;
}

export function fromScaled( x ) {
	const sign = x < 0 ? '-' : '';
	const a = Math.abs( x );
	const int = Math.trunc( a / SCALE );
	const frac = String( a % SCALE ).padStart( 4, '0' ).replace( /0+$/, '' );
	return sign + String( int ) + ( frac === '' ? '' : '.' + frac );
}

export function add( a, b ) {
	const r = a + b;
	guard( Math.abs( r ) );
	return r;
}

export function sub( a, b ) {
	return add( a, -b );
}

// Integer division n/d (BigInt, n ≥ 0n, d > 0n), half away from zero.
function divRoundBig( n, d ) {
	const q = n / d;
	const r = n - q * d;
	return 2n * r >= d ? q + 1n : q;
}

export function mul( a, b ) {
	const approx = ( a / SCALE ) * ( b / SCALE );
	if ( Math.abs( approx ) > MAX_SCALED / SCALE + 1 ) {
		throw new FormulaError( 'overflow', 'Multiplication overflow' );
	}
	const sign = a < 0 !== b < 0 ? -1 : 1;
	const A = BigInt( Math.abs( a ) );
	const B = BigInt( Math.abs( b ) );
	const S = BigInt( SCALE );
	const result = A * ( B / S ) + divRoundBig( A * ( B % S ), S );
	const num = Number( result );
	guard( num );
	return sign * num;
}

export function div( a, b ) {
	if ( b === 0 ) {
		throw new FormulaError( 'div_zero', 'Division by zero' );
	}
	const approx = a / b;
	if ( Math.abs( approx ) > MAX_SCALED / SCALE + 1 ) {
		throw new FormulaError( 'overflow', 'Division overflow' );
	}
	const sign = a < 0 !== b < 0 ? -1 : 1;
	const result = divRoundBig( BigInt( Math.abs( a ) ) * BigInt( SCALE ), BigInt( Math.abs( b ) ) );
	const num = Number( result );
	guard( num );
	return sign * num;
}

export function roundToDecimals( x, n ) {
	const clamped = Math.max( 0, Math.min( 4, n ) );
	const f = 10 ** ( 4 - clamped );
	const sign = x < 0 ? -1 : 1;
	const a = Math.abs( x );
	const q = Math.trunc( a / f );
	const r = a - q * f;
	return sign * ( 2 * r >= f ? q + 1 : q ) * f;
}

export function ceilToInt( x ) {
	let q = Math.trunc( x / SCALE );
	if ( x - q * SCALE > 0 ) {
		q++;
	}
	const result = q * SCALE;
	guard( Math.abs( result ) );
	return result;
}

export function floorToInt( x ) {
	let q = Math.trunc( x / SCALE );
	if ( x - q * SCALE < 0 ) {
		q--;
	}
	const result = q * SCALE;
	guard( Math.abs( result ) );
	return result;
}
