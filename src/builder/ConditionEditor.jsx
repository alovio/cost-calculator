import { useDispatch, useSelect } from '@wordpress/data';
import { ToggleControl, SelectControl, TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const T = 'alovio-calculator';

const OP_LABELS = {
	is: __( 'is', T ),
	is_not: __( 'is not', T ),
	contains: __( 'contains', T ),
	gt: __( 'greater than', T ),
	lt: __( 'less than', T ),
};

/** Spec §6: conditions reference INPUT fields only — never formula/heading/html. */
const CONTROLLER_TYPES = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text' ];
const NUMERIC = [ 'number', 'slider', 'quantity' ];

function opsFor( controllerType ) {
	if ( NUMERIC.includes( controllerType ) ) {
		return [ 'is', 'gt', 'lt' ];
	}
	if ( controllerType === 'select' || controllerType === 'radio' ) {
		return [ 'is', 'is_not' ];
	}
	if ( controllerType === 'checkbox_group' ) {
		return [ 'contains', 'is', 'is_not' ]; // contains = membership over comma-joined slugs
	}
	if ( controllerType === 'toggle' ) {
		return [ 'is' ];
	}
	return [ 'is', 'is_not', 'contains', 'gt', 'lt' ]; // text
}

function opOptions( ops ) {
	return ops.map( ( o ) => ( { label: OP_LABELS[ o ] || o, value: o } ) );
}

function defaultValueFor( controller ) {
	if ( ! controller ) {
		return '';
	}
	if ( controller.type === 'toggle' ) {
		return '1';
	}
	if ( [ 'select', 'radio', 'checkbox_group' ].includes( controller.type ) ) {
		const first = ( controller.options || [] )[ 0 ];
		return first && first.value ? first.value : '';
	}
	return '';
}

function ValueInput( { controller, value, onChange } ) {
	if ( ! controller ) {
		return <TextControl label={ __( 'Value', T ) } value={ value } onChange={ onChange } />;
	}
	if ( controller.type === 'toggle' ) {
		// Users never type the '1'/'' literals — spec §6.
		return (
			<SelectControl
				label={ __( 'Value', T ) }
				value={ value }
				options={ [
					{ label: __( 'On', T ), value: '1' },
					{ label: __( 'Off', T ), value: '' },
				] }
				onChange={ onChange }
			/>
		);
	}
	if ( [ 'select', 'radio', 'checkbox_group' ].includes( controller.type ) ) {
		// Labels for humans; stable opt_ slugs stored. Unsaved options (no slug yet) can't be referenced.
		const opts = ( controller.options || [] )
			.filter( ( o ) => o.value )
			.map( ( o ) => ( { label: o.label || o.value, value: o.value } ) );
		return (
			<SelectControl
				label={ __( 'Value', T ) }
				value={ value }
				options={ opts.length ? opts : [ { label: __( '— save first to reference new options —', T ), value: '' } ] }
				onChange={ onChange }
			/>
		);
	}
	if ( NUMERIC.includes( controller.type ) ) {
		return <TextControl type="number" label={ __( 'Value', T ) } value={ value } onChange={ onChange } />;
	}
	return <TextControl label={ __( 'Value', T ) } value={ value } onChange={ onChange } />;
}

function RuleRow( { rule, controllers, onChange, onRemove, canRemove } ) {
	const controller = controllers.find( ( f ) => f.id === rule.field ) || null;
	const ops = opsFor( controller ? controller.type : 'text' );

	return (
		<div className="alc-rule">
			<SelectControl
				label={ __( 'When field', T ) }
				value={ rule.field }
				options={ controllers.map( ( f ) => ( { label: f.label || f.type, value: f.id } ) ) }
				onChange={ ( v ) => {
					const next = controllers.find( ( f ) => f.id === v ) || null;
					onChange( { field: v, operator: opsFor( next ? next.type : 'text' )[ 0 ], value: defaultValueFor( next ) } );
				} }
			/>
			<SelectControl
				label={ __( 'Operator', T ) }
				value={ ops.includes( rule.operator ) ? rule.operator : ops[ 0 ] }
				options={ opOptions( ops ) }
				onChange={ ( operator ) => onChange( { ...rule, operator } ) }
			/>
			<ValueInput controller={ controller } value={ rule.value } onChange={ ( value ) => onChange( { ...rule, value } ) } />
			{ canRemove && (
				<Button isDestructive variant="link" onClick={ onRemove }>{ __( 'Remove rule', T ) }</Button>
			) }
		</div>
	);
}

export default function ConditionEditor( { field } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { updateField } = useDispatch( STORE );
	const controllers = fields.filter( ( f ) => f.id !== field.id && CONTROLLER_TYPES.includes( f.type ) );

	const rules = Array.isArray( field.conditions ) ? field.conditions : [];
	const enabled = rules.length > 0;

	const makeRule = () => {
		const first = controllers[ 0 ] || null;
		return {
			field: first ? first.id : '',
			operator: opsFor( first ? first.type : 'text' )[ 0 ],
			value: defaultValueFor( first ),
		};
	};

	const setRules = ( newRules ) =>
		updateField( field.id, {
			conditions: newRules,
			conditionMatch: field.conditionMatch || 'all',
			conditionAction: field.conditionAction || 'show',
		} );

	const toggle = ( on ) => ( on ? setRules( [ makeRule() ] ) : updateField( field.id, { conditions: [] } ) );

	const updateRule = ( i, newRule ) => setRules( rules.map( ( r, idx ) => ( idx === i ? newRule : r ) ) );
	const addRule = () => setRules( [ ...rules, makeRule() ] );
	const removeRule = ( i ) => setRules( rules.filter( ( _, idx ) => idx !== i ) );

	if ( ! controllers.length ) {
		return null; // Nothing can drive a condition yet.
	}

	return (
		<div className="alc-condition">
			<ToggleControl
				label={ __( 'Conditional logic', T ) }
				help={ __( 'Show or hide this field based on another field.', T ) }
				checked={ enabled }
				onChange={ toggle }
			/>
			{ enabled && (
				<>
					<SelectControl
						label={ __( 'Match', T ) }
						value={ field.conditionMatch || 'all' }
						options={ [
							{ label: __( 'All rules (AND)', T ), value: 'all' },
							{ label: __( 'Any rule (OR)', T ), value: 'any' },
						] }
						onChange={ ( v ) => updateField( field.id, { conditionMatch: v } ) }
					/>
					{ rules.map( ( r, i ) => (
						<RuleRow
							key={ i }
							rule={ r }
							controllers={ controllers }
							onChange={ ( nr ) => updateRule( i, nr ) }
							onRemove={ () => removeRule( i ) }
							canRemove={ rules.length > 1 }
						/>
					) ) }
					<Button variant="secondary" onClick={ addRule }>{ __( '+ Add rule', T ) }</Button>
					<SelectControl
						label={ __( 'Then', T ) }
						value={ field.conditionAction || 'show' }
						options={ [
							{ label: __( 'Show this field', T ), value: 'show' },
							{ label: __( 'Hide this field', T ), value: 'hide' },
						] }
						onChange={ ( v ) => updateField( field.id, { conditionAction: v } ) }
					/>
				</>
			) }
		</div>
	);
}
