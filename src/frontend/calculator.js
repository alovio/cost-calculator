import { prepare, run } from './compute';
import { updateSummary } from './summary';
import { wireQuoteForm } from './quote-form';
import { setupWizard } from './wizard';

/** Collect raw values from the DOM, scoped by [data-alc-field] wrappers. */
function collectRawValues( root, fields ) {
	const raw = {};
	fields.forEach( ( f ) => {
		const wrap = root.querySelector( `[data-alc-field="${ f.id }"]` );
		if ( ! wrap ) {
			return;
		}
		switch ( f.type ) {
			case 'number':
			case 'slider':
			case 'quantity':
			case 'text': {
				const input = wrap.querySelector( 'input' );
				raw[ f.id ] = input ? input.value : '';
				break;
			}
			case 'select': {
				const select = wrap.querySelector( 'select' );
				raw[ f.id ] = select ? select.value : '';
				break;
			}
			case 'radio': {
				const checked = wrap.querySelector( 'input:checked' );
				raw[ f.id ] = checked ? checked.value : '';
				break;
			}
			case 'checkbox_group':
				raw[ f.id ] = Array.from( wrap.querySelectorAll( 'input:checked' ) ).map( ( i ) => i.value );
				break;
			case 'toggle': {
				const box = wrap.querySelector( 'input[type="checkbox"]' );
				raw[ f.id ] = box && box.checked ? '1' : '';
				break;
			}
		}
	} );
	return raw;
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

function initCalculator( root ) {
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
			const out = e.target.parentElement.querySelector( 'output' );
			if ( out ) {
				out.textContent = e.target.value;
			}
		}
		recompute();
	} );
	root.addEventListener( 'change', ( e ) => {
		if ( ! e.target.closest( '.alc-quote' ) ) {
			recompute();
		}
	} );

	wireQuoteForm( root, config, () => collectRawValues( root, fields ), validateRequired );
	recompute(); // Sync once on init (server already rendered defaults; this is idempotent).

	if ( config.settings && config.settings.layout === 'wizard' ) {
		setupWizard( root, config, validateStep );
	}
}

export function initCalculators( doc ) {
	doc.querySelectorAll( '.alc-calculator' ).forEach( initCalculator );
}
