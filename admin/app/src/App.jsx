/**
 * Root of the admin app: a full-width product bar + a contained TabPanel shell.
 */
import {
	useCallback,
	useMemo,
	useState,
	useEffect,
} from '@wordpress/element';
import { Button, TabPanel } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import Settings from './tabs/Settings';
import Logs from './tabs/Logs';
import Statistics from './tabs/Statistics';
import Search from './tabs/Search';
import Diagnostics from './tabs/Diagnostics';
import Sites from './tabs/Sites';
import {
	getDiagnostics,
	getProviderStatus,
	getSettings,
	getMode,
	setDiagnosticAcknowledgement,
} from './api';
import ModeBadge from './components/ModeBadge';
import AppFooter from './components/AppFooter';
import ViewSwitcher from './components/ViewSwitcher';
import QuotaBanner from './components/QuotaBanner';
import { criticalChecks, issueCount } from './diagnosticsLogic';
import { dismissQuotaBanner, shouldShowBanner } from './quotaBannerLogic';
import {
	betaUrl,
	queryParam,
	replaceTabInUrl,
	resolveTabName,
} from './navigation';

const boot = window.ipLocationBlockAdmin || {};
const isNetwork = !! boot.isNetwork;

const TABS = [
	{
		name: 'settings',
		title: __( 'Settings', 'ip-location-block' ),
		Component: Settings,
	},
	{
		name: 'statistics',
		title: __( 'Statistics', 'ip-location-block' ),
		Component: Statistics,
	},
	{ name: 'logs', title: __( 'Logs', 'ip-location-block' ), Component: Logs },
	{
		name: 'search',
		title: __( 'Search', 'ip-location-block' ),
		Component: Search,
	},
	{
		name: 'diagnostics',
		title: __( 'Diagnostics', 'ip-location-block' ),
		Component: Diagnostics,
	},
	...( isNetwork
		? [
				{
					name: 'sites',
					title: __( 'Sites', 'ip-location-block' ),
					Component: Sites,
				},
		  ]
		: [] ),
];

// Protection is "on" if back-end country blocking is set, or front-end blocking
// (bit 1 of validation.public) is enabled.
const isProtected = ( s ) =>
	!! s &&
	( Number( s.matching_rule ) !== -1 ||
		Number( s?.validation?.public ) % 2 === 1 );

function Header() {
	const [ status, setStatus ] = useState( null ); // null = unknown, bool once loaded
	const [ mode, setMode ] = useState( null ); // geolocation Native/Standard mode

	useEffect( () => {
		let alive = true;
		const refresh = ( event ) => {
			if ( event?.detail?.settings && alive ) {
				setStatus( isProtected( event.detail.settings ) );
			} else {
				getSettings()
					.then( ( s ) => alive && setStatus( isProtected( s ) ) )
					.catch( () => {} );
			}
			getMode()
				.then( ( m ) => alive && setMode( m ) )
				.catch( () => {} );
		};
		refresh();
		window.addEventListener( 'ip-location-block-settings-saved', refresh );
		return () => {
			alive = false;
			window.removeEventListener(
				'ip-location-block-settings-saved',
				refresh
			);
		};
	}, [] );

	return (
		<header className="ilb-topbar">
			<div className="ilb-topbar__inner">
				<div className="ilb-topbar__identity">
					{ boot.logoUrl && (
						<img
							className="ilb-topbar__logo"
							src={ boot.logoUrl }
							alt=""
						/>
					) }
					<h1 className="ilb-topbar__title">
						{ __( 'IP Location Block', 'ip-location-block' ) }
					</h1>
				</div>
				<div className="ilb-topbar__meta">
					<ModeBadge mode={ mode } />
					{ status !== null && (
						<span
							className={ `ilb-status ilb-status--${
								status ? 'on' : 'off'
							}` }
							title={ __(
								'Blocking protection status',
								'ip-location-block'
							) }
						>
							{ status
								? __( 'Protection on', 'ip-location-block' )
								: __( 'Protection off', 'ip-location-block' ) }
						</span>
					) }
					<a
						className="ilb-topbar__docs"
						href={
							boot.docsUrl || 'https://iplocationblock.com/codex/'
						}
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Documentation', 'ip-location-block' ) }
						<span
							className="dashicons dashicons-external"
							aria-hidden="true"
						/>
					</a>
				</div>
			</div>
		</header>
	);
}

