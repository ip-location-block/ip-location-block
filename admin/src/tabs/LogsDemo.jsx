/**
 * Temporary Logs preview — exercises <DataTable> with sample rows until the
 * real Logs tab (live REST data + bulk actions) is built.
 */
import { __ } from '@wordpress/i18n';

import DataTable from '../components/DataTable';

const columns = [
	{ key: 'time', header: __( 'Time', 'ip-location-block' ) },
	{ key: 'ip', header: __( 'IP address', 'ip-location-block' ) },
	{ key: 'code', header: __( 'Code', 'ip-location-block' ) },
	{ key: 'result', header: __( 'Result', 'ip-location-block' ), priority: 2 },
	{ key: 'hits', header: __( 'Hits', 'ip-location-block' ), priority: 3 },
];

const rows = [
	{ id: 1, time: '2026-07-05 10:01', ip: '203.0.113.7', code: 'US', result: 'passed', hits: 3 },
	{ id: 2, time: '2026-07-05 10:04', ip: '198.51.100.24', code: 'DE', result: 'blocked', hits: 12 },
	{ id: 3, time: '2026-07-05 10:09', ip: '203.0.113.42', code: 'CN', result: 'blocked', hits: 7 },
	{ id: 4, time: '2026-07-05 10:12', ip: '192.0.2.5', code: 'FR', result: 'passed', hits: 1 },
	{ id: 5, time: '2026-07-05 10:15', ip: '203.0.113.9', code: 'US', result: 'passed', hits: 22 },
	{ id: 6, time: '2026-07-05 10:19', ip: '198.51.100.3', code: 'RU', result: 'blocked', hits: 5 },
	{ id: 7, time: '2026-07-05 10:24', ip: '192.0.2.88', code: 'GB', result: 'passed', hits: 9 },
	{ id: 8, time: '2026-07-05 10:31', ip: '203.0.113.200', code: 'BR', result: 'blocked', hits: 2 },
	{ id: 9, time: '2026-07-05 10:33', ip: '198.51.100.77', code: 'US', result: 'passed', hits: 14 },
	{ id: 10, time: '2026-07-05 10:40', ip: '192.0.2.130', code: 'JP', result: 'passed', hits: 6 },
	{ id: 11, time: '2026-07-05 10:44', ip: '203.0.113.15', code: 'CN', result: 'blocked', hits: 30 },
	{ id: 12, time: '2026-07-05 10:51', ip: '198.51.100.61', code: 'DE', result: 'passed', hits: 4 },
];

export default function LogsDemo() {
	return (
		<DataTable
			columns={ columns }
			rows={ rows }
			selectable
			searchKeys={ [ 'ip', 'code', 'result' ] }
			initialPageSize={ 10 }
			bulkActions={ [
				{
					label: __( 'Delete', 'ip-location-block' ),
					destructive: true,
					// eslint-disable-next-line no-alert
					onClick: ( ids ) => window.alert( ids.join( ', ' ) ),
				},
			] }
		/>
	);
}
