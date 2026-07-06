import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TextControl, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';
import { REPEATER_CHILD_TYPES } from '../reducer';
import OptionsTab from './OptionsTab'; // Chunk-4 successor of the retired OptionsEditor (same { field, set } contract)

const TYPE_LABELS = {
	number: __( 'Number', 'alovio-calculator' ),
	slider: __( 'Slider', 'alovio-calculator' ),
	select: __( 'Dropdown', 'alovio-calculator' ),
	radio: __( 'Radio', 'alovio-calculator' ),
	checkbox_group: __( 'Checkbox group', 'alovio-calculator' ),
	toggle: __( 'Toggle', 'alovio-calculator' ),
	quantity: __( 'Quantity', 'alovio-calculator' ),
};
const HAS_RANGE = [ 'number', 'slider', 'quantity' ];
const HAS_OPTIONS = [ 'select', 'radio', 'checkbox_group' ];

function num( v ) {
	return v === '' || v === null || v === undefined ? null : v;
}

/** "Row fields" editor — lives in the Options-tab slot when a repeater is selected (spec §3.1). */
export default function RepeaterFields( { field } ) {
	const { addChildField, updateChildField, removeChildField, reorderChild, updateField } = useDispatch( STORE );
	const [ childId, setChildId ] = useState( null );
	const [ newType, setNewType ] = useState( 'number' );
	const children = field.fields || [];
	const child = children.find( ( c ) => c.id === childId ) || null;
	const set = ( patch ) => updateField( field.id, patch );
	const setChild = ( patch ) => updateChildField( field.id, child.id, patch );

	return (
		<div className="alc-repeater-panel">
			<span className="alcb-sec-label">{ __( 'Row fields', 'alovio-calculator' ) }</span>
			{ children.map( ( c, i ) => (
				<div className={ `alc-repeater-panel__row${ c.id === childId ? ' is-selected' : '' }` } key={ c.id }>
					<button type="button" className="alc-repeater-panel__pick" onClick={ () => setChildId( c.id ) }>
						{ c.label || c.id } <em>({ TYPE_LABELS[ c.type ] })</em>
					</button>
					<Button size="small" disabled={ i === 0 } onClick={ () => reorderChild( field.id, i, i - 1 ) } aria-label={ __( 'Move up', 'alovio-calculator' ) }>↑</Button>
					<Button size="small" disabled={ i === children.length - 1 } onClick={ () => reorderChild( field.id, i, i + 1 ) } aria-label={ __( 'Move down', 'alovio-calculator' ) }>↓</Button>
					<Button size="small" isDestructive onClick={ () => { removeChildField( field.id, c.id ); if ( childId === c.id ) { setChildId( null ); } } } aria-label={ __( 'Remove row field', 'alovio-calculator' ) }>✕</Button>
				</div>
			) ) }
			<div className="alc-repeater-panel__add">
				<SelectControl
					label={ __( 'Add row field', 'alovio-calculator' ) }
					hideLabelFromVision
					value={ newType }
					options={ REPEATER_CHILD_TYPES.map( ( t ) => ( { value: t, label: TYPE_LABELS[ t ] } ) ) }
					onChange={ setNewType }
				/>
				<Button variant="secondary" size="small" onClick={ () => addChildField( field.id, newType ) }>
					{ __( '+ Add', 'alovio-calculator' ) }
				</Button>
			</div>

			{ child && (
				<div className="alc-repeater-panel__editor">
					<TextControl label={ __( 'Label', 'alovio-calculator' ) } value={ child.label || '' } onChange={ ( label ) => setChild( { label } ) } />
					{ HAS_RANGE.includes( child.type ) && (
						<div className="alcb-row4">
							<TextControl type="number" label={ __( 'Min', 'alovio-calculator' ) } value={ child.min ?? '' } onChange={ ( v ) => setChild( { min: num( v ) } ) } />
							<TextControl type="number" label={ __( 'Max', 'alovio-calculator' ) } value={ child.max ?? '' } onChange={ ( v ) => setChild( { max: num( v ) } ) } />
							<TextControl type="number" label={ __( 'Step', 'alovio-calculator' ) } value={ child.step ?? '' } onChange={ ( v ) => setChild( { step: num( v ) } ) } />
							<TextControl type="number" label={ __( 'Default', 'alovio-calculator' ) } value={ child.default ?? '' } onChange={ ( v ) => setChild( { default: num( v ) } ) } />
						</div>
					) }
					{ child.type === 'toggle' && (
						<>
							<TextControl type="number" step="0.01" label={ __( 'Price when on', 'alovio-calculator' ) } value={ child.price === 0 || child.price ? String( child.price ) : '' } onChange={ ( price ) => setChild( { price } ) } />
							<ToggleControl label={ __( 'On by default', 'alovio-calculator' ) } checked={ !! child.default } onChange={ ( on ) => setChild( { default: on } ) } />
						</>
					) }
					{ HAS_OPTIONS.includes( child.type ) && (
						<>
							<OptionsTab field={ child } set={ setChild } />
							<p className="alcb-hint">{ __( 'Option images are stored but never shown inside repeater rows.', 'alovio-calculator' ) }</p>
						</>
					) }
				</div>
			) }

			<span className="alcb-sec-label">{ __( 'Rows', 'alovio-calculator' ) }</span>
			<div className="alcb-row4">
				<TextControl type="number" label={ __( 'Min rows', 'alovio-calculator' ) } value={ field.minRows ?? 1 } onChange={ ( v ) => set( { minRows: num( v ) } ) } />
				<TextControl type="number" label={ __( 'Max rows', 'alovio-calculator' ) } value={ field.maxRows ?? 10 } onChange={ ( v ) => set( { maxRows: num( v ) } ) } />
			</div>
			<TextControl label={ __( 'Row label', 'alovio-calculator' ) } help={ __( 'Use {n} for the row number, e.g. "Room {n}".', 'alovio-calculator' ) } value={ field.rowLabel || '' } onChange={ ( rowLabel ) => set( { rowLabel } ) } />
			<TextControl label={ __( 'Add button label', 'alovio-calculator' ) } value={ field.addLabel || '' } onChange={ ( addLabel ) => set( { addLabel } ) } />
			<TextControl
				label={ __( 'Row expression', 'alovio-calculator' ) }
				help={ __( 'Optional. May reference this repeater’s row fields only, e.g. {area} * {rate}. Leave empty to sum option/toggle prices.', 'alovio-calculator' ) }
				value={ field.rowExpression || '' }
				onChange={ ( rowExpression ) => set( { rowExpression } ) }
			/>
		</div>
	);
}
