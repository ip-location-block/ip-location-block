/**
 * Statistics tab — summary tiles + zero-dependency SVG/HTML charts
 * (blocked-by-country, daily blocked-by-hook) from GET /statistics.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { Card, CardBody, Button, Notice, Spinner, Flex } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getStatistics, clearStatistics } from '../api';
import { CountryBars, DailyStacked } from '../components/Charts';

const Tile = ( { label, value, icon, accent } ) => (
	<Card
		size="small"
		className="ilb-stat-tile"
		style={ accent ? { '--tile-accent': accent } : undefined }
	>
		<CardBody>
			<div className="ilb-stat-tile__head">
				<span className={ `dashicons dashicons-${ icon }` } aria-hidden="true" />
			</div>
			<div className="ilb-stat-tile__value">{ value }</div>
			<div className="ilb-stat-tile__label">{ label }</div>
		</CardBody>
	</Card>
);

export default function Statistics() {
	const [ stat, setStat ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ notice, setNotice ] = useState( null );

	const load = useCallback( () => {
		setLoading( true );
		getStatistics()
			.then( ( s ) => setStat( s ) )
			.catch( ( e ) => setNotice( { status: 'error', msg: e.message } ) )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	if ( loading ) {
		return <Spinner />;
	}
	if ( ! stat ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __( 'Failed to load statistics.', 'ip-location-block' ) }
			</Notice>
		);
	}

	const clear = () => {
		setNotice( null );
		clearStatistics()
			.then( () => {
				setNotice( { status: 'success', msg: __( 'Statistics cleared.', 'ip-location-block' ) } );
				load();
			} )
			.catch( ( e ) => setNotice( { status: 'error', msg: e.message } ) );
	};

	return (
		<div className="ilb-stats ilb-stack">
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }

			<Flex justify="flex-start" gap={ 3 } wrap className="ilb-stat-tiles">
				<Tile label={ __( 'Blocked', 'ip-location-block' ) } value={ stat.blocked } icon="shield" accent="var(--ilb-accent)" />
				<Tile label={ __( 'IPv4', 'ip-location-block' ) } value={ stat.ipv4 } icon="admin-site-alt3" accent="#2a78d6" />
				<Tile label={ __( 'IPv6', 'ip-location-block' ) } value={ stat.ipv6 } icon="admin-site-alt" accent="#3858e9" />
				<Tile label={ __( 'Unknown', 'ip-location-block' ) } value={ stat.unknown } icon="editor-help" accent="var(--ilb-muted)" />
			</Flex>

			<Card>
				<CardBody>
					<h3 className="ilb-section-title">
						<span className="dashicons dashicons-admin-site" aria-hidden="true" />
						{ __( 'Blocked by country', 'ip-location-block' ) }
					</h3>
					<CountryBars data={ stat.countries } />
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					<h3 className="ilb-section-title">
						<span className="dashicons dashicons-chart-bar" aria-hidden="true" />
						{ __( 'Daily blocked requests', 'ip-location-block' ) }
					</h3>
					<DailyStacked data={ stat.daily } />
				</CardBody>
			</Card>

			<div>
				<Button variant="secondary" isDestructive onClick={ clear }>
					{ __( 'Clear statistics', 'ip-location-block' ) }
				</Button>
			</div>
		</div>
	);
}
