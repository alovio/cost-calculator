/**
 * Client mirror of includes/Logic/Evaluation.php (spec §6 value maps + §8 order).
 * Pure functions, no DOM — unit-tested against the same expected values as the
 * PHP EvaluationTest. The server stays authoritative on submission.
 */
import { compile, evaluate, references, orderFormulas, toScaled, FormulaError } from '../shared/formula';
import { activeMap } from './conditional-logic';

const REFERENCEABLE_INPUTS = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity' ];
const CONTROLLERS = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text' ];
const NUMERIC = [ 'number', 'slider', 'quantity' ];

/** Compile every formula once at init. */
export function prepare( fields ) {
	const asts = {};
	const errors = {};
	const idToRefs = {};
	fields
		.filter( ( f ) => f.type === 'formula' )
		.forEach( ( f ) => {
			try {
				const ast = compile( f.expression || '' );
				asts[ f.id ] = ast;
				idToRefs[ f.id ] = references( ast );
			} catch ( e ) {
				if ( ! ( e instanceof FormulaError ) ) {
					throw e;
				}
				asts[ f.id ] = null;
				errors[ f.id ] = e.code;
				idToRefs[ f.id ] = [];
			}
		} );
	const graph = orderFormulas( idToRefs );
	graph.cycles.forEach( ( id ) => {
		errors[ id ] = 'cycle';
		asts[ id ] = null;
	} );
	return { asts, errors, order: graph.order };
}

function clampedNumber( field, v ) {
	let n = v !== null && v !== undefined && v !== '' && Number.isFinite( Number( v ) ) ? Number( v ) : Number( field.default ?? 0 );
	if ( field.min !== null && field.min !== undefined ) {
		n = Math.max( Number( field.min ), n );
	}
	if ( field.max !== null && field.max !== undefined ) {
		n = Math.min( Number( field.max ), n );
	}
	return n;
}

function validSlug( field, v ) {
	return ( field.options || [] ).some( ( o ) => o.value === v ) ? v : '';
}

function validSlugs( field, vs ) {
	const valid = new Set( ( field.options || [] ).map( ( o ) => o.value ) );
	return ( Array.isArray( vs ) ? vs : [] ).map( String ).filter( ( v ) => valid.has( v ) );
}

function toggleOn( field, v ) {
	if ( v === null || v === undefined ) {
		return !! field.default;
	}
	if ( v === false ) {
		return false;
	}
	const s = typeof v === 'object' ? '' : String( v );
	return s !== '' && s !== '0';
}

/** Spec §6 condition value map (string map for the conditional engine). */
export function conditionValues( fields, rawValues ) {
	const out = {};
	fields
		.filter( ( f ) => CONTROLLERS.includes( f.type ) )
		.forEach( ( f ) => {
			const v = rawValues[ f.id ];
			switch ( f.type ) {
				case 'number':
				case 'slider':
				case 'quantity':
					out[ f.id ] = String( clampedNumber( f, v ) );
					break;
				case 'select':
				case 'radio':
					out[ f.id ] = validSlug( f, typeof v === 'string' ? v : '' );
					break;
				case 'checkbox_group':
					out[ f.id ] = validSlugs( f, v ).join( ',' );
					break;
				case 'toggle':
					out[ f.id ] = toggleOn( f, v ) ? '1' : '';
					break;
				case 'text':
					out[ f.id ] = typeof v === 'string' ? v.trim() : '';
					break;
			}
		} );
	return out;
}

function inputAmount( field, v ) {
	switch ( field.type ) {
		case 'number':
		case 'slider':
		case 'quantity':
			return toScaled( clampedNumber( field, v ) );
		case 'select':
		case 'radio': {
			const slug = validSlug( field, typeof v === 'string' ? v : '' );
			const opt = ( field.options || [] ).find( ( o ) => o.value === slug );
			return opt ? toScaled( opt.price || 0 ) : 0;
		}
		case 'checkbox_group': {
			const selected = new Set( validSlugs( field, v ) );
			return ( field.options || [] )
				.filter( ( o ) => selected.has( o.value ) )
				.reduce( ( sum, o ) => sum + toScaled( o.price || 0 ), 0 );
		}
		case 'toggle':
			return toggleOn( field, v ) ? toScaled( field.price || 0 ) : 0;
	}
	return 0;
}

function isPriced( field ) {
	return [ 'select', 'radio', 'checkbox_group', 'toggle' ].includes( field.type );
}

/** Full §8 pass: condition values → active map → input amounts → ordered formulas → summary. */
export function run( fields, prepared, rawValues ) {
	const condValues = conditionValues( fields, rawValues );
	const active = activeMap( fields, condValues );

	const values = {};
	fields
		.filter( ( f ) => REFERENCEABLE_INPUTS.includes( f.type ) )
		.forEach( ( f ) => {
			values[ f.id ] = active[ f.id ] !== false ? inputAmount( f, rawValues[ f.id ] ) : 0;
		} );

	prepared.order.forEach( ( id ) => {
		if ( prepared.errors[ id ] || ! prepared.asts[ id ] ) {
			values[ id ] = 0;
			return;
		}
		if ( active[ id ] === false ) {
			values[ id ] = 0;
			return;
		}
		try {
			values[ id ] = evaluate( prepared.asts[ id ], values );
		} catch ( e ) {
			if ( ! ( e instanceof FormulaError ) ) {
				throw e;
			}
			values[ id ] = 0;
		}
	} );

	const lineItems = [];
	let totalScaled = null;
	fields.forEach( ( f ) => {
		if ( f.type === 'formula' && active[ f.id ] !== false ) {
			totalScaled = values[ f.id ];
		}
		if ( ! f.showInSummary || active[ f.id ] === false || ! ( f.id in values ) ) {
			return;
		}
		lineItems.push( {
			id: f.id,
			label: f.label,
			amount: values[ f.id ],
			isCurrency: f.type === 'formula' || isPriced( f ),
		} );
	} );

	return { active, values, lineItems, totalScaled };
}
