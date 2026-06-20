import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, ToggleControl, TextareaControl, SelectControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import ConditionEditor from './ConditionEditor';
import OptionsEditor from './OptionsEditor';
import FormulaPanel from './FormulaPanel';

const HAS_OPTIONS = [ 'select', 'radio', 'checkbox_group' ];
const HAS_RANGE = [ 'number', 'slider', 'quantity' ];

function num( v ) {
	return v === '' || v === null || v === undefined ? null : v;
}

export default function FieldSettings() {
	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { updateField } = useDispatch( STORE );

	if ( ! field ) {
		return <div className="alc-settings alc-settings--empty">{ __( 'Select a field to edit its settings.', 'alovio-calculator' ) }</div>;
	}

	const set = ( patch ) => updateField( field.id, patch );

	const summaryControl = (
		<ToggleControl
			label={ __( 'Show in summary', 'alovio-calculator' ) }
			help={ __( 'List this field as a line item in the quote summary.', 'alovio-calculator' ) }
			checked={ !! field.showInSummary }
			onChange={ ( showInSummary ) => set( { showInSummary } ) }
		/>
	);

	if ( field.type === 'heading' ) {
		return (
			<div className="alc-settings">
				<TextControl label={ __( 'Heading text', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<ConditionEditor field={ field } />
			</div>
		);
	}

	if ( field.type === 'html' ) {
		return (
			<div className="alc-settings">
				<TextControl label={ __( 'Label (admin only)', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<TextareaControl label={ __( 'Content (HTML allowed)', 'alovio-calculator' ) } value={ field.content || '' } onChange={ ( content ) => set( { content } ) } rows={ 5 } />
				<ConditionEditor field={ field } />
			</div>
		);
	}

	if ( field.type === 'step' ) {
		return (
			<div className="alc-settings">
				<TextControl label={ __( 'Step title', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<TextareaControl label={ __( 'Description (optional)', 'alovio-calculator' ) } value={ field.description || '' } onChange={ ( description ) => set( { description } ) } rows={ 3 } />
				<p className="alc-hint">{ __( 'Splits the form into a section. With the Wizard layout (Pro), each section becomes a step.', 'alovio-calculator' ) }</p>
				<ConditionEditor field={ field } />
			</div>
		);
	}

	if ( field.type === 'formula' ) {
		return (
			<div className="alc-settings">
				<TextControl label={ __( 'Label', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<FormulaPanel field={ field } fields={ fields } set={ set } />
				{ summaryControl }
				<p className="alc-hint">{ __( 'The LAST formula in the field list is shown as the grand total.', 'alovio-calculator' ) }</p>
				<ConditionEditor field={ field } />
			</div>
		);
	}

	const optionsEmpty = HAS_OPTIONS.includes( field.type ) && ! ( field.options || [] ).length;

	return (
		<div className="alc-settings">
			<TextControl label={ __( 'Label', 'alovio-calculator' ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />

			{ field.type === 'text' && (
				<TextControl label={ __( 'Placeholder', 'alovio-calculator' ) } value={ field.placeholder || '' } onChange={ ( placeholder ) => set( { placeholder } ) } />
			) }

			{ HAS_RANGE.includes( field.type ) && (
				<div className="alc-row4">
					<TextControl type="number" label={ __( 'Min', 'alovio-calculator' ) } value={ field.min ?? '' } onChange={ ( v ) => set( { min: num( v ) } ) } />
					<TextControl type="number" label={ __( 'Max', 'alovio-calculator' ) } value={ field.max ?? '' } onChange={ ( v ) => set( { max: num( v ) } ) } />
					<TextControl type="number" label={ __( 'Step', 'alovio-calculator' ) } value={ field.step ?? '' } onChange={ ( v ) => set( { step: num( v ) } ) } />
					<TextControl type="number" label={ __( 'Default', 'alovio-calculator' ) } value={ field.default ?? '' } onChange={ ( v ) => set( { default: num( v ) } ) } />
				</div>
			) }

			{ field.type === 'toggle' && (
				<>
					<TextControl
						type="number"
						step="0.01"
						label={ __( 'Price when on', 'alovio-calculator' ) }
						value={ field.price === 0 || field.price ? String( field.price ) : '' }
						onChange={ ( price ) => set( { price } ) }
					/>
					<ToggleControl label={ __( 'On by default', 'alovio-calculator' ) } checked={ !! field.default } onChange={ ( on ) => set( { default: on } ) } />
				</>
			) }

			{ HAS_OPTIONS.includes( field.type ) && <OptionsEditor field={ field } set={ set } /> }
			{ optionsEmpty && (
				<Notice status="warning" isDismissible={ false }>{ __( 'Add at least one option.', 'alovio-calculator' ) }</Notice>
			) }

			{ summaryControl }

			<ConditionEditor field={ field } />
		</div>
	);
}
