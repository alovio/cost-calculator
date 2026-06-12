/**
 * Live builder-side validation for formula expressions (spec §7).
 * Pure module — unit-tested with plain Jest.
 */
import { compile, references, orderFormulas, FormulaError } from '../shared/formula';

const REFERENCEABLE = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'formula' ];

/**
 * @param {string} expression Draft expression.
 * @param {string} fieldId    The formula field being edited.
 * @param {Array}  fields     All fields in the calculator.
 * @return {{ok: boolean, error: null|{code: string, message: string, pos: number}}} Result.
 */
export function validateExpression( expression, fieldId, fields ) {
	let ast;
	try {
		ast = compile( expression );
	} catch ( e ) {
		if ( e instanceof FormulaError ) {
			return { ok: false, error: { code: e.code, message: e.message, pos: e.pos } };
		}
		throw e;
	}

	const referenceable = new Set(
		fields.filter( ( f ) => REFERENCEABLE.includes( f.type ) && f.id !== fieldId ).map( ( f ) => f.id )
	);
	for ( const ref of references( ast ) ) {
		if ( ! referenceable.has( ref ) ) {
			return { ok: false, error: { code: 'unknown_field', message: `Unknown or non-referenceable field: {${ ref }}`, pos: -1 } };
		}
	}

	// Rebuild the formula graph with the draft expression substituted; report cycles.
	const idToRefs = {};
	fields
		.filter( ( f ) => f.type === 'formula' )
		.forEach( ( f ) => {
			if ( f.id === fieldId ) {
				idToRefs[ f.id ] = references( ast );
				return;
			}
			try {
				idToRefs[ f.id ] = references( compile( f.expression || '0' ) );
			} catch ( e ) {
				idToRefs[ f.id ] = []; // Broken sibling formulas can't form cycles.
			}
		} );
	if ( ! ( fieldId in idToRefs ) ) {
		idToRefs[ fieldId ] = references( ast ); // Field not yet typed as formula in the list (defensive).
	}
	const { cycles } = orderFormulas( idToRefs );
	if ( cycles.includes( fieldId ) ) {
		return { ok: false, error: { code: 'cycle', message: 'This formula creates a circular reference.', pos: -1 } };
	}

	return { ok: true, error: null };
}
