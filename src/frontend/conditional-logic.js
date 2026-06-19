/**
 * Front-end conditional logic. Mirrors PHP CoreLabs\CheckoutFields\Logic\ConditionalLogic
 * and is kept in lockstep via tests/fixtures/conditional-cases.json.
 *
 * Pure evaluation only: a rule's `field` key may be a sibling field id or a
 * checkout "source token" (e.g. `user_role`, `payment_method`). The DOM/context
 * value map is assembled separately (src/checkout-conditions/data-sources.js) and
 * the classic/Block runtimes feed it in.
 *
 * Single condition: `condition` {field, operator, value, action}.
 * Multiple: `conditions` [{field, operator, value}] + conditionMatch (all|any) +
 *           conditionAction (show|hide|require). Operators: is/is_not/contains/gt/lt.
 */

function isNum( v ) {
	return v !== '' && ! isNaN( parseFloat( v ) ) && isFinite( v );
}

function matchRule( rule, values ) {
	if ( ! rule || typeof rule !== 'object' ) {
		return false;
	}
	const left = values[ rule.field ] !== undefined && values[ rule.field ] !== null ? String( values[ rule.field ] ) : '';
	const right = rule.value !== undefined && rule.value !== null ? String( rule.value ) : '';
	switch ( rule.operator ) {
		case 'is_not':
			return left !== right;
		case 'contains':
			return right !== '' && left.indexOf( right ) !== -1;
		case 'gt':
			return isNum( left ) && isNum( right ) && parseFloat( left ) > parseFloat( right );
		case 'gte':
			return isNum( left ) && isNum( right ) && parseFloat( left ) >= parseFloat( right );
		case 'lt':
			return isNum( left ) && isNum( right ) && parseFloat( left ) < parseFloat( right );
		case 'lte':
			return isNum( left ) && isNum( right ) && parseFloat( left ) <= parseFloat( right );
		case 'is_empty':
			return left === '';
		case 'is_not_empty':
			return left !== '';
		case 'is':
		default:
			return left === right;
	}
}

/**
 * Evaluate a single condition. Kept for fixture parity.
 *
 * @param {Object|null} condition
 * @param {Object} values
 * @return {boolean}
 */
export function evaluate( condition, values ) {
	if ( ! condition || typeof condition !== 'object' ) {
		return true;
	}
	const action = condition.action || 'show';
	if ( action === 'require' ) {
		return true;
	}
	const match = matchRule( condition, values );
	if ( action === 'hide' ) {
		return ! match;
	}
	return match;
}

/**
 * Whether a field is active given submitted values (single or multi rules).
 *
 * @param {Object} field
 * @param {Object} values
 * @return {boolean}
 */
export function fieldActive( field, values ) {
	if ( Array.isArray( field.conditions ) && field.conditions.length ) {
		const action = field.conditionAction || 'show';
		if ( action === 'require' ) {
			return true;
		}
		const results = field.conditions.map( ( r ) => matchRule( r, values ) );
		const combined =
			field.conditionMatch === 'any' ? results.indexOf( true ) !== -1 : results.indexOf( false ) === -1;
		if ( action === 'hide' ) {
			return ! combined;
		}
		return combined;
	}
	return evaluate( field.condition, values );
}

/**
 * Whether a require-action field is mandatory right now (mirrors PHP
 * ConditionalLogic::requires). 'require' affects validation, not visibility.
 *
 * @param {Object} field
 * @param {Object} values
 * @return {boolean}
 */
export function fieldRequired( field, values ) {
	if ( Array.isArray( field.conditions ) && field.conditions.length ) {
		if ( ( field.conditionAction || 'show' ) !== 'require' ) {
			return false;
		}
		const results = field.conditions.map( ( r ) => matchRule( r, values ) );
		return field.conditionMatch === 'any' ? results.indexOf( true ) !== -1 : results.indexOf( false ) === -1;
	}
	const c = field.condition;
	return !! c && ( c.action || 'show' ) === 'require' && matchRule( c, values );
}

function controllers( field ) {
	if ( Array.isArray( field.conditions ) && field.conditions.length ) {
		return field.conditions.map( ( r ) => r.field ).filter( Boolean );
	}
	if ( field.condition && field.condition.field ) {
		return [ field.condition.field ];
	}
	return [];
}

/**
 * Transitive active map (mirrors PHP active_map): a field is active only if its
 * own rules pass AND every referenced controller is active. Cycle-safe.
 * Source-token controllers aren't in the field map, so they resolve to true.
 *
 * @param {Array} fields
 * @param {Object} values
 * @return {Object} id -> boolean
 */
export function activeMap( fields, values ) {
	const byId = {};
	fields.forEach( ( f ) => {
		byId[ f.id ] = f;
	} );
	const cache = {};
	const inStack = {};
	const resolve = ( id ) => {
		if ( id in cache ) {
			return cache[ id ];
		}
		if ( ! byId[ id ] || inStack[ id ] ) {
			return true;
		}
		inStack[ id ] = true;
		const f = byId[ id ];
		let active = fieldActive( f, values );
		controllers( f ).forEach( ( cid ) => {
			active = active && resolve( cid );
		} );
		delete inStack[ id ];
		cache[ id ] = active;
		return active;
	};
	const map = {};
	fields.forEach( ( f ) => {
		map[ f.id ] = resolve( f.id );
	} );
	return map;
}
