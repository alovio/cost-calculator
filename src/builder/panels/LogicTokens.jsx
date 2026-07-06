import { useDispatch, useSelect } from '@wordpress/data';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';

const CONTROLLER_TYPES = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text', 'formula' ];
const NUMERIC = [ 'number', 'slider', 'quantity', 'formula' ];
const CHOICE = [ 'select', 'radio', 'checkbox_group' ];
const NO_VALUE_OPS = [ 'is_empty', 'is_not_empty' ];

const OP_LABELS = () => ( {
	is: __( 'is', 'alovio-calculator' ),
	is_not: __( 'is not', 'alovio-calculator' ),
	contains: __( 'contains', 'alovio-calculator' ),
	gt: __( 'greater than', 'alovio-calculator' ),
	gte: __( 'at least', 'alovio-calculator' ),
	lt: __( 'less than', 'alovio-calculator' ),
	lte: __( 'at most', 'alovio-calculator' ),
	is_empty: __( 'is empty', 'alovio-calculator' ),
	is_not_empty: __( 'is not empty', 'alovio-calculator' ),
} );

function opsFor( type ) {
	if ( NUMERIC.indexOf( type ) !== -1 ) {
		return [ 'is', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty' ];
	}
	if ( 'select' === type || 'radio' === type ) {
		return [ 'is', 'is_not', 'is_empty', 'is_not_empty' ];
	}
	if ( 'checkbox_group' === type ) {
		return [ 'contains', 'is', 'is_not', 'is_empty', 'is_not_empty' ];
	}
	if ( 'toggle' === type ) {
		return [ 'is' ];
	}
	return [ 'is', 'is_not', 'contains', 'gt', 'gte', 'lt', 'lte', 'is_empty', 'is_not_empty' ]; // text
}

function defaultValueFor( controller ) {
	if ( ! controller ) {
		return '';
	}
	if ( 'toggle' === controller.type ) {
		return '1';
	}
	if ( CHOICE.indexOf( controller.type ) !== -1 ) {
		const first = ( controller.options || [] ).find( ( o ) => o.value );
		return first ? first.value : '';
	}
	return '';
}

function makeRule( controller ) {
	return { field: controller ? controller.id : '', operator: opsFor( controller ? controller.type : 'text' )[ 0 ], value: defaultValueFor( controller ) };
}

/** A select disguised as a token chip (donor pattern). */
function TokSelect( { kind, value, valueLabel, options, onChange } ) {
	return (
		<span className={ `alcb-tok${ kind ? ` alcb-tok--${ kind }` : '' }` }>
			{ valueLabel } <span className="alcb-car"></span>
			<select value={ value } onChange={ ( e ) => onChange( e.target.value ) }>
				{ options.map( ( o ) => (
					<option key={ o.value } value={ o.value }>{ o.label }</option>
				) ) }
			</select>
		</span>
	);
}

function ValueToken( { controller, rule, onChange } ) {
	if ( controller && 'toggle' === controller.type ) {
		const opts = [ { label: __( 'On', 'alovio-calculator' ), value: '1' }, { label: __( 'Off', 'alovio-calculator' ), value: '' } ];
		return <TokSelect kind="val" value={ rule.value } valueLabel={ '1' === rule.value ? __( 'On', 'alovio-calculator' ) : __( 'Off', 'alovio-calculator' ) } options={ opts } onChange={ ( value ) => onChange( { ...rule, value } ) } />;
	}
	if ( controller && CHOICE.indexOf( controller.type ) !== -1 ) {
		const opts = ( controller.options || [] ).filter( ( o ) => o.value ).map( ( o ) => ( { label: o.label || o.value, value: o.value } ) );
		const current = opts.find( ( o ) => o.value === rule.value );
		return (
			<TokSelect
				kind="val"
				value={ rule.value }
				valueLabel={ current ? current.label : '—' }
				options={ opts.length ? opts : [ { label: __( '— save first to reference new options —', 'alovio-calculator' ), value: '' } ] }
				onChange={ ( value ) => onChange( { ...rule, value } ) }
			/>
		);
	}
	const numeric = controller && NUMERIC.indexOf( controller.type ) !== -1;
	return (
		<span className="alcb-tok alcb-tok--val">
			<input type={ numeric ? 'number' : 'text' } value={ rule.value } placeholder={ __( 'value…', 'alovio-calculator' ) } onChange={ ( e ) => onChange( { ...rule, value: e.target.value } ) } />
		</span>
	);
}

function RuleRow( { rule, controllers, onChange, onRemove, canRemove } ) {
	const controller = controllers.find( ( f ) => f.id === rule.field ) || null;
	const ops = opsFor( controller ? controller.type : 'text' );
	const operator = ops.indexOf( rule.operator ) !== -1 ? rule.operator : ops[ 0 ];
	const labels = OP_LABELS();

	return (
		<div className="alcb-sentence">
			<TokSelect
				kind="src"
				value={ rule.field }
				valueLabel={ controller ? controller.label || controller.type : '—' }
				options={ controllers.map( ( f ) => ( { label: f.label || f.type, value: f.id } ) ) }
				onChange={ ( v ) => onChange( makeRule( controllers.find( ( f ) => f.id === v ) || null ) ) }
			/>
			<TokSelect
				value={ operator }
				valueLabel={ labels[ operator ] || operator }
				options={ ops.map( ( o ) => ( { label: labels[ o ] || o, value: o } ) ) }
				onChange={ ( v ) => onChange( NO_VALUE_OPS.indexOf( v ) !== -1 ? { ...rule, operator: v, value: '' } : { ...rule, operator: v } ) }
			/>
			{ NO_VALUE_OPS.indexOf( operator ) === -1 && <ValueToken controller={ controller } rule={ rule } onChange={ onChange } /> }
			{ canRemove && <button className="alcb-rule-x" aria-label={ __( 'Remove rule', 'alovio-calculator' ) } onClick={ onRemove }>✕</button> }
		</div>
	);
}

export default function LogicTokens( { field } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { updateField } = useDispatch( STORE );
	const controllers = fields.filter( ( f ) => f.id !== field.id && CONTROLLER_TYPES.indexOf( f.type ) !== -1 );

	const rules = Array.isArray( field.conditions ) ? field.conditions : [];
	const enabled = rules.length > 0;

	const setRules = ( newRules ) =>
		updateField( field.id, {
			conditions: newRules,
			conditionMatch: field.conditionMatch || 'all',
			conditionAction: field.conditionAction || 'show',
		} );

	if ( ! controllers.length ) {
		return <p className="alcb-hint">{ __( 'Add another input field first — conditions react to other fields (or a formula total).', 'alovio-calculator' ) }</p>;
	}

	const toggle = ( on ) => ( on ? setRules( [ makeRule( controllers[ 0 ] ) ] ) : updateField( field.id, { conditions: [] } ) );

	return (
		<>
			<ToggleControl
				label={ __( 'Conditional logic', 'alovio-calculator' ) }
				help={ __( 'Show, hide, or require this field based on another field or the running total.', 'alovio-calculator' ) }
				checked={ enabled }
				onChange={ toggle }
			/>
			{ enabled && (
				<>
					<div className="alcb-rule-card">
						<div className="alcb-rule-when">{ __( 'When', 'alovio-calculator' ) }</div>
						{ rules.map( ( r, i ) => (
							<div key={ i }>
								{ i > 0 && (
									<div className="alcb-andor">
										<button className={ 'all' === ( field.conditionMatch || 'all' ) ? 'is-on' : '' } onClick={ () => updateField( field.id, { conditionMatch: 'all' } ) }>{ __( 'AND', 'alovio-calculator' ) }</button>
										<button className={ 'any' === field.conditionMatch ? 'is-on' : '' } onClick={ () => updateField( field.id, { conditionMatch: 'any' } ) }>{ __( 'OR', 'alovio-calculator' ) }</button>
									</div>
								) }
								<RuleRow
									rule={ r }
									controllers={ controllers }
									onChange={ ( nr ) => setRules( rules.map( ( x, idx ) => ( idx === i ? nr : x ) ) ) }
									onRemove={ () => setRules( rules.filter( ( _, idx ) => idx !== i ) ) }
									canRemove={ rules.length > 1 }
								/>
							</div>
						) ) }
						<button className="alcb-addrule" onClick={ () => setRules( [ ...rules, makeRule( controllers[ 0 ] ) ] ) }>＋ { __( 'Add condition', 'alovio-calculator' ) }</button>
					</div>
					<div className="alcb-then">
						<div className="alcb-then-lbl">{ __( 'Then', 'alovio-calculator' ) }</div>
						<div className="alcb-seg">
							{ [ [ 'show', __( 'Show', 'alovio-calculator' ) ], [ 'hide', __( 'Hide', 'alovio-calculator' ) ], [ 'require', __( 'Require', 'alovio-calculator' ) ] ].map( ( [ v, l ] ) => (
								<button key={ v } className={ ( field.conditionAction || 'show' ) === v ? 'is-on' : '' } onClick={ () => updateField( field.id, { conditionAction: v } ) }>{ l }</button>
							) ) }
						</div>
						{ 'require' === ( field.conditionAction || 'show' ) && (
							<p className="alcb-hint">{ __( 'The field stays visible and must be filled in before a quote can be requested.', 'alovio-calculator' ) }</p>
						) }
					</div>
				</>
			) }
		</>
	);
}
