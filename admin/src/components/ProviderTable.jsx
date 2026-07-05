/**
 * Geolocation provider selection + API keys. Writes settings.providers as a
 * map { name: '' | '@' | key } — '' off, '@' on without key, or the key. The
 * REST save honors this map directly (see class-rest.php).
 */
import { CheckboxControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const yesno = ( ok ) => ( ok ? '✓' : '—' );

export default function ProviderTable( { providers, value, onChange } ) {
	if ( ! providers || ! providers.length ) {
		return <em>{ __( 'No providers available.', 'ip-location-block' ) }</em>;
	}
	const map = value || {};
	const setProvider = ( name, next ) => onChange( { ...map, [ name ]: next } );

	return (
		<table className="wp-list-table widefat striped ilb-provider-table">
			<thead>
				<tr>
					<th scope="col">{ __( 'Name', 'ip-location-block' ) }</th>
					<th scope="col">{ __( 'API Key', 'ip-location-block' ) }</th>
					<th scope="col">{ __( 'IPv4', 'ip-location-block' ) }</th>
					<th scope="col">{ __( 'IPv6', 'ip-location-block' ) }</th>
					<th scope="col">{ __( 'ASN', 'ip-location-block' ) }</th>
					<th scope="col">{ __( 'City', 'ip-location-block' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ providers.map( ( p ) => {
					const cur = map[ p.name ] ?? '';
					const enabled = !! cur;
					const key = cur === '@' ? '' : cur;
					return (
						<tr key={ p.name }>
							<td>
								<CheckboxControl
									__nextHasNoMarginBottom
									label={ p.name }
									checked={ enabled }
									onChange={ ( on ) => setProvider( p.name, on ? key || '@' : '' ) }
								/>
							</td>
							<td>
								<TextControl
									__nextHasNoMarginBottom
									value={ key }
									placeholder={ __( 'API Key', 'ip-location-block' ) }
									onChange={ ( v ) => setProvider( p.name, v ? v : enabled ? '@' : '' ) }
								/>
							</td>
							<td>{ yesno( p.supports.ipv4 ) }</td>
							<td>{ yesno( p.supports.ipv6 ) }</td>
							<td>{ yesno( p.supports.asn ) }</td>
							<td>{ yesno( p.supports.city ) }</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
}
