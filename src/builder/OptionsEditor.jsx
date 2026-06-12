import { Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const T = 'alovio-calculator';

/**
 * Per-option editor for select/radio/checkbox_group: label + price (+ image on radio).
 * New options ship WITHOUT a `value` — the server assigns stable `opt_` slugs on
 * save; existing `value` keys are preserved untouched (conditions reference them).
 */
export default function OptionsEditor( { field, set } ) {
	const options = field.options || [];
	const withImages = field.type === 'radio';

	const update = ( i, patch ) => set( { options: options.map( ( o, idx ) => ( idx === i ? { ...o, ...patch } : o ) ) } );
	const add = () => set( { options: [ ...options, { label: '', price: 0 } ] } );
	const remove = ( i ) => set( { options: options.filter( ( _, idx ) => idx !== i ) } );

	const pickImage = ( i ) => {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		const frame = window.wp.media( {
			title: __( 'Choose option image', T ),
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			update( i, { image: attachment.id, imageUrl: ( attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url ) } );
		} );
		frame.open();
	};

	return (
		<div className="alc-options">
			<span className="alc-options__title">{ __( 'Options', T ) }</span>
			{ options.map( ( o, i ) => (
				<div className="alc-options__row" key={ o.value || `new-${ i }` }>
					<TextControl
						label={ __( 'Label', T ) }
						hideLabelFromVision
						placeholder={ __( 'Label', T ) }
						value={ o.label || '' }
						onChange={ ( label ) => update( i, { label } ) }
					/>
					<TextControl
						label={ __( 'Price', T ) }
						hideLabelFromVision
						placeholder="0"
						type="number"
						step="0.01"
						value={ o.price === 0 || o.price ? String( o.price ) : '' }
						onChange={ ( price ) => update( i, { price } ) }
					/>
					{ withImages && (
						<span className="alc-options__image">
							{ o.image > 0 && o.imageUrl && <img src={ o.imageUrl } alt="" width="32" height="32" /> }
							<Button size="small" onClick={ () => pickImage( i ) }>
								{ o.image > 0 ? __( 'Change', T ) : __( 'Image', T ) }
							</Button>
							{ o.image > 0 && (
								<Button size="small" isDestructive onClick={ () => update( i, { image: 0, imageUrl: '' } ) }>✕</Button>
							) }
						</span>
					) }
					<Button size="small" isDestructive disabled={ options.length < 2 } onClick={ () => remove( i ) } aria-label={ __( 'Remove option', T ) }>✕</Button>
				</div>
			) ) }
			<Button variant="secondary" size="small" onClick={ add }>{ __( '+ Add option', T ) }</Button>
		</div>
	);
}
