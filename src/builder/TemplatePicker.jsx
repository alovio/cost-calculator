import { useState } from '@wordpress/element';
import { Modal, Button, TextControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { createCalculator } from './api';

const T = 'alovio-calculator';

export default function TemplatePicker( { onClose, onCreated } ) {
	const templates = ( window.ALC_BUILDER && window.ALC_BUILDER.templates ) || [];
	const [ title, setTitle ] = useState( '' );
	const [ selected, setSelected ] = useState( '' ); // '' = blank
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const create = async () => {
		setBusy( true );
		setError( null );
		try {
			const body = { title: title || __( 'New calculator', T ) };
			if ( selected ) {
				body.template = selected;
			}
			const result = await createCalculator( body );
			onCreated( result.id );
		} catch ( e ) {
			setError( __( 'Could not create the calculator. Please try again.', T ) );
			setBusy( false );
		}
	};

	return (
		<Modal title={ __( 'New calculator', T ) } onRequestClose={ onClose } className="alc-template-modal">
			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			<TextControl
				label={ __( 'Name', T ) }
				value={ title }
				onChange={ setTitle }
				placeholder={ __( 'e.g. Cleaning quote', T ) }
			/>
			<div className="alc-template-grid" role="radiogroup" aria-label={ __( 'Template', T ) }>
				<TemplateCard
					title={ __( 'Blank calculator', T ) }
					description={ __( 'Start from scratch.', T ) }
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
				<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', T ) }</Button>
				<Button variant="primary" onClick={ create } isBusy={ busy } disabled={ busy }>
					{ __( 'Create', T ) }
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
