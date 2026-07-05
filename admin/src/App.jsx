/**
 * Root of the Beta admin: a TabPanel shell. Settings is a live REST-backed
 * tab; the rest are ported incrementally.
 */
import { TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import Settings from './tabs/Settings';
import LogsDemo from './tabs/LogsDemo';

const TABS = [
	{ name: 'settings', title: __( 'Settings', 'ip-location-block' ), Component: Settings },
	{ name: 'logs', title: __( 'Logs (preview)', 'ip-location-block' ), Component: LogsDemo },
];

export default function App() {
	return (
		<div className="ilb-app">
			<h1 className="wp-heading-inline">
				{ __( 'IP Location Block', 'ip-location-block' ) }{ ' ' }
				<span className="ilb-badge">{ __( 'Beta', 'ip-location-block' ) }</span>
			</h1>
			<TabPanel className="ilb-tabs" tabs={ TABS.map( ( { name, title } ) => ( { name, title } ) ) }>
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
