/**
 * IP Location Block — React (Beta) admin entry point.
 *
 * Mounts the React app into the container rendered by the Beta admin page.
 * Imports from @wordpress/* are externalized at build time by
 * @wordpress/scripts to the wp.* globals WordPress already loads, so the
 * bundle ships no React/runtime of its own.
 */
import { createRoot } from '@wordpress/element';

import App from './App';
import './style.scss';

const container = document.getElementById( 'ip-location-block-app' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
