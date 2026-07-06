import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, ToggleControl, CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';

export default function CalcQuote() {
	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
	const { updateSettings } = useDispatch( STORE );
	const quote = settings.quoteForm || {};
	const setQuote = ( patch ) => updateSettings( { quoteForm: { ...quote, ...patch } } );

	const quoteFields = quote.fields || [ 'name', 'email' ];
	const toggleQuoteField = ( key, on ) => {
		const next = on ? [ ...quoteFields, key ] : quoteFields.filter( ( f ) => f !== key );
		setQuote( { fields: next } );
	};

	return (
		<>
			<span className="alcb-sec-label">{ __( 'Quote requests', 'alovio-calculator' ) }</span>
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
		</>
	);
}
