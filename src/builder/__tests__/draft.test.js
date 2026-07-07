import { saveDraft, loadDraft, clearDraft, isDraftNewer, parseModifiedGmt, draftKey, DRAFT_DEBOUNCE_MS } from '../draft';

const memStorage = () => {
	const m = new Map();
	return {
		getItem: ( k ) => ( m.has( k ) ? m.get( k ) : null ),
		setItem: ( k, v ) => m.set( k, String( v ) ),
		removeItem: ( k ) => m.delete( k ),
	};
};

describe( 'draft storage', () => {
	it( 'round-trips and stamps calcId + savedAt under the documented key', () => {
		const s = memStorage();
		expect( draftKey( 7 ) ).toBe( 'alovio_calc_draft_7' );
		saveDraft( 7, { name: 'Roof', fields: [ { id: 'a' } ], settings: { x: 1 } }, s );
		const d = loadDraft( 7, s );
		expect( d.calcId ).toBe( 7 );
		expect( d.name ).toBe( 'Roof' );
		expect( typeof d.savedAt ).toBe( 'number' );
	} );
	it( 'returns null for missing, corrupt, or shape-invalid entries', () => {
		const s = memStorage();
		expect( loadDraft( 7, s ) ).toBeNull();
		s.setItem( 'alovio_calc_draft_7', '{not json' );
		expect( loadDraft( 7, s ) ).toBeNull();
		s.setItem( 'alovio_calc_draft_7', JSON.stringify( { savedAt: 'nope' } ) );
		expect( loadDraft( 7, s ) ).toBeNull();
	} );
	it( 'clears', () => {
		const s = memStorage();
		saveDraft( 7, { name: '', fields: [], settings: {} }, s );
		clearDraft( 7, s );
		expect( loadDraft( 7, s ) ).toBeNull();
	} );
	it( 'swallows storage failures (private mode / quota)', () => {
		const broken = { getItem() { throw new Error( 'x' ); }, setItem() { throw new Error( 'x' ); }, removeItem() { throw new Error( 'x' ); } };
		expect( () => saveDraft( 7, { fields: [] }, broken ) ).not.toThrow();
		expect( loadDraft( 7, broken ) ).toBeNull();
		expect( () => clearDraft( 7, broken ) ).not.toThrow();
	} );
} );

describe( 'modified comparison', () => {
	it( 'parses MySQL GMT as UTC; invalid → 0', () => {
		expect( parseModifiedGmt( '2026-07-05 09:30:00' ) ).toBe( Date.UTC( 2026, 6, 5, 9, 30, 0 ) );
		expect( parseModifiedGmt( '' ) ).toBe( 0 );
		expect( parseModifiedGmt( 'garbage' ) ).toBe( 0 );
		expect( parseModifiedGmt( null ) ).toBe( 0 );
	} );
	it( 'isDraftNewer compares savedAt to the server timestamp', () => {
		const at = Date.UTC( 2026, 6, 5, 10, 0, 0 );
		expect( isDraftNewer( { savedAt: at, fields: [] }, '2026-07-05 09:30:00' ) ).toBe( true );
		expect( isDraftNewer( { savedAt: at, fields: [] }, '2026-07-05 11:00:00' ) ).toBe( false );
		expect( isDraftNewer( null, '2026-07-05 09:30:00' ) ).toBe( false );
		expect( isDraftNewer( { savedAt: at, fields: [] }, '' ) ).toBe( true ); // no server stamp → draft wins
	} );
	it( 'exports the 1s debounce constant', () => {
		expect( DRAFT_DEBOUNCE_MS ).toBe( 1000 );
	} );
} );