function CriticalBanner( { checks } ) {
	if ( ! checks.length ) {
		return null;
	}
	const first = checks[ 0 ];

	return (
		<div className="ilb-critical-banner" role="alert">
			<span className="dashicons dashicons-warning" aria-hidden="true" />
			<div className="ilb-critical-banner__message">
				<strong>
					{ __( 'Immediate action required', 'ip-location-block' ) }
				</strong>
				<span>
					{ checks.length > 1
						? sprintf(
								/* translators: 1: first issue title, 2: number of additional critical issues. */
								__(
									'%1$s and %2$d more critical issue(s).',
									'ip-location-block'
								),
								first.title,
								checks.length - 1
						  )
						: first.message }
				</span>
			</div>
			<Button
				variant="secondary"
				href={ betaUrl( { tab: 'diagnostics' } ) }
			>
				{ __( 'Review diagnostics', 'ip-location-block' ) }
			</Button>
		</div>
	);
}

export default function App() {
	// Accept both React tab names and legacy classic numeric `tab=` deep links.
	const requestedTab = resolveTabName( queryParam( 'tab' ) );
	const initialTabName = TABS.some( ( tab ) => tab.name === requestedTab )
		? requestedTab
		: 'settings';
	const [ report, setReport ] = useState( null );
	const [ diagnosticsLoading, setDiagnosticsLoading ] = useState( true );
	const [ diagnosticsError, setDiagnosticsError ] = useState( '' );
	const [ acknowledging, setAcknowledging ] = useState( '' );
	const [ quota, setQuota ] = useState( null );
	const [ quotaDismissTick, setQuotaDismissTick ] = useState( 0 );

	const refreshDiagnostics = useCallback( () => {
		setDiagnosticsLoading( true );
		setDiagnosticsError( '' );
		return getDiagnostics()
			.then( setReport )
			.catch( ( error ) => {
				setDiagnosticsError( error.message || String( error ) );
			} )
			.finally( () => setDiagnosticsLoading( false ) );
	}, [] );

	useEffect( () => {
		refreshDiagnostics();
		window.addEventListener(
			'ip-location-block-settings-saved',
			refreshDiagnostics
		);
		return () =>
			window.removeEventListener(
				'ip-location-block-settings-saved',
				refreshDiagnostics
			);
	}, [ refreshDiagnostics ] );

	// Native-provider quota drives the persistent upsell banner. It rides on the
	// existing /providers/status payload; refresh it on save like diagnostics.
	useEffect( () => {
		let alive = true;
		const loadQuota = () =>
			getProviderStatus()
				.then( ( status ) => alive && setQuota( status?.quota || null ) )
				.catch( () => {} );
		loadQuota();
		window.addEventListener(
			'ip-location-block-settings-saved',
			loadQuota
		);
		return () => {
			alive = false;
			window.removeEventListener(
				'ip-location-block-settings-saved',
				loadQuota
			);
		};
	}, [] );

	const showQuotaBanner = useMemo(
		() => shouldShowBanner( quota ),
		// quotaDismissTick forces a re-read of the client-side dismissal state.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ quota, quotaDismissTick ]
	);

	const onQuotaDismiss = () => {
		dismissQuotaBanner( quota );
		setQuotaDismissTick( ( tick ) => tick + 1 );
	};

	const acknowledge = ( id, acknowledged ) => {
		setAcknowledging( id );
		setDiagnosticsError( '' );
		return setDiagnosticAcknowledgement( id, acknowledged )
			.then( setReport )
			.catch( ( error ) => {
				setDiagnosticsError( error.message || String( error ) );
			} )
			.finally( () => setAcknowledging( '' ) );
	};

	const count = issueCount( report );
	const critical = criticalChecks( report );
	const tabs = TABS.map( ( tab ) => ( {
		name: tab.name,
		title:
			tab.name === 'diagnostics' ? (
				<span className="ilb-diagnostics-tab-label">
					{ tab.title }
					{ count > 0 && (
						<span
							className={ `ilb-diagnostics-tab-count is-${
								report?.status || 'warning'
							}` }
						>
							{ count }
						</span>
					) }
				</span>
			) : (
				tab.title
			),
	} ) );

	return (
		<div className="ilb-app">
			<Header />
			<main className="ilb-app__content">
				{ showQuotaBanner && (
					<QuotaBanner
						quota={ quota }
						onDismiss={ onQuotaDismiss }
					/>
				) }
				<CriticalBanner checks={ critical } />
				<TabPanel
					className="ilb-tabs"
					initialTabName={ initialTabName }
					onSelect={ replaceTabInUrl }
					tabs={ tabs }
				>
					{ ( tab ) => {
						const found = TABS.find(
							( item ) => item.name === tab.name
						);
						const Component = found ? found.Component : () => null;
						return (
							<div className="ilb-tab-panel">
								{ tab.name === 'diagnostics' ? (
									<Component
										report={ report }
										loading={ diagnosticsLoading }
										error={ diagnosticsError }
										onRefresh={ refreshDiagnostics }
										onAcknowledge={ acknowledge }
										acknowledging={ acknowledging }
									/>
								) : (
									<Component />
								) }
							</div>
						);
					} }
				</TabPanel>
			</main>
			<AppFooter />
			<ViewSwitcher />
		</div>
	);
}
