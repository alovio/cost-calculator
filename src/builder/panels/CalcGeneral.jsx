import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';

export default function CalcGeneral() {
	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
	const { updateSettings } = useDispatch( STORE );
	const currency = settings.currency || {};
	const setCurrency = ( patch ) => updateSettings( { currency: { ...currency, ...patch } } );

	return (
		<>
			<span className="alcb-sec-label">{ __( 'Currency', 'alovio-calculator' ) }</span>
			<div className="alcb-row4">
				<TextControl label={ __( 'Symbol', 'alovio-calculator' ) } value={ currency.symbol || '$' } onChange={ ( symbol ) => setCurrency( { symbol } ) } />
				<SelectControl
					label={ __( 'Position', 'alovio-calculator' ) }
					value={ currency.position || 'before' }
					options={ [
						{ label: __( 'Before amount', 'alovio-calculator' ), value: 'before' },
						{ label: __( 'After amount', 'alovio-calculator' ), value: 'after' },
					] }
					onChange={ ( position ) => setCurrency( { position } ) }
				/>
				<TextControl type="number" min={ 0 } max={ 4 } label={ __( 'Decimals', 'alovio-calculator' ) } value={ currency.decimals ?? 2 } onChange={ ( v ) => setCurrency( { decimals: parseInt( v, 10 ) || 0 } ) } />
				<TextControl label={ __( 'Thousand sep.', 'alovio-calculator' ) } value={ currency.thousandSep ?? ',' } onChange={ ( thousandSep ) => setCurrency( { thousandSep } ) } />
			</div>
			<TextControl
				className="alcb-narrow"
				label={ __( 'Decimal separator', 'alovio-calculator' ) }
				value={ currency.decimalSep ?? '.' }
				onChange={ ( decimalSep ) => setCurrency( { decimalSep } ) }
			/>
		</>
	);
}
