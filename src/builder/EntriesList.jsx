import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice, SelectControl, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { listEntries, updateEntry, deleteEntry, listCalculators } from './api';

const T = 'alovio-calculator';
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
			.catch( () => setError( __( 'Could not load entries.', T ) ) );

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
		if ( window.confirm( __( 'Delete this entry permanently?', T ) ) ) {
			await deleteEntry( row.id );
			setOpen( null );
			refresh();
		}
	};

	const exportUrl = `${ window.ALC_BUILDER.adminPost }?action=alc_export_entries&calculator=${ calculator }&_wpnonce=${ window.ALC_BUILDER.exportNonce }`;
	const pages = data ? Math.max( 1, Math.ceil( data.total / PER_PAGE ) ) : 1;

	return (
		<div className="alc-app">
			<div className="alc-topbar">
				<Button variant="tertiary" onClick={ onBack }>← { __( 'All calculators', T ) }</Button>
				<h1 className="alc-heading">{ __( 'Entries', T ) }</h1>
				<SelectControl
					label={ __( 'Calculator', T ) }
					hideLabelFromVision
					value={ String( calculator ) }
					options={ [
						{ label: __( 'All calculators', T ), value: '0' },
						...calculators.map( ( c ) => ( { label: c.title, value: String( c.id ) } ) ),
					] }
					onChange={ ( v ) => {
						setCalculator( parseInt( v, 10 ) || 0 );
						setPage( 1 );
					} }
				/>
				<Button variant="secondary" href={ exportUrl }>{ __( 'Export CSV', T ) }</Button>
			</div>

			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			{ ! data && ! error && <Spinner /> }

			{ data && ! data.rows.length && <p className="alc-empty">{ __( 'No entries yet.', T ) }</p> }

			{ data && !! data.rows.length && (
				<table className="widefat striped alc-table">
					<thead>
						<tr>
							<th>{ __( 'Date', T ) }</th>
							<th>{ __( 'Name', T ) }</th>
							<th>{ __( 'Email', T ) }</th>
							<th>{ __( 'Total', T ) }</th>
							<th>{ __( 'Status', T ) }</th>
							<th>{ __( 'Actions', T ) }</th>
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
									<Button size="small" variant="secondary" onClick={ () => setOpen( row ) }>{ __( 'View', T ) }</Button>
									{ row.status === 'new' && (
										<Button size="small" onClick={ () => markRead( row ) }>{ __( 'Mark read', T ) }</Button>
									) }
									<Button size="small" isDestructive onClick={ () => remove( row ) }>{ __( 'Delete', T ) }</Button>
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
						<strong>{ __( 'Email:', T ) }</strong> { open.email }
						{ open.phone && <> · <strong>{ __( 'Phone:', T ) }</strong> { open.phone }</> }
					</p>
					{ open.message && <p><strong>{ __( 'Message:', T ) }</strong> { open.message }</p> }
					{ open.snapshot && Array.isArray( open.snapshot.lineItems ) && (
						<table className="widefat striped">
							<tbody>
								{ open.snapshot.lineItems.map( ( item ) => (
									<tr key={ item.id }>
										<td>{ item.label }</td>
										<td>{ String( item.amount / 10000 ) }</td>
									</tr>
								) ) }
								<tr>
									<th>{ __( 'Total', T ) }</th>
									<th>{ open.total }</th>
								</tr>
							</tbody>
						</table>
					) }
					<div className="alc-modal-actions">
						{ open.status === 'new' && (
							<Button variant="secondary" onClick={ () => { markRead( open ); setOpen( null ); } }>{ __( 'Mark read', T ) }</Button>
						) }
						<Button isDestructive onClick={ () => remove( open ) }>{ __( 'Delete', T ) }</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
