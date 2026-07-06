/**
 * Human summaries of a field's conditional rules for the canvas IF pills
 * (spec §2.3). Pure module — Jest-tested. Ported from Checkout Fields'
 * describe.js, adapted to our operator set and sibling-only controllers
 * (incl. formula/total; no @context tokens).
 */
import { __ } from '@wordpress/i18n';

const NO_VALUE_OPS = [ 'is_empty', 'is_not_empty' ];

function ops() {
	return {
		is: __( 'is', 'alovio-calculator' ),
		is_not: __( 'is not', 'alovio-calculator' ),
		contains: __( 'contains', 'alovio-calculator' ),
		gt: '>',
		gte: '≥',
		lt: '<',
		lte: '≤',
		is_empty: __( 'is empty', 'alovio-calculator' ),
		is_not_empty: __( 'is not empty', 'alovio-calculator' ),
	};
}

function controllerFor( rule, fields ) {
	return ( fields || [] ).find( ( x ) => x.id === rule.field ) || null;
}

function sourceLabel( rule, fields ) {
	const c = controllerFor( rule, fields );
	return c ? c.label || c.type : rule.field || '';
}

function valueLabel( rule, fields ) {
	if ( NO_VALUE_OPS.indexOf( rule.operator ) !== -1 ) {
		return '';
	}
	const c = controllerFor( rule, fields );
	if ( c && 'toggle' === c.type ) {
		return '1' === rule.value ? __( 'On', 'alovio-calculator' ) : __( 'Off', 'alovio-calculator' );
	}
	if ( c && Array.isArray( c.options ) ) {
		const opt = c.options.find( ( o ) => o.value === rule.value );
		if ( opt ) {
			return opt.label || opt.value;
		}
	}
	return String( rule.value ?? '' );
}

/** One rule as a sentence fragment, e.g. "Area (m²) ≥ 100". */
export function describeRule( rule, fields ) {
	const op = ops()[ rule.operator ] || rule.operator;
	return `${ sourceLabel( rule, fields ) } ${ op } ${ valueLabel( rule, fields ) }`.trim();
}

/** One-line summary: first rule + "AND/OR +n". Empty string when unconditioned. */
export function describeCondition( field, fields ) {
	const rules = Array.isArray( field.conditions ) ? field.conditions : [];
	if ( ! rules.length ) {
		return '';
	}
	let txt = describeRule( rules[ 0 ], fields );
	if ( rules.length > 1 ) {
		const joiner = 'any' === field.conditionMatch ? __( 'OR', 'alovio-calculator' ) : __( 'AND', 'alovio-calculator' );
		txt += ` ${ joiner } +${ rules.length - 1 }`;
	}
	return txt;
}

/** Action word for the pill: SHOW | HIDE | REQUIRE. */
export function conditionAction( field ) {
	const a = field.conditionAction || 'show';
	if ( 'hide' === a ) {
		return __( 'HIDE', 'alovio-calculator' );
	}
	if ( 'require' === a ) {
		return __( 'REQUIRE', 'alovio-calculator' );
	}
	return __( 'SHOW', 'alovio-calculator' );
}
