import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const T = 'alovio-calculator';

export default function Canvas() {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const selectedId = useSelect( ( select ) => select( STORE ).getSelectedId(), [] );
	const { selectField, removeField, reorder, duplicateField } = useDispatch( STORE );
	const [ dragIndex, setDragIndex ] = useState( null );
	const [ overIndex, setOverIndex ] = useState( null );

	if ( ! fields.length ) {
		return <div className="alc-canvas alc-canvas--empty">{ __( 'Add a field from the left to get started.', T ) }</div>;
	}

	const onDrop = ( to ) => {
		if ( dragIndex !== null && dragIndex !== to ) {
			reorder( dragIndex, to );
		}
		setDragIndex( null );
		setOverIndex( null );
	};

	return (
		<ul className="alc-canvas">
			{ fields.map( ( f, i ) => {
				let cls = f.id === selectedId ? 'is-selected' : '';
				if ( i === overIndex && dragIndex !== null && dragIndex !== i ) {
					cls += ' is-drop-target';
				}
				if ( i === dragIndex ) {
					cls += ' is-dragging';
				}
				return (
					<li
						key={ f.id }
						className={ cls.trim() }
						draggable
						role="button"
						tabIndex={ 0 }
						aria-current={ f.id === selectedId }
						onClick={ () => selectField( f.id ) }
						onKeyDown={ ( e ) => { if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); selectField( f.id ); } } }
						onDragStart={ () => setDragIndex( i ) }
						onDragOver={ ( e ) => { e.preventDefault(); setOverIndex( i ); } }
						onDrop={ () => onDrop( i ) }
						onDragEnd={ () => { setDragIndex( null ); setOverIndex( null ); } }
					>
						<span className="alc-canvas__grip" aria-hidden="true" title={ __( 'Drag to reorder', T ) }>⠿</span>
						<span className="alc-canvas__label">
							{ f.label || f.type } <em>({ f.type })</em>
						</span>
						<span className="alc-canvas__ops">
							<Button size="small" disabled={ i === 0 } onClick={ ( e ) => { e.stopPropagation(); reorder( i, i - 1 ); } } aria-label={ __( 'Move up', T ) }>↑</Button>
							<Button size="small" disabled={ i === fields.length - 1 } onClick={ ( e ) => { e.stopPropagation(); reorder( i, i + 1 ); } } aria-label={ __( 'Move down', T ) }>↓</Button>
							<Button size="small" onClick={ ( e ) => { e.stopPropagation(); duplicateField( f.id ); } } aria-label={ __( 'Duplicate', T ) }>⧉</Button>
							<Button size="small" isDestructive onClick={ ( e ) => { e.stopPropagation(); removeField( f.id ); } } aria-label={ __( 'Delete', T ) }>✕</Button>
						</span>
					</li>
				);
			} ) }
		</ul>
	);
}
