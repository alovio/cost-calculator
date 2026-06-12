import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';


/**
 * The single upsell surface in the entire plugin (Guideline 11: sparing,
 * contextual, settings-page-confined). Static content, no nags elsewhere.
 */
export default function ProTab() {
	const features = [
		__( 'Multi-step wizard layout', 'alovio-calculator' ),
		__( 'PDF quotes (download & email)', 'alovio-calculator' ),
		__( 'Repeater / group fields', 'alovio-calculator' ),
		__( 'Image option styles', 'alovio-calculator' ),
		__( 'Webhooks & Zapier', 'alovio-calculator' ),
		__( 'Quote analytics', 'alovio-calculator' ),
	];

	return (
		<div className="alc-pro-tab">
			<h3>{ __( 'Alovio Calculator Pro — coming soon', 'alovio-calculator' ) }</h3>
			<p>{ __( 'Everything you use today stays free — including conditional logic. Pro adds:', 'alovio-calculator' ) }</p>
			<ul>
				{ features.map( ( f ) => (
					<li key={ f }>• { f }</li>
				) ) }
			</ul>
			<ExternalLink href="https://alovio.org/calculator">{ __( 'Learn more', 'alovio-calculator' ) }</ExternalLink>
		</div>
	);
}
