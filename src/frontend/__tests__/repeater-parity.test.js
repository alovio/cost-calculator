/**
 * JS side of tests/fixtures/repeater-cases.json — same file the PHP
 * RepeaterCasesTest consumes. Full-run parity via run() + raw row-level
 * parity via repeaterResult() (pre-visibility, like the PHP pre-pass).
 */
import cases from '../../../tests/fixtures/repeater-cases.json';
import { prepare, run, repeaterResult } from '../compute';
import { fromScaled } from '../../shared/formula';

describe( 'repeater PHP/JS parity fixtures', () => {
	cases.cases.forEach( ( c ) => {
		it( c.name, () => {
			const prepared = prepare( c.fields );
			const r = run( c.fields, prepared, c.values );

			Object.entries( c.expected.values ).forEach( ( [ id, want ] ) => {
				expect( fromScaled( r.values[ id ] ) ).toBe( want );
			} );
			if ( 'total' in c.expected ) {
				expect( fromScaled( r.totalScaled || 0 ) ).toBe( c.expected.total );
			}
			Object.entries( c.expected.active || {} ).forEach( ( [ id, want ] ) => {
				expect( r.active[ id ] ).toBe( want );
			} );
			if ( c.expected.lineItems ) {
				expect(
					r.lineItems.map( ( i ) => {
						const out = { id: i.id, label: i.label, amount: fromScaled( i.amount ), isCurrency: i.isCurrency };
						if ( i.repeaterId ) {
							out.repeaterId = i.repeaterId;
						}
						return out;
					} )
				).toEqual( c.expected.lineItems );
			}

			const field = c.fields.find( ( f ) => f.id === c.repeater.id );
			const rep = repeaterResult( field, prepared.repeaters[ field.id ], c.values[ field.id ] );
			expect( fromScaled( rep.sum ) ).toBe( c.repeater.sum );
			expect( rep.rows.map( ( row ) => ( { label: row.label, total: fromScaled( row.total ) } ) ) ).toEqual( c.repeater.rows );
			if ( c.repeater.error ) {
				expect( rep.error ).toBe( c.repeater.error );
				expect( prepared.errors[ field.id ] ).toBe( c.repeater.error );
			}
		} );
	} );
} );
