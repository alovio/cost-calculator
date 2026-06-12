import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button, Snackbar, Notice, Spinner, TextControl, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { getCalculator, saveCalculator } from './api';
import FieldPalette from './FieldPalette';
import Canvas from './Canvas';
import FieldSettings from './FieldSettings';
import SettingsTab from './SettingsTab';
import CalculatorList from './CalculatorList';
import EntriesList from './EntriesList';
import ProTab from './ProTab';


export default function App() {
	const [ view, setView ] = useState( 'list' );
	const [ calculatorId, setCalculatorId ] = useState( null );

	if ( view === 'builder' && calculatorId ) {
		return <Builder calculatorId={ calculatorId } onBack={ () => setView( 'list' ) } />;
	}
	if ( view === 'entries' ) {
		return <EntriesList onBack={ () => setView( 'list' ) } />;
	}
	return (
		<CalculatorList
			onEdit={ ( id ) => {
				setCalculatorId( id );
				setView( 'builder' );
			} }
			onEntries={ () => setView( 'entries' ) }
		/>
	);
}

function Builder( { calculatorId, onBack } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
	const { hydrate } = useDispatch( STORE );
	const [ title, setTitle ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ notice, setNotice ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const savedRef = useRef( null );

	const snapshot = ( f, s, t ) => JSON.stringify( { f, s, t } );

	useEffect( () => {
		setLoading( true );
		getCalculator( calculatorId )
			.then( ( calc ) => {
				hydrate( calc.config.fields || [], calc.config.settings || {} );
				setTitle( calc.title || '' );
				savedRef.current = snapshot( calc.config.fields || [], calc.config.settings || {}, calc.title || '' );
			} )
			.catch( () => setNotice( { type: 'error', text: __( 'Could not load this calculator.', 'alovio-calculator' ) } ) )
			.finally( () => setLoading( false ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ calculatorId ] );

	const dirty = savedRef.current !== null && snapshot( fields, settings, title ) !== savedRef.current;

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

	const save = async () => {
		setSaving( true );
		try {
			const saved = await saveCalculator( calculatorId, {
				title,
				config: { schemaVersion: 1, fields, settings },
			} );
			// Re-hydrate from the normalized response — the server may rewrite option slugs (spec §4).
			hydrate( saved.config.fields || [], saved.config.settings || {} );
			setTitle( saved.title || '' );
			savedRef.current = snapshot( saved.config.fields || [], saved.config.settings || {}, saved.title || '' );
			setNotice( { type: 'success', text: __( 'Calculator saved.', 'alovio-calculator' ) } );
		} catch ( e ) {
			setNotice( { type: 'error', text: __( 'Save failed. Please try again.', 'alovio-calculator' ) } );
		}
		setSaving( false );
	};

	const back = () => {
		// eslint-disable-next-line no-alert
		if ( ! dirty || window.confirm( __( 'You have unsaved changes. Leave anyway?', 'alovio-calculator' ) ) ) {
			onBack();
		}
	};

	if ( loading ) {
		return (
			<div className="alc-app alc-app--loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="alc-app">
			<div className="alc-topbar">
				<Button variant="tertiary" onClick={ back }>← { __( 'All calculators', 'alovio-calculator' ) }</Button>
				<TextControl
					className="alc-title-input"
					label={ __( 'Calculator name', 'alovio-calculator' ) }
					hideLabelFromVision
					value={ title }
					onChange={ setTitle }
				/>
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ dirty ? __( 'Save changes •', 'alovio-calculator' ) : __( 'Save changes', 'alovio-calculator' ) }
				</Button>
				{ dirty && <span className="alc-unsaved">{ __( 'Unsaved changes', 'alovio-calculator' ) }</span> }
			</div>
			{ notice && notice.type === 'error' && (
				<Notice status="error" onRemove={ () => setNotice( null ) }>{ notice.text }</Notice>
			) }
			<TabPanel
				tabs={ [
					{ name: 'fields', title: __( 'Fields', 'alovio-calculator' ) },
					{ name: 'settings', title: __( 'Settings', 'alovio-calculator' ) },
					...( window.ALC_BUILDER && window.ALC_BUILDER.isPro
						? []
						: [ { name: 'pro', title: __( 'Pro', 'alovio-calculator' ) } ] ),
				] }
			>
				{ ( tab ) => {
					if ( tab.name === 'fields' ) {
						return (
							<div className="alc-build">
								<FieldPalette />
								<Canvas />
								<FieldSettings />
							</div>
						);
					}
					if ( tab.name === 'pro' ) {
						return <ProTab />;
					}
					return <SettingsTab />;
				} }
			</TabPanel>
			{ notice && notice.type === 'success' && (
				<Snackbar onRemove={ () => setNotice( null ) }>{ notice.text }</Snackbar>
			) }
		</div>
	);
}
