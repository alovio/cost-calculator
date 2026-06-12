import { useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const T = 'alovio-calculator';
const FALLBACK = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'text', 'heading', 'html', 'formula' ];

const LABELS = {
	number: __( 'Number', T ),
	slider: __( 'Slider', T ),
	select: __( 'Dropdown', T ),
	radio: __( 'Multiple choice', T ),
	checkbox_group: __( 'Checkboxes', T ),
	toggle: __( 'Toggle', T ),
	quantity: __( 'Quantity', T ),
	text: __( 'Text', T ),
	heading: __( 'Heading', T ),
	html: __( 'HTML content', T ),
	formula: __( 'Formula (calculated)', T ),
};

export default function FieldPalette() {
	const { addField } = useDispatch( STORE );
	const types = ( window.ALC_BUILDER && window.ALC_BUILDER.fieldTypes ) || FALLBACK;

	return (
		<div className="alc-palette" aria-label={ __( 'Field types', T ) }>
			<h3>{ __( 'Add field', T ) }</h3>
			{ types.map( ( type ) => (
				<Button key={ type } variant="secondary" className="alc-palette__btn" onClick={ () => addField( type ) }>
					{ LABELS[ type ] || type }
				</Button>
			) ) }
		</div>
	);
}
