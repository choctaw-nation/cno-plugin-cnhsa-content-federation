import './styles.scss';
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import App from './components/App';

function initApp() {
	const el = document.getElementById( 'cnhsa-federation-app' );
	if ( ! el ) {
		throw new Error( 'App root element not found' );
	}

	const root = createRoot( el );
	root.render(
		<App nonce={ el.dataset.nonce! } restUrl={ el.dataset.restUrl! } />
	);
}
domReady( initApp );
