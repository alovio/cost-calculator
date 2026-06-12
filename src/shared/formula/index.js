import { tokenize } from './lexer';
import { parse } from './parser';
import { evaluate as rawEvaluate } from './evaluator';
import { FUNCTION_SPECS } from './functions';

export { FormulaError, SCALE, toScaled, fromScaled } from './decimal';
export { orderFormulas } from './graph';

export function compile( expr ) {
	return parse( tokenize( expr ), FUNCTION_SPECS );
}

export function evaluate( ast, scaledValues ) {
	return rawEvaluate( ast, scaledValues, FUNCTION_SPECS );
}

export function references( ast ) {
	const refs = [];
	const walk = ( node ) => {
		if ( node.type === 'field' ) {
			refs.push( node.id );
		} else if ( node.type === 'neg' ) {
			walk( node.operand );
		} else if ( node.type === 'bin' || node.type === 'cmp' ) {
			walk( node.left );
			walk( node.right );
		} else if ( node.type === 'call' ) {
			node.args.forEach( walk );
		}
	};
	walk( ast );
	return [ ...new Set( refs ) ];
}
