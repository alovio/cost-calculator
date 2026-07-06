import { createRoot } from '@wordpress/element';
import App from './builder/App';
import '../assets/css/builder.css';
import './builder/builder.scss';

const node = document.getElementById( 'alc-builder-root' );
if ( node ) {
	createRoot( node ).render( <App /> );
}
