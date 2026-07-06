import { useState, useEffect, useRef } from '@wordpress/element';
import { Button, Spinner, Notice, ToggleControl, DropdownMenu, MenuGroup, MenuItem, Modal, CheckboxControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { listCalculators, createCalculator, getCalculator, saveCalculator, deleteCalculator, getSettings, saveSettings, listCcbImport, runCcbImport } from './api';
import TemplatePicker from './TemplatePicker';

const slugify = ( s ) =>
	( s || 'calculator' ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' ).slice( 0, 60 ) || 'calculator';

export default function CalculatorList( { onEdit, onEntries } ) {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ picking, setPicking ] = useState( false );
	const [ copiedId, setCopiedId ] = useState( null );
	const [ deleteOnUninstall, setDeleteOnUninstall ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const [ ccbOpen, setCcbOpen ] = useState( false );
	const fileInputRef = useRef( null );

	const refresh = () =>
		listCalculators()
			.then( setItems )
			.catch( () => setError( __( 'Could not load calculators.', 'alovio-calculator' ) ) );

	useEffect( () => {
		refresh();
		getSettings()
			.then( ( s ) => setDeleteOnUninstall( !! s.deleteOnUninstall ) )
			.catch( () => {} );
	}, [] );

	const copyShortcode = ( item ) => {
		window.navigator.clipboard.writeText( item.shortcode ).then( () => {
			setCopiedId( item.id );
			setTimeout( () => setCopiedId( null ), 1500 );
		} );
	};

	const duplicate = async ( item ) => {
		await createCalculator( { title: `${ item.title } (copy)`, duplicateOf: item.id } );
		refresh();
	};

	const remove = async ( item ) => {
		// eslint-disable-next-line no-alert
		if ( window.confirm( __( 'Delete this calculator? Its entries stay in the database.', 'alovio-calculator' ) ) ) {
			await deleteCalculator( item.id );
			refresh();
		}
	};

	const exportCalculator = async ( item ) => {
		try {
			const calc = await getCalculator( item.id );
			const payload = { plugin: 'alovio-calculator', schemaVersion: 1, name: calc.title || '', config: calc.config || {} };
			const blob = new window.Blob( [ JSON.stringify( payload, null, 2 ) ], { type: 'application/json' } );
			const url = window.URL.createObjectURL( blob );
			const a = window.document.createElement( 'a' );
			a.href = url;
			a.download = `alovio-${ slugify( calc.title ) }.json`;
			window.document.body.appendChild( a );
			a.click();
			a.remove();
			window.URL.revokeObjectURL( url );
		} catch ( e ) {
			setNotice( { status: 'error', text: __( 'Export failed.', 'alovio-calculator' ) } );
		}
	};

	const importFile = async ( file ) => {
		if ( ! file ) {
			return;
		}
		try {
			const data = JSON.parse( await file.text() );
			const config = data && data.config ? data.config : data; // accept a {config} wrapper or a bare config object
			if ( ! config || ( ! Array.isArray( config.fields ) && typeof config.settings !== 'object' ) ) {
				throw new Error( 'invalid' );
			}
			const title = String( data.name || __( 'Imported calculator', 'alovio-calculator' ) );
			const created = await createCalculator( { title } );
			// The server normalizes/sanitizes the config on save, so an untrusted file can't inject anything.
			await saveCalculator( created.id, {
				title,
				config: { schemaVersion: 1, fields: config.fields || [], settings: config.settings || {} },
			} );
			onEdit( created.id );
		} catch ( e ) {
			setNotice( { status: 'error', text: __( 'Import failed — not a valid Alovio Calculator export file.', 'alovio-calculator' ) } );
		}
	};

	if ( error ) {
		return <Notice status="error" isDismissible={ false }>{ error }</Notice>;
	}
	if ( items === null ) {
		return (
			<div className="alc-app alc-app--loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="alc-app">
			<div className="alc-topbar">
				<h1 className="alc-heading">{ __( 'Alovio Calculator', 'alovio-calculator' ) }</h1>
				<Button variant="primary" onClick={ () => setPicking( true ) }>{ __( 'Add new', 'alovio-calculator' ) }</Button>
				<Button variant="secondary" onClick={ onEntries }>{ __( 'Entries', 'alovio-calculator' ) }</Button>
				<DropdownMenu text={ __( 'Import', 'alovio-calculator' ) } icon={ null } label={ __( 'Import', 'alovio-calculator' ) } toggleProps={ { variant: 'secondary' } }>
					{ ( { onClose } ) => (
						<MenuGroup>
							<MenuItem onClick={ () => { onClose(); if ( fileInputRef.current ) { fileInputRef.current.click(); } } }>
								{ __( 'From JSON file', 'alovio-calculator' ) }
							</MenuItem>
							{ !! ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.ccbDetected ) && (
								<MenuItem onClick={ () => { onClose(); setCcbOpen( true ); } }>
									{ __( 'From Cost Calculator Builder', 'alovio-calculator' ) }
								</MenuItem>
							) }
						</MenuGroup>
					) }
				</DropdownMenu>
				<input
					type="file"
					accept="application/json,.json"
					ref={ fileInputRef }
					style={ { display: 'none' } }
					onChange={ ( e ) => {
						importFile( e.target.files[ 0 ] );
						e.target.value = '';
					} }
				/>
			</div>

			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>{ notice.text }</Notice>
			) }

			{ ! items.length && (
				<p className="alc-empty">{ __( 'No calculators yet — create your first one from a template.', 'alovio-calculator' ) }</p>
			) }

			{ !! items.length && (
				<table className="widefat striped alc-table">
					<thead>
						<tr>
							<th>{ __( 'Name', 'alovio-calculator' ) }</th>
							<th>{ __( 'Shortcode', 'alovio-calculator' ) }</th>
							<th>{ __( 'Updated', 'alovio-calculator' ) }</th>
							<th>{ __( 'Actions', 'alovio-calculator' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( item ) => (
							<tr key={ item.id }>
								<td>
									<Button variant="link" onClick={ () => onEdit( item.id ) }>{ item.title || __( '(untitled)', 'alovio-calculator' ) }</Button>
								</td>
								<td>
									<code>{ item.shortcode }</code>{ ' ' }
									<Button size="small" onClick={ () => copyShortcode( item ) }>
										{ copiedId === item.id ? __( 'Copied!', 'alovio-calculator' ) : __( 'Copy', 'alovio-calculator' ) }
									</Button>
								</td>
								<td>{ item.updated }</td>
								<td className="alc-table__ops">
									<Button size="small" variant="secondary" onClick={ () => onEdit( item.id ) }>{ __( 'Edit', 'alovio-calculator' ) }</Button>
									<Button size="small" onClick={ () => duplicate( item ) }>{ __( 'Duplicate', 'alovio-calculator' ) }</Button>
									<Button size="small" onClick={ () => exportCalculator( item ) }>{ __( 'Export', 'alovio-calculator' ) }</Button>
									<Button size="small" isDestructive onClick={ () => remove( item ) }>{ __( 'Delete', 'alovio-calculator' ) }</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ deleteOnUninstall !== null && (
				<details className="alc-danger-zone">
					<summary>{ __( 'Plugin settings', 'alovio-calculator' ) }</summary>
					<ToggleControl
						label={ __( 'Delete all plugin data on uninstall', 'alovio-calculator' ) }
						help={ __( 'When enabled, deleting the plugin removes calculators, entries and settings permanently.', 'alovio-calculator' ) }
						checked={ deleteOnUninstall }
						onChange={ ( on ) => {
							setDeleteOnUninstall( on );
							saveSettings( { deleteOnUninstall: on } );
						} }
					/>
				</details>
			) }

			{ picking && (
				<TemplatePicker
					onClose={ () => setPicking( false ) }
					onCreated={ ( id ) => {
						setPicking( false );
						onEdit( id );
					} }
				/>
			) }

			{ ccbOpen && (
				<CcbImportModal
					onClose={ () => {
						setCcbOpen( false );
						refresh();
					} }
				/>
			) }
		</div>
	);
}

function CcbImportModal( { onClose } ) {
	const [ items, setItems ] = useState( null );
	const [ checked, setChecked ] = useState( {} );
	const [ busy, setBusy ] = useState( false );
	const [ report, setReport ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		listCcbImport()
			.then( ( r ) => {
				setItems( r.present ? r.items : [] );
				const all = {};
				( r.items || [] ).forEach( ( it ) => ( all[ it.id ] = true ) ); // default: import everything
				setChecked( all );
			} )
			.catch( () => setError( __( 'Could not read Cost Calculator Builder data.', 'alovio-calculator' ) ) );
	}, [] );

	const selectedIds = Object.keys( checked ).filter( ( id ) => checked[ id ] ).map( Number );

	const run = async () => {
		setBusy( true );
		setError( null );
		try {
			const r = await runCcbImport( selectedIds );
			setReport( r.results || [] );
		} catch ( e ) {
			setError( __( 'Import failed. Please try again.', 'alovio-calculator' ) );
		}
		setBusy( false );
	};

	return (
		<Modal
			title={ __( 'Import from Cost Calculator Builder', 'alovio-calculator' ) }
			onRequestClose={ onClose }
			className="alc-ccb-modal"
		>
			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }
			{ items === null && ! error && <Spinner /> }

			{ items !== null && ! report && (
				<>
					{ ! items.length && <p>{ __( 'No Cost Calculator Builder calculators were found.', 'alovio-calculator' ) }</p> }
					{ items.map( ( it ) => (
						<CheckboxControl
							key={ it.id }
							label={ `${ it.title || __( '(untitled)', 'alovio-calculator' ) } — ${ sprintf( _n( '%d field', '%d fields', it.fieldCount, 'alovio-calculator' ), it.fieldCount ) }` }
							checked={ !! checked[ it.id ] }
							onChange={ ( on ) => setChecked( { ...checked, [ it.id ]: on } ) }
						/>
					) ) }
					<div className="alc-modal-actions">
						<Button variant="tertiary" onClick={ onClose }>{ __( 'Cancel', 'alovio-calculator' ) }</Button>
						<Button variant="primary" onClick={ run } isBusy={ busy } disabled={ busy || ! selectedIds.length }>
							{ __( 'Import selected', 'alovio-calculator' ) }
						</Button>
					</div>
				</>
			) }

			{ report && (
				<div className="alc-ccb-report">
					{ report.map( ( r ) => (
						<div key={ r.ccbId } className="alc-ccb-report__item">
							<h3>{ ( r.created ? '✓ ' : '✕ ' ) + ( r.title || __( '(untitled)', 'alovio-calculator' ) ) }</h3>
							{ ! r.created && !! r.error && <p className="alc-ccb-report__error">{ r.error }</p> }
							{ !! ( r.skipped && r.skipped.length ) && (
								<ul className="alc-ccb-report__skipped">{ r.skipped.map( ( s, i ) => <li key={ i }>{ s }</li> ) }</ul>
							) }
							{ !! ( r.warnings && r.warnings.length ) && (
								<ul className="alc-ccb-report__warnings">{ r.warnings.map( ( w, i ) => <li key={ i }>{ w }</li> ) }</ul>
							) }
						</div>
					) ) }
					<div className="alc-modal-actions">
						<Button variant="primary" onClick={ onClose }>{ __( 'Done', 'alovio-calculator' ) }</Button>
					</div>
				</div>
			) }
		</Modal>
	);
}
