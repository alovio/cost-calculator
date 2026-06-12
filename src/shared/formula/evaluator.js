import { FormulaError, SCALE, add, sub, mul, div, roundToDecimals, ceilToInt, floorToInt } from './decimal';

// Line-for-line port of includes/Formula/Evaluator.php — same semantics, same error codes.
// values: map field-id => scaled int. Callers pre-resolve inactive fields to 0 (spec §6/§8).
export function evaluate( ast, values, functionSpecs ) {
	switch ( ast.type ) {
		case 'num':
			return ast.value;

		case 'field':
			if ( ! Object.prototype.hasOwnProperty.call( values, ast.id ) ) {
				throw new FormulaError( 'unknown_field', 'Unknown field: ' + ast.id );
			}
			return values[ ast.id ];

		case 'neg':
			return -evaluate( ast.operand, values, functionSpecs );

		case 'bin': {
			const l = evaluate( ast.left, values, functionSpecs );
			const r = evaluate( ast.right, values, functionSpecs );
			switch ( ast.op ) {
				case '+':
					return add( l, r );
				case '-':
					return sub( l, r );
				case '*':
					return mul( l, r );
				case '/':
					return div( l, r );
			}
			break;
		}

		case 'cmp': {
			const l = evaluate( ast.left, values, functionSpecs );
			const r = evaluate( ast.right, values, functionSpecs );
			let bool = false;
			switch ( ast.op ) {
				case '>':
					bool = l > r;
					break;
				case '<':
					bool = l < r;
					break;
				case '>=':
					bool = l >= r;
					break;
				case '<=':
					bool = l <= r;
					break;
				case '==':
					bool = l === r;
					break;
				case '!=':
					bool = l !== r;
					break;
			}
			return bool ? SCALE : 0;
		}

		case 'call':
			return call( ast.name, ast.args, values, functionSpecs );
	}

	throw new FormulaError( 'syntax', 'Malformed AST node' );
}

function call( name, args, values, functionSpecs ) {
	if ( ! Object.prototype.hasOwnProperty.call( functionSpecs, name ) ) {
		throw new FormulaError( 'unknown_function', 'Unknown function: ' + name );
	}

	if ( name === 'if' ) { // Lazy: only the taken branch is evaluated.
		const cond = evaluate( args[ 0 ], values, functionSpecs );
		return evaluate( cond !== 0 ? args[ 1 ] : args[ 2 ], values, functionSpecs );
	}

	const vals = args.map( ( a ) => evaluate( a, values, functionSpecs ) );

	switch ( name ) {
		case 'min':
			return Math.min( ...vals );
		case 'max':
			return Math.max( ...vals );
		case 'round': {
			const n = vals.length > 1 ? Math.trunc( vals[ 1 ] / SCALE ) : 0;
			return roundToDecimals( vals[ 0 ], n );
		}
		case 'ceil':
			return ceilToInt( vals[ 0 ] );
		case 'floor':
			return floorToInt( vals[ 0 ] );
		case 'abs':
			return Math.abs( vals[ 0 ] );
	}

	throw new FormulaError( 'unknown_function', 'No evaluator for function: ' + name );
}
