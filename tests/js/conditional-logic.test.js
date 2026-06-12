/**
 * JS/PHP parity: `evaluate` must match every case in the shared fixture
 * (the same file Alovio\Calculator\Logic\ConditionalLogic is tested against).
 */
import { evaluate, activeMap, fieldActive } from '../../src/frontend/conditional-logic';
import cases from '../fixtures/conditional-cases.json';

describe( 'evaluate (parity with PHP ConditionalLogic)', () => {
	cases.forEach( ( c ) => {
		it( c.name, () => {
			expect( evaluate( c.condition, c.values ) ).toBe( c.expectedActive );
		} );
	} );
} );

describe( 'activeMap (transitive, parity with PHP active_map)', () => {
	const fields = [
		{ id: 'gate', type: 'checkbox', condition: null },
		{ id: 'a', type: 'checkbox', condition: { field: 'gate', operator: 'is', value: 'yes', action: 'show' } },
		{ id: 'b', type: 'text', condition: { field: 'a', operator: 'is', value: 'yes', action: 'show' } },
	];

	it( 'hides a dependent whose controller is transitively hidden', () => {
		const map = activeMap( fields, { gate: 'no', a: 'yes' } );
		expect( map.a ).toBe( false );
		expect( map.b ).toBe( false );
	} );

	it( 'activates the chain when controllers pass', () => {
		const map = activeMap( fields, { gate: 'yes', a: 'yes' } );
		expect( map.a ).toBe( true );
		expect( map.b ).toBe( true );
	} );
} );

describe( 'operators + multi-conditions (parity with PHP)', () => {
	it( 'contains / gt / lt operators', () => {
		expect( evaluate( { field: 't', operator: 'contains', value: 'abc', action: 'show' }, { t: 'x abc' } ) ).toBe( true );
		expect( evaluate( { field: 't', operator: 'contains', value: 'abc', action: 'show' }, { t: 'no' } ) ).toBe( false );
		expect( evaluate( { field: 'n', operator: 'gt', value: '5', action: 'show' }, { n: '7' } ) ).toBe( true );
		expect( evaluate( { field: 'n', operator: 'lt', value: '5', action: 'show' }, { n: '7' } ) ).toBe( false );
	} );

	it( 'fieldActive combines multiple rules with all / any', () => {
		const all = {
			conditions: [
				{ field: 'a', operator: 'is', value: 'x' },
				{ field: 'b', operator: 'is', value: 'y' },
			],
			conditionMatch: 'all',
			conditionAction: 'show',
		};
		expect( fieldActive( all, { a: 'x', b: 'y' } ) ).toBe( true );
		expect( fieldActive( all, { a: 'x', b: 'z' } ) ).toBe( false );

		const any = { ...all, conditionMatch: 'any' };
		expect( fieldActive( any, { a: 'x', b: 'z' } ) ).toBe( true );
		expect( fieldActive( any, { a: 'p', b: 'q' } ) ).toBe( false );
	} );

	it( 'toggle convention: "1"/"" values evaluate like any other field key (spec §6)', () => {
		const field = {
			conditions: [ { field: 'express', operator: 'is', value: '1' } ],
			conditionMatch: 'all',
			conditionAction: 'show',
		};
		expect( fieldActive( field, { express: '1' } ) ).toBe( true );
		expect( fieldActive( field, { express: '' } ) ).toBe( false );
	} );
} );
