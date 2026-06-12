import { orderFormulas } from '../graph';

describe( 'orderFormulas', () => {
	it( 'orders dependencies before dependents', () => {
		const result = orderFormulas( {
			total: [ 'subtotal' ],
			subtotal: [],
			tax: [ 'subtotal' ],
			grand: [ 'total', 'tax' ],
		} );
		expect( result.cycles ).toEqual( [] );
		const { order } = result;
		expect( order.indexOf( 'subtotal' ) ).toBeLessThan( order.indexOf( 'total' ) );
		expect( order.indexOf( 'tax' ) ).toBeLessThan( order.indexOf( 'grand' ) );
		expect( order ).toHaveLength( 4 );
	} );

	it( 'detects cycles', () => {
		const result = orderFormulas( {
			a: [ 'b' ],
			b: [ 'a' ],
			c: [],
		} );
		expect( result.order ).toEqual( [ 'c' ] );
		expect( result.cycles.sort() ).toEqual( [ 'a', 'b' ] );
	} );

	it( 'ignores refs to non-formula fields', () => {
		const result = orderFormulas( { total: [ 'qty', 'price' ] } );
		expect( result.order ).toEqual( [ 'total' ] );
		expect( result.cycles ).toEqual( [] );
	} );
} );
