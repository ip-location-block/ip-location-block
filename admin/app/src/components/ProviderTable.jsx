/**
 * Geolocation provider selection + API keys. Writes settings.providers as a
 * map { name: '' | '@' | key } — '' off, '@' on without key, or the key. The
 * REST save honors this map directly (see class-rest.php).
 *
 * Also the free->paid surface: the IP Location Block provider gets an
 * understated recommendation marker, City/State support is shown, and a
 * registration link points to sign-up. City/State data only comes from that
 * provider.
 */
/* eslint-disable no-nested-ternary */
import {
	CheckboxControl,
	TextControl,
	Tooltip,
	Button,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { quotaSummary } from '../providerLogic';

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

const capabilityLabels = () => [
	[ 'ipv4', __( 'IPv4', 'ip-location-block' ) ],
	[ 'ipv6', __( 'IPv6', 'ip-location-block' ) ],
	[ 'asn', __( 'ASN', 'ip-location-block' ) ],
	[ 'city', __( 'City', 'ip-location-block' ) ],
	[ 'state', __( 'State', 'ip-location-block' ) ],
];

const LiveQuota = ( { quota } ) => {
	if ( ! quota ) {
		return <span>{ __( 'Quota unavailable', 'ip-location-block' ) }</span>;
	}
	const summary = quotaSummary( quota, {
		unavailable: __( 'Quota unavailable', 'ip-location-block' ),
		unlimited: __( 'Unlimited', 'ip-location-block' ),
		remaining: ( value ) =>
			sprintf(
				/* translators: %s: remaining API requests. */
				__( '%s remaining', 'ip-location-block' ),
				value
			),
	} );

	return (
		<div className={ `ilb-provider-live-quota is-${ quota.status }` }>
			<strong>{ summary }</strong>
			{ quota.planName && <span>{ quota.planName }</span> }
		</div>
	);
};

export default function ProviderTable( {
	providers,
	value,
	status,
	onChange,
} ) {
	if ( ! providers || ! providers.length ) {
		return (
			<em>{ __( 'No providers available.', 'ip-location-block' ) }</em>
		);
	}
	const map = value || {};
	const setProvider = ( name, next ) =>
		onChange( { ...map, [ name ]: next } );

	return (
		<>
			<table className="wp-list-table widefat ilb-provider-table">
				<thead>
					<tr>
						<th scope="col">
							{ __( 'Provider', 'ip-location-block' ) }
						</th>
						<th scope="col">
							{ __( 'API Key', 'ip-location-block' ) }
						</th>
						<th scope="col">
							{ __( 'Capabilities', 'ip-location-block' ) }
						</th>
						<th scope="col">
							{ __( 'Status / allowance', 'ip-location-block' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ providers.map( ( p ) => {
						const cur = Object.prototype.hasOwnProperty.call(
							map,
							p.name
						)
							? map[ p.name ]
							: p.selected
							? '@'
							: '';
						const enabled = !! cur;
						const key = cur === '@' ? '' : cur;
						const liveQuota =
							p.name === 'IP Location Block' &&
							enabled &&
							cur === p.value
								? status?.quota
								: null;
						const capabilities = capabilityLabels()
							.filter( ( [ id ] ) => p.supports?.[ id ] )
							.map( ( [ , label ] ) => label );
						return (
							<tr
								key={ p.name }
								className={
									p.recommended ? 'is-recommended' : undefined
								}
							>
								<td
									data-colname={ __(
										'Provider',
										'ip-location-block'
									) }
								>
									<div className="ilb-provider-name">
										<CheckboxControl
											__nextHasNoMarginBottom
											label={ p.name }
											checked={ enabled }
											onChange={ ( on ) =>
												setProvider(
													p.name,
													on ? key || '@' : ''
												)
											}
										/>
										{ p.recommended && (
											<span className="ilb-provider-recommended">
												{ __(
													'Recommended',
													'ip-location-block'
												) }
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
												? __(
														'Get an API key',
														'ip-location-block'
												  )
												: __(
														'Register',
														'ip-location-block'
												  ) }
										</Button>
									) }
								</td>
								<td
									data-colname={ __(
										'API Key',
										'ip-location-block'
									) }
								>
									{ p.auth === 'none' ? (
										<span className="ilb-provider-no-key">
											{ __(
												'No API key required',
												'ip-location-block'
											) }
										</span>
									) : (
										<TextControl
											__nextHasNoMarginBottom
											hideLabelFromVision
											label={ sprintf(
												/* translators: %s: geolocation provider name. */
												__(
													'API key for %s',
													'ip-location-block'
												),
												p.name
											) }
											value={ key }
											placeholder={ __(
												'Enter API key',
												'ip-location-block'
											) }
											onChange={ ( v ) =>
												setProvider(
													p.name,
													v ? v : enabled ? '@' : ''
												)
											}
										/>
									) }
								</td>
								<td
									data-colname={ __(
										'Capabilities',
										'ip-location-block'
									) }
									className="ilb-provider-capabilities"
								>
									{ capabilities.length
										? capabilities.join( ' · ' )
										: '—' }
								</td>
								<td
									data-colname={ __(
										'Status / allowance',
										'ip-location-block'
									) }
									className="ilb-provider-quota"
								>
									{ p.local ? (
										<span
											className={
												p.databaseReady
													? 'is-ready'
													: 'is-warning'
											}
										>
											{ p.databaseReady
												? __(
														'Database ready',
														'ip-location-block'
												  )
												: __(
														'Database required',
														'ip-location-block'
												  ) }
										</span>
									) : p.name === 'IP Location Block' &&
									  enabled ? (
										<LiveQuota quota={ liveQuota } />
									) : (
										formatRequests( p.requests )
									) }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
			<div className="ilb-provider-note" role="note">
				<span
					className="dashicons dashicons-info-outline"
					aria-hidden="true"
				/>
				<p>
					{ __(
						'City and state rules require the IP Location Block provider and cannot be combined with other providers.',
						'ip-location-block'
					) }
				</p>
			</div>
		</>
	);
}
