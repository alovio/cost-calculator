import {
	FormulaError, toScaled, fromScaled, add, sub, mul, div,
	roundToDecimals, ceilToInt, floorToInt,
} from '../decimal';

describe( 'decimal', () => {
	it( 'rounds at the conversion boundary, sign-aware', () => {
		expect( toScaled( 4.1 ) ).toBe( 41000 );   // naive 4.1*10000 = 41000.00000000001
		expect( toScaled( '4.1' ) ).toBe( 41000 );
		expect( toScaled( -4.1 ) ).toBe( -41000 ); // Math.round(-41000.0000...) would also pass here,
		expect( toScaled( -0.00005 ) ).toBe( -1 ); // ...but the half-case requires sign-aware rounding (spec §7).
	} );

	it( 'rejects non-numeric input', () => {
		expect( () => toScaled( 'abc' ) ).toThrow( FormulaError );
		expect( () => toScaled( '' ) ).toThrow( FormulaError );
	} );

	it( 'add/sub exact: 0.1 + 0.2 = 0.3', () => {
		expect( fromScaled( add( toScaled( '0.1' ), toScaled( '0.2' ) ) ) ).toBe( '0.3' );
		expect( fromScaled( sub( toScaled( '0.1' ), toScaled( '0.2' ) ) ) ).toBe( '-0.1' );
	} );

	it( 'mul/div rescale with half-away rounding (BigInt path)', () => {
		expect( fromScaled( mul( 1000, 2000 ) ) ).toBe( '0.02' );
		expect( fromScaled( mul( 41000, 30000 ) ) ).toBe( '12.3' );
		expect( mul( 1, 5000 ) ).toBe( 1 );    // 0.0001 * 0.5 → 0.0001 (half away)
		expect( mul( -1, 5000 ) ).toBe( -1 );
		expect( fromScaled( div( 100000, 30000 ) ) ).toBe( '3.3333' );
		expect( fromScaled( div( -100000, 30000 ) ) ).toBe( '-3.3333' );
	} );

	it( 'div by zero / overflow throw coded errors', () => {
		expect( () => div( 10000, 0 ) ).toThrow( expect.objectContaining( { code: 'div_zero' } ) );
		const big = toScaled( '999999999' );
		expect( () => mul( big, big ) ).toThrow( expect.objectContaining( { code: 'overflow' } ) );
	} );

	it( 'roundToDecimals half away from zero', () => {
		expect( roundToDecimals( 25000, 0 ) ).toBe( 30000 );
		expect( roundToDecimals( -25000, 0 ) ).toBe( -30000 );
		expect( fromScaled( roundToDecimals( toScaled( '1.235' ), 2 ) ) ).toBe( '1.24' );
	} );

	it( 'ceil/floor to integer incl. negatives', () => {
		expect( fromScaled( ceilToInt( toScaled( '2.1' ) ) ) ).toBe( '3' );
		expect( fromScaled( floorToInt( toScaled( '2.9' ) ) ) ).toBe( '2' );
		expect( fromScaled( ceilToInt( toScaled( '-2.5' ) ) ) ).toBe( '-2' );
		expect( fromScaled( floorToInt( toScaled( '-2.5' ) ) ) ).toBe( '-3' );
	} );

	it( 'fromScaled trims trailing zeros', () => {
		expect( fromScaled( 120000 ) ).toBe( '12' );
		expect( fromScaled( 125000 ) ).toBe( '12.5' );
		expect( fromScaled( 1 ) ).toBe( '0.0001' );
		expect( fromScaled( -125000 ) ).toBe( '-12.5' );
	} );
} );
