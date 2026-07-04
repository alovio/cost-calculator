import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';


/**
 * The single upsell surface in the entire plugin (Guideline 11: sparing,
 * contextual, settings-page-confined). Static content, no nags elsewhere.
 */
export default function ProTab() {
	const features = [
		__( 'Multi-step wizard layout', 'alovio-calculator' ),
		__( 'Branded PDF quotes (download & email, logo, tax/VAT)', 'alovio-calculator' ),
		__( 'Webhooks & Zapier', 'alovio-calculator' ),
		__( 'Quote analytics dashboard', 'alovio-calculator' ),
	];

	return (
		<div className="alc-pro-tab">
			<h3>{ __( 'Alovio Calculator Pro', 'alovio-calculator' ) }</h3>
			<p>{ __( 'Everything you use today stays free — including conditional logic. Pro adds:', 'alovio-calculator' ) }</p>
			<ul>
				{ features.map( ( f ) => (
					<li key={ f }>• { f }</li>
				) ) }
			</ul>
			<ExternalLink href="https://alovio.org/store/calculator-pro">{ __( 'Get Alovio Calculator Pro', 'alovio-calculator' ) }</ExternalLink>
		</div>
	);
}
