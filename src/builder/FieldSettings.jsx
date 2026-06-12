import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, ToggleControl, TextareaControl, SelectControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import ConditionEditor from './ConditionEditor';
import OptionsEditor from './OptionsEditor';
import FormulaPanel from './FormulaPanel';

const T = 'alovio-calculator';
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
		return <div className="alc-settings alc-settings--empty">{ __( 'Select a field to edit its settings.', T ) }</div>;
	}

	const set = ( patch ) => updateField( field.id, patch );

	const summaryControl = (
		<ToggleControl
			label={ __( 'Show in summary', T ) }
			help={ __( 'List this field as a line item in the quote summary.', T ) }
			checked={ !! field.showInSummary }
			onChange={ ( showInSummary ) => set( { showInSummary } ) }
		/>
	);

	if ( field.type === 'heading' ) {
		return (
			<div className="alc-settings">
				<TextControl label={ __( 'Heading text', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<ConditionEditor field={ field } />
			</div>
		);
	}

	if ( field.type === 'html' ) {
		return (
			<div className="alc-settings">
				<TextControl label={ __( 'Label (admin only)', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<TextareaControl label={ __( 'Content (HTML allowed)', T ) } value={ field.content || '' } onChange={ ( content ) => set( { content } ) } rows={ 5 } />
				<ConditionEditor field={ field } />
			</div>
		);
	}

	if ( field.type === 'formula' ) {
		return (
			<div className="alc-settings">
				<TextControl label={ __( 'Label', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<FormulaPanel field={ field } fields={ fields } set={ set } />
				{ summaryControl }
				<p className="alc-hint">{ __( 'The LAST formula in the field list is shown as the grand total.', T ) }</p>
				<ConditionEditor field={ field } />
			</div>
		);
	}

	const optionsEmpty = HAS_OPTIONS.includes( field.type ) && ! ( field.options || [] ).length;

	return (
		<div className="alc-settings">
			<TextControl label={ __( 'Label', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />

			{ field.type === 'text' && (
				<TextControl label={ __( 'Placeholder', T ) } value={ field.placeholder || '' } onChange={ ( placeholder ) => set( { placeholder } ) } />
			) }

			{ HAS_RANGE.includes( field.type ) && (
				<div className="alc-row4">
					<TextControl type="number" label={ __( 'Min', T ) } value={ field.min ?? '' } onChange={ ( v ) => set( { min: num( v ) } ) } />
					<TextControl type="number" label={ __( 'Max', T ) } value={ field.max ?? '' } onChange={ ( v ) => set( { max: num( v ) } ) } />
					<TextControl type="number" label={ __( 'Step', T ) } value={ field.step ?? '' } onChange={ ( v ) => set( { step: num( v ) } ) } />
					<TextControl type="number" label={ __( 'Default', T ) } value={ field.default ?? '' } onChange={ ( v ) => set( { default: num( v ) } ) } />
				</div>
			) }

			{ field.type === 'toggle' && (
				<>
					<TextControl
						type="number"
						step="0.01"
						label={ __( 'Price when on', T ) }
						value={ field.price === 0 || field.price ? String( field.price ) : '' }
						onChange={ ( price ) => set( { price } ) }
					/>
					<ToggleControl label={ __( 'On by default', T ) } checked={ !! field.default } onChange={ ( on ) => set( { default: on } ) } />
				</>
			) }

			{ HAS_OPTIONS.includes( field.type ) && <OptionsEditor field={ field } set={ set } /> }
			{ optionsEmpty && (
				<Notice status="warning" isDismissible={ false }>{ __( 'Add at least one option.', T ) }</Notice>
			) }

			{ summaryControl }

			<ConditionEditor field={ field } />
		</div>
	);
}
