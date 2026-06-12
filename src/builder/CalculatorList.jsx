import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { listCalculators, createCalculator, deleteCalculator, getSettings, saveSettings } from './api';
import TemplatePicker from './TemplatePicker';

const T = 'alovio-calculator';

export default function CalculatorList( { onEdit, onEntries } ) {
	const [ items, setItems ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ picking, setPicking ] = useState( false );
	const [ copiedId, setCopiedId ] = useState( null );
	const [ deleteOnUninstall, setDeleteOnUninstall ] = useState( null );

	const refresh = () =>
		listCalculators()
			.then( setItems )
			.catch( () => setError( __( 'Could not load calculators.', T ) ) );

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
		if ( window.confirm( __( 'Delete this calculator? Its entries stay in the database.', T ) ) ) {
			await deleteCalculator( item.id );
			refresh();
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
				<h1 className="alc-heading">{ __( 'Alovio Calculator', T ) }</h1>
				<Button variant="primary" onClick={ () => setPicking( true ) }>{ __( 'Add new', T ) }</Button>
				<Button variant="secondary" onClick={ onEntries }>{ __( 'Entries', T ) }</Button>
			</div>

			{ ! items.length && (
				<p className="alc-empty">{ __( 'No calculators yet — create your first one from a template.', T ) }</p>
			) }

			{ !! items.length && (
				<table className="widefat striped alc-table">
					<thead>
						<tr>
							<th>{ __( 'Name', T ) }</th>
							<th>{ __( 'Shortcode', T ) }</th>
							<th>{ __( 'Updated', T ) }</th>
							<th>{ __( 'Actions', T ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( item ) => (
							<tr key={ item.id }>
								<td>
									<Button variant="link" onClick={ () => onEdit( item.id ) }>{ item.title || __( '(untitled)', T ) }</Button>
								</td>
								<td>
									<code>{ item.shortcode }</code>{ ' ' }
									<Button size="small" onClick={ () => copyShortcode( item ) }>
										{ copiedId === item.id ? __( 'Copied!', T ) : __( 'Copy', T ) }
									</Button>
								</td>
								<td>{ item.updated }</td>
								<td className="alc-table__ops">
									<Button size="small" variant="secondary" onClick={ () => onEdit( item.id ) }>{ __( 'Edit', T ) }</Button>
									<Button size="small" onClick={ () => duplicate( item ) }>{ __( 'Duplicate', T ) }</Button>
									<Button size="small" isDestructive onClick={ () => remove( item ) }>{ __( 'Delete', T ) }</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			{ deleteOnUninstall !== null && (
				<details className="alc-danger-zone">
					<summary>{ __( 'Plugin settings', T ) }</summary>
					<ToggleControl
						label={ __( 'Delete all plugin data on uninstall', T ) }
						help={ __( 'When enabled, deleting the plugin removes calculators, entries and settings permanently.', T ) }
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
