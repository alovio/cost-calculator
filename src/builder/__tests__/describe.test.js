import { describeCondition, describeRule, conditionAction } from '../describe';

const fields = [
	{ id: 'area', type: 'slider', label: 'Area (m²)' },
	{
		id: 'service', type: 'radio', label: 'Service',
		options: [ { value: 'opt_std', label: 'Standard' }, { value: 'opt_deep', label: 'Deep clean' } ],
	},
	{ id: 'express', type: 'toggle', label: 'Express' },
	{ id: 'total', type: 'formula', label: 'Estimated price' },
];
const f = ( conditions, extra = {} ) => ( { id: 'x', type: 'heading', conditions, ...extra } );

describe( 'describeRule', () => {
	it( 'renders numeric operators as symbols', () => {
		expect( describeRule( { field: 'area', operator: 'gte', value: '100' }, fields ) ).toBe( 'Area (m²) ≥ 100' );
		expect( describeRule( { field: 'area', operator: 'lt', value: '5' }, fields ) ).toBe( 'Area (m²) < 5' );
	} );
	it( 'resolves option slugs to labels', () => {
		expect( describeRule( { field: 'service', operator: 'is', value: 'opt_deep' }, fields ) ).toBe( 'Service is Deep clean' );
	} );
	it( 'renders toggle values as On/Off', () => {
		expect( describeRule( { field: 'express', operator: 'is', value: '1' }, fields ) ).toBe( 'Express is On' );
		expect( describeRule( { field: 'express', operator: 'is', value: '' }, fields ) ).toBe( 'Express is Off' );
	} );
	it( 'omits the value for presence operators', () => {
		expect( describeRule( { field: 'area', operator: 'is_empty', value: '' }, fields ) ).toBe( 'Area (m²) is empty' );
	} );
	it( 'supports formula (total) controllers and falls back to the raw id when unknown', () => {
		expect( describeRule( { field: 'total', operator: 'gt', value: '500' }, fields ) ).toBe( 'Estimated price > 500' );
		expect( describeRule( { field: 'ghost', operator: 'is', value: '3' }, fields ) ).toBe( 'ghost is 3' );
	} );
} );

describe( 'describeCondition', () => {
	it( 'is empty without rules', () => {
		expect( describeCondition( f( [] ), fields ) ).toBe( '' );
		expect( describeCondition( { id: 'x', type: 'heading' }, fields ) ).toBe( '' );
	} );
	it( 'shows the first rule plus a connector count', () => {
		const field = f(
			[ { field: 'area', operator: 'gt', value: '100' }, { field: 'express', operator: 'is', value: '1' } ],
			{ conditionMatch: 'any' }
		);
		expect( describeCondition( field, fields ) ).toBe( 'Area (m²) > 100 OR +1' );
		field.conditionMatch = 'all';
		expect( describeCondition( field, fields ) ).toBe( 'Area (m²) > 100 AND +1' );
	} );
} );

describe( 'conditionAction', () => {
	it( 'maps the action to its chip word (SHOW default)', () => {
		expect( conditionAction( {} ) ).toBe( 'SHOW' );
		expect( conditionAction( { conditionAction: 'hide' } ) ).toBe( 'HIDE' );
		expect( conditionAction( { conditionAction: 'require' } ) ).toBe( 'REQUIRE' );
	} );
} );
