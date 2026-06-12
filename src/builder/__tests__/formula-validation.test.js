import { validateExpression } from '../formula-validation';

const fields = [
	{ id: 'area', type: 'slider' },
	{ id: 'service', type: 'radio' },
	{ id: 'note', type: 'text' }, // not referenceable
	{ id: 'tax', type: 'formula', expression: '{subtotal} * 0.18' },
	{ id: 'subtotal', type: 'formula', expression: '{area} * {service}' },
];

describe( 'validateExpression', () => {
	it( 'accepts valid expressions', () => {
		expect( validateExpression( '{area} * 2', 'subtotal', fields ) ).toEqual( { ok: true, error: null } );
	} );
	it( 'flags syntax errors with the engine error code', () => {
		expect( validateExpression( '{area} +', 'subtotal', fields ).error.code ).toBe( 'syntax' );
	} );
	it( 'flags references to unknown or non-referenceable fields', () => {
		expect( validateExpression( '{ghost}', 'subtotal', fields ).error.code ).toBe( 'unknown_field' );
		expect( validateExpression( '{note}', 'subtotal', fields ).error.code ).toBe( 'unknown_field' );
	} );
	it( 'flags self-references as unknown (a formula cannot reference itself)', () => {
		expect( validateExpression( '{subtotal} + 1', 'subtotal', fields ).error.code ).toBe( 'unknown_field' );
	} );
	it( 'flags cycles introduced by the draft expression', () => {
		expect( validateExpression( '{tax} + 1', 'subtotal', fields ).error.code ).toBe( 'cycle' );
	} );
} );
