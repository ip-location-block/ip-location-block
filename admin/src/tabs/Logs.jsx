/**
 * Logs tab — the first real <DataTable> consumer: live REST log data with a
 * target filter, refresh / auto-refresh, bulk whitelist / blacklist / erase,
 * and CSV export.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { SelectControl, Button, ToggleControl, Notice, Flex } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import DataTable from '../components/DataTable';
import { getLogs, clearLogs, eraseLogEntries, addToList } from '../api';

const HOOKS = [
	{ label: __( 'All targets', 'ip-location-block' ), value: '' },
	{ label: __( 'Comment', 'ip-location-block' ), value: 'comment' },
	{ label: __( 'Login', 'ip-location-block' ), value: 'login' },
	{ label: __( 'Admin', 'ip-location-block' ), value: 'admin' },
	{ label: __( 'XML-RPC', 'ip-location-block' ), value: 'xmlrpc' },
	{ label: __( 'Public', 'ip-location-block' ), value: 'public' },
];

const columns = [
	{
		key: 'time',
		header: __( 'Time', 'ip-location-block' ),
		render: ( r ) => ( r.time ? new Date( r.time * 1000 ).toLocaleString() : '' ),
	},
	{ key: 'ip', header: __( 'IP address', 'ip-location-block' ) },
	{ key: 'code', header: __( 'Code', 'ip-location-block' ) },
	{ key: 'asn', header: __( 'ASN', 'ip-location-block' ), priority: 3 },
	{ key: 'result', header: __( 'Result', 'ip-location-block' ) },
	{ key: 'target', header: __( 'Target', 'ip-location-block' ), priority: 2 },
	{ key: 'method', header: __( 'Request', 'ip-location-block' ), priority: 2 },
];

export default function Logs() {
	const [ rows, setRows ] = useState( [] );
	const [ hook, setHook ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ auto, setAuto ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const load = useCallback( () => {
		setLoading( true );
		getLogs( hook )
			.then( ( data ) => setRows( Array.isArray( data ) ? data : [] ) )
			.catch( ( e ) => setNotice( { status: 'error', msg: e.message } ) )
			.finally( () => setLoading( false ) );
	}, [ hook ] );

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		if ( ! auto ) {
			return undefined;
		}
		const id = setInterval( load, 5000 );
		return () => clearInterval( id );
	}, [ auto, load ] );

	const ipsFor = ( ids ) => {
		const set = new Set( ids );
		return rows.filter( ( r ) => set.has( r.id ) ).map( ( r ) => r.ip ).filter( Boolean );
	};

	const act = ( promise, okMsg ) => {
		setNotice( null );
		promise
			.then( () => {
				setNotice( { status: 'success', msg: okMsg } );
				load();
			} )
			.catch( ( e ) => setNotice( { status: 'error', msg: e.message } ) );
	};

	const exportCsv = () => {
		const head = columns.map( ( c ) => c.key );
		const csv = [ head.join( ',' ) ]
			.concat(
				rows.map( ( r ) =>
					head.map( ( k ) => `"${ String( r[ k ] ?? '' ).replace( /"/g, '""' ) }"` ).join( ',' )
				)
			)
			.join( '\n' );
		const url = window.URL.createObjectURL( new window.Blob( [ csv ], { type: 'text/csv' } ) );
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = 'ip-location-block-logs.csv';
		a.click();
		window.URL.revokeObjectURL( url );
	};

	const bulkActions = [
		{ label: __( 'Whitelist', 'ip-location-block' ), onClick: ( ids ) => act( addToList( 'white', ipsFor( ids ) ), __( 'Added to whitelist.', 'ip-location-block' ) ) },
		{ label: __( 'Blacklist', 'ip-location-block' ), onClick: ( ids ) => act( addToList( 'black', ipsFor( ids ) ), __( 'Added to blacklist.', 'ip-location-block' ) ) },
		{ label: __( 'Erase', 'ip-location-block' ), destructive: true, onClick: ( ids ) => act( eraseLogEntries( ipsFor( ids ) ), __( 'Entries erased.', 'ip-location-block' ) ) },
	];

	return (
		<div className="ilb-logs">
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }

			<Flex justify="flex-start" gap={ 3 } wrap style={ { marginBottom: '12px' } }>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Target', 'ip-location-block' ) }
					hideLabelFromVision
					value={ hook }
					options={ HOOKS }
					onChange={ setHook }
				/>
				<Button variant="secondary" onClick={ load }>
					{ __( 'Refresh', 'ip-location-block' ) }
				</Button>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Auto-refresh (5s)', 'ip-location-block' ) }
					checked={ auto }
					onChange={ setAuto }
				/>
				<Button variant="secondary" onClick={ exportCsv } disabled={ ! rows.length }>
					{ __( 'Export CSV', 'ip-location-block' ) }
				</Button>
				<Button variant="secondary" isDestructive onClick={ () => act( clearLogs( hook ), __( 'Logs cleared.', 'ip-location-block' ) ) }>
					{ __( 'Clear logs', 'ip-location-block' ) }
				</Button>
			</Flex>

			<DataTable
				columns={ columns }
				rows={ rows }
				loading={ loading }
				selectable
				searchKeys={ [ 'ip', 'code', 'asn', 'result', 'target', 'method' ] }
				bulkActions={ bulkActions }
				emptyMessage={ __( 'No log entries.', 'ip-location-block' ) }
			/>
		</div>
	);
}
