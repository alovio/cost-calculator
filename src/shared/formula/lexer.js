import { FormulaError } from './decimal';

// Line-for-line port of includes/Formula/Lexer.php (sticky-y regexes replace PHP's /A anchor).
const FIELD_RE = /\{([a-z0-9_]+)\}/y;
const NUM_RE = /[0-9]+(\.[0-9]+)?/y;
const IDENT_RE = /[a-z_][a-z0-9_]*/iy;

export function tokenize( expr ) {
	const tokens = [];
	const len = expr.length;
	let i = 0;

	while ( i < len ) {
		const c = expr[ i ];

		if ( c === ' ' || c === '\t' || c === '\n' || c === '\r' ) {
			i++;
			continue;
		}

		if ( c === '{' ) {
			FIELD_RE.lastIndex = i;
			const m = FIELD_RE.exec( expr );
			if ( ! m ) {
				throw new FormulaError( 'syntax', 'Malformed field reference', i );
			}
			tokens.push( { type: 'field', value: m[ 1 ], pos: i } );
			i += m[ 0 ].length;
			continue;
		}

		if ( /[0-9]/.test( c ) ) {
			NUM_RE.lastIndex = i;
			const m = NUM_RE.exec( expr );
			const end = i + m[ 0 ].length;
			if ( end < len && ( expr[ end ] === '.' || /[0-9a-z_]/i.test( expr[ end ] ) ) ) {
				throw new FormulaError( 'syntax', 'Malformed number', i );
			}
			tokens.push( { type: 'num', value: m[ 0 ], pos: i } );
			i = end;
			continue;
		}

		if ( /[a-z_]/i.test( c ) ) {
			IDENT_RE.lastIndex = i;
			const m = IDENT_RE.exec( expr );
			tokens.push( { type: 'ident', value: m[ 0 ].toLowerCase(), pos: i } );
			i += m[ 0 ].length;
			continue;
		}

		const two = expr.substr( i, 2 );
		if ( [ '>=', '<=', '==', '!=' ].includes( two ) ) {
			tokens.push( { type: 'cmp', value: two, pos: i } );
			i += 2;
			continue;
		}
		if ( c === '>' || c === '<' ) {
			tokens.push( { type: 'cmp', value: c, pos: i } );
			i++;
			continue;
		}
		if ( c === '+' || c === '-' || c === '*' || c === '/' ) {
			tokens.push( { type: 'op', value: c, pos: i } );
			i++;
			continue;
		}
		if ( c === '(' ) {
			tokens.push( { type: 'lparen', value: c, pos: i } );
			i++;
			continue;
		}
		if ( c === ')' ) {
			tokens.push( { type: 'rparen', value: c, pos: i } );
			i++;
			continue;
		}
		if ( c === ',' ) {
			tokens.push( { type: 'comma', value: c, pos: i } );
			i++;
			continue;
		}

		throw new FormulaError( 'syntax', 'Unexpected character: ' + c, i );
	}

	return tokens;
}
