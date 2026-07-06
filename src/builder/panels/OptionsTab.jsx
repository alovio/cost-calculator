import { useState } from '@wordpress/element';
import { Button, TextControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Options editor v2 (spec §2.4): drag-reorder, label+price+image for ALL
 * choice types, per-option default. New options ship WITHOUT `value` — the
 * server assigns stable opt_ slugs on save (conditions reference them).
 */
export default function OptionsTab( { field, set } ) {
	const options = field.options || [];
	const single = 'select' === field.type || 'radio' === field.type;
	const [ drag, setDrag ] = useState( null );

	const update = ( i, patch ) => set( { options: options.map( ( o, idx ) => ( idx === i ? { ...o, ...patch } : o ) ) } );
	const add = () => set( { options: [ ...options, { label: '', price: 0 } ] } );
	const remove = ( i ) => set( { options: options.filter( ( _, idx ) => idx !== i ) } );
	const move = ( from, to ) => {
		if ( from === to || to < 0 || to >= options.length ) {
			return;
		}
		const next = [ ...options ];
		const [ m ] = next.splice( from, 1 );
		next.splice( to, 0, m );
		set( { options: next } );
	};
	const setDefault = ( i, on ) =>
		set( { options: options.map( ( o, idx ) => ( { ...o, default: idx === i ? on : single ? false : !! o.default } ) ) } );

	const pickImage = ( i ) => {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		const frame = window.wp.media( { title: __( 'Choose option image', 'alovio-calculator' ), library: { type: 'image' }, multiple: false } );
		frame.on( 'select', () => {
			const a = frame.state().get( 'selection' ).first().toJSON();
			update( i, { image: a.id, imageUrl: a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url } );
		} );
		frame.open();
	};

	return (
		<div className="alcb-options">
			{ options.map( ( o, i ) => (
				<div
					key={ o.value || `new-${ i }` }
					className={ 'alcb-opt-row' + ( drag === i ? ' is-dragging' : '' ) }
					onDragOver={ ( e ) => e.preventDefault() }
					onDrop={ () => {
						if ( null !== drag ) {
							move( drag, i );
						}
						setDrag( null );
					} }
				>
					<span className="alcb-opt-grip" draggable onDragStart={ () => setDrag( i ) } onDragEnd={ () => setDrag( null ) } title={ __( 'Drag to reorder', 'alovio-calculator' ) }>⠿</span>
					<TextControl label={ __( 'Label', 'alovio-calculator' ) } hideLabelFromVision placeholder={ __( 'Label', 'alovio-calculator' ) } value={ o.label || '' } onChange={ ( label ) => update( i, { label } ) } />
					<TextControl label={ __( 'Price', 'alovio-calculator' ) } hideLabelFromVision placeholder="0" type="number" step="0.01" value={ 0 === o.price || o.price ? String( o.price ) : '' } onChange={ ( price ) => update( i, { price } ) } />
					<span className="alcb-opt-img">
						{ o.image > 0 && o.imageUrl && <img src={ o.imageUrl } alt="" width="28" height="28" /> }
						<Button size="small" onClick={ () => pickImage( i ) }>{ o.image > 0 ? __( 'Change', 'alovio-calculator' ) : __( 'Image', 'alovio-calculator' ) }</Button>
						{ o.image > 0 && <Button size="small" isDestructive onClick={ () => update( i, { image: 0, imageUrl: '' } ) }>✕</Button> }
					</span>
					<label className="alcb-opt-default" title={ single ? __( 'Selected by default', 'alovio-calculator' ) : __( 'Checked by default', 'alovio-calculator' ) }>
						<input type={ single ? 'radio' : 'checkbox' } name={ `alcb-def-${ field.id }` } checked={ !! o.default } onChange={ ( e ) => setDefault( i, e.target.checked ) } />
						{ __( 'Default', 'alovio-calculator' ) }
					</label>
					<Button size="small" isDestructive disabled={ options.length < 2 } onClick={ () => remove( i ) } aria-label={ __( 'Remove option', 'alovio-calculator' ) }>✕</Button>
				</div>
			) ) }
			{ ! options.length && <Notice status="warning" isDismissible={ false }>{ __( 'Add at least one option.', 'alovio-calculator' ) }</Notice> }
			<div className="alcb-opt-foot">
				<Button variant="secondary" size="small" onClick={ add }>{ __( '+ Add option', 'alovio-calculator' ) }</Button>
				{ single && options.some( ( o ) => o.default ) && (
					<Button variant="link" onClick={ () => set( { options: options.map( ( o ) => ( { ...o, default: false } ) ) } ) }>
						{ __( 'Clear default', 'alovio-calculator' ) }
					</Button>
				) }
			</div>
			{ 'select' === field.type && (
				<p className="alcb-hint">{ __( 'Images are stored for dropdown options but only shown for Multiple choice and Checkboxes (a native dropdown cannot render images).', 'alovio-calculator' ) }</p>
			) }
		</div>
	);
}
