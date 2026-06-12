import { useState, useEffect, useRef } from '@wordpress/element';
import { TextareaControl, SelectControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { validateExpression } from './formula-validation';


const REFERENCEABLE = [ 'number', 'slider', 'select', 'radio', 'checkbox_group', 'toggle', 'quantity', 'formula' ];

/** Advisory only — errors NEVER block saving (spec §7: safe-0 on the front end). */
export default function FormulaPanel( { field, fields, set } ) {
	const [ result, setResult ] = useState( { ok: true, error: null } );
	const timer = useRef( null );
	const inputRef = useRef( null );

	const expression = field.expression || '';

	useEffect( () => {
		window.clearTimeout( timer.current );
		if ( expression.trim() === '' ) {
			setResult( { ok: true, error: null } );
			return;
		}
		timer.current = window.setTimeout( () => {
			setResult( validateExpression( expression, field.id, fields ) );
		}, 300 );
		return () => window.clearTimeout( timer.current );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ expression, field.id, fields ] );

	const insertable = fields.filter( ( f ) => f.id !== field.id && REFERENCEABLE.includes( f.type ) );

	const insertToken = ( id ) => {
		if ( ! id ) {
			return;
		}
		const token = `{${ id }}`;
		const el = inputRef.current;
		if ( el && typeof el.selectionStart === 'number' ) {
			const pos = el.selectionStart;
			set( { expression: expression.slice( 0, pos ) + token + expression.slice( el.selectionEnd ) } );
		} else {
			set( { expression: expression + token } );
		}
	};

	return (
		<div className="alc-formula">
			<TextareaControl
				ref={ inputRef }
				label={ __( 'Formula', 'alovio-calculator' ) }
				help={ __( 'Reference fields as {field}, use + - * / ( ) and if(condition, then, else), min, max (2–8 args), round(x, decimals), ceil, floor, abs. Write 0.5, not .5.', 'alovio-calculator' ) }
				value={ expression }
				onChange={ ( expression ) => set( { expression } ) }
				rows={ 3 }
			/>
			<SelectControl
				label={ __( 'Insert field', 'alovio-calculator' ) }
				value=""
				options={ [
					{ label: __( '— pick a field to insert —', 'alovio-calculator' ), value: '' },
					...insertable.map( ( f ) => ( { label: `${ f.label || f.type } {${ f.id }}`, value: f.id } ) ),
				] }
				onChange={ insertToken }
			/>
			{ expression.trim() !== '' && ! result.ok && (
				<Notice status="warning" isDismissible={ false }>
					{ result.error.message }
					{ result.error.pos >= 0 && ` ${ __( '(at position', 'alovio-calculator' ) } ${ result.error.pos })` }
				</Notice>
			) }
			{ expression.trim() !== '' && result.ok && (
				<p className="alc-formula__ok">{ __( '✓ Formula OK', 'alovio-calculator' ) }</p>
			) }
		</div>
	);
}
