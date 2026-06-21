import { useState, useEffect, useRef } from '@wordpress/element';
import { Button, Spinner, Notice, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { listCalculators, createCalculator, getCalculator, saveCalculator, deleteCalculator, getSettings, saveSettings } from './api';
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
				<Button variant="secondary" onClick={ () => fileInputRef.current && fileInputRef.current.click() }>{ __( 'Import', 'alovio-calculator' ) }</Button>
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
		</div>
	);
}
