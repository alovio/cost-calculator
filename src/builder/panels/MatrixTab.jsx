import { Button, TextControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { newSlug } from '../reducer';

/**
 * Price-matrix editor: two named dimensions (rows × columns) and a price per cell.
 * Rows/columns carry client opt_ slugs (the server preserves them), so cells —
 * keyed "rowSlug|colSlug" — survive a save round-trip without re-keying.
 */
const cellKey = ( r, c ) => `${ r }|${ c }`;

export default function MatrixTab( { field, set } ) {
	const rows = field.rows || [];
	const columns = field.columns || [];
	const cells = field.cells || {};

	const priceOf = ( r, c ) => {
		const v = cells[ cellKey( r, c ) ];
		return v === 0 || v ? String( v ) : '';
	};

	const setCell = ( r, c, value ) => {
		const next = { ...cells };
		if ( '' === value ) {
			delete next[ cellKey( r, c ) ];
		} else {
			next[ cellKey( r, c ) ] = value;
		}
		set( { cells: next } );
	};

	const renameRow = ( i, label ) => set( { rows: rows.map( ( o, idx ) => ( idx === i ? { ...o, label } : o ) ) } );
	const renameCol = ( i, label ) => set( { columns: columns.map( ( o, idx ) => ( idx === i ? { ...o, label } : o ) ) } );

	const addRow = () => set( { rows: [ ...rows, { value: newSlug(), label: '' } ] } );
	const addCol = () => set( { columns: [ ...columns, { value: newSlug(), label: '' } ] } );

	// Removing an axis prunes that axis's cell keys in the same update.
	const pruneCells = ( keep ) => {
		const next = {};
		Object.keys( cells ).forEach( ( k ) => {
			if ( keep( k ) ) {
				next[ k ] = cells[ k ];
			}
		} );
		return next;
	};
	const removeRow = ( i ) => {
		const gone = rows[ i ].value;
		set( { rows: rows.filter( ( _, idx ) => idx !== i ), cells: pruneCells( ( k ) => k.split( '|' )[ 0 ] !== gone ) } );
	};
	const removeCol = ( i ) => {
		const gone = columns[ i ].value;
		set( { columns: columns.filter( ( _, idx ) => idx !== i ), cells: pruneCells( ( k ) => k.split( '|' )[ 1 ] !== gone ) } );
	};

	return (
		<div className="alcb-matrix">
			<div className="alcb-matrix-dims">
				<TextControl label={ __( 'Row dimension', 'alovio-calculator' ) } value={ field.rowLabel || '' } placeholder={ __( 'e.g. Size', 'alovio-calculator' ) } onChange={ ( rowLabel ) => set( { rowLabel } ) } />
				<TextControl label={ __( 'Column dimension', 'alovio-calculator' ) } value={ field.colLabel || '' } placeholder={ __( 'e.g. Material', 'alovio-calculator' ) } onChange={ ( colLabel ) => set( { colLabel } ) } />
			</div>

			{ ( ! rows.length || ! columns.length ) && (
				<Notice status="warning" isDismissible={ false }>{ __( 'Add at least one row and one column.', 'alovio-calculator' ) }</Notice>
			) }

			<div className="alcb-matrix-wrap">
				<table className="alcb-matrix-grid">
					<thead>
						<tr>
							<th className="alcb-matrix-corner" />
							{ columns.map( ( c, ci ) => (
								<th key={ c.value }>
									<input className="alcb-matrix-head" value={ c.label || '' } placeholder={ __( 'Column', 'alovio-calculator' ) } onChange={ ( e ) => renameCol( ci, e.target.value ) } />
									<button className="alcb-matrix-x" disabled={ columns.length < 2 } aria-label={ __( 'Remove column', 'alovio-calculator' ) } onClick={ () => removeCol( ci ) }>✕</button>
								</th>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( r, ri ) => (
							<tr key={ r.value }>
								<th>
									<input className="alcb-matrix-head" value={ r.label || '' } placeholder={ __( 'Row', 'alovio-calculator' ) } onChange={ ( e ) => renameRow( ri, e.target.value ) } />
									<button className="alcb-matrix-x" disabled={ rows.length < 2 } aria-label={ __( 'Remove row', 'alovio-calculator' ) } onClick={ () => removeRow( ri ) }>✕</button>
								</th>
								{ columns.map( ( c ) => (
									<td key={ c.value }>
										<input type="number" step="0.01" placeholder="0" value={ priceOf( r.value, c.value ) } onChange={ ( e ) => setCell( r.value, c.value, e.target.value ) } />
									</td>
								) ) }
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
			<div className="alcb-matrix-foot">
				<Button size="small" variant="secondary" onClick={ addRow }>{ __( '+ Row', 'alovio-calculator' ) }</Button>
				<Button size="small" variant="secondary" onClick={ addCol }>{ __( '+ Column', 'alovio-calculator' ) }</Button>
			</div>
			<p className="alcb-hint">{ __( 'Visitors pick one row and one column; the matching cell price is added to the total.', 'alovio-calculator' ) }</p>
		</div>
	);
}
