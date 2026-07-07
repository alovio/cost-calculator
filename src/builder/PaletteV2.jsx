import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { ICONS } from './icons';
import { TYPE_MIME } from './CanvasOverlay';

const LABELS = () => ( {
	number: __( 'Number', 'alovio-calculator' ),
	slider: __( 'Slider', 'alovio-calculator' ),
	quantity: __( 'Quantity', 'alovio-calculator' ),
	text: __( 'Text', 'alovio-calculator' ),
	textarea: __( 'Text area', 'alovio-calculator' ),
	date: __( 'Date', 'alovio-calculator' ),
	email: __( 'Email', 'alovio-calculator' ),
	phone: __( 'Phone', 'alovio-calculator' ),
	url: __( 'Website', 'alovio-calculator' ),
	select: __( 'Dropdown', 'alovio-calculator' ),
	radio: __( 'Multiple choice', 'alovio-calculator' ),
	checkbox_group: __( 'Checkboxes', 'alovio-calculator' ),
	toggle: __( 'Toggle', 'alovio-calculator' ),
	heading: __( 'Heading', 'alovio-calculator' ),
	html: __( 'HTML content', 'alovio-calculator' ),
	step: __( 'Step / Section', 'alovio-calculator' ),
	formula: __( 'Formula', 'alovio-calculator' ),
	repeater: __( 'Repeater', 'alovio-calculator' ),
} );

const CATEGORIES = () => [
	{ key: 'inputs', label: __( 'Inputs', 'alovio-calculator' ), types: [ 'number', 'slider', 'quantity', 'text', 'textarea', 'date', 'email', 'phone', 'url' ] },
	{ key: 'choices', label: __( 'Choices', 'alovio-calculator' ), types: [ 'select', 'radio', 'checkbox_group', 'toggle' ] },
	{ key: 'content', label: __( 'Content', 'alovio-calculator' ), types: [ 'heading', 'html', 'step' ] },
	{ key: 'math', label: __( 'Math', 'alovio-calculator' ), types: [ 'formula', 'repeater' ] },
];

export default function PaletteV2() {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const selectedId = useSelect( ( select ) => select( STORE ).getSelectedId(), [] );
	const { insertAt, insertFields } = useDispatch( STORE );
	const available = ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.fieldTypes ) || [];
	const templates = ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.templates ) || [];
	const labels = LABELS();

	/** Plain click inserts AFTER the selected field (end when none) — spec §2.3. */
	const insertIndex = () => {
		const i = fields.findIndex( ( f ) => f.id === selectedId );
		return -1 === i ? fields.length : i + 1;
	};

	return (
		<div className="alcb-palette" data-tour="palette" aria-label={ __( 'Field types', 'alovio-calculator' ) }>
			{ CATEGORIES().map( ( cat ) => {
				const types = cat.types.filter( ( t ) => available.indexOf( t ) !== -1 );
				if ( ! types.length ) {
					return null; // Track-B types appear here automatically in chunks 5–7
				}
				return (
					<div key={ cat.key }>
						<span className="alcb-sec-label">{ cat.label }</span>
						<div className="alcb-ptypes">
							{ types.map( ( type ) => (
								<button
									key={ type }
									className="alcb-ptype"
									draggable
									onDragStart={ ( e ) => {
										e.dataTransfer.setData( TYPE_MIME, type );
										e.dataTransfer.effectAllowed = 'copy';
									} }
									onClick={ () => insertAt( type, insertIndex() ) }
								>
									<span className="alcb-ic">{ ICONS[ type ] || null }</span>
									{ labels[ type ] || type }
								</button>
							) ) }
						</div>
					</div>
				);
			} ) }

			{ templates.length > 0 && (
				<div>
					<span className="alcb-sec-label">{ __( 'Templates', 'alovio-calculator' ) }</span>
					<div className="alcb-tpl">
						<p>{ __( 'Insert a pre-built field set into this calculator.', 'alovio-calculator' ) }</p>
						<div className="alcb-chips">
							{ templates.map( ( tpl ) => (
								<button key={ tpl.key } className="alcb-chip" title={ tpl.description } onClick={ () => insertFields( tpl.fields || [], insertIndex() ) }>
									{ tpl.title }
								</button>
							) ) }
						</div>
					</div>
				</div>
			) }
		</div>
	);
}
