import { useState } from '@wordpress/element';
import { Modal, Button, TextControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createCalculator } from './api';


export default function TemplatePicker( { onClose, onCreated } ) {
	const templates = ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.templates ) || [];
	const [ title, setTitle ] = useState( '' );
	const [ selected, setSelected ] = useState( '' ); // '' = blank
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const create = async () => {
		setBusy( true );
		setError( null );
		try {
			const body = { title: title || __( 'New calculator', 'alovio-calculator' ) };
			if ( selected ) {
				body.template = selected;
			}
			const result = await createCalculator( body );
			onCreated( result.id );
		} catch ( e ) {
			setError( __( 'Could not create the calculator. Please try again.', 'alovio-calculator' ) );
			setBusy( false );
		}
	};

	return (
		<Modal title={ __( 'New calculator', 'alovio-calculator' ) } onRequestClose={ onClose } className="alc-template-modal">
			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			<TextControl
				label={ __( 'Name', 'alovio-calculator' ) }
				value={ title }
				onChange={ setTitle }
				placeholder={ __( 'e.g. Cleaning quote', 'alovio-calculator' ) }
			/>
			<div className="alc-template-grid" role="radiogroup" aria-label={ __( 'Template', 'alovio-calculator' ) }>
				<TemplateCard
					title={ __( 'Blank calculator', 'alovio-calculator' ) }
					description={ __( 'Start from scratch.', 'alovio-calculator' ) }
					selected={ selected === '' }
					onSelect={ () => setSelected( '' ) }
				/>
				{ templates.map( ( t ) => (
					<TemplateCard
						key={ t.key }
						title={ t.title }
						description={ t.description }
						selected={ selected === t.key }
						onSelect={ () => setSelected( t.key ) }
					/>
				) ) }
			</div>
			<div className="alc-modal-actions">
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', 'alovio-calculator' ) }</Button>
				<Button variant="primary" onClick={ create } isBusy={ busy } disabled={ busy }>
					{ __( 'Create', 'alovio-calculator' ) }
				</Button>
			</div>
		</Modal>
	);
}

function TemplateCard( { title, description, selected, onSelect } ) {
	return (
		<button
			type="button"
			role="radio"
			aria-checked={ selected }
			className={ 'alc-template-card' + ( selected ? ' is-selected' : '' ) }
			onClick={ onSelect }
		>
			<strong>{ title }</strong>
			<span>{ description }</span>
		</button>
	);
}
