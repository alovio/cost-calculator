import { useDispatch, useSelect } from '@wordpress/data';
import { SelectControl, ColorPicker, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';
import { THEME_PRESETS } from '../CanvasToolbar';

export default function CalcDesign() {
	const settings = useSelect( ( select ) => select( STORE ).getSettings(), [] );
	const { updateSettings } = useDispatch( STORE );
	const theme = settings.theme || {};
	const isPro = !! ( window.ALOVIO_CALC_BUILDER && window.ALOVIO_CALC_BUILDER.isPro );

	return (
		<>
			<span className="alcb-sec-label">{ __( 'Appearance', 'alovio-calculator' ) }</span>
			<SelectControl
				label={ __( 'Theme', 'alovio-calculator' ) }
				help={ __( 'A ready-made look — pick one and tweak the accent below. No CSS needed.', 'alovio-calculator' ) }
				value={ theme.preset || 'classic' }
				options={ THEME_PRESETS }
				onChange={ ( preset ) => updateSettings( { theme: { ...theme, preset } } ) }
			/>
			<p className="alcb-hint">{ __( 'Accent color (buttons, slider, total).', 'alovio-calculator' ) }</p>
			<ColorPicker
				color={ theme.accent || '#f97316' }
				onChange={ ( accent ) => updateSettings( { theme: { ...theme, accent } } ) }
				enableAlpha={ false }
			/>
			<span className="alcb-sec-label">{ __( 'Layout', 'alovio-calculator' ) }</span>
			{ isPro ? (
				<SelectControl
					label={ __( 'Form display', 'alovio-calculator' ) }
					help={ __( 'Wizard splits the form into steps at each Step / Section divider.', 'alovio-calculator' ) }
					value={ theme.layout || 'single' }
					options={ [
						{ label: __( 'Single page', 'alovio-calculator' ), value: 'single' },
						{ label: __( 'Multi-step wizard', 'alovio-calculator' ), value: 'wizard' },
					] }
					onChange={ ( layout ) => updateSettings( { theme: { ...theme, layout } } ) }
				/>
			) : (
				<Notice status="info" isDismissible={ false }>
					{ __( 'Multi-step wizard is a Pro feature.', 'alovio-calculator' ) }
				</Notice>
			) }
		</>
	);
}
