/* eslint-disable no-nested-ternary */
import { useEffect, useMemo, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Modal,
	Notice,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { testProvider } from '../api';
import { betaUrl } from '../navigation';
import {
	activeProviderStatuses,
	formatAllowance,
	makeExclusiveProviderMap,
	providerNames,
	quotaBlocksProvider,
	quotaSummary,
} from '../providerLogic';
import { RegionalBenefits, UpgradeButton } from './PrecisionContent';

const NATIVE = 'IP Location Block';
const boot = window.ipLocationBlockAdmin || {};

const statusCopy = ( ready, active, quota, activated ) => {
	if ( quotaBlocksProvider( quota ) ) {
		return {
			className: 'is-error',
			label: __( 'Action required', 'ip-location-block' ),
		};
	}
	if ( activated ) {
		return {
			className: 'is-ready',
			label: __( 'Ready to save', 'ip-location-block' ),
		};
	}
	if ( ready ) {
		return {
			className: 'is-ready',
			label: __( 'Connected', 'ip-location-block' ),
		};
	}
	return {
		className: 'is-warning',
		label: active.length
			? __( 'Needs attention', 'ip-location-block' )
			: __( 'Set up provider', 'ip-location-block' ),
	};
};

const localizedAllowance = ( allowance ) =>
	formatAllowance( allowance, {
		unavailable: __( 'Not available', 'ip-location-block' ),
		unlimited: __( 'Unlimited', 'ip-location-block' ),
	} );

const localizedQuotaSummary = ( quota ) =>
	quotaSummary( quota, {
		unavailable: __( 'Quota unavailable', 'ip-location-block' ),
		unlimited: __( 'Unlimited', 'ip-location-block' ),
		remaining: ( value ) =>
			sprintf(
				/* translators: %s: remaining API requests. */
				__( '%s remaining', 'ip-location-block' ),
				value
			),
	} );

function QuotaDetails( { quota } ) {
	if ( ! quota ) {
		return null;
	}

	const rows = [];
	if ( quota.planName ) {
		rows.push( [ __( 'Plan', 'ip-location-block' ), quota.planName ] );
	}
	if ( quota.unlimited ) {
		rows.push( [
			__( 'Recurring balance', 'ip-location-block' ),
			__( 'Unlimited', 'ip-location-block' ),
		] );
	} else if ( quota.recurring !== null ) {
		rows.push( [
			__( 'Recurring balance', 'ip-location-block' ),
			quota.limit !== null
				? `${ Number( quota.recurring ).toLocaleString() } / ${ Number(
						quota.limit
				  ).toLocaleString() }`
				: Number( quota.recurring ).toLocaleString(),
		] );
	}
	if ( ! quota.unlimited && quota.oneTime !== null ) {
		rows.push( [
			__( 'One-time balance', 'ip-location-block' ),
			Number( quota.oneTime ).toLocaleString(),
		] );
	}
	if ( ! quota.unlimited && quota.total !== null ) {
		rows.push( [
			__( 'Total remaining', 'ip-location-block' ),
			Number( quota.total ).toLocaleString(),
		] );
	}
	if ( quota.checkedAt ) {
		rows.push( [
			__( 'Last checked', 'ip-location-block' ),
			new Date( Number( quota.checkedAt ) * 1000 ).toLocaleString(),
		] );
	}

	return (
		<details className="ilb-provider-setup__quota-details">
			<summary>{ __( 'Quota details', 'ip-location-block' ) }</summary>
			{ rows.length > 0 && (
				<dl>
					{ rows.map( ( [ label, value ] ) => (
						<div key={ label }>
							<dt>{ label }</dt>
							<dd>{ value }</dd>
						</div>
					) ) }
				</dl>
			) }
			{ quota.message && (
				<p className="ilb-provider-setup__quota-message">
					{ quota.message }
				</p>
			) }
		</details>
	);
}

export default function ProviderSetup( {
	settings,
	providers = [],
	status = null,
	onChange,
	onStateChange,
	providerAction,
} ) {
	const providerMap =
		settings.providers && ! Array.isArray( settings.providers )
			? settings.providers
			: {};
	const activeFromStatus = activeProviderStatuses( status );
	const [ activated, setActivated ] = useState( null );
	const [ credentials, setCredentials ] = useState( () =>
		Object.fromEntries(
			Object.entries( providerMap )
				.filter( ( [ , value ] ) => value && value !== '@' )
				.map( ( [ name, value ] ) => [ name, value ] )
		)
	);
	const [ nativeFormOpen, setNativeFormOpen ] = useState( false );
	const [ alternativesOpen, setAlternativesOpen ] = useState( false );
	const [ switchRequest, setSwitchRequest ] = useState( null );
	const [ testing, setTesting ] = useState( '' );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		setCredentials( ( current ) => {
			const next = { ...current };
			Object.entries( providerMap ).forEach( ( [ name, value ] ) => {
				if ( value === '' ) {
					next[ name ] = '';
				} else if ( value !== '@' && ! next[ name ] ) {
					next[ name ] = value;
				}
			} );
			return next;
		} );
		if ( activated && ! providerMap[ activated.provider ] ) {
			setActivated( null );
		}
	}, [ providerMap, activated ] );

	useEffect( () => {
		const clearDraftStatus = () => setActivated( null );
		window.addEventListener(
			'ip-location-block-settings-saved',
			clearDraftStatus
		);
		return () =>
			window.removeEventListener(
				'ip-location-block-settings-saved',
				clearDraftStatus
			);
	}, [] );

	const active = useMemo( () => {
		if ( activated && providerMap[ activated.provider ] ) {
			return [
				{
					name: activated.provider,
					active: true,
					ready: activated.ready !== false,
					verified: activated.verified !== false,
					local: !! activated.local,
					allowance: providers.find(
						( item ) => item.name === activated.provider
					)?.requests,
				},
			];
		}
		return activeFromStatus.filter(
			( item ) =>
				! Object.prototype.hasOwnProperty.call(
					providerMap,
					item.name
				) || !! providerMap[ item.name ]
		);
	}, [ activated, activeFromStatus, providerMap, providers ] );
	const switchableProviders = useMemo( () => {
		const activeNames = new Set( active.map( ( item ) => item.name ) );
		return providers
			.filter( ( provider ) => ! activeNames.has( provider.name ) )
			.sort(
				( left, right ) =>
					Number( right.name === NATIVE ) -
					Number( left.name === NATIVE )
			);
	}, [ active, providers ] );
	const [ alternative, setAlternative ] = useState(
		() =>
			providers.find( ( provider ) => provider.name === NATIVE )?.name ||
			providers[ 0 ]?.name ||
			''
	);

	useEffect( () => {
		if (
			! switchableProviders.some(
				( provider ) => provider.name === alternative
			)
		) {
			setAlternative( switchableProviders[ 0 ]?.name || '' );
		}
	}, [ alternative, switchableProviders ] );

	const activeNative = active.some( ( item ) => item.name === NATIVE );
	const quota = activeNative ? activated?.quota || status?.quota : null;
	const ready = active.some(
		( item ) =>
			item.ready !== false &&
			( item.name !== NATIVE || ! quotaBlocksProvider( quota ) )
	);
	const hasSelection = active.length > 0;
	const transitioning = !! testing || !! switchRequest;
	const activeNamesKey = active.map( ( item ) => item.name ).join( '\u0000' );
	const badge = statusCopy( ready, active, quota, activated );

	useEffect( () => {
		onStateChange?.( {
			ready,
			hasSelection,
			transitioning,
			activeNames: activeNamesKey ? activeNamesKey.split( '\u0000' ) : [],
		} );
	}, [ activeNamesKey, hasSelection, onStateChange, ready, transitioning ] );

	const getCredential = ( provider ) => {
		if ( Object.prototype.hasOwnProperty.call( credentials, provider ) ) {
			return credentials[ provider ];
		}
		const stored = providerMap[ provider ];
		return stored && stored !== '@' ? stored : '';
	};

	const setCredential = ( provider, value ) =>
		setCredentials( ( current ) => ( {
			...current,
			[ provider ]: value,
		} ) );

	const requestSwitch = ( provider, apply, immediate = false ) => {
		const replaced = active
			.map( ( item ) => item.name )
			.filter( ( name ) => name !== provider );
		if ( ! replaced.length ) {
			apply();
			return;
		}
		setSwitchRequest( { provider, replaced, apply, immediate } );
	};

	const activateAfterTest = ( provider, credential, response ) => {
		const connectedProviders = response.settings?.providers;
		if ( response.connected && connectedProviders ) {
			onChange( 'providers', connectedProviders );
			setActivated( null );
			providerAction?.connected?.(
				connectedProviders,
				response.providerStatus
			);
			setNativeFormOpen( false );
			setNotice( {
				status: 'success',
				message: sprintf(
					/* translators: %s: provider name. */
					__( '%s is connected.', 'ip-location-block' ),
					provider
				),
			} );
			return;
		}

		onChange(
			'providers',
			makeExclusiveProviderMap(
				providerMap,
				providerNames( providers, status ),
				provider,
				credential
			)
		);
		setActivated( { ...response, provider, ready: true } );
		providerAction?.reportReady?.( provider, true );
		setNativeFormOpen( false );
		setNotice( {
			status: 'success',
			message: sprintf(
				/* translators: %s: provider name. */
				__(
					'%s is verified and selected. Save changes to apply it.',
					'ip-location-block'
				),
				provider
			),
		} );
	};

	const selectLocalProvider = ( provider ) => {
		const meta = providers.find( ( item ) => item.name === provider );
		const credential = getCredential( provider ).trim();
		if ( meta?.auth === 'required' && ! credential ) {
			setNotice( {
				status: 'error',
				message: __(
					'Enter the provider license key before selecting it.',
					'ip-location-block'
				),
			} );
			return;
		}
		requestSwitch( provider, () => {
			onChange(
				'providers',
				makeExclusiveProviderMap(
					providerMap,
					providerNames( providers, status ),
					provider,
					credential || '@'
				)
			);
			setActivated( {
				provider,
				local: true,
				ready: !! meta?.databaseReady,
				verified: true,
			} );
			providerAction?.reportReady?.( provider, !! meta?.databaseReady );
			setNotice( {
				status: meta?.databaseReady ? 'success' : 'warning',
				message: meta?.databaseReady
					? sprintf(
							/* translators: %s: provider name. */
							__(
								'%s is selected. Save changes to apply it.',
								'ip-location-block'
							),
							provider
					  )
					: __(
							'The provider is selected, but its database is missing. Save changes, then download the database in Advanced settings.',
							'ip-location-block'
					  ),
			} );
		} );
	};

	const runTest = ( provider, connect = false ) => {
		const meta = providers.find( ( item ) => item.name === provider );
		const credential = getCredential( provider ).trim();
		if (
			( provider === NATIVE || meta?.auth === 'required' ) &&
			! credential
		) {
			setNotice( {
				status: 'error',
				message: __(
					'Enter an API key before testing.',
					'ip-location-block'
				),
			} );
			setNativeFormOpen( provider === NATIVE );
			return;
		}

		requestSwitch(
			provider,
			() => {
				setTesting( provider );
				setNotice( null );
				testProvider( provider, credential, connect )
					.then( ( response ) => {
						if ( ! response.ok ) {
							setNotice( {
								status: 'error',
								message:
									response.message ||
									__(
										'The connection test failed.',
										'ip-location-block'
									),
							} );
							return;
						}
						activateAfterTest( provider, credential, response );
					} )
					.catch( ( error ) =>
						setNotice( {
							status: 'error',
							message:
								error.message ||
								__(
									'The connection test failed.',
									'ip-location-block'
								),
						} )
					)
					.finally( () => setTesting( '' ) );
			},
			connect
		);
	};

	const draftLocked = !! providerAction?.pending;
	const alternativeMeta = providers.find(
		( item ) => item.name === alternative
	);
	return (
		<Card
			id="ilb-provider-setup"
			className="ilb-panel-shell ilb-settings-card ilb-provider-setup"
		>
			<CardHeader className="ilb-panel-shell__header">
				<div>
					<h2
						id="ilb-provider-setup-title"
						className="ilb-panel-shell__title"
						tabIndex="-1"
					>
						{ __( 'Geolocation provider', 'ip-location-block' ) }
					</h2>
					<p className="ilb-panel-shell__description">
						{ __(
							'Connect the source that identifies each visitor’s location.',
							'ip-location-block'
						) }
					</p>
				</div>
				<span className={ `ilb-provider-state ${ badge.className }` }>
					{ badge.label }
				</span>
			</CardHeader>
			<CardBody>
				{ notice && (
					<Notice
						status={ notice.status }
						onRemove={ () => setNotice( null ) }
						className="ilb-provider-setup__notice"
					>
						{ notice.message }
					</Notice>
				) }

				{ active.length > 0 && (
					<section
						className={ `ilb-provider-dashboard${
							quotaBlocksProvider( quota ) ? ' is-error' : ''
						}` }
						aria-label={ __(
							'Connected provider',
							'ip-location-block'
						) }
					>
						{ active.map( ( item ) => {
							const isNative = item.name === NATIVE;
							const summary = isNative
								? localizedQuotaSummary( quota ) ||
								  __( 'Quota unavailable', 'ip-location-block' )
								: item.local
								? item.ready
									? __(
											'Local database ready',
											'ip-location-block'
									  )
									: __(
											'Local database required',
											'ip-location-block'
									  )
								: sprintf(
										/* translators: %s: provider request allowance. */
										__(
											'Plan allowance: %s',
											'ip-location-block'
										),
										localizedAllowance( item.allowance )
								  );
							return (
								<div
									className={ `ilb-provider-dashboard__provider${
										isNative ? ' is-native' : ''
									}` }
									key={ item.name }
								>
									<div className="ilb-provider-dashboard__top">
										<div className="ilb-provider-dashboard__identity">
											{ isNative && boot.logoUrl ? (
												<img
													src={ boot.logoUrl }
													alt=""
												/>
											) : (
												<span
													className="dashicons dashicons-location-alt"
													aria-hidden="true"
												/>
											) }
											<div>
												<span className="ilb-provider-setup__eyebrow">
													{ __(
														'Current provider',
														'ip-location-block'
													) }
												</span>
												<h3>{ item.name }</h3>
												<p>{ summary }</p>
											</div>
										</div>
									</div>

									{ isNative && (
										<div className="ilb-provider-dashboard__features">
											<span>
												{ __(
													'State/region precision',
													'ip-location-block'
												) }
											</span>
											<span>
												{ __(
													'IPv6',
													'ip-location-block'
												) }
											</span>
											<span>
												{ __(
													'ASN',
													'ip-location-block'
												) }
											</span>
										</div>
									) }

									{ isNative &&
										quota?.message &&
										quotaBlocksProvider( quota ) && (
											<p className="ilb-provider-dashboard__problem">
												{ quota.message }
											</p>
										) }
									{ isNative && (
										<QuotaDetails quota={ quota } />
									) }

									<div className="ilb-provider-dashboard__actions">
										{ isNative &&
											quotaBlocksProvider( quota ) &&
											quota?.upgradeUrl && (
												<Button
													variant="primary"
													href={ quota.upgradeUrl }
													target="_blank"
													rel="noreferrer"
												>
													{ __(
														'View plans',
														'ip-location-block'
													) }
												</Button>
											) }
										{ ! item.local && (
											<Button
												variant="secondary"
												isBusy={ testing === item.name }
												disabled={
													!! testing || draftLocked
												}
												onClick={ () =>
													runTest( item.name )
												}
											>
												{ __(
													'Test connection',
													'ip-location-block'
												) }
											</Button>
										) }
										{ isNative && quota?.accountUrl && (
											<Button
												variant="link"
												href={ quota.accountUrl }
												target="_blank"
												rel="noreferrer"
											>
												{ __(
													'Manage account',
													'ip-location-block'
												) }
											</Button>
										) }
										{ isNative && (
											<Button
												variant="link"
												disabled={ draftLocked }
												onClick={ () =>
													setNativeFormOpen( true )
												}
											>
												{ __(
													'Replace key',
													'ip-location-block'
												) }
											</Button>
										) }
										{ item.local && (
											<Button
												variant="link"
												href={ betaUrl( {
													tab: 'settings',
													view: 'advanced',
													section: 'database',
												} ) }
											>
												{ __(
													'Manage local databases',
													'ip-location-block'
												) }
											</Button>
										) }
										{ switchableProviders.length > 0 && (
											<Button
												variant="link"
												disabled={ draftLocked }
												onClick={ () =>
													setAlternativesOpen( true )
												}
											>
												{ __(
													'Switch provider',
													'ip-location-block'
												) }
											</Button>
										) }
										<Button
											variant="tertiary"
											isDestructive
											disabled={ draftLocked }
											onClick={ () =>
												providerAction?.requestDisconnect?.(
													item.name
												)
											}
										>
											{ __(
												'Disconnect',
												'ip-location-block'
											) }
										</Button>
									</div>
								</div>
							);
						} ) }
					</section>
				) }

				{ ! activeNative && (
					<section className="ilb-provider-promo">
						<div className="ilb-provider-promo__content">
							<span className="ilb-provider-promo__eyebrow">
								{ __( 'Native Mode', 'ip-location-block' ) }
							</span>
							<h3>
								{ __(
									'Block by state or region, not just country.',
									'ip-location-block'
								) }
							</h3>
							<p>
								{ __(
									'Use the administrative area returned for each country, such as a state, province, prefecture, or region.',
									'ip-location-block'
								) }
							</p>
							<RegionalBenefits />
							<div className="ilb-provider-promo__actions">
								<UpgradeButton content="provider-card" />
								<Button
									variant="secondary"
									disabled={ draftLocked }
									onClick={ () => setNativeFormOpen( true ) }
								>
									{ __(
										'I already have a key',
										'ip-location-block'
									) }
								</Button>
							</div>
						</div>

						<div
							className="ilb-provider-promo__example"
							role="group"
							aria-label={ __(
								'Example lookup',
								'ip-location-block'
							) }
						>
							<span className="ilb-provider-promo__example-label">
								{ __( 'Example lookup', 'ip-location-block' ) }
							</span>
							<div className="ilb-provider-promo__result is-standard">
								<span>
									{ __(
										'Country-level',
										'ip-location-block'
									) }
								</span>
								<strong>
									{ __(
										'United States',
										'ip-location-block'
									) }
								</strong>
							</div>
							<div
								className="ilb-provider-promo__connector"
								aria-hidden="true"
							>
								↓
							</div>
							<div className="ilb-provider-promo__result is-native">
								<span>
									{ __( 'Native Mode', 'ip-location-block' ) }
								</span>
								<strong>
									{ __(
										'United States',
										'ip-location-block'
									) }
								</strong>
								<em>
									{ __(
										'Kentucky · State/region',
										'ip-location-block'
									) }
								</em>
							</div>
						</div>

						{ nativeFormOpen && (
							<div className="ilb-provider-promo__form">
								<TextControl
									__nextHasNoMarginBottom
									type="password"
									label={ __(
										'IP Location Block API key',
										'ip-location-block'
									) }
									value={ getCredential( NATIVE ) }
									disabled={ draftLocked }
									onChange={ ( value ) =>
										setCredential( NATIVE, value )
									}
									autoComplete="off"
								/>
								<div className="ilb-provider-choice__actions">
									<Button
										variant="primary"
										isBusy={ testing === NATIVE }
										disabled={ !! testing || draftLocked }
										onClick={ () =>
											runTest( NATIVE, true )
										}
									>
										{ __(
											'Test and connect',
											'ip-location-block'
										) }
									</Button>
									<Button
										variant="tertiary"
										onClick={ () =>
											setNativeFormOpen( false )
										}
									>
										{ __( 'Cancel', 'ip-location-block' ) }
									</Button>
								</div>
							</div>
						) }
					</section>
				) }

				{ activeNative && nativeFormOpen && (
					<section className="ilb-provider-replace-key">
						<h3>
							{ __( 'Replace API key', 'ip-location-block' ) }
						</h3>
						<TextControl
							__nextHasNoMarginBottom
							type="password"
							label={ __( 'New API key', 'ip-location-block' ) }
							value={ getCredential( NATIVE ) }
							disabled={ draftLocked }
							onChange={ ( value ) =>
								setCredential( NATIVE, value )
							}
							autoComplete="off"
						/>
						<div className="ilb-provider-choice__actions">
							<Button
								variant="primary"
								isBusy={ testing === NATIVE }
								disabled={ !! testing || draftLocked }
								onClick={ () => runTest( NATIVE, true ) }
							>
								{ __(
									'Test and replace key',
									'ip-location-block'
								) }
							</Button>
							<Button
								variant="tertiary"
								onClick={ () => setNativeFormOpen( false ) }
							>
								{ __( 'Cancel', 'ip-location-block' ) }
							</Button>
						</div>
					</section>
				) }

				{ switchableProviders.length > 0 && (
					<details
						className="ilb-provider-alternatives"
						open={ alternativesOpen }
						onToggle={ ( event ) =>
							setAlternativesOpen( event.currentTarget.open )
						}
					>
						<summary>
							{ active.length
								? __( 'Switch provider', 'ip-location-block' )
								: __(
										'Prefer another provider?',
										'ip-location-block'
								  ) }
						</summary>
						<div className="ilb-provider-alternatives__body">
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Provider', 'ip-location-block' ) }
								value={ alternative }
								disabled={ draftLocked }
								options={ switchableProviders.map(
									( item ) => ( {
										label: item.name,
										value: item.name,
									} )
								) }
								onChange={ setAlternative }
							/>
							{ alternativeMeta?.auth !== 'none' && (
								<TextControl
									__nextHasNoMarginBottom
									type="password"
									label={ __(
										'API key',
										'ip-location-block'
									) }
									value={ getCredential( alternative ) }
									disabled={ draftLocked }
									onChange={ ( value ) =>
										setCredential( alternative, value )
									}
									autoComplete="off"
								/>
							) }
							<div className="ilb-provider-choice__actions">
								<Button
									variant="secondary"
									isBusy={
										alternativeMeta?.local
											? false
											: testing === alternative
									}
									disabled={
										!! testing ||
										! alternative ||
										draftLocked
									}
									onClick={ () =>
										alternativeMeta?.local
											? selectLocalProvider( alternative )
											: runTest( alternative, true )
									}
								>
									{ alternativeMeta?.local
										? __(
												'Use local provider',
												'ip-location-block'
										  )
										: alternative === NATIVE
										? __(
												'Test and connect',
												'ip-location-block'
										  )
										: __(
												'Test and use',
												'ip-location-block'
										  ) }
								</Button>
								{ alternativeMeta?.link && (
									<Button
										variant="link"
										href={ alternativeMeta.link }
										target="_blank"
										rel="noreferrer"
									>
										{ alternative === NATIVE
											? __(
													'Get API key',
													'ip-location-block'
											  )
											: __(
													'Register',
													'ip-location-block'
											  ) }
									</Button>
								) }
							</div>
							{ ! alternativeMeta?.local && (
								<p className="ilb-provider-alternatives__allowance">
									{ sprintf(
										/* translators: %s: advertised provider allowance. */
										__(
											'Published plan allowance: %s',
											'ip-location-block'
										),
										localizedAllowance(
											alternativeMeta?.requests
										)
									) }
								</p>
							) }
							{ alternativeMeta?.local && (
								<p
									className={ `ilb-provider-alternatives__allowance ${
										alternativeMeta.databaseReady
											? 'is-ready'
											: 'is-warning'
									}` }
								>
									{ alternativeMeta.databaseReady
										? __(
												'The local database is installed and ready.',
												'ip-location-block'
										  )
										: __(
												'The local database must be downloaded before blocking can start.',
												'ip-location-block'
										  ) }
								</p>
							) }
						</div>
					</details>
				) }
			</CardBody>

			{ switchRequest && (
				<Modal
					title={ sprintf(
						/* translators: %s: new provider name. */
						__( 'Switch to %s?', 'ip-location-block' ),
						switchRequest.provider
					) }
					onRequestClose={ () => setSwitchRequest( null ) }
					className="ilb-provider-switch-modal"
				>
					<p>
						{ switchRequest.immediate
							? sprintf(
									/* translators: %s: providers that will be disconnected. */
									__(
										'Connecting will immediately disconnect the following provider(s): %s.',
										'ip-location-block'
									),
									switchRequest.replaced.join( ', ' )
							  )
							: sprintf(
									/* translators: %s: providers that will be disconnected. */
									__(
										'This will stage the following provider(s) for disconnection: %s. Nothing changes until you save.',
										'ip-location-block'
									),
									switchRequest.replaced.join( ', ' )
							  ) }
					</p>
					<div className="ilb-provider-switch-modal__actions">
						<Button
							variant="tertiary"
							onClick={ () => setSwitchRequest( null ) }
						>
							{ __( 'Cancel', 'ip-location-block' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ () => {
								const apply = switchRequest.apply;
								setSwitchRequest( null );
								apply();
							} }
						>
							{ __( 'Continue', 'ip-location-block' ) }
						</Button>
					</div>
				</Modal>
			) }
		</Card>
	);
}
