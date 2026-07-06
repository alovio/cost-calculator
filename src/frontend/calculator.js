import { prepare, run } from './compute';
import { updateSummary } from './summary';
import { wireQuoteForm } from './quote-form';
import { setupWizard } from './wizard';
import { setupRepeaters } from './repeater';

/** Read one field-shaped value from a scope element (a field wrapper or a repeater child cell). */
function readValue( scope, type ) {
	switch ( type ) {
		case 'number':
		case 'slider':
		case 'quantity':
		case 'text':
		case 'date':
		case 'email':
		case 'phone':
		case 'url': {
			const input = scope.querySelector( 'input' );
			return input ? input.value : '';
		}
		case 'textarea': {
			const ta = scope.querySelector( 'textarea' );
			return ta ? ta.value : '';
		}
		case 'select': {
			const select = scope.querySelector( 'select' );
			return select ? select.value : '';
		}
		case 'radio': {
			const checked = scope.querySelector( 'input:checked' );
			return checked ? checked.value : '';
		}
		case 'checkbox_group':
			return Array.from( scope.querySelectorAll( 'input:checked' ) ).map( ( i ) => i.value );
		case 'toggle': {
			const box = scope.querySelector( 'input[type="checkbox"]' );
			return box && box.checked ? '1' : '';
		}
	}
	return undefined;
}

/** Collect raw values from the DOM, scoped by [data-alc-field] wrappers. Exported for tests. */
export function collectRawValues( root, fields ) {
	const raw = {};
	fields.forEach( ( f ) => {
		const wrap = root.querySelector( `[data-alc-field="${ f.id }"]` );
		if ( ! wrap ) {
			return;
		}
		if ( f.type === 'repeater' ) {
			const rows = [];
			wrap.querySelectorAll( '[data-alc-rows] [data-alc-row]' ).forEach( ( rowEl ) => {
				const row = {};
				( f.fields || [] ).forEach( ( child ) => {
					const cell = rowEl.querySelector( `[data-alc-child="${ child.id }"]` );
					const v = cell ? readValue( cell, child.type ) : undefined;
					if ( v !== undefined ) {
						row[ child.id ] = v;
					}
				} );
				rows.push( row );
			} );
			raw[ f.id ] = rows;
			return;
		}
		const v = readValue( wrap, f.type );
		if ( v !== undefined ) {
			raw[ f.id ] = v;
		}
	} );
	return raw;
}

/** Bubble text (+unit) and thumb-tracking position. Works for top-level and repeater-row sliders. */
export function updateSliderUi( input ) {
	const holder = input.closest( '.alc-slider' );
	const out = holder && holder.querySelector( 'output' );
	if ( ! out ) {
		return;
	}
	const unit = holder.getAttribute( 'data-alc-unit' ) || '';
	out.textContent = input.value + ( unit ? ' ' + unit : '' );
	const min = parseFloat( input.min || '0' );
	const max = parseFloat( input.max || '100' );
	const pct = max > min ? ( ( parseFloat( input.value ) - min ) / ( max - min ) ) * 100 : 0;
	holder.style.setProperty( '--alc-pos', `${ pct }%` );
}

function applyVisibility( root, active ) {
	Object.entries( active ).forEach( ( [ id, isActive ] ) => {
		const wrap = root.querySelector( `[data-alc-field="${ id }"]` );
		if ( wrap ) {
			wrap.hidden = isActive === false;
		}
	} );
}

/** THEN=require: flag mandatory fields (visual marker + aria-required for a11y). */
function applyRequired( root, required ) {
	Object.entries( required ).forEach( ( [ id, isReq ] ) => {
		const wrap = root.querySelector( `[data-alc-field="${ id }"]` );
		if ( ! wrap ) {
			return;
		}
		wrap.classList.toggle( 'alc-field--required', !! isReq );
		wrap.querySelectorAll( 'input, select, textarea' ).forEach( ( el ) => {
			el.setAttribute( 'aria-required', isReq ? 'true' : 'false' );
		} );
	} );
}

