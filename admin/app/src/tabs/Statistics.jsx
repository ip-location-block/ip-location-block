/**
 * Statistics tab — modern presentation of the three classic statistics areas:
 * validation metrics, validation-log summaries, and the IP cache browser.
 */
/* eslint-disable no-nested-ternary */
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	Card,
	CardBody,
	Button,
	Notice,
	Spinner,
	Flex,
	Modal,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	getStatistics,
	clearStatistics,
	getLogStatistics,
	clearLogs,
	getCache,
	clearCache,
	eraseCacheEntries,
	addToList,
} from '../api';
import { betaUrl } from '../navigation';
import DataTable from '../components/DataTable';
import { CountryBars, DailyStacked } from '../components/Charts';

const Tile = ( { label, value, icon } ) => (
	<Card size="small" className="ilb-stat-tile">
		<CardBody>
			<div className="ilb-stat-tile__head">
				<span
					className={ `dashicons dashicons-${ icon }` }
					aria-hidden="true"
				/>
			</div>
			<div className="ilb-stat-tile__value">{ value }</div>
			<div className="ilb-stat-tile__label">{ label }</div>
		</CardBody>
	</Card>
);

const EmptyPanel = ( { children } ) => (
	<div className="ilb-stats__empty-panel">
		<span className="dashicons dashicons-info-outline" aria-hidden="true" />
		<div>
			<p>{ children }</p>
			<Button
				variant="secondary"
				href={ betaUrl( {
					tab: 'settings',
					view: 'advanced',
					section: 'recording',
					s: null,
				} ) }
			>
				{ __( 'Open recording settings', 'ip-location-block' ) }
			</Button>
		</div>
	</div>
);

const LoadingBlock = () => (
	<div className="ilb-stats__loading">
		<Spinner />
	</div>
);

