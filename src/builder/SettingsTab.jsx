import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, SelectControl, ToggleControl, CheckboxControl, ColorPicker } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';


export default function SettingsTab() {
	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
	const { updateSettings } = useDispatch( STORE );

	const currency = settings.currency || {};
	const theme = settings.theme || {};
	const quote = settings.quoteForm || {};

	const setCurrency = ( patch ) => updateSettings( { currency: { ...currency, ...patch } } );
	const setQuote = ( patch ) => updateSettings( { quoteForm: { ...quote, ...patch } } );

	const quoteFields = quote.fields || [ 'name', 'email' ];
	const toggleQuoteField = ( key, on ) => {
		const next = on ? [ ...quoteFields, key ] : quoteFields.filter( ( f ) => f !== key );
		setQuote( { fields: next } );
	};

	return (
		<div className="alc-settings-tab">
			<section>
				<h3>{ __( 'Currency', 'alovio-calculator' ) }</h3>
				<div className="alc-row4">
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
					className="alc-narrow"
					label={ __( 'Decimal separator', 'alovio-calculator' ) }
					value={ currency.decimalSep ?? '.' }
					onChange={ ( decimalSep ) => setCurrency( { decimalSep } ) }
				/>
			</section>

			<section>
				<h3>{ __( 'Appearance', 'alovio-calculator' ) }</h3>
				<p className="alc-hint">{ __( 'Accent color (buttons, slider, total).', 'alovio-calculator' ) }</p>
				<ColorPicker
					color={ theme.accent || '#f97316' }
					onChange={ ( accent ) => updateSettings( { theme: { ...theme, accent } } ) }
					enableAlpha={ false }
				/>
			</section>

			<section>
				<h3>{ __( 'Quote requests', 'alovio-calculator' ) }</h3>
				<ToggleControl
					label={ __( 'Collect quote requests', 'alovio-calculator' ) }
					help={ __( 'Adds a contact form under the calculator; submissions appear under Entries.', 'alovio-calculator' ) }
					checked={ !! quote.enabled }
					onChange={ ( enabled ) => setQuote( { enabled } ) }
				/>
				{ !! quote.enabled && (
					<>
						<CheckboxControl label={ __( 'Name (always on)', 'alovio-calculator' ) } checked disabled onChange={ () => {} } />
						<CheckboxControl label={ __( 'Email (always on)', 'alovio-calculator' ) } checked disabled onChange={ () => {} } />
						<CheckboxControl label={ __( 'Phone', 'alovio-calculator' ) } checked={ quoteFields.includes( 'phone' ) } onChange={ ( on ) => toggleQuoteField( 'phone', on ) } />
						<CheckboxControl label={ __( 'Message', 'alovio-calculator' ) } checked={ quoteFields.includes( 'message' ) } onChange={ ( on ) => toggleQuoteField( 'message', on ) } />
						<TextControl
							label={ __( 'Notification email', 'alovio-calculator' ) }
							help={ __( 'Leave empty to use the site admin email.', 'alovio-calculator' ) }
							type="email"
							value={ quote.notifyEmail || '' }
							onChange={ ( notifyEmail ) => setQuote( { notifyEmail } ) }
						/>
						<TextControl
							label={ __( 'Success message', 'alovio-calculator' ) }
							placeholder={ __( "Thanks! We'll be in touch shortly.", 'alovio-calculator' ) }
							value={ quote.successMessage || '' }
							onChange={ ( successMessage ) => setQuote( { successMessage } ) }
						/>
					</>
				) }
			</section>
		</div>
	);
}
