import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl, Placeholder, PanelBody, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

const T = 'alovio-calculator';

export default function Edit( { attributes, setAttributes } ) {
	const { calculatorId } = attributes;
	const blockProps = useBlockProps();
	const [ calculators, setCalculators ] = useState( null );

	useEffect( () => {
		apiFetch( { path: 'alc/v1/calculators' } )
			.then( setCalculators )
			.catch( () => setCalculators( [] ) );
	}, [] );

	const options = [
		{ label: __( '— choose a calculator —', T ), value: 0 },
		...( calculators || [] ).map( ( c ) => ( { label: c.title || `#${ c.id }`, value: c.id } ) ),
	];

	const picker = (
		<SelectControl
			label={ __( 'Calculator', T ) }
			value={ calculatorId }
			options={ options }
			onChange={ ( v ) => setAttributes( { calculatorId: parseInt( v, 10 ) || 0 } ) }
		/>
	);

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Calculator', T ) }>{ picker }</PanelBody>
			</InspectorControls>
			{ ! calculatorId && (
				<Placeholder icon="calculator" label={ __( 'Alovio Calculator', T ) }>
					{ calculators === null ? <Spinner /> : picker }
				</Placeholder>
			) }
			{ !! calculatorId && (
				<ServerSideRender block="alovio/calculator" attributes={ attributes } />
			) }
		</div>
	);
}
