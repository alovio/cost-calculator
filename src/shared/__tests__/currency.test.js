import { formatCurrency } from '../currency';

const CUR = { symbol: '$', position: 'before', decimals: 2, thousandSep: ',', decimalSep: '.' };

describe( 'formatCurrency (parity with PHP CurrencyFormatter)', () => {
	it( 'formats scaled amounts', () => {
		expect( formatCurrency( 12345000, CUR ) ).toBe( '$1,234.50' );
		expect( formatCurrency( 0, CUR ) ).toBe( '$0.00' );
		expect( formatCurrency( -123000, CUR ) ).toBe( '-$12.30' );
	} );

	it( 'position after and custom separators', () => {
		expect( formatCurrency( 12345000, { symbol: '₼', position: 'after', decimals: 2, thousandSep: ' ', decimalSep: ',' } ) ).toBe( '1 234,50₼' );
	} );

	it( 'decimals zero rounds half-away', () => {
		expect( formatCurrency( 12345000, { ...CUR, decimals: 0 } ) ).toBe( '$1,235' );
	} );
} );
