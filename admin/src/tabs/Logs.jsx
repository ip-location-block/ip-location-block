/**
 * Validation logs with the workflows and detail level of the stable admin,
 * presented in the shared Beta panel shell.
 */
/* eslint-disable no-nested-ternary */
import {
	useState,
	useEffect,
	useCallback,
	useMemo,
	useRef,
} from '@wordpress/element';
import {
	SelectControl,
	Card,
	CardHeader,
	CardBody,
	Button,
	Notice,
	Flex,
	Modal,
	Spinner,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import DataTable from '../components/DataTable';
import {
	getLogs,
	updateLiveLogs,
	clearLogs,
	eraseLogEntries,
	addToList,
} from '../api';
import { betaUrl, queryParam } from '../navigation';
import {
	LOG_SEARCH_KEYS,
	LOG_PRESETS,
	buildLogsCsv,
	filterLogRows,
} from './logsLogic';

const HOOKS = [
	{ label: __( 'All targets', 'ip-location-block' ), value: '' },
	{ label: __( 'Comment', 'ip-location-block' ), value: 'comment' },
	{ label: __( 'Login', 'ip-location-block' ), value: 'login' },
	{ label: __( 'Admin', 'ip-location-block' ), value: 'admin' },
	{ label: __( 'XML-RPC', 'ip-location-block' ), value: 'xmlrpc' },
	{ label: __( 'Public', 'ip-location-block' ), value: 'public' },
];

const PRESET_LABELS = {
	'passed-whitelist': __( 'Passed in whitelist', 'ip-location-block' ),
	'passed-blacklist': __( 'Passed in blacklist', 'ip-location-block' ),
	'passed-none': __( 'Passed not in a list', 'ip-location-block' ),
	'blocked-whitelist': __( 'Blocked in whitelist', 'ip-location-block' ),
	'blocked-blacklist': __( 'Blocked in blacklist', 'ip-location-block' ),
	'blocked-none': __( 'Blocked not in a list', 'ip-location-block' ),
};

const CONTEXT_LABELS = {
	whitelist: __( 'Whitelist', 'ip-location-block' ),
	blacklist: __( 'Blacklist', 'ip-location-block' ),
	none: __( 'Not in either list', 'ip-location-block' ),
};

const dateText = ( timestamp ) =>
	timestamp ? new Date( timestamp * 1000 ).toLocaleString() : '';

const decorateRows = ( rows ) =>
	( Array.isArray( rows ) ? rows : [] ).map( ( row ) => ( {
		...row,
		timeText: dateText( row.time ),
	} ) );

const DetailValue = ( { children, pre = false } ) => {
	const value = String( children ?? '' ).trim();
	if ( ! value ) {
		return (
			<span className="ilb-log-details__empty">
				{ __( 'Not recorded', 'ip-location-block' ) }
			</span>
		);
	}
	return pre ? <pre>{ value }</pre> : value;
};

const RowDetails = ( { row } ) => (
	<div className="ilb-log-details">
		<dl className="ilb-log-details__grid">
			<div>
				<dt>{ __( 'Full timestamp', 'ip-location-block' ) }</dt>
				<dd>
					<DetailValue>{ row.timeText }</DetailValue>
				</dd>
			</div>
			<div>
				<dt>{ __( 'City', 'ip-location-block' ) }</dt>
				<dd>
					<DetailValue>{ row.city }</DetailValue>
				</dd>
			</div>
			<div>
				<dt>{ __( 'Country code', 'ip-location-block' ) }</dt>
				<dd>
					<DetailValue>{ row.code }</DetailValue>
				</dd>
			</div>
			<div>
				<dt>{ __( 'State', 'ip-location-block' ) }</dt>
				<dd>
					<DetailValue>{ row.state }</DetailValue>
				</dd>
			</div>
			<div>
				<dt>{ __( 'ASN', 'ip-location-block' ) }</dt>
				<dd>
					<DetailValue>{ row.asn }</DetailValue>
				</dd>
			</div>
			<div>
				<dt>{ __( 'Target', 'ip-location-block' ) }</dt>
				<dd>
					<DetailValue>{ row.target }</DetailValue>
				</dd>
			</div>
			<div>
				<dt>{ __( 'Validation result', 'ip-location-block' ) }</dt>
				<dd>
					<DetailValue>{ row.result }</DetailValue>
				</dd>
			</div>
			<div>
				<dt>{ __( 'Request', 'ip-location-block' ) }</dt>
				<dd>
					<DetailValue>{ row.method }</DetailValue>
				</dd>
			</div>
		</dl>
		<div className="ilb-log-details__long">
			<div>
				<h4>{ __( 'User agent', 'ip-location-block' ) }</h4>
				<DetailValue pre>{ row.userAgent }</DetailValue>
			</div>
			<div>
				<h4>{ __( 'HTTP headers', 'ip-location-block' ) }</h4>
				<DetailValue pre>{ row.headers }</DetailValue>
			</div>
			<div>
				<h4>{ __( '$_POST data', 'ip-location-block' ) }</h4>
				<DetailValue pre>{ row.postData }</DetailValue>
			</div>
		</div>
	</div>
);

const columns = [
	{
		key: 'time',
		header: __( 'Time', 'ip-location-block' ),
		priority: 2,
		render: ( row ) => row.timeText,
	},
	{
		key: 'ip',
		header: __( 'IP address', 'ip-location-block' ),
		render: ( row ) => (
			<a href={ betaUrl( { tab: 'search', s: row.ip } ) }>{ row.ip }</a>
		),
	},
	{ key: 'code', header: __( 'Code', 'ip-location-block' ) },
	{ key: 'asn', header: __( 'ASN', 'ip-location-block' ), priority: 3 },
	{ key: 'target', header: __( 'Target', 'ip-location-block' ), priority: 2 },
	{
		key: 'result',
		header: __( 'Result', 'ip-location-block' ),
		render: ( row ) => (
			<div className="ilb-log-result">
				<span
					className={ `ilb-log-result__badge is-${ row.verdict }` }
					title={ row.result }
				>
					{ row.verdict === 'passed'
						? __( 'Passed', 'ip-location-block' )
						: __( 'Blocked', 'ip-location-block' ) }
				</span>
				<span className="ilb-log-result__context">
					{ CONTEXT_LABELS[ row.listContext ] || row.result }
				</span>
			</div>
		),
	},
];

export default function Logs() {
	const [ rows, setRows ] = useState( [] );
	const [ features, setFeatures ] = useState( null );
	const [ liveConfig, setLiveConfig ] = useState( {
		intervalSeconds: 5,
		pauseTimeoutSeconds: 60,
	} );
	const [ hook, setHook ] = useState( '' );
	const [ query, setQuery ] = useState( queryParam( 's' ) );
	const [ preset, setPreset ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ liveMode, setLiveMode ] = useState( 'stopped' );
	const [ pausedUntil, setPausedUntil ] = useState( 0 );
	const [ clock, setClock ] = useState( Date.now() );
	const [ notice, setNotice ] = useState( null );
	const [ confirm, setConfirm ] = useState( null );
	const liveModeRef = useRef( liveMode );

	useEffect( () => {
		liveModeRef.current = liveMode;
	}, [ liveMode ] );

	const load = useCallback( () => {
		setLoading( true );
		setNotice( null );
		return getLogs( hook )
			.then( ( data ) => {
				setRows( decorateRows( data?.rows ) );
				setFeatures( data?.features || {} );
				setLiveConfig( ( current ) => ( {
					...current,
					...data?.live,
				} ) );
			} )
			.catch( ( error ) => {
				setNotice( { status: 'error', msg: error.message } );
				throw error;
			} )
			.finally( () => setLoading( false ) );
	}, [ hook ] );

	useEffect( () => {
		load().catch( () => {} );
	}, [ load ] );

	const appendLiveRows = useCallback( ( incoming ) => {
		const nextRows = decorateRows( incoming );
		if ( ! nextRows.length ) {
			return;
		}
		setRows( ( current ) => {
			const ids = new Set( current.map( ( row ) => row.id ) );
			return [
				...nextRows.filter( ( row ) => ! ids.has( row.id ) ),
				...current,
			];
		} );
	}, [] );

	const pollLive = useCallback( () => {
		return updateLiveLogs( 'poll', hook )
			.then( ( data ) => appendLiveRows( data?.rows ) )
			.catch( ( error ) => {
				setNotice( { status: 'error', msg: error.message } );
				setLiveMode( 'stopped' );
				throw error;
			} );
	}, [ appendLiveRows, hook ] );

	useEffect( () => {
		if ( liveMode !== 'running' ) {
			return undefined;
		}
		const id = setInterval(
			() => pollLive().catch( () => {} ),
			Number( liveConfig.intervalSeconds || 5 ) * 1000
		);
		return () => clearInterval( id );
	}, [ liveConfig.intervalSeconds, liveMode, pollLive ] );

	const stopLive = useCallback( () => {
		setLoading( true );
		return updateLiveLogs( 'stop', hook )
			.catch( ( error ) => {
				setNotice( { status: 'error', msg: error.message } );
			} )
			.then( () => {
				setLiveMode( 'stopped' );
				setPausedUntil( 0 );
				return load();
			} )
			.catch( () => {} );
	}, [ hook, load ] );

	useEffect( () => {
		if ( liveMode !== 'paused' || ! pausedUntil ) {
			return undefined;
		}
		const tick = setInterval( () => setClock( Date.now() ), 1000 );
		const timeout = setTimeout(
			() => stopLive(),
			Math.max( 0, pausedUntil - Date.now() )
		);
		return () => {
			clearInterval( tick );
			clearTimeout( timeout );
		};
	}, [ liveMode, pausedUntil, stopLive ] );

	useEffect( () => {
		return () => {
			if ( liveModeRef.current !== 'stopped' ) {
				updateLiveLogs( 'stop', hook ).catch( () => {} );
			}
		};
	}, [ hook ] );

	const startLive = () => {
		const starting = liveMode === 'stopped';
		setLoading( true );
		setNotice( null );
		if ( starting ) {
			setRows( [] );
			setPreset( '' );
		}
		return updateLiveLogs( 'start', hook )
			.then( ( data ) => {
				appendLiveRows( data?.rows );
				setLiveMode( 'running' );
				setPausedUntil( 0 );
			} )
			.catch( ( error ) => {
				setNotice( { status: 'error', msg: error.message } );
				setLiveMode( 'stopped' );
				if ( starting ) {
					load().catch( () => {} );
				}
			} )
			.finally( () => setLoading( false ) );
	};

	const pauseLive = () => {
		return updateLiveLogs( 'pause', hook )
			.then( () => {
				setLiveMode( 'paused' );
				setClock( Date.now() );
				setPausedUntil(
					Date.now() +
						Number( liveConfig.pauseTimeoutSeconds || 60 ) * 1000
				);
			} )
			.catch( ( error ) =>
				setNotice( { status: 'error', msg: error.message } )
			);
	};

	const askConfirmation = ( title, message, confirmLabel ) =>
		new Promise( ( resolve, reject ) => {
			setConfirm( {
				title,
				message,
				confirmLabel,
				onConfirm: () => {
					setConfirm( null );
					resolve();
				},
				onCancel: () => {
					setConfirm( null );
					reject( new Error( 'cancelled' ) );
				},
			} );
		} );

	const act = ( promise, okMessage ) => {
		setNotice( null );
		return promise
			.then( () => {
				setNotice( { status: 'success', msg: okMessage } );
				return load();
			} )
			.catch( ( error ) => {
				if ( error.message !== 'cancelled' ) {
					setNotice( { status: 'error', msg: error.message } );
				}
				throw error;
			} );
	};

	const valuesFor = ( ids, key ) => {
		const selected = new Set( ids );
		return [
			...new Set(
				rows
					.filter( ( row ) => selected.has( row.id ) )
					.map( ( row ) => row[ key ] )
					.filter( Boolean )
			),
		];
	};

	const bulkActions = [
		{
			label: __( 'Whitelist selected IPs', 'ip-location-block' ),
			onClick: ( ids ) =>
				act(
					addToList( 'white', valuesFor( ids, 'ip' ) ),
					__(
						'Selected IP addresses were added to the whitelist.',
						'ip-location-block'
					)
				),
		},
		{
			label: __( 'Blacklist selected IPs', 'ip-location-block' ),
			onClick: ( ids ) =>
				act(
					addToList( 'black', valuesFor( ids, 'ip' ) ),
					__(
						'Selected IP addresses were added to the blacklist.',
						'ip-location-block'
					)
				),
		},
		...( features?.asn
			? [
					{
						label: __(
							'Whitelist selected ASNs',
							'ip-location-block'
						),
						onClick: ( ids ) =>
							act(
								addToList(
									'white',
									valuesFor( ids, 'asn' ),
									'asn'
								),
								__(
									'Selected ASNs were added to the whitelist.',
									'ip-location-block'
								)
							),
					},
					{
						label: __(
							'Blacklist selected ASNs',
							'ip-location-block'
						),
						onClick: ( ids ) =>
							act(
								addToList(
									'black',
									valuesFor( ids, 'asn' ),
									'asn'
								),
								__(
									'Selected ASNs were added to the blacklist.',
									'ip-location-block'
								)
							),
					},
			  ]
			: [] ),
		{
			label: __( 'Erase selected entries', 'ip-location-block' ),
			destructive: true,
			onClick: ( ids ) =>
				askConfirmation(
					__( 'Erase selected log entries?', 'ip-location-block' ),
					sprintf(
						/* translators: %d: number of selected rows */
						__(
							'This will permanently erase entries for the IP addresses in %d selected rows.',
							'ip-location-block'
						),
						ids.length
					),
					__( 'Erase entries', 'ip-location-block' )
				).then( () =>
					act(
						eraseLogEntries( valuesFor( ids, 'ip' ) ),
						__(
							'Selected log entries were erased.',
							'ip-location-block'
						)
					)
				),
		},
	];

	const presetRows = useMemo(
		() => filterLogRows( rows, '', preset ),
		[ preset, rows ]
	);
	const displayedRows = useMemo(
		() => filterLogRows( rows, query, preset ),
		[ preset, query, rows ]
	);

	const exportCsv = () => {
		const csv = buildLogsCsv( displayedRows );
		const url = window.URL.createObjectURL(
			new window.Blob( [ `\ufeff${ csv }` ], {
				type: 'text/csv;charset=utf-8',
			} )
		);
		const anchor = document.createElement( 'a' );
		anchor.href = url;
		anchor.download = `ip-location-block_${ new Date()
			.toISOString()
			.replace( /[:T]/g, '-' )
			.slice( 0, 19 ) }.csv`;
		anchor.click();
		window.URL.revokeObjectURL( url );
	};

	const clearCurrentLogs = () => {
		const targetLabel = HOOKS.find(
			( item ) => item.value === hook
		)?.label;
		return askConfirmation(
			__( 'Clear validation logs?', 'ip-location-block' ),
			hook
				? sprintf(
						/* translators: %s: validation target */
						__(
							'All recorded entries for the %s target will be permanently removed.',
							'ip-location-block'
						),
						targetLabel
				  )
				: __(
						'All recorded validation log entries will be permanently removed.',
						'ip-location-block'
				  ),
			__( 'Clear logs', 'ip-location-block' )
		).then( () =>
			act(
				clearLogs( hook ),
				__( 'Validation logs cleared.', 'ip-location-block' )
			)
		);
	};

	const isLive = liveMode !== 'stopped';
	const pauseRemaining = Math.max(
		0,
		Math.ceil( ( pausedUntil - clock ) / 1000 )
	);

	return (
		<div className="ilb-logs">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.msg }
				</Notice>
			) }

			<Card className="ilb-panel-shell ilb-logs__panel">
				<CardHeader className="ilb-panel-shell__header">
					<h2 className="ilb-panel-shell__title">
						{ __( 'Validation logs', 'ip-location-block' ) }
					</h2>
					<a
						className="ilb-panel-shell__help"
						href="https://iplocationblock.com/codex/validation-logs/"
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Help', 'ip-location-block' ) }
						<span
							className="dashicons dashicons-external"
							aria-hidden="true"
						/>
					</a>
				</CardHeader>
				<CardBody>
					{ ! features && loading ? (
						<div className="ilb-logs__loading">
							<Spinner />
						</div>
					) : features?.recording === false ? (
						<div className="ilb-logs__disabled">
							<span
								className="dashicons dashicons-info-outline"
								aria-hidden="true"
							/>
							<div>
								<h3>
									{ __(
										'Validation log recording is disabled',
										'ip-location-block'
									) }
								</h3>
								<p>
									{ __(
										'Enable recording to inspect individual requests, use preset filters, and capture live traffic.',
										'ip-location-block'
									) }
								</p>
								<Button
									variant="secondary"
									href={ betaUrl( {
										tab: 'settings',
										view: 'advanced',
										section: 'recording',
										s: null,
									} ) }
								>
									{ __(
										'Open recording settings',
										'ip-location-block'
									) }
								</Button>
							</div>
						</div>
					) : (
						<>
							<Flex
								justify="flex-start"
								gap={ 3 }
								wrap
								className="ilb-toolbar ilb-logs__toolbar"
							>
								<SelectControl
									__nextHasNoMarginBottom
									label={ __(
										'Target',
										'ip-location-block'
									) }
									hideLabelFromVision
									value={ hook }
									options={ HOOKS }
									onChange={ setHook }
									disabled={ isLive }
								/>
								<Button
									variant="secondary"
									onClick={ load }
									disabled={ isLive || loading }
								>
									{ __( 'Refresh', 'ip-location-block' ) }
								</Button>

								{ features?.live && (
									<div
										className="ilb-live-controls"
										role="group"
										aria-label={ __(
											'Live log controls',
											'ip-location-block'
										) }
									>
										{ liveMode === 'stopped' && (
											<Button
												variant="secondary"
												onClick={ startLive }
												disabled={ loading }
											>
												<span
													className="dashicons dashicons-controls-play"
													aria-hidden="true"
												/>
												{ __(
													'Start live',
													'ip-location-block'
												) }
											</Button>
										) }
										{ liveMode === 'running' && (
											<Button
												variant="secondary"
												onClick={ pauseLive }
											>
												<span
													className="dashicons dashicons-controls-pause"
													aria-hidden="true"
												/>
												{ __(
													'Pause',
													'ip-location-block'
												) }
											</Button>
										) }
										{ liveMode === 'paused' && (
											<Button
												variant="secondary"
												onClick={ startLive }
											>
												<span
													className="dashicons dashicons-controls-play"
													aria-hidden="true"
												/>
												{ __(
													'Resume',
													'ip-location-block'
												) }
											</Button>
										) }
										{ isLive && (
											<Button
												variant="secondary"
												onClick={ stopLive }
											>
												<span
													className="dashicons dashicons-controls-stop"
													aria-hidden="true"
												/>
												{ __(
													'Stop',
													'ip-location-block'
												) }
											</Button>
										) }
									</div>
								) }

								<Button
									variant="secondary"
									onClick={ exportCsv }
									disabled={
										isLive || ! displayedRows.length
									}
								>
									{ __( 'Export CSV', 'ip-location-block' ) }
								</Button>
								<Button
									variant="secondary"
									isDestructive
									onClick={ () =>
										clearCurrentLogs().catch( () => {} )
									}
									disabled={ isLive || ! rows.length }
								>
									{ __( 'Clear logs', 'ip-location-block' ) }
								</Button>
							</Flex>

							{ isLive && (
								<div
									className={ `ilb-live-status is-${ liveMode }` }
									role="status"
								>
									<span
										className="ilb-live-status__dot"
										aria-hidden="true"
									/>
									{ liveMode === 'running'
										? __(
												'Capturing live validation requests',
												'ip-location-block'
										  )
										: sprintf(
												/* translators: %d: seconds until live session ends */
												__(
													'Live capture paused — session ends in %d seconds',
													'ip-location-block'
												),
												pauseRemaining
										  ) }
								</div>
							) }

							<div
								className="ilb-log-presets"
								aria-label={ __(
									'Preset filters',
									'ip-location-block'
								) }
							>
								<span className="ilb-log-presets__label">
									{ __(
										'Quick filters',
										'ip-location-block'
									) }
								</span>
								{ LOG_PRESETS.map( ( item ) => (
									<button
										key={ item.id }
										type="button"
										className={ `ilb-filter-chip ${
											preset === item.id
												? 'is-active'
												: ''
										} is-${ item.verdict }` }
										aria-pressed={ preset === item.id }
										onClick={ () =>
											setPreset(
												preset === item.id
													? ''
													: item.id
											)
										}
									>
										{ PRESET_LABELS[ item.id ] }
									</button>
								) ) }
								{ ( query || preset ) && (
									<Button
										variant="link"
										onClick={ () => {
											setQuery( '' );
											setPreset( '' );
										} }
									>
										{ __(
											'Reset filters',
											'ip-location-block'
										) }
									</Button>
								) }
							</div>

							<DataTable
								key={ `${ hook }-${ liveMode }` }
								columns={ columns }
								rows={ presetRows }
								loading={ loading }
								selectable={ ! isLive }
								query={ query }
								onQueryChange={ setQuery }
								searchKeys={ LOG_SEARCH_KEYS }
								bulkActionMode="select"
								bulkActions={ bulkActions }
								renderRowDetails={ ( row ) => (
									<RowDetails row={ row } />
								) }
								emptyMessage={
									isLive
										? __(
												'Waiting for validation requests…',
												'ip-location-block'
										  )
										: __(
												'No log entries match the current filters.',
												'ip-location-block'
										  )
								}
							/>
						</>
					) }
				</CardBody>
			</Card>

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
