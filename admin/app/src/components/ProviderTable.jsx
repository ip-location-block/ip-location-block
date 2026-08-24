/**
 * Geolocation provider selection + API keys. Writes settings.providers as a
 * map { name: '' | '@' | key } — '' off, '@' on without key, or the key. The
 * REST save honors this map directly (see class-rest.php).
 *
 * Also the free->paid surface: the IP Location Block provider gets an
 * understated recommendation marker and regional support is shown without
 * duplicating the full Native Mode product story from Simple settings.
 */
/* eslint-disable no-nested-ternary */
import {
	CheckboxControl,
	TextControl,
	Tooltip,
	Button,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { quotaSummary } from '../providerLogic';

const formatRequests = ( r ) => {
	if ( ! r || ! r.total ) {
		return __( 'Not available', 'ip-location-block' );
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
	[ 'state', __( 'State/region', 'ip-location-block' ) ],
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
			<span
				className="ilb-provider-live-quota__indicator"
				aria-hidden="true"
			/>
			<span className="ilb-provider-live-quota__content">
				<strong>{ summary }</strong>
				{ quota.planName && <span>{ quota.planName }</span> }
			</span>
		</div>
	);
};

export default function ProviderTable( {
	providers,
	value,
	status,
	onChange,
	providerAction,
} ) {
	const [ editingKeys, setEditingKeys ] = useState( {} );
	if ( ! providers || ! providers.length ) {
		return (
			<em>{ __( 'No providers available.', 'ip-location-block' ) }</em>
		);
	}
	const map = value || {};
	const setProvider = ( name, next ) =>
		onChange( { ...map, [ name ]: next } );
	const requestDisconnect = ( name ) => {
		if ( providerAction?.requestDisconnect ) {
			providerAction.requestDisconnect( name );
			return;
		}
		setProvider( name, '' );
	};

	return (
		<>
			<table className="widefat ilb-provider-table">
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
							{ __( 'Availability', 'ip-location-block' ) }
						</th>
						<th scope="col">
							{ __( 'Actions', 'ip-location-block' ) }
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
						const editingKey = !! editingKeys[ p.name ];
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
								className={ [
									p.recommended ? 'is-recommended' : '',
									enabled ? 'is-enabled' : '',
								]
									.filter( Boolean )
									.join( ' ' ) }
							>
								<td
									data-colname={ __(
										'Provider',
										'ip-location-block'
									) }
									className="ilb-provider-table__provider"
								>
									<div className="ilb-provider-identity">
										<CheckboxControl
											__nextHasNoMarginBottom
											label={ p.name }
											checked={ enabled }
											onChange={ ( on ) =>
												on
													? setProvider(
															p.name,
															key || '@'
													  )
													: requestDisconnect(
															p.name
													  )
											}
											disabled={
												!! providerAction?.pending
											}
										/>
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
									{ ( p.recommended || p.link ) && (
										<div className="ilb-provider-table__provider-meta">
											{ p.recommended && (
												<span className="ilb-provider-recommended">
													{ __(
														'Recommended',
														'ip-location-block'
													) }
												</span>
											) }
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
																'Plans & API key',
																'ip-location-block'
														  )
														: __(
																'Register',
																'ip-location-block'
														  ) }
												</Button>
											) }
										</div>
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
											<span
												className="dashicons dashicons-yes-alt"
												aria-hidden="true"
											/>
											{ __(
												'No API key required',
												'ip-location-block'
											) }
										</span>
									) : enabled && ! editingKey && key ? (
										<div className="ilb-provider-saved-key">
											<span className="ilb-provider-saved-key__status">
												<span
													className="dashicons dashicons-yes-alt"
													aria-hidden="true"
												/>
												<span>
													{ __(
														'Credential saved',
														'ip-location-block'
													) }
												</span>
											</span>
											<Button
												variant="link"
												disabled={
													!! providerAction?.pending
												}
												onClick={ () =>
													setEditingKeys(
														( current ) => ( {
															...current,
															[ p.name ]: true,
														} )
													)
												}
											>
												{ __(
													'Replace key',
													'ip-location-block'
												) }
											</Button>
										</div>
									) : (
										<TextControl
											__nextHasNoMarginBottom
											type="password"
											className="ilb-provider-key-control"
											disabled={
												!! providerAction?.pending
											}
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
									{ capabilities.length ? (
										<div className="ilb-provider-capability-list">
											{ capabilities.map(
												( capability ) => (
													<span
														className={ `ilb-provider-capability${
															capability ===
															__(
																'State/region',
																'ip-location-block'
															)
																? ' is-precision'
																: ''
														}` }
														key={ capability }
													>
														{ capability }
													</span>
												)
											) }
										</div>
									) : (
										__(
											'Not available',
											'ip-location-block'
										)
									) }
								</td>
								<td
									data-colname={ __(
										'Availability',
										'ip-location-block'
									) }
									className="ilb-provider-quota"
								>
									{ p.local ? (
										<span
											className={
												'ilb-provider-availability ' +
												( p.databaseReady
													? 'is-ready'
													: 'is-warning' )
											}
										>
											<span
												className="ilb-provider-availability__indicator"
												aria-hidden="true"
											/>
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
										<span className="ilb-provider-allowance">
											{ formatRequests( p.requests ) }
										</span>
									) }
								</td>
								<td
									data-colname={ __(
										'Actions',
										'ip-location-block'
									) }
									className={ `ilb-provider-table__actions${
										enabled ? '' : ' is-empty'
									}` }
								>
									{ enabled ? (
										<Button
											variant="link"
											isDestructive
											disabled={
												!! providerAction?.pending
											}
											onClick={ () =>
												requestDisconnect( p.name )
											}
										>
											{ __(
												'Disconnect',
												'ip-location-block'
											) }
										</Button>
									) : (
										<span className="screen-reader-text">
											{ __(
												'No action available',
												'ip-location-block'
											) }
										</span>
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
						'IP Location Block is prioritized automatically for regional rules. Other selected providers remain available as country-level fallbacks.',
						'ip-location-block'
					) }
				</p>
			</div>
		</>
	);
}