const ProviderTable = ( { rows } ) => (
	<Card className="ilb-stats__provider-card">
		<CardBody>
			<h3 className="ilb-section-title">
				<span
					className="dashicons dashicons-performance"
					aria-hidden="true"
				/>
				{ __( 'Provider performance', 'ip-location-block' ) }
			</h3>
			{ rows?.length ? (
				<div className="ilb-stats__table-scroll">
					<table className="widefat striped ilb-stats__provider-table">
						<thead>
							<tr>
								<th>
									{ __( 'Provider', 'ip-location-block' ) }
								</th>
								<th>{ __( 'Calls', 'ip-location-block' ) }</th>
								<th>
									{ __(
										'Average response',
										'ip-location-block'
									) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ rows.map( ( row ) => (
								<tr key={ row.name }>
									<td>{ row.name }</td>
									<td>
										{ Number(
											row.calls || 0
										).toLocaleString() }
									</td>
									<td>
										{ sprintf(
											/* translators: %s: milliseconds */
											__( '%s ms', 'ip-location-block' ),
											Number(
												row.averageMs || 0
											).toLocaleString( undefined, {
												minimumFractionDigits: 1,
												maximumFractionDigits: 1,
											} )
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) : (
				<p className="ilb-stats__muted">
					{ __(
						'No provider timing data yet.',
						'ip-location-block'
					) }
				</p>
			) }
		</CardBody>
	</Card>
);

const RankedList = ( { title, rows, sortMode, onToggleSort } ) => {
	const displayed = useMemo( () => {
		if ( sortMode !== 'alpha' ) {
			return rows || [];
		}
		return [ ...( rows || [] ) ].sort( ( a, b ) =>
			String( a.value ).localeCompare( String( b.value ), undefined, {
				numeric: true,
				sensitivity: 'base',
			} )
		);
	}, [ rows, sortMode ] );

	return (
		<Card className="ilb-ranked-card">
			<CardBody>
				<div className="ilb-ranked-card__header">
					<h3>{ title }</h3>
					{ onToggleSort && (
						<Button variant="tertiary" onClick={ onToggleSort }>
							{ sortMode === 'alpha'
								? __( 'Sort by count', 'ip-location-block' )
								: __( 'Sort A–Z', 'ip-location-block' ) }
						</Button>
					) }
				</div>
				{ displayed.length ? (
					<ol className="ilb-ranked-list">
						{ displayed.map( ( row, index ) => (
							<li key={ `${ row.value }-${ index }` }>
								<a
									href={ betaUrl( {
										tab: 'logs',
										s: row.search || row.value,
										view: null,
										section: null,
									} ) }
								>
									{ row.value }
								</a>
								<span>
									{ Number(
										row.count || 0
									).toLocaleString() }
								</span>
							</li>
						) ) }
					</ol>
				) : (
					<p className="ilb-stats__muted">
						{ __( 'No log data yet.', 'ip-location-block' ) }
					</p>
				) }
			</CardBody>
		</Card>
	);
};

const cacheColumns = [
	{
		key: 'ip',
		header: __( 'IP address', 'ip-location-block' ),
		render: ( row ) => (
			<a
				href={ betaUrl( {
					tab: 'search',
					s: row.ip,
					view: null,
					section: null,
				} ) }
			>
				{ row.ip }
			</a>
		),
	},
	{ key: 'code', header: __( 'Country', 'ip-location-block' ) },
	{ key: 'city', header: __( 'City', 'ip-location-block' ), priority: 3 },
	{ key: 'state', header: __( 'State', 'ip-location-block' ), priority: 3 },
	{ key: 'asn', header: __( 'ASN', 'ip-location-block' ), priority: 2 },
	{
		key: 'host',
		header: __( 'Host name', 'ip-location-block' ),
		priority: 3,
	},
	{ key: 'target', header: __( 'Target', 'ip-location-block' ) },
	{
		key: 'requests',
		header: __( 'Failures / Requests', 'ip-location-block' ),
		render: ( row ) => `${ row.failures } / ${ row.requests }`,
	},
	{
		key: 'elapsed',
		header: __( 'Age', 'ip-location-block' ),
		render: ( row ) => {
			const seconds = Number( row.elapsed || 0 );
			if ( seconds < 60 ) {
				/* translators: %d: elapsed seconds. */
				return sprintf( __( '%ds', 'ip-location-block' ), seconds );
			}
			if ( seconds < 3600 ) {
				return sprintf(
					/* translators: %d: elapsed minutes. */
					__( '%dm', 'ip-location-block' ),
					Math.floor( seconds / 60 )
				);
			}
			if ( seconds < 86400 ) {
				return sprintf(
					/* translators: %d: elapsed hours. */
					__( '%dh', 'ip-location-block' ),
					Math.floor( seconds / 3600 )
				);
			}
			return sprintf(
				/* translators: %d: elapsed days. */
				__( '%dd', 'ip-location-block' ),
				Math.floor( seconds / 86400 )
			);
		},
	},
];

const csvCell = ( value ) =>
	`"${ String( value ?? '' ).replace( /"/g, '""' ) }"`;

export default function Statistics() {
	const [ stat, setStat ] = useState( null );
	const [ logStat, setLogStat ] = useState( null );
	const [ cacheRows, setCacheRows ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ logLoading, setLogLoading ] = useState( false );
	const [ cacheLoading, setCacheLoading ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ confirm, setConfirm ] = useState( null );
	const [ slugSort, setSlugSort ] = useState( 'count' );
	const [ open, setOpen ] = useState( {
		validation: true,
		logs: false,
		cache: false,
	} );

	const load = useCallback( () => {
		setLoading( true );
		return getStatistics()
			.then( setStat )
			.catch( ( error ) => {
				setNotice( { status: 'error', msg: error.message } );
				throw error;
			} )
			.finally( () => setLoading( false ) );
	}, [] );

	const loadLogStat = useCallback( () => {
		setLogLoading( true );
		return getLogStatistics()
			.then( setLogStat )
			.catch( ( error ) => {
				setNotice( { status: 'error', msg: error.message } );
				throw error;
			} )
			.finally( () => setLogLoading( false ) );
	}, [] );

	const loadCache = useCallback( () => {
		setCacheLoading( true );
		return getCache()
			.then( ( data ) =>
				setCacheRows( Array.isArray( data?.rows ) ? data.rows : [] )
			)
			.catch( ( error ) => {
				setNotice( { status: 'error', msg: error.message } );
				throw error;
			} )
			.finally( () => setCacheLoading( false ) );
	}, [] );

	useEffect( () => {
		load().catch( () => {} );
	}, [ load ] );

	const runAction = ( action, successMessage, refresh ) => {
		setNotice( null );
		return Promise.resolve()
			.then( action )
			.then( () => ( refresh ? refresh() : null ) )
			.then( () =>
				setNotice( { status: 'success', msg: successMessage } )
			)
			.catch( ( error ) => {
				if ( ! error?.cancelled ) {
					setNotice( {
						status: 'error',
						msg: error?.message || String( error ),
					} );
				}
				throw error;
			} );
	};

	const askConfirm = ( title, message, confirmLabel, action ) =>
		new Promise( ( resolve, reject ) => {
			setConfirm( {
				title,
				message,
				confirmLabel,
				onConfirm: () => {
					setConfirm( null );
					Promise.resolve().then( action ).then( resolve, reject );
				},
				onCancel: () => {
					setConfirm( null );
					reject( { cancelled: true } );
				},
			} );
		} );

	const toggleSection = ( key, next ) => {
		setOpen( ( current ) => ( { ...current, [ key ]: next } ) );
		if (
			next &&
			key === 'logs' &&
			stat?.features?.logs &&
			logStat === null
		) {
			loadLogStat().catch( () => {} );
		}
		if (
			next &&
			key === 'cache' &&
			stat?.features?.cache &&
			cacheRows === null
		) {
			loadCache().catch( () => {} );
		}
	};

	const toggleAll = () => {
		const next = ! Object.values( open ).every( Boolean );
		setOpen( { validation: next, logs: next, cache: next } );
		if ( next && stat?.features?.logs && logStat === null ) {
			loadLogStat().catch( () => {} );
		}
		if ( next && stat?.features?.cache && cacheRows === null ) {
			loadCache().catch( () => {} );
		}
	};

	const exportCache = () => {
		const headers = [
			__( 'IP address', 'ip-location-block' ),
			__( 'Code', 'ip-location-block' ),
			__( 'City', 'ip-location-block' ),
			__( 'State', 'ip-location-block' ),
			__( 'ASN', 'ip-location-block' ),
			__( 'Host name', 'ip-location-block' ),
			__( 'Target', 'ip-location-block' ),
			__( 'Failure / Total', 'ip-location-block' ),
			__( 'Elapsed [sec]', 'ip-location-block' ),
		];
		const csv = [ headers.map( csvCell ).join( ',' ) ]
			.concat(
				( cacheRows || [] ).map( ( row ) =>
					[
						row.ip,
						row.code,
						row.city,
						row.state,
						row.asn,
						row.host,
						row.target,
						`${ row.failures } / ${ row.requests }`,
						row.elapsed,
					]
						.map( csvCell )
						.join( ',' )
				)
			)
			.join( '\n' );
		const url = window.URL.createObjectURL(
			new window.Blob( [ `\ufeff${ csv }` ], {
				type: 'text/csv;charset=utf-8',
			} )
		);
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = `ip-location-block_${ new Date()
			.toISOString()
			.replace( /[:T]/g, '-' )
			.slice( 0, 19 ) }.csv`;
		link.click();
		window.URL.revokeObjectURL( url );
	};

	if ( loading ) {
		return <LoadingBlock />;
	}
	if ( ! stat ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __( 'Failed to load statistics.', 'ip-location-block' ) }
			</Notice>
		);
	}

	const selectedRows = ( ids ) => {
		const selected = new Set( ids );
		return ( cacheRows || [] ).filter( ( row ) => selected.has( row.id ) );
	};
	const ipsFor = ( ids ) =>
		selectedRows( ids )
			.map( ( row ) => row.ip )
			.filter( Boolean );
	const asnsFor = ( ids ) =>
		selectedRows( ids )
			.map( ( row ) => row.asn )
			.filter( Boolean );

	const cacheBulkActions = [
		{
			label: __( 'Add IPs to whitelist', 'ip-location-block' ),
			onClick: ( ids ) =>
				runAction(
					() => addToList( 'white', ipsFor( ids ), 'ip' ),
					__(
						'IP addresses added to the whitelist.',
						'ip-location-block'
					)
				),
		},
		{
			label: __( 'Add IPs to blacklist', 'ip-location-block' ),
			onClick: ( ids ) =>
				runAction(
					() => addToList( 'black', ipsFor( ids ), 'ip' ),
					__(
						'IP addresses added to the blacklist.',
						'ip-location-block'
					)
				),
		},
		...( stat.features?.asn
			? [
					{
						label: __(
							'Add ASNs to whitelist',
							'ip-location-block'
						),
						onClick: ( ids ) =>
							runAction(
								() =>
									addToList( 'white', asnsFor( ids ), 'asn' ),
								__(
									'ASNs added to the whitelist.',
									'ip-location-block'
								)
							),
					},
					{
						label: __(
							'Add ASNs to blacklist',
							'ip-location-block'
						),
						onClick: ( ids ) =>
							runAction(
								() =>
									addToList( 'black', asnsFor( ids ), 'asn' ),
								__(
									'ASNs added to the blacklist.',
									'ip-location-block'
								)
							),
					},
			  ]
			: [] ),
		{
			label: __( 'Remove from cache', 'ip-location-block' ),
			destructive: true,
			onClick: ( ids ) =>
				askConfirm(
					__( 'Remove cache entries?', 'ip-location-block' ),
					sprintf(
						/* translators: %d: selected cache entries */
						__(
							'This will permanently remove %d selected cache entries.',
							'ip-location-block'
						),
						ids.length
					),
					__( 'Remove entries', 'ip-location-block' ),
					() =>
						runAction(
							() => eraseCacheEntries( ids ),
							__( 'Cache entries removed.', 'ip-location-block' ),
							loadCache
						)
				),
		},
	];

	const allOpen = Object.values( open ).every( Boolean );

	return (
		<div className="ilb-stats ilb-stack">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.msg }
				</Notice>
			) }

			<div className="ilb-stats__panel-toolbar">
				<Button variant="link" onClick={ toggleAll }>
					{ allOpen
						? __( 'Collapse all', 'ip-location-block' )
						: __( 'Expand all', 'ip-location-block' ) }
				</Button>
			</div>

			<Panel className="ilb-panel-shell ilb-stats__panels">
				<PanelBody
					title={ __(
						'Statistics of validation',
						'ip-location-block'
					) }
					className="ilb-panel-section ilb-stats-section"
					opened={ open.validation }
					onToggle={ ( next ) => toggleSection( 'validation', next ) }
				>
					{ ! stat.features?.statistics ? (
						<EmptyPanel>
							{ __(
								'Recording validation statistics is currently disabled.',
								'ip-location-block'
							) }
						</EmptyPanel>
					) : (
						<div className="ilb-stats__section-content">
							<Flex
								justify="flex-start"
								gap={ 3 }
								wrap
								className="ilb-stat-tiles"
							>
								<Tile
									label={ __(
										'Blocked',
										'ip-location-block'
									) }
									value={ stat.blocked }
									icon="shield"
								/>
								<Tile
									label={ __( 'IPv4', 'ip-location-block' ) }
									value={ stat.ipv4 }
									icon="admin-site-alt3"
								/>
								<Tile
									label={ __( 'IPv6', 'ip-location-block' ) }
									value={ stat.ipv6 }
									icon="admin-site-alt"
								/>
								<Tile
									label={ __(
										'Unknown',
										'ip-location-block'
									) }
									value={ stat.unknown }
									icon="editor-help"
								/>
							</Flex>

							<div className="ilb-stats__chart-grid">
								<Card>
									<CardBody>
										<h3 className="ilb-section-title">
											<span
												className="dashicons dashicons-admin-site"
												aria-hidden="true"
											/>
											{ __(
												'Blocked by country',
												'ip-location-block'
											) }
										</h3>
										<CountryBars data={ stat.countries } />
									</CardBody>
								</Card>
								<Card>
									<CardBody>
										<h3 className="ilb-section-title">
											<span
												className="dashicons dashicons-chart-bar"
												aria-hidden="true"
											/>
											{ __(
												'Daily blocked requests',
												'ip-location-block'
											) }
										</h3>
										<DailyStacked data={ stat.daily } />
									</CardBody>
								</Card>
							</div>

							<ProviderTable rows={ stat.providers } />
							<div className="ilb-stats__actions">
								<Button
									variant="secondary"
									isDestructive
									onClick={ () =>
										askConfirm(
											__(
												'Clear validation statistics?',
												'ip-location-block'
											),
											__(
												'Country, daily, IP-version, and provider timing totals will be permanently reset.',
												'ip-location-block'
											),
											__(
												'Clear statistics',
												'ip-location-block'
											),
											() =>
												runAction(
													clearStatistics,
													__(
														'Statistics cleared.',
														'ip-location-block'
													),
													load
												)
										).catch( () => {} )
									}
								>
									{ __(
										'Clear statistics',
										'ip-location-block'
									) }
								</Button>
							</div>
						</div>
					) }
				</PanelBody>

				<PanelBody
					title={ __(
						'Statistics in validation logs',
						'ip-location-block'
					) }
					className="ilb-panel-section ilb-stats-section"
					opened={ open.logs }
					onToggle={ ( next ) => toggleSection( 'logs', next ) }
				>
					{ ! stat.features?.logs ? (
						<EmptyPanel>
							{ __(
								'Recording validation logs is currently disabled.',
								'ip-location-block'
							) }
						</EmptyPanel>
					) : logLoading && logStat === null ? (
						<LoadingBlock />
					) : (
						<div className="ilb-stats__section-content">
							<div className="ilb-stats__rank-grid">
								<RankedList
									title={ __(
										'Country (Top 10)',
										'ip-location-block'
									) }
									rows={ logStat?.countries }
								/>
								<RankedList
									title={ __(
										'AS number (Top 10)',
										'ip-location-block'
									) }
									rows={ logStat?.asns }
								/>
								<RankedList
									title={ __(
										'IP address (Top 10)',
										'ip-location-block'
									) }
									rows={ logStat?.ips }
								/>
								<RankedList
									title={ __(
										'Slug in back-end',
										'ip-location-block'
									) }
									rows={ logStat?.slugs }
									sortMode={ slugSort }
									onToggleSort={ () =>
										setSlugSort( ( value ) =>
											value === 'count'
												? 'alpha'
												: 'count'
										)
									}
								/>
							</div>
							<div className="ilb-stats__actions">
								<Button
									variant="secondary"
									isDestructive
									onClick={ () =>
										askConfirm(
											__(
												'Clear validation logs?',
												'ip-location-block'
											),
											__(
												'All recorded validation log entries will be permanently removed.',
												'ip-location-block'
											),
											__(
												'Clear logs',
												'ip-location-block'
											),
											() =>
												runAction(
													() => clearLogs(),
													__(
														'Validation logs cleared.',
														'ip-location-block'
													),
													loadLogStat
												)
										).catch( () => {} )
									}
								>
									{ __( 'Clear logs', 'ip-location-block' ) }
								</Button>
							</div>
						</div>
					) }
				</PanelBody>

				<PanelBody
					title={ __(
						'Statistics in IP address cache',
						'ip-location-block'
					) }
					className="ilb-panel-section ilb-stats-section"
					opened={ open.cache }
					onToggle={ ( next ) => toggleSection( 'cache', next ) }
				>
					{ ! stat.features?.cache ? (
						<EmptyPanel>
							{ __(
								'Recording the IP address cache is currently disabled.',
								'ip-location-block'
							) }
						</EmptyPanel>
					) : cacheLoading && cacheRows === null ? (
						<LoadingBlock />
					) : (
						<div className="ilb-stats__section-content">
							<div className="ilb-stats__cache-actions">
								<Button
									variant="secondary"
									onClick={ exportCache }
									disabled={ ! cacheRows?.length }
								>
									{ __( 'Export CSV', 'ip-location-block' ) }
								</Button>
								<Button
									variant="secondary"
									isDestructive
									disabled={ ! cacheRows?.length }
									onClick={ () =>
										askConfirm(
											__(
												'Clear the IP cache?',
												'ip-location-block'
											),
											__(
												'All cached IP address records will be permanently removed.',
												'ip-location-block'
											),
											__(
												'Clear cache',
												'ip-location-block'
											),
											() =>
												runAction(
													clearCache,
													__(
														'IP cache cleared.',
														'ip-location-block'
													),
													loadCache
												)
										).catch( () => {} )
									}
								>
									{ __( 'Clear cache', 'ip-location-block' ) }
								</Button>
							</div>
							<DataTable
								columns={ cacheColumns }
								rows={ cacheRows || [] }
								loading={ cacheLoading }
								selectable
								bulkActionMode="select"
								bulkActions={ cacheBulkActions }
								searchKeys={ [
									'ip',
									'code',
									'city',
									'state',
									'asn',
									'host',
									'target',
								] }
								emptyMessage={ __(
									'No IP cache entries.',
									'ip-location-block'
								) }
							/>
						</div>
					) }
				</PanelBody>
			</Panel>

			{ confirm && (
				<Modal
					title={ confirm.title }
					onRequestClose={ confirm.onCancel }
				>
					<p>{ confirm.message }</p>
					<div className="ilb-confirm__actions">
						<Button variant="tertiary" onClick={ confirm.onCancel }>
							{ __( 'Cancel', 'ip-location-block' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ confirm.onConfirm }
						>
							{ confirm.confirmLabel }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
