import { groupSteps } from '../wizard';

function fieldEl( id, isStep ) {
	const el = document.createElement( 'div' );
	el.setAttribute( 'data-alc-field', id );
	el.className = 'alc-field alc-field--' + ( isStep ? 'step' : 'number' );
	return el;
}

const ids = ( arr ) => arr.map( ( e ) => e.getAttribute( 'data-alc-field' ) );

describe( 'groupSteps', () => {
	it( 'splits at step dividers; fields before the first divider form an unheaded group', () => {
		const groups = groupSteps( [
			fieldEl( 'a' ),
			fieldEl( 's1', true ),
			fieldEl( 'b' ),
			fieldEl( 'c' ),
			fieldEl( 's2', true ),
			fieldEl( 'd' ),
		] );
		expect( groups ).toHaveLength( 3 );
		expect( groups[ 0 ].header ).toBeNull();
		expect( ids( groups[ 0 ].items ) ).toEqual( [ 'a' ] );
		expect( groups[ 1 ].header.getAttribute( 'data-alc-field' ) ).toBe( 's1' );
		expect( ids( groups[ 1 ].items ) ).toEqual( [ 'b', 'c' ] );
		expect( groups[ 2 ].header.getAttribute( 'data-alc-field' ) ).toBe( 's2' );
		expect( ids( groups[ 2 ].items ) ).toEqual( [ 'd' ] );
	} );

	it( 'a leading divider yields no empty first group', () => {
		const groups = groupSteps( [ fieldEl( 's1', true ), fieldEl( 'a' ), fieldEl( 's2', true ), fieldEl( 'b' ) ] );
		expect( groups ).toHaveLength( 2 );
		expect( groups[ 0 ].header.getAttribute( 'data-alc-field' ) ).toBe( 's1' );
		expect( ids( groups[ 0 ].items ) ).toEqual( [ 'a' ] );
	} );

	it( 'no dividers → a single unheaded group (wizard stays inactive)', () => {
		const groups = groupSteps( [ fieldEl( 'a' ), fieldEl( 'b' ) ] );
		expect( groups ).toHaveLength( 1 );
		expect( groups[ 0 ].header ).toBeNull();
		expect( ids( groups[ 0 ].items ) ).toEqual( [ 'a', 'b' ] );
	} );
} );
