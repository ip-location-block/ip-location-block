/**
 * Thin React wrapper around the bundled Leaflet (window.L). Renders a single
 * marker at the given coordinates.
 */
import { useEffect, useRef } from '@wordpress/element';

export default function MapView( { lat, lng, zoom = 6 } ) {
	const el = useRef();
	const map = useRef();
	const marker = useRef();

	useEffect( () => {
		const L = window.L;
		if ( ! L || ! el.current ) {
			return;
		}
		if ( ! map.current ) {
			map.current = L.map( el.current ).setView( [ lat, lng ], zoom );
			// CARTO Voyager basemap (OSM data): keyless raster tiles with a much
			// cleaner cartography than the OSM standard style. {r} serves retina
			// tiles on high-DPI screens.
			L.tileLayer( 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
				attribution:
					'&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noreferrer">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions" target="_blank" rel="noreferrer">CARTO</a>',
				subdomains: 'abcd',
				maxZoom: 20,
			} ).addTo( map.current );
		} else {
			map.current.setView( [ lat, lng ], zoom );
		}
		if ( marker.current ) {
			marker.current.remove();
		}
		marker.current = L.marker( [ lat, lng ] ).addTo( map.current );
	}, [ lat, lng, zoom ] );

	useEffect(
		() => () => {
			if ( map.current ) {
				map.current.remove();
				map.current = null;
			}
		},
		[]
	);

	return <div ref={ el } className="ilb-map" />;
}
