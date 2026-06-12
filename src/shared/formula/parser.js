import { FormulaError, toScaled } from './decimal';

// Line-for-line port of includes/Formula/Parser.php — same binding powers, node shapes, error codes.
const BP_CMP = 10;
const BP_ADD = 20;
const BP_MUL = 30;

export function parse( tokens, functionSpecs ) {
	let i = 0;

	const peek = () => ( i < tokens.length ? tokens[ i ] : null );
	const next = () => {
		const t = peek();
		if ( t !== null ) {
			i++;
		}
		return t;
	};
	const expect = ( type, contextPos ) => {
		const t = next();
		if ( t === null || t.type !== type ) {
			throw new FormulaError( 'syntax', 'Expected ' + type, t === null ? contextPos : t.pos );
		}
	};

	function expression( minBp ) {
		let left = primary();

		for ( ;; ) {
			const t = peek();
			if ( t === null ) {
				break;
			}
			let bp, node;
			if ( t.type === 'op' && ( t.value === '+' || t.value === '-' ) ) {
				bp = BP_ADD;
				node = 'bin';
			} else if ( t.type === 'op' ) {
				bp = BP_MUL;
				node = 'bin';
			} else if ( t.type === 'cmp' ) {
				bp = BP_CMP;
				node = 'cmp';
			} else {
				break;
			}
			if ( bp < minBp ) {
				break;
			}
			next();
			const right = expression( bp + 1 ); // Left-associative.
			left = { type: node, op: t.value, left, right };
		}

		return left;
	}

	function primary() {
		const t = next();
		if ( t === null ) {
			throw new FormulaError( 'syntax', 'Unexpected end of expression' );
		}

		switch ( t.type ) {
			case 'num':
				return { type: 'num', value: toScaled( t.value ) };

			case 'field':
				return { type: 'field', id: t.value };

			case 'op':
				if ( t.value === '-' ) {
					return { type: 'neg', operand: expression( BP_MUL + 1 ) };
				}
				break;

			case 'lparen': {
				const inner = expression( 0 );
				expect( 'rparen', t.pos );
				return inner;
			}

			case 'ident': {
				if ( ! Object.prototype.hasOwnProperty.call( functionSpecs, t.value ) ) {
					throw new FormulaError( 'unknown_function', 'Unknown function: ' + t.value, t.pos );
				}
				expect( 'lparen', t.pos );
				const args = [ expression( 0 ) ];
				while ( peek() !== null && peek().type === 'comma' ) {
					next();
					args.push( expression( 0 ) );
				}
				expect( 'rparen', t.pos );
				const [ min, max ] = functionSpecs[ t.value ];
				if ( args.length < min || args.length > max ) {
					throw new FormulaError( 'arity', `${ t.value }() expects ${ min }-${ max } arguments`, t.pos );
				}
				return { type: 'call', name: t.value, args };
			}
		}

		throw new FormulaError( 'syntax', 'Unexpected token', t.pos );
	}

	if ( tokens.length === 0 ) {
		throw new FormulaError( 'syntax', 'Empty expression' );
	}
	const ast = expression( 0 );
	if ( peek() !== null ) {
		throw new FormulaError( 'syntax', 'Unexpected token', peek().pos );
	}
	return ast;
}
