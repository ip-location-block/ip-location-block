/**
 * Search tab — geolocate a single IP against a chosen provider and plot it.
 * Mirrors the classic "Search geolocation" tool: input + provider picker →
 * a details panel of every field the provider returned + a Leaflet marker.
 */
import { useEffect, useState } from '@wordpress/element';
import {
	Card,
	CardBody,
	TextControl,
	SelectControl,
	Button,
	Notice,
	Flex,
	FlexItem,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { searchIp, getProviders } from '../api';
import MapView from '../components/MapView';

// Human labels for the common provider keys; anything else falls back to its
// raw key so nothing the provider returns is hidden (classic parity).
const LABELS = {
	countryCode: __( 'Country code', 'ip-location-block' ),
	countryName: __( 'Country', 'ip-location-block' ),
	regionName: __( 'Region', 'ip-location-block' ),
	stateName: __( 'State', 'ip-location-block' ),
	cityName: __( 'City', 'ip-location-block' ),
	latitude: __( 'Latitude', 'ip-location-block' ),
	longitude: __( 'Longitude', 'ip-location-block' ),
	zipCode: __( 'ZIP code', 'ip-location-block' ),
	timeZone: __( 'Time zone', 'ip-location-block' ),
	asn: __( 'AS number', 'ip-location-block' ),
	dns: __( 'Host name', 'ip-location-block' ),
	geoTime: __( 'Geolocation lookup', 'ip-location-block' ),
	asnTime: __( 'ASN lookup', 'ip-location-block' ),
	dnsTime: __( 'DNS lookup', 'ip-location-block' ),
};

const label = ( key ) => LABELS[ key ] || key;

export default function Search() {
	const [ ip, setIp ] = useState( '' );
	const [ provider, setProvider ] = useState( '' );
	const [ providers, setProviders ] = useState( [] );
	const [ result, setResult ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		getProviders()
			.then( ( list ) => {
				const names = ( list || [] ).map( ( p ) => p.name || p );
				setProviders( names );
				if ( names.length ) {
					setProvider( names[ 0 ] );
				}
			} )
			.catch( () => {} );
	}, [] );

	const runSearch = () => {
		if ( ! ip.trim() ) {
			return;
		}
		setBusy( true );
		setError( null );
		setResult( null );
		searchIp( ip.trim(), provider )
			.then( ( res ) => {
				if ( res && res.errorMessage ) {
					setError( res.errorMessage );
				} else {
					setResult( res || {} );
				}
			} )
			.catch( ( e ) => setError( e.message || String( e ) ) )
			.finally( () => setBusy( false ) );
	};

	const lat = result ? Number( result.latitude ) : NaN;
	const lng = result ? Number( result.longitude ) : NaN;
	const hasCoords =
		result &&
		! Number.isNaN( lat ) &&
		! Number.isNaN( lng ) &&
		( lat !== 0 || lng !== 0 );

	const entries = result
		? Object.keys( result ).filter(
				( k ) => result[ k ] !== '' && result[ k ] !== null && result[ k ] !== undefined
		  )
		: [];

	return (
		<div className="ilb-search">
			<Card>
				<CardBody>
					<Flex align="flex-end" gap={ 3 } wrap>
						<FlexItem isBlock>
							<TextControl
								__nextHasNoMarginBottom
								label={ __( 'IP address', 'ip-location-block' ) }
								value={ ip }
								onChange={ setIp }
								placeholder="8.8.8.8"
								onKeyDown={ ( e ) => e.key === 'Enter' && runSearch() }
							/>
						</FlexItem>
						<FlexItem>
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Provider', 'ip-location-block' ) }
								value={ provider }
								options={ providers.map( ( name ) => ( {
									label: name,
									value: name,
								} ) ) }
								onChange={ setProvider }
							/>
						</FlexItem>
						<FlexItem>
							<Button
								variant="primary"
								isBusy={ busy }
								disabled={ busy || ! ip.trim() }
								onClick={ runSearch }
							>
								{ __( 'Search', 'ip-location-block' ) }
							</Button>
						</FlexItem>
					</Flex>
				</CardBody>
			</Card>

			{ error && (
				<Notice
					status="warning"
					isDismissible
					onRemove={ () => setError( null ) }
					className="ilb-search__notice"
				>
					{ error }
				</Notice>
			) }

			{ busy && ! result && (
				<div className="ilb-search__loading">
					<Spinner />
				</div>
			) }

			{ result && (
				<div className="ilb-search__results">
					<Card>
						<CardBody>
							<dl className="ilb-search__details">
								{ entries.map( ( k ) => (
									<div key={ k } className="ilb-search__row">
										<dt>{ label( k ) }</dt>
										<dd>{ String( result[ k ] ) }</dd>
									</div>
								) ) }
							</dl>
						</CardBody>
					</Card>

					{ hasCoords && (
						<Card className="ilb-search__map-card">
							<CardBody>
								<MapView lat={ lat } lng={ lng } zoom={ 7 } />
							</CardBody>
						</Card>
					) }
				</div>
			) }
		</div>
	);
}
