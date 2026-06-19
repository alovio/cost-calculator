import { useDispatch, useSelect } from '@wordpress/data';
import { ToggleControl, SelectControl, TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';


const OP_LABELS = {
	is: __( 'is', 'alovio-calculator' ),
	is_not: __( 'is not', 'alovio-calculator' ),
	contains: __( 'contains', 'alovio-calculator' ),
	gt: __( 'greater than', 'alovio-calculator' ),
	gte: __( 'is at least', 'alovio-calculator' ),
	lt: __( 'less than', 'alovio-calculator' ),
	lte: __( 'is at most', 'alovio-calculator' ),
	is_empty: __( 'is empty', 'alovio-calculator' ),
	is_not_empty: __( 'is not empty', 'alovio-calculator' ),
};

/** Operators that need no value (presence checks). */
const NO_VALUE_OPS = [ 'is_empty', 'is_not_empty' ];

/** Conditions reference input fields and formula results (e.g. the running total). */
const CONTROLLER_TYPES = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text', 'formula' ];
const NUMERIC = [ 'number', 'slider', 'quantity', 'formula' ];

function opsFor( controllerType ) {
	if ( NUMERIC.includes( controllerType ) ) {
		return [ 'is', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty' ];
	}
	if ( controllerType === 'select' || controllerType === 'radio' ) {
		return [ 'is', 'is_not', 'is_empty', 'is_not_empty' ];
	}
	if ( controllerType === 'checkbox_group' ) {
		return [ 'contains', 'is', 'is_not', 'is_empty', 'is_not_empty' ]; // contains = membership over comma-joined slugs
	}
	if ( controllerType === 'toggle' ) {
		return [ 'is' ];
	}
	return [ 'is', 'is_not', 'contains', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty' ]; // text
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
		return <TextControl label={ __( 'Value', 'alovio-calculator' ) } value={ value } onChange={ onChange } />;
	}
	if ( controller.type === 'toggle' ) {
		// Users never type the '1'/'' literals — spec §6.
		return (
			<SelectControl
				label={ __( 'Value', 'alovio-calculator' ) }
				value={ value }
				options={ [
					{ label: __( 'On', 'alovio-calculator' ), value: '1' },
					{ label: __( 'Off', 'alovio-calculator' ), value: '' },
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
				label={ __( 'Value', 'alovio-calculator' ) }
				value={ value }
				options={ opts.length ? opts : [ { label: __( '— save first to reference new options —', 'alovio-calculator' ), value: '' } ] }
				onChange={ onChange }
			/>
		);
	}
	if ( NUMERIC.includes( controller.type ) ) {
		return <TextControl type="number" label={ __( 'Value', 'alovio-calculator' ) } value={ value } onChange={ onChange } />;
	}
	return <TextControl label={ __( 'Value', 'alovio-calculator' ) } value={ value } onChange={ onChange } />;
}

function RuleRow( { rule, controllers, onChange, onRemove, canRemove } ) {
	const controller = controllers.find( ( f ) => f.id === rule.field ) || null;
	const ops = opsFor( controller ? controller.type : 'text' );

	return (
		<div className="alc-rule">
			<SelectControl
				label={ __( 'When field', 'alovio-calculator' ) }
				value={ rule.field }
				options={ controllers.map( ( f ) => ( { label: f.label || f.type, value: f.id } ) ) }
				onChange={ ( v ) => {
					const next = controllers.find( ( f ) => f.id === v ) || null;
					onChange( { field: v, operator: opsFor( next ? next.type : 'text' )[ 0 ], value: defaultValueFor( next ) } );
				} }
			/>
			<SelectControl
				label={ __( 'Operator', 'alovio-calculator' ) }
				value={ ops.includes( rule.operator ) ? rule.operator : ops[ 0 ] }
				options={ opOptions( ops ) }
				onChange={ ( operator ) =>
					onChange( NO_VALUE_OPS.includes( operator ) ? { ...rule, operator, value: '' } : { ...rule, operator } )
				}
			/>
			{ ! NO_VALUE_OPS.includes( rule.operator ) && (
				<ValueInput controller={ controller } value={ rule.value } onChange={ ( value ) => onChange( { ...rule, value } ) } />
			) }
			{ canRemove && (
				<Button isDestructive variant="link" onClick={ onRemove }>{ __( 'Remove rule', 'alovio-calculator' ) }</Button>
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
				label={ __( 'Conditional logic', 'alovio-calculator' ) }
				help={ __( 'Show or hide this field based on another field.', 'alovio-calculator' ) }
				checked={ enabled }
				onChange={ toggle }
			/>
			{ enabled && (
				<>
					<SelectControl
						label={ __( 'Match', 'alovio-calculator' ) }
						value={ field.conditionMatch || 'all' }
						options={ [
							{ label: __( 'All rules (AND)', 'alovio-calculator' ), value: 'all' },
							{ label: __( 'Any rule (OR)', 'alovio-calculator' ), value: 'any' },
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
					<Button variant="secondary" onClick={ addRule }>{ __( '+ Add rule', 'alovio-calculator' ) }</Button>
					<SelectControl
						label={ __( 'Then', 'alovio-calculator' ) }
						value={ field.conditionAction || 'show' }
						options={ [
							{ label: __( 'Show this field', 'alovio-calculator' ), value: 'show' },
							{ label: __( 'Hide this field', 'alovio-calculator' ), value: 'hide' },
							{ label: __( 'Require this field', 'alovio-calculator' ), value: 'require' },
						] }
						help={
							( field.conditionAction || 'show' ) === 'require'
								? __( 'The field stays visible and must be filled in before a quote can be requested.', 'alovio-calculator' )
								: undefined
						}
						onChange={ ( v ) => updateField( field.id, { conditionAction: v } ) }
					/>
				</>
			) }
		</div>
	);
}
