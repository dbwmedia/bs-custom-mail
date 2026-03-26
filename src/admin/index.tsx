/**
 * Admin entry point
 */
import { createRoot } from '@wordpress/element';
import { App } from './App';

// Wait for DOM to be ready
document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'bs-custom-mail-admin-app' );
	if ( container ) {
		const root = createRoot( container );
		root.render( <App /> );
	}
} );
