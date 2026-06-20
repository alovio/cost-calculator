/**
 * Multi-step wizard layout. Groups the rendered [data-alc-field] elements into
 * steps at `.alc-field--step` dividers, shows one step at a time with Back / Next
 * + progress, and validates the current step's required fields before advancing.
 * The calculator still computes across ALL fields (hidden steps stay in the DOM),
 * so the running summary is always correct.
 */

/** Pure: split an ordered list of field elements into step groups. Exported for tests. */
export function groupSteps( els ) {
	const groups = [];
	let current = null;
	els.forEach( ( el ) => {
		const isDivider = el.classList && el.classList.contains( 'alc-field--step' );
		if ( isDivider ) {
			current = { header: el, items: [] };
			groups.push( current );
		} else {
			if ( ! current ) {
				current = { header: null, items: [] };
				groups.push( current );
			}
			current.items.push( el );
		}
	} );
	return groups;
}

function fieldId( el ) {
	return el.getAttribute( 'data-alc-field' );
}

export function setupWizard( root, config, validateStep ) {
	const fieldsEl = root.querySelector( '.alc-fields' );
	if ( ! fieldsEl ) {
		return;
	}
	const els = Array.prototype.slice.call( fieldsEl.querySelectorAll( ':scope > [data-alc-field]' ) );
	const groups = groupSteps( els );
	if ( groups.length < 2 ) {
		return; // Not enough dividers to step through — leave it single-page.
	}

	const i18n = ( config.i18n && config.i18n.wizard ) || {};
	const t = {
		back: i18n.back || 'Back',
		next: i18n.next || 'Next',
		step: i18n.step || 'Step',
		of: i18n.of || 'of',
	};

	// Wrap each group's elements in a .alc-step container.
	const steps = groups.map( ( g ) => {
		const step = document.createElement( 'div' );
		step.className = 'alc-step';
		if ( g.header ) {
			step.appendChild( g.header );
		}
		g.items.forEach( ( it ) => step.appendChild( it ) );
		fieldsEl.appendChild( step );
		return { el: step, ids: g.items.map( fieldId ).filter( Boolean ) };
	} );

	// Progress (dots + counter) above the fields.
	const progress = document.createElement( 'div' );
	progress.className = 'alc-wizard-progress';
	const dots = document.createElement( 'div' );
	dots.className = 'alc-wizard-dots';
	steps.forEach( () => {
		const dot = document.createElement( 'span' );
		dot.className = 'alc-wizard-dot';
		dots.appendChild( dot );
	} );
	const counter = document.createElement( 'span' );
	counter.className = 'alc-wizard-count';
	progress.appendChild( dots );
	progress.appendChild( counter );

	// Back / Next below the fields.
	const nav = document.createElement( 'div' );
	nav.className = 'alc-wizard-nav';
	const back = document.createElement( 'button' );
	back.type = 'button';
	back.className = 'alc-wizard-btn alc-wizard-btn--back';
	back.textContent = t.back;
	const next = document.createElement( 'button' );
	next.type = 'button';
	next.className = 'alc-wizard-btn alc-wizard-btn--next';
	next.textContent = t.next;
	nav.appendChild( back );
	nav.appendChild( next );

	fieldsEl.parentNode.insertBefore( progress, fieldsEl );
	fieldsEl.parentNode.insertBefore( nav, fieldsEl.nextSibling );

	const quote = root.querySelector( '.alc-quote' );
	let idx = 0;

	const clearErrors = () => {
		root.querySelectorAll( '.alc-field-error' ).forEach( ( el ) => el.remove() );
	};

	const showErrors = ( errors ) => {
		Object.keys( errors ).forEach( ( id ) => {
			const wrap = root.querySelector( `[data-alc-field="${ id }"]` );
			if ( wrap ) {
				const note = document.createElement( 'span' );
				note.className = 'alc-field-error';
				note.textContent = errors[ id ];
				wrap.appendChild( note );
			}
		} );
	};

	const render = () => {
		steps.forEach( ( s, n ) => {
			s.el.hidden = n !== idx;
		} );
		back.hidden = idx === 0;
		next.hidden = idx === steps.length - 1;
		if ( quote ) {
			// Inline style (not the hidden attr) so plugin CSS can't override it.
			quote.style.display = idx === steps.length - 1 ? '' : 'none';
		}
		counter.textContent = `${ t.step } ${ idx + 1 } ${ t.of } ${ steps.length }`;
		Array.prototype.slice.call( dots.children ).forEach( ( d, n ) => {
			d.classList.toggle( 'is-active', n === idx );
			d.classList.toggle( 'is-done', n < idx );
		} );
	};

	next.addEventListener( 'click', () => {
		clearErrors();
		const errors = validateStep ? validateStep( steps[ idx ].ids ) : {};
		if ( Object.keys( errors ).length ) {
			showErrors( errors );
			return;
		}
		idx = Math.min( steps.length - 1, idx + 1 );
		render();
	} );
	back.addEventListener( 'click', () => {
		clearErrors();
		idx = Math.max( 0, idx - 1 );
		render();
	} );

	render();
}
