/**
 * Sites tab (network admin only) — per-blog blocked-request counts across the
 * multisite network. Ports the classic "Statistics in the network" table.
 */
import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, Flex, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import DataTable from '../components/DataTable';
import { getNetworkStats } from '../api';

const DURATIONS = [
	{ label: __( 'All', 'ip-location-block' ), value: '0' },
	{ label: __( 'Latest 1 hour', 'ip-location-block' ), value: '1' },
	{ label: __( 'Latest 24 hours', 'ip-location-block' ), value: '2' },
	{ label: __( 'Latest 1 week', 'ip-location-block' ), value: '3' },
	{ label: __( 'Latest 1 month', 'ip-location-block' ), value: '4' },
];

const columns = [
	{
		key: 'site',
		header: __( 'Site', 'ip-location-block' ),
		render: ( r ) =>
			r.link ? (
				<a href={ r.link } target="_blank" rel="noreferrer">
					{ r.site }
				</a>
			) : (
				r.site
			),
	},
	{ key: 'comment', header: __( 'Comment', 'ip-location-block' ) },
	{ key: 'xmlrpc', header: __( 'XML-RPC', 'ip-location-block' ) },
	{ key: 'login', header: __( 'Login', 'ip-location-block' ) },
	{ key: 'admin', header: __( 'Admin', 'ip-location-block' ) },
	{ key: 'public', header: __( 'Public', 'ip-location-block' ) },
	{ key: 'total', header: __( 'Total', 'ip-location-block' ) },
];

export default function Sites() {
	const [ rows, setRows ] = useState( [] );
	const [ duration, setDuration ] = useState( '0' );
	const [ tick, setTick ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		setLoading( true );
		setError( null );
		getNetworkStats( duration )
			.then( ( data ) =>
				setRows(
					( Array.isArray( data ) ? data : [] ).map( ( r, i ) => ( {
						id: i,
						...r,
					} ) )
				)
			)
			.catch( ( e ) => setError( e.message || String( e ) ) )
			.finally( () => setLoading( false ) );
	}, [ duration, tick ] );

	return (
		<div className="ilb-sites">
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<Flex justify="flex-start" gap={ 3 } wrap className="ilb-toolbar">
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Duration', 'ip-location-block' ) }
					hideLabelFromVision
					value={ duration }
					options={ DURATIONS }
					onChange={ setDuration }
				/>
				<Button
					variant="secondary"
					onClick={ () => setTick( ( t ) => t + 1 ) }
				>
					{ __( 'Refresh', 'ip-location-block' ) }
				</Button>
			</Flex>

			<DataTable
				columns={ columns }
				rows={ rows }
				loading={ loading }
				searchKeys={ [ 'site' ] }
				emptyMessage={ __( 'No sites found.', 'ip-location-block' ) }
			/>
		</div>
	);
}
