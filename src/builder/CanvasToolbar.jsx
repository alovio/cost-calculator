import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { previewCalculator } from './api';

export const DEVICES = [
	{ id: 'desktop', label: __( 'Desktop', 'alovio-calculator' ), width: '100%' },
	{ id: 'tablet', label: __( 'Tablet', 'alovio-calculator' ), width: '820px' },
	{ id: 'mobile', label: __( 'Mobile', 'alovio-calculator' ), width: '390px' },
];

// Single source for the preset list in studio chrome (CalcDesign reuses it in chunk 4).
export const THEME_PRESETS = [
	{ value: 'classic', label: __( 'Classic', 'alovio-calculator' ) },
	{ value: 'minimal', label: __( 'Minimal', 'alovio-calculator' ) },
	{ value: 'midnight', label: __( 'Midnight', 'alovio-calculator' ) },
	{ value: 'soft', label: __( 'Soft', 'alovio-calculator' ) },
	{ value: 'bold', label: __( 'Bold', 'alovio-calculator' ) },
	{ value: 'slate', label: __( 'Slate', 'alovio-calculator' ) },
];

export default function CanvasToolbar( { device, onDevice, onResetValues, busy } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
	const { updateSettings } = useDispatch( STORE );
	const [ opening, setOpening ] = useState( false );
	const theme = settings.theme || {};

	const openPreview = async () => {
		setOpening( true );
		try {
			const res = await previewCalculator( { fields, settings } );
			window.open( res.url, '_blank', 'noopener' );
		} catch ( e ) {
			// Non-fatal — the canvas itself is the preview; the error strip covers render issues.
		}
		setOpening( false );
	};

	return (
		<div className="alcb-toolbar">
			<div className="alcb-devices" role="group" aria-label={ __( 'Canvas width', 'alovio-calculator' ) }>
				{ DEVICES.map( ( d ) => (
					<button key={ d.id } className={ device === d.id ? 'is-on' : '' } aria-pressed={ device === d.id } onClick={ () => onDevice( d.id ) }>
						{ d.label }
					</button>
				) ) }
			</div>
			<label className="alcb-theme-pick">
				{ __( 'Theme', 'alovio-calculator' ) }
				{ /* Writes settings.theme.preset through the store — UPDATE_SETTINGS is remembered, so it is undoable. */ }
				<select value={ theme.preset || 'classic' } onChange={ ( e ) => updateSettings( { theme: { ...theme, preset: e.target.value } } ) }>
					{ THEME_PRESETS.map( ( t ) => (
						<option key={ t.value } value={ t.value }>{ t.label }</option>
					) ) }
				</select>
			</label>
			<div className="alcb-grow"></div>
			{ busy && <Spinner /> }
			<button className="alcb-tool-btn" onClick={ onResetValues }>{ __( 'Reset values', 'alovio-calculator' ) }</button>
			<button className="alcb-tool-btn" disabled={ opening } onClick={ openPreview }>{ __( 'Open full preview', 'alovio-calculator' ) } ↗</button>
		</div>
	);
}
