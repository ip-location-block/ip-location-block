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
			L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '&copy; OpenStreetMap contributors',
				maxZoom: 18,
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
