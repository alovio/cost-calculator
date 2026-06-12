import { ExternalLink } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const T = 'alovio-calculator';

/**
 * The single upsell surface in the entire plugin (Guideline 11: sparing,
 * contextual, settings-page-confined). Static content, no nags elsewhere.
 */
export default function ProTab() {
	const features = [
		__( 'Multi-step wizard layout', T ),
		__( 'PDF quotes (download & email)', T ),
		__( 'Repeater / group fields', T ),
		__( 'Image option styles', T ),
		__( 'Webhooks & Zapier', T ),
		__( 'Quote analytics', T ),
	];

	return (
		<div className="alc-pro-tab">
			<h3>{ __( 'Alovio Calculator Pro — coming soon', T ) }</h3>
			<p>{ __( 'Everything you use today stays free — including conditional logic. Pro adds:', T ) }</p>
			<ul>
				{ features.map( ( f ) => (
					<li key={ f }>• { f }</li>
				) ) }
			</ul>
			<ExternalLink href="https://alovio.org/calculator">{ __( 'Learn more', T ) }</ExternalLink>
		</div>
	);
}
