import { reducer, actions, selectors, initialState, HISTORY_LIMIT, remapFields, REPEATER_CHILD_TYPES } from '../reducer';

const run = ( list, start = initialState ) => list.reduce( ( s, a ) => reducer( s, a ), start );

describe( 'history: undo/redo', () => {
	it( 'undo restores fields, settings and name; redo re-applies', () => {
		let s = run( [ actions.addField( 'number' ), actions.updateSettings( { theme: { preset: 'bold' } } ), actions.setName( 'Roof quote' ) ] );
		expect( s.fields ).toHaveLength( 1 );
		s = reducer( s, actions.undo() ); // name gone
		expect( s.name ).toBe( '' );
		s = reducer( s, actions.undo() ); // settings gone
		expect( s.settings ).toEqual( {} );
		s = reducer( s, actions.redo() );
		expect( s.settings.theme.preset ).toBe( 'bold' );
		s = reducer( s, actions.redo() );
		expect( s.name ).toBe( 'Roof quote' );
		expect( selectors.canRedo( s ) ).toBe( false );
	} );

	it( 'redo stack is cleared by a new mutation', () => {
		let s = run( [ actions.addField( 'number' ), actions.addField( 'slider' ) ] );
		s = reducer( s, actions.undo() );
		expect( selectors.canRedo( s ) ).toBe( true );
		s = reducer( s, actions.addField( 'toggle' ) );
		expect( selectors.canRedo( s ) ).toBe( false );
	} );

	it( 'history is bounded to HISTORY_LIMIT (50)', () => {
		expect( HISTORY_LIMIT ).toBe( 50 );
		let s = initialState;
		for ( let i = 0; i < 60; i++ ) {
			s = reducer( s, actions.setName( `n${ i }` ) );
		}
		expect( s.past ).toHaveLength( 50 );
	} );

	it( 'SELECT is never recorded; HYDRATE clears both stacks and sets the name', () => {
		let s = run( [ actions.addField( 'number' ) ] );
		const depth = s.past.length;
		s = reducer( s, actions.selectField( null ) );
		expect( s.past ).toHaveLength( depth );
		s = reducer( s, actions.undo() );
		s = reducer( s, actions.hydrate( [ { id: 'a', type: 'number' } ], { x: 1 }, 'Loaded' ) );
		expect( s.past ).toHaveLength( 0 );
		expect( s.future ).toHaveLength( 0 );
		expect( s.name ).toBe( 'Loaded' );
	} );

	it( 'UNDO/REDO on empty stacks are no-ops', () => {
		expect( reducer( initialState, actions.undo() ) ).toBe( initialState );
		expect( reducer( initialState, actions.redo() ) ).toBe( initialState );
	} );

	it( 'selection is dropped when the selected field vanishes on undo', () => {
		let s = run( [ actions.addField( 'number' ), actions.addField( 'slider' ) ] );
		expect( s.selectedId ).toBe( s.fields[ 1 ].id );
		s = reducer( s, actions.undo() );
		expect( s.selectedId ).toBeNull();
	} );
} );

describe( 'INSERT_AT', () => {
	it( 'inserts at the index, selects the new field, records history', () => {
		let s = run( [ actions.addField( 'number' ), actions.addField( 'slider' ) ] );
		s = reducer( s, actions.insertAt( 'toggle', 1 ) );
		expect( s.fields.map( ( f ) => f.type ) ).toEqual( [ 'number', 'toggle', 'slider' ] );
		expect( s.selectedId ).toBe( s.fields[ 1 ].id );
		s = reducer( s, actions.undo() );
		expect( s.fields.map( ( f ) => f.type ) ).toEqual( [ 'number', 'slider' ] );
	} );

	it( 'clamps out-of-range indexes', () => {
		let s = run( [ actions.addField( 'number' ) ] );
		s = reducer( s, actions.insertAt( 'slider', 99 ) );
		expect( s.fields[ 1 ].type ).toBe( 'slider' );
		s = reducer( s, actions.insertAt( 'toggle', -5 ) );
		expect( s.fields[ 0 ].type ).toBe( 'toggle' );
	} );
} );