/** A required field is unsatisfied when the visitor left it empty / unselected. */
function isEmptyValue( field, raw ) {
	if ( field.type === 'checkbox_group' ) {
		return ! Array.isArray( raw ) || raw.length === 0;
	}
	if ( field.type === 'toggle' ) {
		return raw !== '1';
	}
	return raw === undefined || raw === null || String( raw ).trim() === '';
}

function updateInlineLines( root, fields, values ) {
	fields
		.filter( ( f ) => f.type === 'formula' )
		.forEach( ( f ) => {
			const el = root.querySelector( `[data-alc-field="${ f.id }"] .alc-line__value` );
			if ( el && f.id in values ) {
				el.textContent = String( values[ f.id ] / 10000 );
			}
		} );
}

/** Initialise ONE rendered calculator root (`.alc-calculator` element). Idempotent per fresh fragment; the studio canvas calls this after each inject (spec §2.2). */
export function init( root ) {
	const configEl = root.querySelector( '.alc-config' );
	if ( ! configEl ) {
		return;
	}
	let config;
	try {
		config = JSON.parse( configEl.textContent );
	} catch ( e ) {
		return;
	}
	config.i18n = {
		networkError: 'Something went wrong. Please try again.',
		requiredError: 'Please fill in the required fields.',
		wizard: { back: 'Back', next: 'Next', step: 'Step', of: 'of' },
	};

	const fields = config.fields || [];
	const prepared = prepare( fields );

	let lastResult = { active: {}, required: {} };
	const recompute = () => {
		const raw = collectRawValues( root, fields );
		const result = run( fields, prepared, raw );
		lastResult = result;
		applyVisibility( root, result.active );
		applyRequired( root, result.required );
		updateInlineLines( root, fields, result.values );
		updateSummary( root, result, config.settings.currency );
	};

	// THEN=require: block the quote when an active, mandatory field is empty.
	// Keyed by field id; the server re-validates authoritatively.
	const validateRequired = () => {
		const raw = collectRawValues( root, fields );
		const errors = {};
		fields.forEach( ( f ) => {
			if ( lastResult.required[ f.id ] && lastResult.active[ f.id ] !== false && isEmptyValue( f, raw[ f.id ] ) ) {
				errors[ f.id ] = ( f.label || '' ) + ' ' + 'is required.';
			}
		} );
		return errors;
	};

	// Per-step validation for the wizard layout (same rule, scoped to given ids).
	const validateStep = ( ids ) => {
		const raw = collectRawValues( root, fields );
		const errors = {};
		fields.forEach( ( f ) => {
			if ( ids.indexOf( f.id ) !== -1 && lastResult.required[ f.id ] && lastResult.active[ f.id ] !== false && isEmptyValue( f, raw[ f.id ] ) ) {
				errors[ f.id ] = ( f.label || '' ) + ' ' + 'is required.';
			}
		} );
		return errors;
	};

	root.addEventListener( 'input', ( e ) => {
		if ( e.target.closest( '.alc-quote' ) ) {
			return; // Contact inputs don't affect the math.
		}
		if ( e.target.type === 'range' ) {
			updateSliderUi( e.target );
		}
		recompute();
	} );
	root.addEventListener( 'change', ( e ) => {
		if ( ! e.target.closest( '.alc-quote' ) ) {
			recompute();
		}
	} );

	wireQuoteForm( root, config, () => collectRawValues( root, fields ), validateRequired );
	setupRepeaters( root, fields, recompute );
	recompute(); // Sync once on init (server already rendered defaults; this is idempotent).

	if ( config.settings && config.settings.layout === 'wizard' ) {
		setupWizard( root, config, validateStep );
	}
}

export function initCalculators( doc ) {
	doc.querySelectorAll( '.alc-calculator' ).forEach( init );
}
