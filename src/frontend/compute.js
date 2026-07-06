/**
 * Client mirror of includes/Logic/Evaluation.php (spec §6 value maps + §8 order).
 * Pure functions, no DOM — unit-tested against the same expected values as the
 * PHP EvaluationTest. The server stays authoritative on submission.
 */
import { compile, evaluate, references, orderFormulas, toScaled, fromScaled, FormulaError } from '../shared/formula';
import { add } from '../shared/formula/decimal';
import { activeMap, fieldRequired } from './conditional-logic';

/** Fixed-point cap: bounds the formula↔condition feedback loop (cycle safety). */
const MAX_PASSES = 8;

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
	const repeaters = {};
	fields
		.filter( ( f ) => f.type === 'repeater' )
		.forEach( ( f ) => {
			if ( ! f.rowExpression ) {
				repeaters[ f.id ] = { ast: null, error: null };
				return;
			}
			try {
				repeaters[ f.id ] = { ast: compile( f.rowExpression ), error: null };
			} catch ( e ) {
				if ( ! ( e instanceof FormulaError ) ) {
					throw e;
				}
				repeaters[ f.id ] = { ast: null, error: e.code };
				errors[ f.id ] = e.code; // uniform badge source: the chunk-3 overlay reads prepared.errors
			}
		} );
	return { asts, errors, order: graph.order, repeaters };
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

/** "{n}" substitution; empty template falls back to "<label> <n>" (PHP row_label mirror). */
function rowLabel( field, n ) {
	const tpl = field.rowLabel || '';
	return tpl !== '' ? tpl.replace( '{n}', String( n ) ) : `${ field.label || '' } ${ n }`.trim();
}

/**
 * Mirror of Evaluation::repeater_result — condition-independent row math.
 * Exported for the shared parity fixtures.
 */
export function repeaterResult( field, prepared, raw ) {
	if ( prepared.error ) {
		return { sum: 0, rows: [], error: prepared.error };
	}
	const children = field.fields || [];
	const maxRows = Math.min( Number( field.maxRows ?? 50 ), 50 );
	const rowsRaw = Array.isArray( raw )
		? raw.slice( 0, maxRows )
		: Array.from( { length: Number( field.minRows ?? 1 ) }, () => ( {} ) );

	let sum = 0;
	let error = null;
	const rows = rowsRaw.map( ( rowRaw, i ) => {
		const source = rowRaw && typeof rowRaw === 'object' && ! Array.isArray( rowRaw ) ? rowRaw : {};
		const rowMap = {};
		let priceSum = 0;
		children.forEach( ( child ) => {
			rowMap[ child.id ] = inputAmount( child, source[ child.id ] );
			if ( isPriced( child ) ) {
				// Price mode counts ONLY priced children — a number/slider/quantity
				// raw value is NOT currency (it is only a {ref} for rowExpression).
				priceSum = add( priceSum, rowMap[ child.id ] );
			}
		} );
		let total;
		if ( prepared.ast ) {
			try {
				total = evaluate( prepared.ast, rowMap );
			} catch ( e ) {
				if ( ! ( e instanceof FormulaError ) ) {
					throw e;
				}
				total = 0;
				error = e.code;
			}
		} else {
			total = priceSum;
		}
		sum = add( sum, total );
		return { label: rowLabel( field, i + 1 ), total };
	} );
	return { sum, rows, error };
}

/** One pass: input value map (active-gated) then formulas in dependency order. */
function computeValues( fields, prepared, active, rawValues, reps ) {
	const values = {};
	fields.forEach( ( f ) => {
		if ( f.type === 'repeater' ) {
			values[ f.id ] = active[ f.id ] !== false ? reps[ f.id ].sum : 0;
			return;
		}
		if ( ! REFERENCEABLE_INPUTS.includes( f.type ) ) {
			return;
		}
		values[ f.id ] = active[ f.id ] !== false ? inputAmount( f, rawValues[ f.id ] ) : 0;
	} );
	prepared.order.forEach( ( id ) => {
		if ( prepared.errors[ id ] || ! prepared.asts[ id ] || active[ id ] === false ) {
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
	return values;
}

/** Active maps are equal when every field's active state matches (built in field order). */
function activeEqual( a, b ) {
	const keys = Object.keys( a );
	return keys.length === Object.keys( b ).length && keys.every( ( k ) => a[ k ] === b[ k ] );
}

/**
 * Full §8 pass with the formula↔condition fixed-point — mirrors Evaluation::run.
 * A formula result can drive a condition, which changes the active map, which changes
 * the result; iterate until the active map settles (capped). With no formula-driven
 * condition this converges on the first pass (identical to the old single pass).
 */
export function run( fields, prepared, rawValues ) {
	const baseCond = conditionValues( fields, rawValues );
	const reps = {};
	fields
		.filter( ( f ) => f.type === 'repeater' )
		.forEach( ( f ) => {
			reps[ f.id ] = repeaterResult( f, prepared.repeaters[ f.id ], rawValues[ f.id ] );
		} );
	let condValues = baseCond;
	let active = activeMap( fields, condValues );
	let values = {};

	for ( let pass = 0; pass < MAX_PASSES; pass++ ) {
		values = computeValues( fields, prepared, active, rawValues, reps );
		const nextCond = { ...baseCond };
		fields.forEach( ( f ) => {
			if ( f.type === 'formula' ) {
				nextCond[ f.id ] = active[ f.id ] !== false ? fromScaled( values[ f.id ] || 0 ) : '';
			}
			if ( f.type === 'repeater' ) {
				nextCond[ f.id ] = active[ f.id ] !== false ? fromScaled( reps[ f.id ].sum ) : '';
			}
		} );
		const nextActive = activeMap( fields, nextCond );
		condValues = nextCond;
		if ( activeEqual( nextActive, active ) ) {
			break;
		}
		active = nextActive;
	}
	values = computeValues( fields, prepared, active, rawValues, reps );

	const lineItems = [];
	let totalScaled = null;
	fields.forEach( ( f ) => {
		if ( f.type === 'formula' && active[ f.id ] !== false ) {
			totalScaled = values[ f.id ];
		}
		if ( ! f.showInSummary || active[ f.id ] === false ) {
			return;
		}
		if ( f.type === 'repeater' ) {
			reps[ f.id ].rows.forEach( ( row, i ) => {
				lineItems.push( {
					id: `${ f.id }__${ i + 1 }`,
					label: row.label,
					amount: row.total,
					isCurrency: true,
					repeaterId: f.id,
				} );
			} );
			return;
		}
		if ( ! ( f.id in values ) ) {
			return;
		}
		lineItems.push( {
			id: f.id,
			label: f.label,
			amount: values[ f.id ],
			isCurrency: f.type === 'formula' || isPriced( f ),
		} );
	} );

	// THEN=require: which active fields are mandatory now (against the settled condition map).
	const required = {};
	fields.forEach( ( f ) => {
		required[ f.id ] = fieldRequired( f, condValues );
	} );

	return { active, values, lineItems, totalScaled, required };
}
