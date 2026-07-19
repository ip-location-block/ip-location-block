/**
 * Read-only status of the local geolocation database files.
 */
/* eslint-disable no-nested-ternary */
import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { updateDatabase } from '../api';

// The /database/update response carries per-file results keyed by provider:
// { Provider: { ipv4: { filename, message }, … } }. Flatten it into readable
// "Provider file — message" lines for the success toast.
export const summarizeDatabaseUpdate = ( response ) => {
	if ( ! response || typeof response !== 'object' ) {
		return [];
	}
	const lines = [];
	Object.keys( response ).forEach( ( provider ) => {
		const files = response[ provider ];
		if ( ! files || typeof files !== 'object' ) {
			return;
		}
		Object.keys( files ).forEach( ( key ) => {
			const message = files[ key ] && files[ key ].message;
			if ( message ) {
				lines.push(
					`${ provider } ${ key }: ${ String( message ).replace(
						/<\/?[^>]+>/g,
						''
					) }`
				);
			}
		} );
	} );
	return lines;
};

export default function DatabaseStatus( { rows, schedule, onRefresh } ) {
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( null );
	if ( ! rows || ! rows.length ) {
		return null;
	}
	return (
		<div className="ilb-database-status">
			<div className="ilb-database-status__toolbar">
				<div>
					<strong>
						{ __(
							'Database update schedule',
							'ip-location-block'
						) }
					</strong>
					<p
						className={
							schedule?.status === 'missing' ? 'is-warning' : ''
						}
					>
						{ schedule?.status === 'scheduled'
							? sprintf(
									/* translators: %s: localized schedule date. */
									__( 'Next run: %s', 'ip-location-block' ),
									schedule.formatted
							  )
							: schedule?.status === 'disabled'
							? __(
									'Automatic updates are disabled.',
									'ip-location-block'
							  )
							: __(
									'The expected WP-Cron event is missing.',
									'ip-location-block'
							  ) }
					</p>
				</div>
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ busy }
					onClick={ () => {
						setBusy( true );
						setMessage( null );
						updateDatabase()
							.then( ( response ) => {
								setMessage( {
									status: 'success',
									text: __(
										'Database update completed.',
										'ip-location-block'
									),
									details:
										summarizeDatabaseUpdate( response ),
								} );
								return onRefresh?.();
							} )
							.catch( ( error ) =>
								setMessage( {
									status: 'error',
									text: error.message,
								} )
							)
							.finally( () => setBusy( false ) );
					} }
				>
					{ __( 'Download database now', 'ip-location-block' ) }
				</Button>
			</div>
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
					{ message.details && message.details.length > 0 && (
						<ul className="ilb-database-status__summary">
							{ message.details.map( ( line, index ) => (
								<li key={ index }>{ line }</li>
							) ) }
						</ul>
					) }
				</Notice>
			) }
			<table className="wp-list-table widefat striped ilb-db-status">
				<thead>
					<tr>
						<th scope="col">
							{ __( 'Database', 'ip-location-block' ) }
						</th>
						<th scope="col">
							{ __( 'File', 'ip-location-block' ) }
						</th>
						<th scope="col">
							{ __( 'Last update', 'ip-location-block' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( r ) => (
						<tr key={ r.label }>
							<td
								data-colname={ __(
									'Database',
									'ip-location-block'
								) }
							>
								{ r.label }
							</td>
							<td
								data-colname={ __(
									'File',
									'ip-location-block'
								) }
							>
								{ r.path ? (
									r.exists ? (
										<code>{ r.path }</code>
									) : (
										<em>
											{ __(
												'File does not exist.',
												'ip-location-block'
											) }
										</em>
									)
								) : (
									<em>
										{ __(
											'Not configured.',
											'ip-location-block'
										) }
									</em>
								) }
							</td>
							<td
								data-colname={ __(
									'Last update',
									'ip-location-block'
								) }
							>
								{ r.last || '—' }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
