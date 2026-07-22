/**
 * Search tab — geolocate an IP against the configured providers and plot it.
 * Mirrors the classic "Search geolocation" tool: input + a provider picker that
 * lists only configured providers, a per-provider result card, an optional map,
 * and a transient "anonymize" toggle that masks displayed IPs.
 */
import { useEffect, useState } from '@wordpress/element';
import {
	Card,
	CardHeader,
	CardBody,
	TextControl,
	CheckboxControl,
	ToggleControl,
	Button,
	Notice,
	Flex,
	FlexItem,
	Spinner,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { searchIpMulti, getProviders, getSettings } from '../api';
import MapView from '../components/MapView';
import { queryParam } from '../navigation';
import {
	configuredProviders,
	defaultSelectedProviders,
	defaultAnonymize,
	maskIp,
} from './searchLogic';

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

// Values that look like an IP/host get masked; timings and codes never do.
const MASKED_KEYS = new Set( [ 'dns' ] );

const presetIp = () =>
	queryParam( 's' )
		.replace( /\.\*+$/, '.0' )
		.replace( /:\w*\*+$/, '::' )
		.replace( /:{3,}$/, '::' );

function ResultCard( { name, result, ip, anonymize } ) {
	const errorMessage = result && result.errorMessage;
	const shownIp = anonymize ? maskIp( ip ) : ip;

	const lat = result ? Number( result.latitude ) : NaN;
	const lng = result ? Number( result.longitude ) : NaN;
	const hasCoords =
		! errorMessage &&
		! Number.isNaN( lat ) &&
		! Number.isNaN( lng ) &&
		( lat !== 0 || lng !== 0 );

	const entries = result
		? Object.keys( result ).filter(
				( key ) =>
					key !== 'errorMessage' &&
					result[ key ] !== '' &&
					result[ key ] !== null &&
					result[ key ] !== undefined
		  )
		: [];

	const display = ( key ) => {
		const value = String( result[ key ] );
		return anonymize && MASKED_KEYS.has( key ) ? maskIp( value ) : value;
	};

	return (
		<Card className="ilb-panel-shell ilb-search__result-card">
			<CardHeader className="ilb-panel-shell__header">
				<h2 className="ilb-panel-shell__title">{ name }</h2>
				<span className="ilb-search__result-ip">{ shownIp }</span>
			</CardHeader>
			<CardBody>
				{ errorMessage ? (
					<Notice status="warning" isDismissible={ false }>
						{ errorMessage }
					</Notice>
				) : (
					<>
						<dl className="ilb-search__details">
							{ entries.map( ( key ) => (
								<div key={ key } className="ilb-search__row">
									<dt>{ label( key ) }</dt>
									<dd>{ display( key ) }</dd>
								</div>
							) ) }
						</dl>
						{ hasCoords && (
							<div className="ilb-search__map-card">
								<MapView lat={ lat } lng={ lng } zoom={ 7 } />
							</div>
						) }
					</>
				) }
			</CardBody>
		</Card>
	);
}

export default function Search() {
	const [ ip, setIp ] = useState( presetIp );
	const [ providers, setProviders ] = useState( [] ); // [{name,disabled,reason}]
	const [ selected, setSelected ] = useState( [] );
	const [ anonymize, setAnonymize ] = useState( false );
	const [ results, setResults ] = useState( null ); // [{provider,result}]
	const [ searchedIp, setSearchedIp ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		Promise.all( [
			getProviders().catch( () => [] ),
			getSettings().catch( () => null ),
		] ).then( ( [ list, settings ] ) => {
			const configured = configuredProviders( list );
			setProviders( configured );
			setSelected( defaultSelectedProviders( list ) );
			setAnonymize( defaultAnonymize( settings ) );
		} );
	}, [] );

	const toggleProvider = ( name, checked ) =>
		setSelected( ( current ) =>
			checked
				? [ ...current, name ]
				: current.filter( ( item ) => item !== name )
		);

	const runSearch = () => {
		if ( ! ip.trim() || ! selected.length ) {
			return;
		}
		setBusy( true );
		setError( null );
		setResults( null );
		const query = ip.trim();
		searchIpMulti( query, selected )
			.then( ( res ) => {
				setSearchedIp( query );
				setResults( Array.isArray( res?.results ) ? res.results : [] );
			} )
			.catch( ( e ) => setError( e.message || String( e ) ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="ilb-search">
			<Card className="ilb-panel-shell ilb-search__form-card">
				<CardHeader className="ilb-panel-shell__header">
					<h2 className="ilb-panel-shell__title">
						{ __( 'Search geolocation', 'ip-location-block' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<Flex align="flex-end" gap={ 3 } wrap>
						<FlexItem isBlock>
							<TextControl
								__nextHasNoMarginBottom
								label={ __(
									'IP address',
									'ip-location-block'
								) }
								value={ ip }
								onChange={ setIp }
								placeholder="8.8.8.8"
								onKeyDown={ ( e ) =>
									e.key === 'Enter' && runSearch()
								}
							/>
						</FlexItem>
						<FlexItem>
							<Button
								variant="primary"
								isBusy={ busy }
								disabled={
									busy || ! ip.trim() || ! selected.length
								}
								onClick={ runSearch }
							>
								{ __( 'Search', 'ip-location-block' ) }
							</Button>
						</FlexItem>
					</Flex>

					<fieldset className="ilb-search__providers">
						<legend>
							{ __( 'Providers', 'ip-location-block' ) }
						</legend>
						{ providers.length === 0 ? (
							<p className="ilb-search__providers-empty">
								{ __(
									'No providers are configured. Select a provider in Settings first.',
									'ip-location-block'
								) }
							</p>
						) : (
							<div className="ilb-search__providers-grid">
								{ providers.map( ( provider ) => (
									<CheckboxControl
										__nextHasNoMarginBottom
										key={ provider.name }
										label={
											provider.disabled
												? sprintf(
														/* translators: %s: provider name. */
														__(
															'%s (database not downloaded)',
															'ip-location-block'
														),
														provider.name
												  )
												: provider.name
										}
										checked={ selected.includes(
											provider.name
										) }
										disabled={ provider.disabled }
										onChange={ ( checked ) =>
											toggleProvider(
												provider.name,
												checked
											)
										}
									/>
								) ) }
							</div>
						) }
					</fieldset>

					<ToggleControl
						__nextHasNoMarginBottom
						className="ilb-search__anonymize"
						label={ __(
							'Anonymize IP address',
							'ip-location-block'
						) }
						help={ __(
							'Mask displayed IP addresses in the results below. This only affects this preview.',
							'ip-location-block'
						) }
						checked={ anonymize }
						onChange={ setAnonymize }
					/>
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

			{ busy && ! results && (
				<div className="ilb-search__loading">
					<Spinner />
				</div>
			) }

			{ results && (
				<div className="ilb-search__results">
					{ results.length === 0 ? (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'No providers returned a result.',
								'ip-location-block'
							) }
						</Notice>
					) : (
						results.map( ( row ) => (
							<ResultCard
								key={ row.provider }
								name={ row.provider }
								result={ row.result || {} }
								ip={ searchedIp }
								anonymize={ anonymize }
							/>
						) )
					) }
				</div>
			) }
		</div>
	);
}
