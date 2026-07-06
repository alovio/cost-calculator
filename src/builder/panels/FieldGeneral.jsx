import { TextControl, ToggleControl, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const HAS_RANGE = [ 'number', 'slider', 'quantity' ];
const HAS_PLACEHOLDER = [ 'text', 'date', 'email', 'phone', 'url', 'textarea' ];

function num( v ) {
	return '' === v || null === v || undefined === v ? null : v;
}

export default function FieldGeneral( { field, set } ) {
	const summaryControl = (
		<ToggleControl
			label={ __( 'Show in summary', 'alovio-calculator' ) }
			help={ __( 'List this field as a line item in the quote summary.', 'alovio-calculator' ) }
			checked={ !! field.showInSummary }
			onChange={ ( showInSummary ) => set( { showInSummary } ) }
		/>
	);

	if ( 'heading' === field.type ) {
		return <TextControl label={ __( 'Heading text', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />;
	}
	if ( 'html' === field.type ) {
		return (
			<>
				<TextControl label={ __( 'Label (admin only)', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<TextareaControl label={ __( 'Content (HTML allowed)', 'alovio-calculator' ) } value={ field.content || '' } onChange={ ( content ) => set( { content } ) } rows={ 5 } />
			</>
		);
	}
	if ( 'step' === field.type ) {
		return (
			<>
				<TextControl label={ __( 'Step title', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<TextareaControl label={ __( 'Description (optional)', 'alovio-calculator' ) } value={ field.description || '' } onChange={ ( description ) => set( { description } ) } rows={ 3 } />
				<p className="alcb-hint">{ __( 'Splits the form into a section. With the Wizard layout (Pro), each section becomes a step.', 'alovio-calculator' ) }</p>
			</>
		);
	}
	if ( 'formula' === field.type ) {
		return (
			<>
				<TextControl label={ __( 'Label', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				{ summaryControl }
				<p className="alcb-hint">{ __( 'Edit the expression in the Formula tab. The LAST formula in the field list is shown as the grand total.', 'alovio-calculator' ) }</p>
			</>
		);
	}

	return (
		<>
			<TextControl label={ __( 'Label', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
			<TextControl
				label={ __( 'Help text', 'alovio-calculator' ) }
				help={ __( 'Optional hint shown under the field.', 'alovio-calculator' ) }
				value={ field.help || '' }
				onChange={ ( help ) => set( { help } ) }
			/>
			{ HAS_PLACEHOLDER.indexOf( field.type ) !== -1 && (
				<TextControl label={ __( 'Placeholder', 'alovio-calculator' ) } value={ field.placeholder || '' } onChange={ ( placeholder ) => set( { placeholder } ) } />
			) }
			{ HAS_RANGE.indexOf( field.type ) !== -1 && (
				<div className="alcb-row4">
					<TextControl type="number" label={ __( 'Min', 'alovio-calculator' ) } value={ field.min ?? '' } onChange={ ( v ) => set( { min: num( v ) } ) } />
					<TextControl type="number" label={ __( 'Max', 'alovio-calculator' ) } value={ field.max ?? '' } onChange={ ( v ) => set( { max: num( v ) } ) } />
					<TextControl type="number" label={ __( 'Step', 'alovio-calculator' ) } value={ field.step ?? '' } onChange={ ( v ) => set( { step: num( v ) } ) } />
					<TextControl type="number" label={ __( 'Default', 'alovio-calculator' ) } value={ field.default ?? '' } onChange={ ( v ) => set( { default: num( v ) } ) } />
				</div>
			) }
			{ 'toggle' === field.type && (
				<>
					<TextControl
						type="number"
						step="0.01"
						label={ __( 'Price when on', 'alovio-calculator' ) }
						value={ 0 === field.price || field.price ? String( field.price ) : '' }
						onChange={ ( price ) => set( { price } ) }
					/>
					<ToggleControl label={ __( 'On by default', 'alovio-calculator' ) } checked={ !! field.default } onChange={ ( on ) => set( { default: on } ) } />
				</>
			) }
			{ summaryControl }
		</>
	);
}
