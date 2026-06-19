/**
 * 1:1 port of the PHP EvaluationTest cases — same expected numbers (parity with
 * the server authority). Configs are the HAND-NORMALIZED shape the embedded
 * payload actually carries after FieldSchema::normalize.
 */
import { prepare, run, conditionValues } from '../compute';

const norm = ( f ) => ( { conditions: [], conditionMatch: 'all', conditionAction: 'show', showInSummary: false, ...f } );

const FIELDS = [
	norm( { id: 'area', type: 'slider', label: 'Area', min: 10, max: 500, step: null, default: 50, showInSummary: true } ),
	norm( { id: 'service', type: 'radio', label: 'Service', showInSummary: true, options: [
		{ value: 'opt_std', label: 'Standard', price: 2.5, image: 0 },
		{ value: 'opt_deep', label: 'Deep', price: 4, image: 0 },
	] } ),
	norm( { id: 'express', type: 'toggle', label: 'Express', price: 50, default: false } ),
	norm( { id: 'discount_note', type: 'heading', label: 'Discount!', conditions: [
		{ field: 'area', operator: 'gt', value: '100' },
	] } ),
	norm( { id: 'total', type: 'formula', label: 'Total', showInSummary: true, expression: '{area} * {service} + {express}' } ),
];

describe( 'compute (parity with PHP Evaluation)', () => {
	it( 'happy path: total + line items + inactive note', () => {
		const r = run( FIELDS, prepare( FIELDS ), { area: '50', service: 'opt_deep', express: '1' } );
		expect( r.totalScaled ).toBe( 2500000 ); // 50*4 + 50 = 250
		expect( r.lineItems.map( ( i ) => i.id ) ).toEqual( [ 'area', 'service', 'total' ] );
		expect( r.active.discount_note ).toBe( false );
	} );

	it( 'condition values follow the spec §6 table', () => {
		const cv = conditionValues( FIELDS, { area: '150', service: 'opt_std', express: '' } );
		expect( cv.area ).toBe( '150' );
		expect( cv.service ).toBe( 'opt_std' ); // slug, not price
		expect( cv.express ).toBe( '' );
		const r = run( FIELDS, prepare( FIELDS ), { area: '150', service: 'opt_std', express: '' } );
		expect( r.active.discount_note ).toBe( true );
	} );

	it( 'coerces invalid inputs instead of trusting them', () => {
		const r = run( FIELDS, prepare( FIELDS ), { area: '999999', service: 'opt_hax', express: 'yes' } );
		expect( r.values.area ).toBe( 5000000 );   // clamped to max 500
		expect( r.values.service ).toBe( 0 );      // unknown slug
		expect( r.values.express ).toBe( 500000 ); // truthy ⇒ on
		const r2 = run( FIELDS, prepare( FIELDS ), { express: 0 } );
		expect( r2.values.express ).toBe( 0 );      // numeric zero ⇒ off
	} );

	it( 'uses defaults when values missing', () => {
		const r = run( FIELDS, prepare( FIELDS ), {} );
		expect( r.values.area ).toBe( 500000 ); // default 50
	} );

	it( 'broken formula yields zero', () => {
		const fields = FIELDS.map( ( f ) => ( f.id === 'total' ? { ...f, expression: '{area} / 0' } : f ) );
		const r = run( fields, prepare( fields ), { area: '50' } );
		expect( r.totalScaled ).toBe( 0 );
	} );

	it( 'inactive field contributes zero', () => {
		const fields = FIELDS.map( ( f ) =>
			f.id === 'express' ? { ...f, conditions: [ { field: 'area', operator: 'gt', value: '100' } ] } : f
		);
		const r = run( fields, prepare( fields ), { area: '50', service: 'opt_std', express: '1' } );
		expect( r.values.express ).toBe( 0 );
		expect( r.totalScaled ).toBe( 1250000 ); // 50*2.5 only
	} );

	it( 'checkbox group sums prices and joins slugs', () => {
		const fields = [
			norm( { id: 'extras', type: 'checkbox_group', label: 'Extras', options: [
				{ value: 'opt_a', label: 'A', price: 10, image: 0 },
				{ value: 'opt_b', label: 'B', price: 5, image: 0 },
			] } ),
			norm( { id: 'total', type: 'formula', label: 'T', expression: '{extras}', showInSummary: true } ),
		];
		const r = run( fields, prepare( fields ), { extras: [ 'opt_a', 'opt_b', 'opt_zzz' ] } );
		expect( r.totalScaled ).toBe( 150000 );
		expect( conditionValues( fields, { extras: [ 'opt_a', 'opt_b', 'opt_zzz' ] } ).extras ).toBe( 'opt_a,opt_b' );
	} );

	it( 'a formula total drives a condition (fixed-point)', () => {
		const fields = [
			norm( { id: 'area', type: 'slider', label: 'Area', min: 10, max: 1000, default: 50 } ),
			norm( { id: 'total', type: 'formula', label: 'Total', showInSummary: true, expression: '{area} * 10' } ),
			norm( { id: 'bulk_note', type: 'heading', label: 'Bulk discount applies', conditions: [
				{ field: 'total', operator: 'gte', value: '1000' },
			] } ),
		];
		const below = run( fields, prepare( fields ), { area: '50' } );  // total 500 < 1000
		expect( below.totalScaled ).toBe( 5000000 );
		expect( below.active.bulk_note ).toBe( false );

		const above = run( fields, prepare( fields ), { area: '120' } ); // total 1200 ≥ 1000
		expect( above.totalScaled ).toBe( 12000000 );
		expect( above.active.bulk_note ).toBe( true );
	} );
} );
