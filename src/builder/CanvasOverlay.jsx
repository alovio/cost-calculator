import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { describeCondition, conditionAction } from './describe';
import { validateExpression } from './formula-validation';

// Drag payload MIME keys. PaletteV2 (chunk 4) sets TYPE_MIME on its items;
// the drop side below already handles both.
export const REORDER_MIME = 'alovio-calc/reorder';
export const TYPE_MIME = 'alovio-calc/field-type';

const box = ( r ) => ( { top: r.top, left: r.left, width: r.width, height: r.height } );

export default function CanvasOverlay( { hostRef, scrollRef, renderTick } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const selectedId = useSelect( ( select ) => select( STORE ).getSelectedId(), [] );
	const { selectField, removeField, duplicateField, reorder, insertAt } = useDispatch( STORE );
	const [ rects, setRects ] = useState( {} );
	const [ hoverId, setHoverId ] = useState( null );
	const [ dragging, setDragging ] = useState( false );
	const [ insertLine, setInsertLine ] = useState( null ); // { index, y }

	const measure = useCallback( () => {
		const host = hostRef.current;
		if ( ! host ) {
			return;
		}
		const base = host.getBoundingClientRect();
		const next = {};
		host.querySelectorAll( '[data-alc-field]' ).forEach( ( el ) => {
			if ( el.hidden ) {
				return; // hidden by conditions — no box, no ops (spec §2.3)
			}
			const r = el.getBoundingClientRect();
			if ( ! r.width && ! r.height ) {
				return; // zero-size = hidden by a display:none ancestor (e.g. an inactive wizard step) — same treatment as el.hidden
			}
			next[ el.getAttribute( 'data-alc-field' ) ] = {
				top: r.top - base.top,
				left: r.left - base.left,
				width: r.width,
				height: r.height,
			};
		} );
		setRects( next );
	}, [ hostRef ] );

	useEffect( () => {
		measure();
	}, [ renderTick, fields, measure ] );

	useEffect( () => {
		const host = hostRef.current;
		const scroller = scrollRef.current;
		if ( ! host ) {
			return undefined;
		}
		const schedule = () => window.requestAnimationFrame( measure );
		const ro = new window.ResizeObserver( schedule );
		ro.observe( host );
		host.addEventListener( 'input', schedule ); // engine-driven visibility flips
		host.addEventListener( 'change', schedule );
		if ( scroller ) {
			scroller.addEventListener( 'scroll', schedule, { passive: true } );
		}
		window.addEventListener( 'resize', schedule );
		return () => {
			ro.disconnect();
			host.removeEventListener( 'input', schedule );
			host.removeEventListener( 'change', schedule );
			if ( scroller ) {
				scroller.removeEventListener( 'scroll', schedule );
			}
			window.removeEventListener( 'resize', schedule );
		};
	}, [ hostRef, scrollRef, measure ] );

	// Click-to-select + hover tracking on the sheet (host parent), so the
	// overlay toolbar keeps hover. Inputs stay interactive: no preventDefault.
	useEffect( () => {
		const host = hostRef.current;
		const sheet = host && host.parentElement;
		if ( ! sheet ) {
			return undefined;
		}
		const onClick = ( e ) => {
			if ( e.target.closest( '.alcb-ops' ) ) {
				return; // toolbar buttons handle themselves
			}
			const wrap = e.target.closest( '[data-alc-field]' );
			if ( wrap ) {
				selectField( wrap.getAttribute( 'data-alc-field' ) );
			}
		};
		const onOver = ( e ) => {
			if ( e.target.closest( '.alcb-ops' ) ) {
				return; // keep the current hover while on the toolbar
			}
			const wrap = e.target.closest( '[data-alc-field]' );
			setHoverId( wrap ? wrap.getAttribute( 'data-alc-field' ) : null );
		};
		const onLeave = () => setHoverId( null );
		sheet.addEventListener( 'click', onClick );
		sheet.addEventListener( 'mouseover', onOver );
		sheet.addEventListener( 'mouseleave', onLeave );
		return () => {
			sheet.removeEventListener( 'click', onClick );
			sheet.removeEventListener( 'mouseover', onOver );
			sheet.removeEventListener( 'mouseleave', onLeave );
		};
	}, [ hostRef, selectField ] );

	/** Map a pointer Y to an insertion index over the VISIBLE fields (midpoint rule). */
	const insertionFromY = useCallback( ( clientY ) => {
		const host = hostRef.current;
		if ( ! host ) {
			return { index: fields.length, y: 0 };
		}
		const y = clientY - host.getBoundingClientRect().top;
		const visible = fields.filter( ( f ) => rects[ f.id ] );
		for ( let i = 0; i < visible.length; i++ ) {
			const r = rects[ visible[ i ].id ];
			if ( y < r.top + r.height / 2 ) {
				return { index: fields.indexOf( visible[ i ] ), y: r.top };
			}
		}
		const last = visible[ visible.length - 1 ];
		return { index: fields.length, y: last ? rects[ last.id ].top + rects[ last.id ].height : 0 };
	}, [ hostRef, fields, rects ] );

	// DnD drop side: field reorder (grip) AND palette insertion (INSERT_AT).
	useEffect( () => {
		const host = hostRef.current;
		const sheet = host && host.parentElement;
		if ( ! sheet ) {
			return undefined;
		}
		const accepts = ( e ) => {
			const types = e.dataTransfer ? Array.from( e.dataTransfer.types ) : [];
			return types.indexOf( REORDER_MIME ) !== -1 || types.indexOf( TYPE_MIME ) !== -1;
		};
		const onDragOver = ( e ) => {
			if ( ! accepts( e ) ) {
				return;
			}
			e.preventDefault(); // required to allow dropping
			setInsertLine( insertionFromY( e.clientY ) );
		};
		const onDrop = ( e ) => {
			if ( ! accepts( e ) ) {
				return;
			}
			e.preventDefault();
			const target = insertionFromY( e.clientY );
			const type = e.dataTransfer.getData( TYPE_MIME );
			const from = e.dataTransfer.getData( REORDER_MIME );
			if ( type ) {
				insertAt( type, target.index ); // palette drag → INSERT_AT (drag source: chunk 4)
			} else if ( '' !== from ) {
				const f = Number( from );
				const to = target.index > f ? target.index - 1 : target.index;
				if ( to !== f ) {
					reorder( f, to );
				}
			}
			setInsertLine( null );
			setDragging( false );
		};
		const onDragLeave = ( e ) => {
			if ( ! sheet.contains( e.relatedTarget ) ) {
				setInsertLine( null );
			}
		};
		sheet.addEventListener( 'dragover', onDragOver );
		sheet.addEventListener( 'drop', onDrop );
		sheet.addEventListener( 'dragleave', onDragLeave );
		return () => {
			sheet.removeEventListener( 'dragover', onDragOver );
			sheet.removeEventListener( 'drop', onDrop );
			sheet.removeEventListener( 'dragleave', onDragLeave );
		};
	}, [ hostRef, insertionFromY, insertAt, reorder ] );

	// Formula-error badges reuse the existing live validator (spec §2.3).
	const formulaErrors = useMemo( () => {
		const map = {};
		fields
			.filter( ( f ) => 'formula' === f.type && '' !== ( f.expression || '' ).trim() )
			.forEach( ( f ) => {
				const r = validateExpression( f.expression, f.id, fields );
				if ( ! r.ok ) {
					map[ f.id ] = r.error.message;
				}
			} );
		return map;
	}, [ fields ] );

	const opsId = hoverId || selectedId; // hover wins; selection is the keyboard fallback
	const opsIndex = opsId ? fields.findIndex( ( f ) => f.id === opsId ) : -1;
	const opsRect = opsId ? rects[ opsId ] : null;

	return (
		<div className="alcb-overlay">
			{ selectedId && rects[ selectedId ] && <div className="alcb-outline" style={ box( rects[ selectedId ] ) }></div> }

			{ fields.map( ( f ) => {
				const r = rects[ f.id ];
				if ( ! r ) {
					return null;
				}
				const summary = describeCondition( f, fields );
				return (
					<div key={ f.id }>
						{ '' !== summary && (
							<span
								className="alcb-if-pill"
								style={ { top: r.top + r.height - 11, left: r.left + 10 } }
								title={ `${ summary } → ${ conditionAction( f ) }` }
							>
								{ __( 'IF', 'alovio-calculator' ) } · { summary } → { conditionAction( f ) }
							</span>
						) }
						{ formulaErrors[ f.id ] && (
							<span
								className="alcb-err-badge"
								style={ { top: r.top - 9, left: r.left + r.width - 26 } }
								title={ formulaErrors[ f.id ] }
								role="img"
								aria-label={ __( 'Formula error', 'alovio-calculator' ) }
							>
								!
							</span>
						) }
					</div>
				);
			} ) }

			{ opsRect && -1 !== opsIndex && ! dragging && (
				<div className="alcb-ops" style={ { top: opsRect.top - 14, left: opsRect.left + opsRect.width - 160 } }>
					<span
						className="alcb-op alcb-op--grip"
						draggable
						title={ __( 'Drag to reorder', 'alovio-calculator' ) }
						onDragStart={ ( e ) => {
							e.dataTransfer.setData( REORDER_MIME, String( opsIndex ) );
							e.dataTransfer.effectAllowed = 'move';
							selectField( opsId );
							setDragging( true );
						} }
						onDragEnd={ () => {
							setDragging( false );
							setInsertLine( null );
						} }
					>
						⠿
					</span>
					<button className="alcb-op" disabled={ 0 === opsIndex } aria-label={ __( 'Move up', 'alovio-calculator' ) } onClick={ () => reorder( opsIndex, opsIndex - 1 ) }>↑</button>
					<button className="alcb-op" disabled={ opsIndex === fields.length - 1 } aria-label={ __( 'Move down', 'alovio-calculator' ) } onClick={ () => reorder( opsIndex, opsIndex + 1 ) }>↓</button>
					<button className="alcb-op" aria-label={ __( 'Duplicate', 'alovio-calculator' ) } onClick={ () => duplicateField( opsId ) }>⧉</button>
					<button className="alcb-op alcb-op--danger" aria-label={ __( 'Delete', 'alovio-calculator' ) } onClick={ () => removeField( opsId ) }>✕</button>
				</div>
			) }

			{ insertLine && <div className="alcb-insert-line" style={ { top: insertLine.y - 2 } }></div> }
		</div>
	);
}
