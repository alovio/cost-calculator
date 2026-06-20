import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { previewCalculator } from './api';

/** Live preview pane: re-renders the current (unsaved) config in an iframe as you edit. */
export default function Preview() {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
	const [ src, setSrc ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( false );

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

	return (
		<div className="alc-preview">
			<div className="alc-preview__bar" style={ { display: 'flex', alignItems: 'center', gap: '8px', margin: '4px 0 12px', color: '#646970', fontSize: '13px' } }>
				{ loading && <Spinner /> }
				<span>{ __( 'Live preview — exactly what visitors see, updated as you edit.', 'alovio-calculator' ) }</span>
			</div>
			{ error && (
				<p style={ { color: '#b32d2e' } }>{ __( 'Could not build the preview. Save and reload if this persists.', 'alovio-calculator' ) }</p>
			) }
			{ src && (
				<iframe
					className="alc-preview__frame"
					title={ __( 'Calculator preview', 'alovio-calculator' ) }
					src={ src }
					style={ { width: '100%', minHeight: '70vh', border: '1px solid #e0e0e0', borderRadius: '8px', background: '#fff' } }
				/>
			) }
		</div>
	);
}
