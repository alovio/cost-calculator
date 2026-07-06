import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice, SelectControl, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { listEntries, updateEntry, deleteEntry, listCalculators } from './api';
import { formatCurrency } from '../shared/currency';

const PER_PAGE = 20;

export default function EntriesList( { onBack } ) {
	const [ calculators, setCalculators ] = useState( [] );
	const [ calculator, setCalculator ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ open, setOpen ] = useState( null ); // row in detail modal

	const refresh = () =>
		listEntries( { calculator, page, per_page: PER_PAGE } )
			.then( setData )
			.catch( () => setError( __( 'Could not load entries.', 'alovio-calculator' ) ) );

	useEffect( () => {
		listCalculators().then( setCalculators ).catch( () => {} );
	}, [] );

	useEffect( () => {
		refresh();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ calculator, page ] );

	const markRead = async ( row ) => {
		await updateEntry( row.id, { status: 'read' } );
		refresh();
	};

	const remove = async ( row ) => {
		// eslint-disable-next-line no-alert
		if ( window.confirm( __( 'Delete this entry permanently?', 'alovio-calculator' ) ) ) {
			await deleteEntry( row.id );
			setOpen( null );
			refresh();
		}
	};

	const exportUrl = `${ window.ALOVIO_CALC_BUILDER.adminPost }?action=alovio_calc_export_entries&calculator=${ calculator }&_wpnonce=${ window.ALOVIO_CALC_BUILDER.exportNonce }`;
	const pages = data ? Math.max( 1, Math.ceil( data.total / PER_PAGE ) ) : 1;

	return (
		<div className="alc-app">
			<div className="alc-topbar">
				<Button variant="tertiary" onClick={ onBack }>← { __( 'All calculators', 'alovio-calculator' ) }</Button>
				<h1 className="alc-heading">{ __( 'Entries', 'alovio-calculator' ) }</h1>
				<SelectControl
					label={ __( 'Calculator', 'alovio-calculator' ) }
					hideLabelFromVision
					value={ String( calculator ) }
					options={ [
						{ label: __( 'All calculators', 'alovio-calculator' ), value: '0' },
						...calculators.map( ( c ) => ( { label: c.title, value: String( c.id ) } ) ),
					] }
					onChange={ ( v ) => {
						setCalculator( parseInt( v, 10 ) || 0 );
						setPage( 1 );
					} }
				/>
				<Button variant="secondary" href={ exportUrl }>{ __( 'Export CSV', 'alovio-calculator' ) }</Button>
			</div>

			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			{ ! data && ! error && <Spinner /> }

			{ data && ! data.rows.length && <p className="alc-empty">{ __( 'No entries yet.', 'alovio-calculator' ) }</p> }

			{ data && !! data.rows.length && (
				<table className="widefat striped alc-table">
					<thead>
						<tr>
							<th>{ __( 'Date', 'alovio-calculator' ) }</th>
							<th>{ __( 'Name', 'alovio-calculator' ) }</th>
							<th>{ __( 'Email', 'alovio-calculator' ) }</th>
							<th>{ __( 'Total', 'alovio-calculator' ) }</th>
							<th>{ __( 'Status', 'alovio-calculator' ) }</th>
							<th>{ __( 'Actions', 'alovio-calculator' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ data.rows.map( ( row ) => (
							<tr key={ row.id } className={ row.status === 'new' ? 'alc-row--new' : '' }>
								<td>{ row.created_at }</td>
								<td>{ row.name }</td>
								<td>{ row.email }</td>
								<td>{ row.total }</td>
								<td><span className={ `alc-badge alc-badge--${ row.status }` }>{ row.status }</span></td>
								<td className="alc-table__ops">
									<Button size="small" variant="secondary" onClick={ () => setOpen( row ) }>{ __( 'View', 'alovio-calculator' ) }</Button>
									{ row.status === 'new' && (
										<Button size="small" onClick={ () => markRead( row ) }>{ __( 'Mark read', 'alovio-calculator' ) }</Button>
									) }
									<Button size="small" isDestructive onClick={ () => remove( row ) }>{ __( 'Delete', 'alovio-calculator' ) }</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ data && pages > 1 && (
				<div className="alc-pagination">
					<Button size="small" disabled={ page <= 1 } onClick={ () => setPage( page - 1 ) }>←</Button>
					<span>{ page } / { pages }</span>
					<Button size="small" disabled={ page >= pages } onClick={ () => setPage( page + 1 ) }>→</Button>
				</div>
			) }

			{ open && (
				<Modal title={ `${ open.name } — ${ open.created_at }` } onRequestClose={ () => setOpen( null ) }>
					<p>
						<strong>{ __( 'Email:', 'alovio-calculator' ) }</strong> { open.email }
						{ open.phone && <> · <strong>{ __( 'Phone:', 'alovio-calculator' ) }</strong> { open.phone }</> }
					</p>
					{ open.message && <p><strong>{ __( 'Message:', 'alovio-calculator' ) }</strong> { open.message }</p> }
					{ open.snapshot && Array.isArray( open.snapshot.repeaters ) && open.snapshot.repeaters.map( ( rep ) => (
						<div key={ rep.id } className="alc-entry-repeater">
							<strong>{ rep.label }</strong>
							<ul>
								{ ( rep.rows || [] ).map( ( row, i ) => (
									<li key={ i }>
										{ row.label }
										{ ': ' }
										{ Object.entries( row.values || {} )
											.filter( ( [ , v ] ) => v !== '' )
											.map( ( [ cid, v ] ) => ( ( rep.types || {} )[ cid ] === 'toggle'
												? ( rep.children || {} )[ cid ] || cid
												: `${ ( rep.children || {} )[ cid ] || cid } ${ v }` ) )
											.join( ', ' ) }
										{ ' — ' }
										{ formatCurrency( row.total || 0, open.snapshot.currency ) }
									</li>
								) ) }
							</ul>
						</div>
					) ) }
					{ open.snapshot && Array.isArray( open.snapshot.lineItems ) && (
						<table className="widefat striped">
							<tbody>
								{ open.snapshot.lineItems.filter( ( item ) => ! item.repeaterId ).map( ( item ) => (
									<tr key={ item.id }>
										<td>{ item.label }</td>
										<td>{ item.display !== undefined ? item.display : String( item.amount / 10000 ) }</td>
									</tr>
								) ) }
								<tr>
									<th>{ __( 'Total', 'alovio-calculator' ) }</th>
									<th>{ open.total }</th>
								</tr>
							</tbody>
						</table>
					) }
					<div className="alc-modal-actions">
						{ open.status === 'new' && (
							<Button variant="secondary" onClick={ () => { markRead( open ); setOpen( null ); } }>{ __( 'Mark read', 'alovio-calculator' ) }</Button>
						) }
						<Button isDestructive onClick={ () => remove( open ) }>{ __( 'Delete', 'alovio-calculator' ) }</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
