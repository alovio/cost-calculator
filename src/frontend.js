import './frontend/frontend-style.scss';
import { initCalculators } from './frontend/calculator';

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => initCalculators( document ) );
} else {
	initCalculators( document );
}
