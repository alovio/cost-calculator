import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { ICONS } from './icons';
import FieldGeneral from './panels/FieldGeneral';
import LogicTokens from './panels/LogicTokens';
import OptionsTab from './panels/OptionsTab';
import FormulaTab from './panels/FormulaTab';
import RepeaterFields from './panels/RepeaterFields';
import CalcGeneral from './panels/CalcGeneral';
import CalcDesign from './panels/CalcDesign';
import CalcQuote from './panels/CalcQuote';
import ProPanel from './panels/ProPanel';

const CHOICE = [ 'select', 'radio', 'checkbox_group' ];

function Tabs( { tabs, current, onChange } ) {
	return (
		<div className="alcb-tabs">
			{ tabs.map( ( [ key, label ] ) => (
				<button key={ key } className={ `alcb-tab${ current === key ? ' is-on' : '' }` } onClick={ () => onChange( key ) }>{ label }</button>
			) ) }
		</div>
	);
}

export default function SettingsPanel( { proOpen } ) {
	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { selectField, updateField } = useDispatch( STORE );
	const [ tab, setTab ] = useState( 'general' );

	useEffect( () => {
		setTab( 'general' ); // reset when the context changes
	}, [ field && field.id, proOpen ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( proOpen ) {
		return <div className="alcb-settings"><div className="alcb-sp-body"><ProPanel /></div></div>;
	}

	if ( ! field ) {
		return (
			<div className="alcb-settings">
				<div className="alcb-sp-head">
					<div className="alcb-sp-title"><h3>{ __( 'Calculator settings', 'alovio-calculator' ) }</h3></div>
					<Tabs
						tabs={ [ [ 'general', __( 'General', 'alovio-calculator' ) ], [ 'design', __( 'Design', 'alovio-calculator' ) ], [ 'quote', __( 'Quote form', 'alovio-calculator' ) ] ] }
						current={ tab }
						onChange={ setTab }
					/>
				</div>
				<div className="alcb-sp-body">
					{ 'design' === tab ? <CalcDesign /> : 'quote' === tab ? <CalcQuote /> : <CalcGeneral /> }
				</div>
			</div>
		);
	}

	const isRepeater = 'repeater' === field.type;
	const third = ( CHOICE.indexOf( field.type ) !== -1 || isRepeater ) ? 'options' : 'formula' === field.type ? 'formula' : null;
	const set = ( patch ) => updateField( field.id, patch );
	const tabs = [
		[ 'general', __( 'General', 'alovio-calculator' ) ],
		[ 'logic', __( 'Logic', 'alovio-calculator' ) ],
		...( 'options' === third ? [ [ 'options', isRepeater ? __( 'Rows', 'alovio-calculator' ) : __( 'Options', 'alovio-calculator' ) ] ] : [] ),
		...( 'formula' === third ? [ [ 'formula', __( 'Formula', 'alovio-calculator' ) ] ] : [] ),
	];

	return (
		<div className="alcb-settings">
			<div className="alcb-sp-head">
				<div className="alcb-sp-title">
					<span className="alcb-ic">{ ICONS[ field.type ] || null }</span>
					<div>
						<h3>{ field.label || field.type }</h3>
						<small>{ field.type }</small>
					</div>
					<button className="alcb-gear" title={ __( 'Calculator settings', 'alovio-calculator' ) } aria-label={ __( 'Calculator settings', 'alovio-calculator' ) } onClick={ () => selectField( null ) }>⚙</button>
				</div>
				<Tabs tabs={ tabs } current={ tab } onChange={ setTab } />
			</div>
			<div className="alcb-sp-body">
				{ 'general' === tab && <FieldGeneral field={ field } set={ set } /> }
				{ 'logic' === tab && <LogicTokens field={ field } /> }
				{ 'options' === tab && 'options' === third && ( isRepeater ? <RepeaterFields field={ field } /> : <OptionsTab field={ field } set={ set } /> ) }
				{ 'formula' === tab && 'formula' === third && <FormulaTab field={ field } fields={ fields } set={ set } /> }
			</div>
			<div className="alcb-sp-foot">
				{ __( 'Changes render live', 'alovio-calculator' ) } · <span className="alcb-kbd">⌘S</span> { __( 'saves', 'alovio-calculator' ) }
			</div>
		</div>
	);
}
