import { useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const FALLBACK = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text', 'heading', 'html', 'formula', 'step' ];

const LABELS = {
	number: __( 'Number', 'alovio-calculator' ),
	slider: __( 'Slider', 'alovio-calculator' ),
	select: __( 'Dropdown', 'alovio-calculator' ),
	radio: __( 'Multiple choice', 'alovio-calculator' ),
	checkbox_group: __( 'Checkboxes', 'alovio-calculator' ),
	toggle: __( 'Toggle', 'alovio-calculator' ),
	quantity: __( 'Quantity', 'alovio-calculator' ),
	text: __( 'Text', 'alovio-calculator' ),
	heading: __( 'Heading', 'alovio-calculator' ),
	html: __( 'HTML content', 'alovio-calculator' ),
	formula: __( 'Formula (calculated)', 'alovio-calculator' ),
	step: __( 'Step / Section', 'alovio-calculator' ),
};

export default function FieldPalette() {
	const { addField } = useDispatch( STORE );
	const types = ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.fieldTypes ) || FALLBACK;

	return (
		<div className="alc-palette" aria-label={ __( 'Field types', 'alovio-calculator' ) }>
			<h3>{ __( 'Add field', 'alovio-calculator' ) }</h3>
			{ types.map( ( type ) => (
				<Button key={ type } variant="secondary" className="alc-palette__btn" onClick={ () => addField( type ) }>
					{ LABELS[ type ] || type }
				</Button>
			) ) }
		</div>
	);
}
