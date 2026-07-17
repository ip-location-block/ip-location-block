/**
 * Root of the Beta admin: a branded header + a TabPanel shell.
 */
import { useState, useEffect } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import Settings from './tabs/Settings';
import Logs from './tabs/Logs';
import Statistics from './tabs/Statistics';
import Search from './tabs/Search';
import Attribution from './tabs/Attribution';
import Sites from './tabs/Sites';
import { getSettings } from './api';

const boot = window.ipLocationBlockBeta || {};
const isNetwork = !! boot.isNetwork;

const TABS = [
	{ name: 'settings', title: __( 'Settings', 'ip-location-block' ), icon: 'admin-settings', Component: Settings },
	{ name: 'statistics', title: __( 'Statistics', 'ip-location-block' ), icon: 'chart-bar', Component: Statistics },
	{ name: 'logs', title: __( 'Logs', 'ip-location-block' ), icon: 'list-view', Component: Logs },
	{ name: 'search', title: __( 'Search', 'ip-location-block' ), icon: 'search', Component: Search },
	{ name: 'attribution', title: __( 'Attribution', 'ip-location-block' ), icon: 'awards', Component: Attribution },
	...( isNetwork
		? [ { name: 'sites', title: __( 'Sites', 'ip-location-block' ), icon: 'networking', Component: Sites } ]
		: [] ),
];

// Protection is "on" if back-end country blocking is set, or front-end blocking
// (bit 1 of validation.public) is enabled.
const isProtected = ( s ) =>
	!! s &&
	( Number( s.matching_rule ) !== -1 || ( Number( s?.validation?.public ) & 1 ) === 1 );

function Header() {
	const [ status, setStatus ] = useState( null ); // null = unknown, bool once loaded

	useEffect( () => {
		let alive = true;
		getSettings()
			.then( ( s ) => alive && setStatus( isProtected( s ) ) )
			.catch( () => {} );
		return () => {
			alive = false;
		};
	}, [] );

	return (
		<div className="ilb-header">
			{ boot.logoUrl && (
				<img className="ilb-header__logo" src={ boot.logoUrl } alt="" />
			) }
			<div className="ilb-header__titles">
				<h1 className="ilb-header__title">
					{ __( 'IP Location Block', 'ip-location-block' ) }
					<span className="ilb-badge">{ __( 'Beta', 'ip-location-block' ) }</span>
				</h1>
				{ boot.version && (
					<p className="ilb-header__sub">
						{ __( 'Version', 'ip-location-block' ) } { boot.version }
					</p>
				) }
			</div>
			<div className="ilb-header__spacer" />
			{ status !== null && (
				<span
					className={ `ilb-status ilb-status--${ status ? 'on' : 'off' }` }
					title={ __( 'Blocking protection status', 'ip-location-block' ) }
				>
					{ status
						? __( 'Protection: On', 'ip-location-block' )
						: __( 'Protection: Off', 'ip-location-block' ) }
				</span>
			) }
		</div>
	);
}

const tabTitle = ( icon, title ) => (
	<span className="ilb-tab-label">
		<span className={ `dashicons dashicons-${ icon } ilb-tab-icon` } aria-hidden="true" />
		{ title }
	</span>
);

export default function App() {
	return (
		<div className="ilb-app">
			<Header />
			<TabPanel
				className="ilb-tabs"
				tabs={ TABS.map( ( { name, title, icon } ) => ( {
					name,
					title: tabTitle( icon, title ),
				} ) ) }
			>
				{ ( tab ) => {
					const found = TABS.find( ( t ) => t.name === tab.name );
					const Component = found ? found.Component : () => null;
					return (
						<div className="ilb-tab-panel">
							<Component />
						</div>
					);
				} }
			</TabPanel>
		</div>
	);
}
