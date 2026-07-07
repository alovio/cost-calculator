import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { renderCalculator } from './api';
import CanvasToolbar, { DEVICES } from './CanvasToolbar';
import CanvasOverlay from './CanvasOverlay';

/**
 * Read a { fieldId: {kind, value} } snapshot off the rendered fragment so
 * typed sample values survive structural re-renders (spec §2.2). Type-agnostic
 * on purpose: it keys off the DOM, not the config, so it also covers the
 * chunk-5+ field types without changes.
 */
export function snapshotValues( root ) {
	const values = {};
	root.querySelectorAll( '[data-alc-field]' ).forEach( ( wrap ) => {
		const id = wrap.getAttribute( 'data-alc-field' );
		if ( wrap.querySelector( 'input[type="radio"]' ) ) {
			const checked = wrap.querySelector( 'input[type="radio"]:checked' );
			values[ id ] = { kind: 'radio', value: checked ? checked.value : '' };
			return;
		}
		const boxes = wrap.querySelectorAll( 'input[type="checkbox"]' );
		if ( boxes.length ) {
			values[ id ] = { kind: 'checks', value: Array.from( boxes ).filter( ( b ) => b.checked ).map( ( b ) => b.value ) };
			return;
		}
		const input = wrap.querySelector( 'input, select, textarea' );
		if ( input ) {
			values[ id ] = { kind: 'input', value: input.value };
		}
	} );
	return values;
}

/** Re-apply a snapshot to a fresh fragment; each touched control dispatches input+change so the engine recomputes. */
export function restoreValues( root, values ) {
	Object.keys( values ).forEach( ( id ) => {
		const snap = values[ id ];
		const wrap = root.querySelector( `[data-alc-field="${ id }"]` );
		if ( ! wrap ) {
			return; // field removed by the edit — nothing to restore
		}
		let touched = null;
		if ( 'radio' === snap.kind ) {
			wrap.querySelectorAll( 'input[type="radio"]' ).forEach( ( r ) => {
				const on = r.value === snap.value;
				if ( r.checked !== on ) {
					r.checked = on;
					touched = on ? r : touched || r;
				}
			} );
		} else if ( 'checks' === snap.kind ) {
			wrap.querySelectorAll( 'input[type="checkbox"]' ).forEach( ( b ) => {
				const on = snap.value.indexOf( b.value ) !== -1;
				if ( b.checked !== on ) {
					b.checked = on;
					touched = b;
				}
			} );
		} else {
			const input = wrap.querySelector( 'input, select, textarea' );
			if ( input && input.value !== snap.value ) {
				input.value = snap.value;
				touched = input;
			}
		}
		if ( touched ) {
			touched.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			touched.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	} );
}

export default function LiveCanvas( { calculatorId } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ busy, setBusy ] = useState( false );
	const [ failed, setFailed ] = useState( false );
	const [ tick, setTick ] = useState( 0 ); // manual retry / reset trigger
	const [ appliedTick, setAppliedTick ] = useState( 0 ); // increments after each applied render (overlay re-measure)
	const hostRef = useRef( null );
	const scrollRef = useRef( null );
	const seqRef = useRef( 0 ); // last issued sequence token
	const appliedSeqRef = useRef( 0 ); // last APPLIED sequence token
	const skipRestoreRef = useRef( false ); // set by "Reset values"

	useEffect( () => {
		const seq = ++seqRef.current;
		const delay = 0 === appliedSeqRef.current ? 0 : 400; // first paint immediate, edits debounced (spec §2.2)
		const timer = window.setTimeout( () => {
			setBusy( true );
			renderCalculator( { calculatorId, fields, settings } )
				.then( ( res ) => {
					if ( seq <= appliedSeqRef.current ) {
						return; // stale success — a newer render already applied; discard
					}
					appliedSeqRef.current = seq;
					const host = hostRef.current;
					if ( ! host ) {
						return;
					}
					const keep = skipRestoreRef.current ? {} : snapshotValues( host );
					skipRestoreRef.current = false;
					host.innerHTML = res.html; // trusted: manage_options-gated endpoint, canonical renderer output
					const rootEl = host.querySelector( '.alc-calculator' );
					if ( rootEl && window.AlovioCalc && window.AlovioCalc.init ) {
						window.AlovioCalc.init( rootEl );
					}
					restoreValues( host, keep );
					// Studio guard: the fragment's quote form must not create real entries.
					host.querySelectorAll( '.alc-quote__submit' ).forEach( ( b ) => {
						b.disabled = true;
						b.title = __( 'Disabled in the studio — use "Open full preview" to test quotes.', 'alovio-calculator' );
					} );
					setAppliedTick( ( t ) => t + 1 );
					setFailed( false );
				} )
				.catch( () => {
					if ( seq === seqRef.current ) {
						setFailed( true ); // latest request failed; last good render stays on screen
					}
				} )
				.finally( () => setBusy( false ) );
		}, delay );
		return () => window.clearTimeout( timer );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ fields, settings, tick ] );

	const resetValues = () => {
		skipRestoreRef.current = true;
		setTick( ( t ) => t + 1 ); // force a fresh render without value restore
	};

	const width = ( DEVICES.find( ( d ) => d.id === device ) || DEVICES[ 0 ] ).width;

	return (
		<div className="alcb-canvas-col" data-tour="canvas">
			<CanvasToolbar device={ device } onDevice={ setDevice } onResetValues={ resetValues } busy={ busy } />
			{ failed && (
				<div className="alcb-render-error" role="alert">
					<span>{ __( 'Live render failed — showing the last good state.', 'alovio-calculator' ) }</span>
					<button onClick={ () => setTick( ( t ) => t + 1 ) }>{ __( 'Retry', 'alovio-calculator' ) }</button>
				</div>
			) }
			{ ! fields.length && (
				<p className="alcb-canvas-hint">{ __( 'Add a field from the left to get started.', 'alovio-calculator' ) }</p>
			) }
			<div className="alcb-canvas" ref={ scrollRef }>
				<div className="alcb-sheet" style={ { maxWidth: width } }>
					<div ref={ hostRef } className="alcb-fragment"></div>
					<CanvasOverlay hostRef={ hostRef } scrollRef={ scrollRef } renderTick={ appliedTick } />
				</div>
			</div>
		</div>
	);
}
