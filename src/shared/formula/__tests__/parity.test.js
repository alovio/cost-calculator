import cases from '../../../../tests/fixtures/formula-cases.json';
import { compile, evaluate } from '../index';
import { toScaled, fromScaled } from '../decimal';

describe( 'PHP/JS parity fixtures', () => {
	cases.cases.forEach( ( c ) => {
		it( c.name, () => {
			const values = {};
			Object.entries( c.values ).forEach( ( [ id, v ] ) => {
				values[ id ] = toScaled( v );
			} );

			if ( typeof c.expected === 'object' ) {
				expect( () => evaluate( compile( c.expression ), values ) ).toThrow(
					expect.objectContaining( { code: c.expected.error } )
				);
				return;
			}
			expect( fromScaled( evaluate( compile( c.expression ), values ) ) ).toBe( c.expected );
		} );
	} );
} );
