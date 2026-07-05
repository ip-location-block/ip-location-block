/**
 * Read-only status of the local geolocation database files.
 */
import { __ } from '@wordpress/i18n';

export default function DatabaseStatus( { rows } ) {
	if ( ! rows || ! rows.length ) {
		return null;
	}
	return (
		<table className="wp-list-table widefat striped ilb-db-status">
			<thead>
				<tr>
					<th scope="col">{ __( 'Database', 'ip-location-block' ) }</th>
					<th scope="col">{ __( 'File', 'ip-location-block' ) }</th>
					<th scope="col">{ __( 'Last update', 'ip-location-block' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( r ) => (
					<tr key={ r.label }>
						<td>{ r.label }</td>
						<td>
							{ r.path
								? r.exists
									? <code>{ r.path }</code>
									: <em>{ __( 'File does not exist.', 'ip-location-block' ) }</em>
								: <em>{ __( 'Not configured.', 'ip-location-block' ) }</em> }
						</td>
						<td>{ r.last || '—' }</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}
