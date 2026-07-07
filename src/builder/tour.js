import { __ } from '@wordpress/i18n';

export const STORAGE_KEY = 'alovio_calc_tour_done';

export const TOUR_STEPS = [
	{
		target: '[data-tour="palette"]',
		title: __( 'Add fields', 'alovio-calculator' ),
		body: __( 'Click a field type to add it, or drag it straight to a spot on the canvas.', 'alovio-calculator' ),
	},
	{
		target: '[data-tour="canvas"]',
		title: __( 'This IS your calculator', 'alovio-calculator' ),
		body: __( 'The canvas runs the real calculator — type values and totals update instantly. Click any field to edit it.', 'alovio-calculator' ),
	},
	{
		target: '[data-tour="save"]',
		title: __( 'Save when ready', 'alovio-calculator' ),
		body: __( 'Save publishes your changes. Undo and redo have your back while you experiment.', 'alovio-calculator' ),
	},
];

/** Pure step sequencing — Jest-tested. */
export function nextTourState( state, action, stepCount = TOUR_STEPS.length ) {
	if ( state.done ) {
		return state;
	}
	if ( action === 'dismiss' ) {
		return { index: state.index, done: true };
	}
	if ( action === 'next' ) {
		const index = state.index + 1;
		return index >= stepCount ? { index: state.index, done: true } : { index, done: false };
	}
	if ( action === 'back' ) {
		return { index: Math.max( 0, state.index - 1 ), done: false };
	}
	return state;
}

export function shouldStartTour( storage ) {
	try {
		const s = storage || window.localStorage;
		return s.getItem( STORAGE_KEY ) !== '1';
	} catch ( e ) {
		return false; // storage unavailable → never nag, never crash
	}
}

export function markTourDone( storage ) {
	try {
		( storage || window.localStorage ).setItem( STORAGE_KEY, '1' );
	} catch ( e ) {
		// Ignore: worst case the tour shows again next session.
	}
}

/** Minimal pointer overlay: one floating card highlighting the current target. */
export function startTour( doc = document ) {
	let state = { index: 0, done: false };
	let card = null;
	let highlighted = null;

	const cleanup = () => {
		if ( card ) {
			card.remove();
			card = null;
		}
		if ( highlighted ) {
			highlighted.classList.remove( 'alcb-tour-target' );
			highlighted = null;
		}
	};

	const advance = ( action ) => {
		state = nextTourState( state, action );
		if ( state.done ) {
			cleanup();
			markTourDone();
			return;
		}
		render();
	};

	const button = ( label, action, primary ) => {
		const b = doc.createElement( 'button' );
		b.type = 'button';
		b.className = primary ? 'alcb-tour__btn alcb-tour__btn--primary' : 'alcb-tour__btn';
		b.textContent = label;
		b.addEventListener( 'click', () => advance( action ) );
		return b;
	};

	const render = () => {
		cleanup();
		const step = TOUR_STEPS[ state.index ];
		const target = doc.querySelector( step.target );
		if ( ! target ) {
			advance( 'next' ); // target missing (e.g. narrow viewport) → skip the step
			return;
		}
		highlighted = target;
		target.classList.add( 'alcb-tour-target' );

		card = doc.createElement( 'div' );
		card.className = 'alcb-tour';
		card.setAttribute( 'role', 'dialog' );
		card.setAttribute( 'aria-label', step.title );

		const title = doc.createElement( 'strong' );
		title.className = 'alcb-tour__title';
		title.textContent = step.title;
		const body = doc.createElement( 'p' );
		body.className = 'alcb-tour__body';
		body.textContent = step.body;
		const meta = doc.createElement( 'span' );
		meta.className = 'alcb-tour__meta';
		meta.textContent = `${ state.index + 1 } / ${ TOUR_STEPS.length }`;
		const actions = doc.createElement( 'div' );
		actions.className = 'alcb-tour__actions';
		actions.appendChild( button( __( 'Skip tour', 'alovio-calculator' ), 'dismiss', false ) );
		actions.appendChild(
			button(
				state.index === TOUR_STEPS.length - 1 ? __( 'Done', 'alovio-calculator' ) : __( 'Next', 'alovio-calculator' ),
				'next',
				true
			)
		);
		card.appendChild( title );
		card.appendChild( body );
		card.appendChild( meta );
		card.appendChild( actions );
		doc.body.appendChild( card );

		const rect = target.getBoundingClientRect();
		const top = Math.min( rect.bottom + 8, window.innerHeight - card.offsetHeight - 16 );
		const left = Math.min( Math.max( 8, rect.left ), window.innerWidth - card.offsetWidth - 16 );
		card.style.top = `${ Math.max( 8, top ) }px`;
		card.style.left = `${ left }px`;
	};

	render();
	return { advance, cleanup }; // exposed for debugging; production flow is button-driven
}
