/**
 * Geolocation provider selection + API keys. Writes settings.providers as a
 * map { name: '' | '@' | key } — '' off, '@' on without key, or the key. The
 * REST save honors this map directly (see class-rest.php).
 *
 * Also the free->paid surface: the IP Location Block provider is highlighted +
 * flagged "Recommended", City/State support is shown, and a Register button
 * links to sign-up. City/State data only comes from that provider.
 */
import { CheckboxControl, TextControl, Tooltip, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const Yes = () => <span className="ilb-yes" aria-label="yes">✓</span>;
const No = () => <span className="ilb-no" aria-label="no">—</span>;
const mark = ( ok ) => ( ok ? <Yes /> : <No /> );

const formatRequests = ( r ) => {
	if ( ! r || ! r.total ) {
		return '—';
	}
	if ( r.total < 0 ) {
		return __( 'Unlimited', 'ip-location-block' );
	}
	const n = r.total.toLocaleString();
	return r.term ? `${ n } / ${ r.term }` : n;
};

export default function ProviderTable( { providers, value, onChange } ) {
	if ( ! providers || ! providers.length ) {
		return <em>{ __( 'No providers available.', 'ip-location-block' ) }</em>;
	}
	const map = value || {};
	const setProvider = ( name, next ) => onChange( { ...map, [ name ]: next } );

	return (
		<>
			<table className="wp-list-table widefat striped ilb-provider-table">
				<thead>
					<tr>
						<th scope="col">{ __( 'Name', 'ip-location-block' ) }</th>
						<th scope="col">{ __( 'API Key', 'ip-location-block' ) }</th>
						<th scope="col" className="ilb-prio-3">{ __( 'IPv4', 'ip-location-block' ) }</th>
						<th scope="col" className="ilb-prio-3">{ __( 'IPv6', 'ip-location-block' ) }</th>
						<th scope="col" className="ilb-prio-2">{ __( 'ASN', 'ip-location-block' ) }</th>
						<th scope="col">{ __( 'City', 'ip-location-block' ) }</th>
						<th scope="col">{ __( 'State', 'ip-location-block' ) }</th>
						<th scope="col" className="ilb-prio-2">{ __( 'Free requests', 'ip-location-block' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ providers.map( ( p ) => {
						const cur = map[ p.name ] ?? '';
						const enabled = !! cur;
						const key = cur === '@' ? '' : cur;
						return (
							<tr key={ p.name } className={ p.recommended ? 'is-recommended' : undefined }>
								<td>
									<div className="ilb-provider-name">
										<CheckboxControl
											__nextHasNoMarginBottom
											label={ p.name }
											checked={ enabled }
											onChange={ ( on ) => setProvider( p.name, on ? key || '@' : '' ) }
										/>
										{ p.recommended && (
											<span className="ilb-provider-badge">
												{ __( 'Recommended', 'ip-location-block' ) }
											</span>
										) }
										{ p.type && (
											<Tooltip text={ p.type }>
												<span
													className="dashicons dashicons-info-outline ilb-provider-info"
													role="img"
													aria-label={ p.type }
												/>
											</Tooltip>
										) }
									</div>
									{ p.link && (
										<Button
											variant="link"
											href={ p.link }
											target="_blank"
											rel="noreferrer"
											className="ilb-provider-register"
										>
											{ p.recommended
												? __( 'Get an API key', 'ip-location-block' )
												: __( 'Register', 'ip-location-block' ) }
										</Button>
									) }
								</td>
								<td>
									<TextControl
										__nextHasNoMarginBottom
										value={ key }
										placeholder={ __( 'API Key', 'ip-location-block' ) }
										onChange={ ( v ) => setProvider( p.name, v ? v : enabled ? '@' : '' ) }
									/>
								</td>
								<td className="ilb-prio-3">{ mark( p.supports.ipv4 ) }</td>
								<td className="ilb-prio-3">{ mark( p.supports.ipv6 ) }</td>
								<td className="ilb-prio-2">{ mark( p.supports.asn ) }</td>
								<td>{ mark( p.supports.city ) }</td>
								<td>{ mark( p.supports.state ) }</td>
								<td className="ilb-prio-2">{ formatRequests( p.requests ) }</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
			<p className="ilb-provider-note">
				<span className="dashicons dashicons-info-outline" aria-hidden="true" />
				{ __(
					'City- and state-level blocking require the IP Location Block provider and are not compatible with other providers.',
					'ip-location-block'
				) }
			</p>
		</>
	);
}
