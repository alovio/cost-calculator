import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Button, ButtonGroup, Spinner, ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { previewCalculator } from './api';

const DEVICES = [
	{ id: 'desktop', label: __( 'Desktop', 'alovio-calculator' ), width: '100%' },
	{ id: 'tablet', label: __( 'Tablet', 'alovio-calculator' ), width: '820px' },
	{ id: 'mobile', label: __( 'Mobile', 'alovio-calculator' ), width: '390px' },
];

/** Live preview pane: re-renders the current (unsaved) config in an iframe as you edit. */
export default function Preview() {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
	const [ src, setSrc ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( false );
	const [ device, setDevice ] = useState( 'desktop' );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( false );
		// Debounce so rapid edits don't hammer the endpoint.
		const timer = setTimeout( () => {
			previewCalculator( { fields, settings } )
				.then( ( res ) => {
					if ( ! cancelled ) {
						setSrc( res.url + '&t=' + Date.now() );
					}
				} )
				.catch( () => {
					if ( ! cancelled ) {
						setError( true );
					}
				} )
				.finally( () => {
					if ( ! cancelled ) {
						setLoading( false );
					}
				} );
		}, 400 );
		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ fields, settings ] );

	const current = DEVICES.find( ( d ) => d.id === device ) || DEVICES[ 0 ];

	return (
		<div className="alc-preview">
			<div
				className="alc-preview__bar"
				style={ { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px', flexWrap: 'wrap', margin: '4px 0 12px' } }
			>
				<div style={ { display: 'flex', alignItems: 'center', gap: '8px', color: '#646970', fontSize: '13px' } }>
					{ loading && <Spinner /> }
					<span>{ __( 'Live preview — exactly what visitors see, updated as you edit.', 'alovio-calculator' ) }</span>
				</div>
				<div style={ { display: 'flex', alignItems: 'center', gap: '12px' } }>
					<ButtonGroup aria-label={ __( 'Preview width', 'alovio-calculator' ) }>
						{ DEVICES.map( ( d ) => (
							<Button
								key={ d.id }
								size="small"
								variant={ device === d.id ? 'primary' : 'secondary' }
								aria-pressed={ device === d.id }
								onClick={ () => setDevice( d.id ) }
							>
								{ d.label }
							</Button>
						) ) }
					</ButtonGroup>
					{ src && (
						<ExternalLink href={ src }>{ __( 'Open full preview', 'alovio-calculator' ) }</ExternalLink>
					) }
				</div>
			</div>
			{ error && (
				<p style={ { color: '#b32d2e' } }>{ __( 'Could not build the preview. Save and reload if this persists.', 'alovio-calculator' ) }</p>
			) }
			{ src && (
				<div style={ { display: 'flex', justifyContent: 'center' } }>
					<iframe
						className="alc-preview__frame"
						title={ __( 'Calculator preview', 'alovio-calculator' ) }
						src={ src }
						style={ { width: '100%', maxWidth: current.width, minHeight: '70vh', border: '1px solid #e0e0e0', borderRadius: '8px', background: '#fff', transition: 'max-width 0.2s ease' } }
					/>
				</div>
			) }
		</div>
	);
}
