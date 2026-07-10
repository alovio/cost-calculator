import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { getCalculator, saveCalculator } from './api';
import PaletteV2 from './PaletteV2';
import LiveCanvas from './LiveCanvas';
import SettingsPanel from './SettingsPanel';
import { saveDraft, loadDraft, clearDraft, isDraftNewer, DRAFT_DEBOUNCE_MS } from './draft';
import { shouldStartTour, startTour } from './tour';

/**
 * True when the event target edits text — undo/redo shortcuts must never
 * hijack native text-editing undo (spec §2.1).
 */
function isTextTarget( t ) {
	return !! t && ( 'INPUT' === t.tagName || 'TEXTAREA' === t.tagName || 'SELECT' === t.tagName || true === t.isContentEditable );
}

export default function StudioShell( { calculatorId, onBack } ) {
	const { fields, settings, name, selectedId, canUndo, canRedo } = useSelect(
		( select ) => ( {
			fields: select( STORE ).getFields(),
			settings: select( STORE ).getSettings(),
			name: select( STORE ).getName(),
			selectedId: select( STORE ).getSelectedId(),
			canUndo: select( STORE ).canUndo(),
			canRedo: select( STORE ).canRedo(),
		} ),
		[]
	);
	const { hydrate, undo, redo, setName } = useDispatch( STORE );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ flash, setFlash ] = useState( null ); // 'saved' | 'error' | null
	const [ proOpen, setProOpen ] = useState( false );
	const [ draft, setDraft ] = useState( null );
	// Small screens (≤960px): the side columns become overlay drawers ('palette' | 'settings' | null).
	const [ mobilePanel, setMobilePanel ] = useState( null );
	const savedRef = useRef( null );
	const modifiedRef = useRef( '' ); // server post_modified_gmt (fed from chunk 2; draft.js consumes it in chunk 4)
	const isPro = !! ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.isPro );

	const snapshot = ( f, s, n ) => JSON.stringify( { f, s, n } );

	useEffect( () => {
		setLoading( true );
		getCalculator( calculatorId )
			.then( ( calc ) => {
				hydrate( calc.config.fields || [], calc.config.settings || {}, calc.title || '' );
				savedRef.current = snapshot( calc.config.fields || [], calc.config.settings || {}, calc.title || '' );
				modifiedRef.current = calc.modified || '';
				const d = loadDraft( calculatorId );
				if ( isDraftNewer( d, calc.modified || '' ) ) {
					setDraft( d ); // newest-wins prompt — the user decides (spec §8)
				}
			} )
			.catch( () => setLoadError( true ) )
			.finally( () => setLoading( false ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ calculatorId ] );

	const dirty = savedRef.current !== null && snapshot( fields, settings, name ) !== savedRef.current;

	useEffect( () => {
		const handler = ( e ) => {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', handler );
		return () => window.removeEventListener( 'beforeunload', handler );
	}, [ dirty ] );

	useEffect( () => {
		if ( loading || ! dirty ) {
			return undefined;
		}
		const t = window.setTimeout( () => saveDraft( calculatorId, { name, fields, settings } ), DRAFT_DEBOUNCE_MS );
		return () => window.clearTimeout( t );
	}, [ calculatorId, name, fields, settings, dirty, loading ] );

	useEffect( () => {
		if ( ! loading && shouldStartTour() ) {
			startTour();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ loading ] );

	// Tapping a canvas field on a small screen opens its settings as a bottom sheet.
	useEffect( () => {
		if ( selectedId && window.matchMedia( '(max-width: 960px)' ).matches ) {
			setMobilePanel( 'settings' );
		}
	}, [ selectedId ] );

	const save = useCallback( async () => {
		setSaving( true );
		try {
			const saved = await saveCalculator( calculatorId, {
				title: name,
				config: { schemaVersion: 1, fields, settings },
			} );
			// Re-hydrate from the normalized response — the server may rewrite
			// option slugs. HYDRATE clears undo history ON PURPOSE: stale
			// snapshots could resurrect pre-slug options the server renamed.
			hydrate( saved.config.fields || [], saved.config.settings || {}, saved.title || '' );
			savedRef.current = snapshot( saved.config.fields || [], saved.config.settings || {}, saved.title || '' );
			modifiedRef.current = saved.modified || modifiedRef.current;
			clearDraft( calculatorId );
			setDraft( null );
			setFlash( 'saved' );
			window.setTimeout( () => setFlash( null ), 2500 );
		} catch ( e ) {
			setFlash( 'error' );
		}
		setSaving( false );
	}, [ calculatorId, name, fields, settings, hydrate ] );

	useEffect( () => {
		const onKey = ( e ) => {
			if ( ! ( e.metaKey || e.ctrlKey ) ) {
				return;
			}
			const k = e.key.toLowerCase();
			if ( 's' === k ) {
				e.preventDefault();
				save();
				return;
			}
			if ( 'z' !== k || isTextTarget( e.target ) ) {
				return;
			}
			e.preventDefault();
			if ( e.shiftKey ) {
				redo();
			} else {
				undo();
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [ save, undo, redo ] );

	const back = () => {
		// eslint-disable-next-line no-alert
		if ( ! dirty || window.confirm( __( 'You have unsaved changes. Leave anyway?', 'alovio-calculator' ) ) ) {
			onBack();
		}
	};

	if ( loading ) {
		return <div className="alcb-app alcb-app--center"><Spinner /></div>;
	}
	if ( loadError ) {
		return (
			<div className="alcb-app alcb-app--center">
				<Notice status="error" isDismissible={ false }>{ __( 'Could not load this calculator.', 'alovio-calculator' ) }</Notice>
			</div>
		);
	}

	let statusCls = 'alcb-status';
	let statusTxt = __( 'All changes saved', 'alovio-calculator' );
	if ( 'error' === flash ) {
		statusCls += ' is-error';
		statusTxt = __( 'Save failed — try again', 'alovio-calculator' );
	} else if ( dirty ) {
		statusCls += ' is-dirty';
		statusTxt = __( 'Unsaved changes', 'alovio-calculator' );
	} else if ( 'saved' === flash ) {
		statusCls += ' is-saved';
		statusTxt = __( 'Saved', 'alovio-calculator' );
	}

	return (
		<div className="alcb-app">
			<div className="alcb-hdr">
				<button className="alcb-back" onClick={ back } aria-label={ __( 'All calculators', 'alovio-calculator' ) }>←</button>
				<div className="alcb-logo">
					<span className="alcb-mark">▲</span>
					Alovio <span className="alcb-sub">{ __( 'Calculator', 'alovio-calculator' ) }</span>
				</div>
				<input
					className="alcb-name"
					value={ name }
					placeholder={ __( 'Calculator name', 'alovio-calculator' ) }
					aria-label={ __( 'Calculator name', 'alovio-calculator' ) }
					onChange={ ( e ) => setName( e.target.value ) }
				/>
				<div className="alcb-grow"></div>
				<span className={ statusCls }><span className="alcb-dot"></span><span className="alcb-status-txt">{ statusTxt }</span></span>
				<button className="alcb-btn-ghost" disabled={ ! canUndo } onClick={ undo } aria-label={ __( 'Undo', 'alovio-calculator' ) }>⟲ <span className="alcb-btn-txt">{ __( 'Undo', 'alovio-calculator' ) }</span></button>
				<button className="alcb-btn-ghost" disabled={ ! canRedo } onClick={ redo } aria-label={ __( 'Redo', 'alovio-calculator' ) }>⟳ <span className="alcb-btn-txt">{ __( 'Redo', 'alovio-calculator' ) }</span></button>
				<button className="alcb-btn-primary" data-tour="save" disabled={ saving } onClick={ save }>
					{ saving ? __( 'Saving…', 'alovio-calculator' ) : __( 'Save', 'alovio-calculator' ) }
				</button>
				{ ! isPro && (
					<button className={ 'alcb-btn-ghost alcb-btn-pro' + ( proOpen ? ' is-on' : '' ) } aria-pressed={ proOpen } onClick={ () => setProOpen( ! proOpen ) }>
						{ __( 'Pro', 'alovio-calculator' ) }
					</button>
				) }
			</div>
			{ draft && (
				<div className="alcb-draftbar" role="status">
					<span>{ __( 'A newer unsaved draft of this calculator exists on this device.', 'alovio-calculator' ) }</span>
					<button className="alcb-draftbar__restore" onClick={ () => { hydrate( draft.fields || [], draft.settings || {}, draft.name || '' ); setDraft( null ); } }>
						{ __( 'Restore draft', 'alovio-calculator' ) }
					</button>
					<button className="alcb-draftbar__discard" onClick={ () => { clearDraft( calculatorId ); setDraft( null ); } }>
						{ __( 'Discard', 'alovio-calculator' ) }
					</button>
				</div>
			) }
			{ /* Small screens only (CSS-hidden on desktop): toggles for the drawer columns. */ }
			<div className="alcb-mobilebar">
				<button
					className={ 'palette' === mobilePanel ? 'is-on' : '' }
					aria-expanded={ 'palette' === mobilePanel }
					onClick={ () => setMobilePanel( 'palette' === mobilePanel ? null : 'palette' ) }
				>
					＋ { __( 'Fields', 'alovio-calculator' ) }
				</button>
				<button
					className={ 'settings' === mobilePanel ? 'is-on' : '' }
					aria-expanded={ 'settings' === mobilePanel }
					onClick={ () => setMobilePanel( 'settings' === mobilePanel ? null : 'settings' ) }
				>
					⚙ { __( 'Settings', 'alovio-calculator' ) }
				</button>
			</div>
			<div className={ 'alcb-work' + ( 'palette' === mobilePanel ? ' is-palette-open' : '' ) + ( 'settings' === mobilePanel ? ' is-settings-open' : '' ) }>
				{ mobilePanel && <div className="alcb-drawer-backdrop" onClick={ () => setMobilePanel( null ) }></div> }
				<div className="alcb-col alcb-col--left">
					<button className="alcb-drawer-close" aria-label={ __( 'Close', 'alovio-calculator' ) } onClick={ () => setMobilePanel( null ) }>✕</button>
					<PaletteV2 />
				</div>
				<div className="alcb-col alcb-col--center alcb-col--canvas"><LiveCanvas calculatorId={ calculatorId } /></div>
				<div className="alcb-col alcb-col--right alcb-col--panel">
					<button className="alcb-drawer-close" aria-label={ __( 'Close', 'alovio-calculator' ) } onClick={ () => setMobilePanel( null ) }>✕</button>
					<SettingsPanel proOpen={ proOpen } />
				</div>
			</div>
		</div>
	);
}
