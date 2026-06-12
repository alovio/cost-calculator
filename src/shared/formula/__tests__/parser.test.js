import { tokenize } from '../lexer';
import { parse } from '../parser';
import { FUNCTION_SPECS } from '../functions';

const p = ( expr ) => parse( tokenize( expr ), FUNCTION_SPECS );

describe( 'lexer + parser', () => {
	it( 'tokenizes fields, numbers, comparisons with positions', () => {
		const tokens = tokenize( 'if({qty} >= 10, 5, 0)' );
		expect( tokens.map( ( t ) => t.type ) ).toEqual(
			[ 'ident', 'lparen', 'field', 'cmp', 'num', 'comma', 'num', 'comma', 'num', 'rparen' ]
		);
		expect( tokens[ 2 ].value ).toBe( 'qty' );
		expect( tokens[ 0 ].pos ).toBe( 0 );
	} );

	it( 'parses with correct precedence and scales numbers', () => {
		const ast = p( '1 + 2 * 3' );
		expect( ast.op ).toBe( '+' );
		expect( ast.right.op ).toBe( '*' );
		expect( p( '4.1' ) ).toEqual( { type: 'num', value: 41000 } );
	} );

	it( 'unary minus binds tighter than mul', () => {
		const ast = p( '-2 * 3' );
		expect( ast.op ).toBe( '*' );
		expect( ast.left.type ).toBe( 'neg' );
	} );

	it( 'throws coded errors', () => {
		expect( () => p( 'sqrt(4)' ) ).toThrow( expect.objectContaining( { code: 'unknown_function' } ) );
		expect( () => p( 'if(1, 2)' ) ).toThrow( expect.objectContaining( { code: 'arity' } ) );
		expect( () => p( '1 + $x' ) ).toThrow( expect.objectContaining( { code: 'syntax' } ) );
		expect( () => p( '1 2' ) ).toThrow( expect.objectContaining( { code: 'syntax' } ) );
		expect( () => p( '' ) ).toThrow( expect.objectContaining( { code: 'syntax' } ) );
	} );
} );
