import { prepare, run } from './compute';
import { updateSummary } from './summary';
import { wireQuoteForm } from './quote-form';

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
	config.i18n = { networkError: 'Something went wrong. Please try again.' };

	const fields = config.fields || [];
	const prepared = prepare( fields );

	const recompute = () => {
		const raw = collectRawValues( root, fields );
		const result = run( fields, prepared, raw );
		applyVisibility( root, result.active );
		updateInlineLines( root, fields, result.values );
		updateSummary( root, result, config.settings.currency );
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

	wireQuoteForm( root, config, () => collectRawValues( root, fields ) );
	recompute(); // Sync once on init (server already rendered defaults; this is idempotent).
}

export function initCalculators( doc ) {
	doc.querySelectorAll( '.alc-calculator' ).forEach( initCalculator );
}
