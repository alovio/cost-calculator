import { TOUR_STEPS, nextTourState, shouldStartTour, markTourDone, STORAGE_KEY } from '../tour';

const fakeStorage = ( initial = {} ) => {
	const data = { ...initial };
	return {
		getItem: ( k ) => ( k in data ? data[ k ] : null ),
		setItem: ( k, v ) => {
			data[ k ] = String( v );
		},
	};
};

describe( 'tour steps', () => {
	it( 'defines exactly 3 steps: palette → canvas → save', () => {
		expect( TOUR_STEPS.map( ( s ) => s.target ) ).toEqual( [
			'[data-tour="palette"]',
			'[data-tour="canvas"]',
			'[data-tour="save"]',
		] );
	} );
} );

describe( 'nextTourState', () => {
	const start = { index: 0, done: false };
	it( 'advances through all steps then completes', () => {
		let s = nextTourState( start, 'next' );
		expect( s ).toEqual( { index: 1, done: false } );
		s = nextTourState( s, 'next' );
		expect( s ).toEqual( { index: 2, done: false } );
		s = nextTourState( s, 'next' );
		expect( s.done ).toBe( true );
	} );
	it( 'dismiss completes from any step', () => {
		expect( nextTourState( { index: 1, done: false }, 'dismiss' ).done ).toBe( true );
	} );
	it( 'back never goes below step 0 and a done tour ignores actions', () => {
		expect( nextTourState( start, 'back' ).index ).toBe( 0 );
		expect( nextTourState( { index: 2, done: true }, 'next' ).done ).toBe( true );
	} );
} );

describe( 'dismissed flag', () => {
	it( 'starts only when the flag is absent, and markTourDone sets it', () => {
		const storage = fakeStorage();
		expect( shouldStartTour( storage ) ).toBe( true );
		markTourDone( storage );
		expect( storage.getItem( STORAGE_KEY ) ).toBe( '1' );
		expect( shouldStartTour( storage ) ).toBe( false );
	} );
	it( 'never starts when storage throws (private mode)', () => {
		const throwing = { getItem: () => { throw new Error( 'nope' ); } };
		expect( shouldStartTour( throwing ) ).toBe( false );
	} );
} );