describe( 'INSERT_FIELDS + remapFields', () => {
	const tpl = [
		{ id: 'area', type: 'slider', label: 'Area', min: 0, max: 100 },
		{ id: 'svc', type: 'radio', label: 'Service', options: [ { value: 'opt_std', label: 'Std', price: 2 } ] },
		{
			id: 'note', type: 'heading', label: 'Note',
			conditions: [ { field: 'area', operator: 'gt', value: '50' }, { field: 'external', operator: 'is', value: 'x' } ],
			conditionMatch: 'all', conditionAction: 'show',
		},
	];

	it( 'remaps ids and intra-template condition refs; leaves foreign refs + option slugs alone', () => {
		const out = remapFields( tpl );
		expect( out ).toHaveLength( 3 );
		expect( out.map( ( f ) => f.id ) ).not.toContain( 'area' );
		expect( new Set( out.map( ( f ) => f.id ) ).size ).toBe( 3 );
		expect( out[ 2 ].conditions[ 0 ].field ).toBe( out[ 0 ].id ); // remapped
		expect( out[ 2 ].conditions[ 1 ].field ).toBe( 'external' ); // untouched
		expect( out[ 1 ].options[ 0 ].value ).toBe( 'opt_std' ); // slug preserved
		expect( tpl[ 0 ].id ).toBe( 'area' ); // input not mutated
	} );

	it( 'inserts the mapped fields at the index and selects the first', () => {
		let s = run( [ actions.addField( 'number' ) ] );
		s = reducer( s, actions.insertFields( tpl, 0 ) );
		expect( s.fields ).toHaveLength( 4 );
		expect( s.fields[ 3 ].type ).toBe( 'number' );
		expect( s.selectedId ).toBe( s.fields[ 0 ].id );
		s = reducer( s, actions.undo() );
		expect( s.fields ).toHaveLength( 1 );
	} );

	it( 'is a state no-op for an empty list', () => {
		const s = run( [ actions.addField( 'number' ) ] );
		expect( reducer( s, { type: 'INSERT_FIELDS', fields: [], index: 0 } ) ).toBe( s );
	} );
} );

const withRepeater = () => reducer( initialState, { type: 'ADD_FIELD', fieldType: 'repeater', id: 'rep1' } );

describe( 'repeater child actions', () => {
	it( 'exposes the restricted child type list', () => {
		expect( REPEATER_CHILD_TYPES ).toEqual( [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity' ] );
	} );

	it( 'ADD_CHILD_FIELD appends a typed child to the parent only', () => {
		let s = withRepeater();
		s = reducer( s, { type: 'ADD_CHILD_FIELD', parentId: 'rep1', fieldType: 'number', id: 'c1' } );
		const rep = s.fields.find( ( f ) => f.id === 'rep1' );
		expect( rep.fields ).toHaveLength( 1 );
		expect( rep.fields[ 0 ] ).toMatchObject( { id: 'c1', type: 'number', label: 'Number' } );
		expect( s.fields ).toHaveLength( 1 ); // child did NOT land at the top level
	} );

	it( 'UPDATE_CHILD_FIELD patches one child in place', () => {
		let s = withRepeater();
		s = reducer( s, { type: 'ADD_CHILD_FIELD', parentId: 'rep1', fieldType: 'number', id: 'c1' } );
		s = reducer( s, { type: 'UPDATE_CHILD_FIELD', parentId: 'rep1', id: 'c1', patch: { label: 'Area', default: 5 } } );
		expect( s.fields[ 0 ].fields[ 0 ] ).toMatchObject( { label: 'Area', default: 5 } );
	} );

	it( 'REMOVE_CHILD_FIELD and REORDER_CHILD manage the child list', () => {
		let s = withRepeater();
		s = reducer( s, { type: 'ADD_CHILD_FIELD', parentId: 'rep1', fieldType: 'number', id: 'c1' } );
		s = reducer( s, { type: 'ADD_CHILD_FIELD', parentId: 'rep1', fieldType: 'toggle', id: 'c2' } );
		s = reducer( s, { type: 'REORDER_CHILD', parentId: 'rep1', from: 1, to: 0 } );
		expect( s.fields[ 0 ].fields.map( ( c ) => c.id ) ).toEqual( [ 'c2', 'c1' ] );
		s = reducer( s, { type: 'REORDER_CHILD', parentId: 'rep1', from: 0, to: 5 } );
		expect( s.fields[ 0 ].fields.map( ( c ) => c.id ) ).toEqual( [ 'c2', 'c1' ] ); // out of range ignored
		s = reducer( s, { type: 'REMOVE_CHILD_FIELD', parentId: 'rep1', id: 'c2' } );
		expect( s.fields[ 0 ].fields.map( ( c ) => c.id ) ).toEqual( [ 'c1' ] );
	} );

	it( 'child edits are undoable', () => {
		let s = withRepeater();
		s = reducer( s, { type: 'ADD_CHILD_FIELD', parentId: 'rep1', fieldType: 'number', id: 'c1' } );
		s = reducer( s, { type: 'UPDATE_CHILD_FIELD', parentId: 'rep1', id: 'c1', patch: { label: 'Area' } } );
		expect( s.fields[ 0 ].fields[ 0 ].label ).toBe( 'Area' );
		s = reducer( s, actions.undo() );
		expect( s.fields[ 0 ].fields[ 0 ].label ).toBe( 'Number' );
	} );
} );
