import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl, Placeholder, PanelBody, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';


export default function Edit( { attributes, setAttributes } ) {
	const { calculatorId } = attributes;
	const blockProps = useBlockProps();
	const [ calculators, setCalculators ] = useState( null );

	useEffect( () => {
		apiFetch( { path: 'alovio-calc/v1/calculators' } )
			.then( setCalculators )
			.catch( () => setCalculators( [] ) );
	}, [] );

	const options = [
		{ label: __( '— choose a calculator —', 'alovio-calculator' ), value: 0 },
		...( calculators || [] ).map( ( c ) => ( { label: c.title || `#${ c.id }`, value: c.id } ) ),
	];

	const picker = (
		<SelectControl
			label={ __( 'Calculator', 'alovio-calculator' ) }
			value={ calculatorId }
			options={ options }
			onChange={ ( v ) => setAttributes( { calculatorId: parseInt( v, 10 ) || 0 } ) }
		/>
	);

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Calculator', 'alovio-calculator' ) }>{ picker }</PanelBody>
			</InspectorControls>
			{ ! calculatorId && (
				<Placeholder icon="calculator" label={ __( 'Alovio Calculator', 'alovio-calculator' ) }>
					{ calculators === null ? <Spinner /> : picker }
				</Placeholder>
			) }
			{ !! calculatorId && (
				<ServerSideRender block="alovio/calculator" attributes={ attributes } />
			) }
		</div>
	);
}
