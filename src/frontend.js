import './frontend/frontend-style.scss';
import { init, initCalculators } from './frontend/calculator';

// Studio contract: the builder re-initialises injected canvas fragments through
// this global — the two bundles cannot share module instances (spec §2.2).
window.AlovioCalc = Object.assign( window.AlovioCalc || {}, { init, initAll: initCalculators } );

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => initCalculators( document ) );
} else {
	initCalculators( document );
}
