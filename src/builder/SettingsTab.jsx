import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, SelectControl, ToggleControl, CheckboxControl, ColorPicker } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const T = 'alovio-calculator';

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
				<h3>{ __( 'Currency', T ) }</h3>
				<div className="alc-row4">
					<TextControl label={ __( 'Symbol', T ) } value={ currency.symbol || '$' } onChange={ ( symbol ) => setCurrency( { symbol } ) } />
					<SelectControl
						label={ __( 'Position', T ) }
						value={ currency.position || 'before' }
						options={ [
							{ label: __( 'Before amount', T ), value: 'before' },
							{ label: __( 'After amount', T ), value: 'after' },
						] }
						onChange={ ( position ) => setCurrency( { position } ) }
					/>
					<TextControl type="number" min={ 0 } max={ 4 } label={ __( 'Decimals', T ) } value={ currency.decimals ?? 2 } onChange={ ( v ) => setCurrency( { decimals: parseInt( v, 10 ) || 0 } ) } />
					<TextControl label={ __( 'Thousand sep.', T ) } value={ currency.thousandSep ?? ',' } onChange={ ( thousandSep ) => setCurrency( { thousandSep } ) } />
				</div>
				<TextControl
					className="alc-narrow"
					label={ __( 'Decimal separator', T ) }
					value={ currency.decimalSep ?? '.' }
					onChange={ ( decimalSep ) => setCurrency( { decimalSep } ) }
				/>
			</section>

			<section>
				<h3>{ __( 'Appearance', T ) }</h3>
				<p className="alc-hint">{ __( 'Accent color (buttons, slider, total).', T ) }</p>
				<ColorPicker
					color={ theme.accent || '#0a66ff' }
					onChange={ ( accent ) => updateSettings( { theme: { ...theme, accent } } ) }
					enableAlpha={ false }
				/>
			</section>

			<section>
				<h3>{ __( 'Quote requests', T ) }</h3>
				<ToggleControl
					label={ __( 'Collect quote requests', T ) }
					help={ __( 'Adds a contact form under the calculator; submissions appear under Entries.', T ) }
					checked={ !! quote.enabled }
					onChange={ ( enabled ) => setQuote( { enabled } ) }
				/>
				{ !! quote.enabled && (
					<>
						<CheckboxControl label={ __( 'Name (always on)', T ) } checked disabled onChange={ () => {} } />
						<CheckboxControl label={ __( 'Email (always on)', T ) } checked disabled onChange={ () => {} } />
						<CheckboxControl label={ __( 'Phone', T ) } checked={ quoteFields.includes( 'phone' ) } onChange={ ( on ) => toggleQuoteField( 'phone', on ) } />
						<CheckboxControl label={ __( 'Message', T ) } checked={ quoteFields.includes( 'message' ) } onChange={ ( on ) => toggleQuoteField( 'message', on ) } />
						<TextControl
							label={ __( 'Notification email', T ) }
							help={ __( 'Leave empty to use the site admin email.', T ) }
							type="email"
							value={ quote.notifyEmail || '' }
							onChange={ ( notifyEmail ) => setQuote( { notifyEmail } ) }
						/>
						<TextControl
							label={ __( 'Success message', T ) }
							placeholder={ __( "Thanks! We'll be in touch shortly.", T ) }
							value={ quote.successMessage || '' }
							onChange={ ( successMessage ) => setQuote( { successMessage } ) }
						/>
					</>
				) }
			</section>
		</div>
	);
}
