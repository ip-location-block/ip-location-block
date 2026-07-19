/* eslint-disable no-alert, no-nested-ternary */
import { useMemo, useState, useEffect } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
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

const NATIVE = 'IP Location Block';

const statusCopy = ( ready, active, quota ) => {
	if ( quotaBlocksProvider( quota ) ) {
		return {
			className: 'is-error',
			label: __( 'Action required', 'ip-location-block' ),
		};
	}
	if ( ready ) {
		return {
			className: 'is-ready',
			label: __( 'Ready', 'ip-location-block' ),
		};
	}
	if ( active.length ) {
		return {
			className: 'is-warning',
			label: __( 'Needs testing', 'ip-location-block' ),
		};
	}
	return {
		className: 'is-warning',
		label: __( 'Action required', 'ip-location-block' ),
	};
};

const localizedAllowance = ( allowance ) =>
	formatAllowance( allowance, {
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
			<div className="ilb-provider-setup__quota-actions">
				<Button
					variant="link"
					href={ quota.accountUrl }
					target="_blank"
					rel="noreferrer"
				>
					{ __( 'My account', 'ip-location-block' ) }
				</Button>
				{ quota.status !== 'ok' && quota.status !== 'unlimited' && (
					<Button
						variant="link"
						href={ quota.upgradeUrl }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'View plans', 'ip-location-block' ) }
					</Button>
				) }
			</div>
		</details>
	);
}

export default function ProviderSetup( {
	settings,
	providers = [],
	status = null,
	onChange,
	onReadyChange,
} ) {
	const providerMap =
		settings.providers && ! Array.isArray( settings.providers )
			? settings.providers
			: {};
	const activeFromStatus = activeProviderStatuses( status );
	const [ activated, setActivated ] = useState( null );
	const [ credentials, setCredentials ] = useState( () => ( {
		[ NATIVE ]:
			providerMap[ NATIVE ] && providerMap[ NATIVE ] !== '@'
				? providerMap[ NATIVE ]
				: '',
	} ) );
	const alternatives = providers.filter(
		( provider ) => provider.name !== NATIVE
	);
	const [ alternative, setAlternative ] = useState(
		alternatives[ 0 ]?.name || ''
	);
	const [ testing, setTesting ] = useState( '' );
	const [ notice, setNotice ] = useState( null );

	const active = useMemo( () => {
		if ( activated ) {
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
		return activeFromStatus;
	}, [ activated, activeFromStatus, providers ] );

	const quota =
		activated?.provider === NATIVE
			? activated.quota
			: active.some( ( item ) => item.name === NATIVE )
			? status?.quota
			: null;
	const ready = activated ? activated.ready !== false : !! status?.ready;
	const badge = statusCopy( ready, active, quota );

	useEffect( () => {
		onReadyChange( ready );
	}, [ onReadyChange, ready ] );

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

	const activateAfterTest = ( provider, credential, response ) => {
		const names = providerNames( providers, status );
		onChange(
			'providers',
			makeExclusiveProviderMap( providerMap, names, provider, credential )
		);
		const meta = providers.find( ( item ) => item.name === provider );
		if ( ! meta?.local && settings.restrict_api ) {
			onChange( 'restrict_api', false );
		}
		setActivated( { ...response, provider, ready: true } );
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
		const replaced = active
			.map( ( item ) => item.name )
			.filter( ( name ) => name !== provider );
		if (
			replaced.length &&
			! window.confirm(
				sprintf(
					/* translators: 1: new provider, 2: providers being disabled. */
					__(
						'Switch to %1$s and disable: %2$s?',
						'ip-location-block'
					),
					provider,
					replaced.join( ', ' )
				)
			)
		) {
			return;
		}
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
	};

	const runTest = ( provider ) => {
		const meta = providers.find( ( item ) => item.name === provider );
		const credential = getCredential( provider ).trim();
		if ( provider === NATIVE || meta?.auth === 'required' ) {
			if ( ! credential ) {
				setNotice( {
					status: 'error',
					message: __(
						'Enter an API key before testing.',
						'ip-location-block'
					),
				} );
				return;
			}
		}

		const replaced = active
			.map( ( item ) => item.name )
			.filter( ( name ) => name !== provider );
		if (
			replaced.length &&
			! window.confirm(
				sprintf(
					/* translators: 1: new provider, 2: providers being disabled. */
					__(
						'Switch to %1$s and disable: %2$s?',
						'ip-location-block'
					),
					provider,
					replaced.join( ', ' )
				)
			)
		) {
			return;
		}
		if (
			! meta?.local &&
			settings.restrict_api &&
			! window.confirm(
				__(
					'This provider sends visitor IP addresses to an external API. Disable “Do not send IP address to external APIs” and continue?',
					'ip-location-block'
				)
			)
		) {
			return;
		}

		setTesting( provider );
		setNotice( null );
		testProvider( provider, credential )
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
	};

	const activeNative = active.some( ( item ) => item.name === NATIVE );
	const activeLocal = active.filter( ( item ) => item.local );
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
							'Choose and verify the service that identifies each visitor’s location.',
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

				<div className="ilb-provider-setup__current">
					<span className="ilb-provider-setup__eyebrow">
						{ __( 'Current provider', 'ip-location-block' ) }
					</span>
					{ active.length ? (
						<div className="ilb-provider-setup__active-list">
							{ active.map( ( item ) => {
								const itemQuota =
									item.name === NATIVE ? quota : null;
								return (
									<div
										className="ilb-provider-setup__active"
										key={ item.name }
									>
										<strong>{ item.name }</strong>
										{ ! item.local && (
											<span>
												{ item.name === NATIVE
													? localizedQuotaSummary(
															itemQuota
													  ) ||
													  __(
															'Quota unavailable',
															'ip-location-block'
													  )
													: sprintf(
															/* translators: %s: provider request allowance. */
															__(
																'Plan allowance: %s',
																'ip-location-block'
															),
															localizedAllowance(
																item.allowance
															)
													  ) }
											</span>
										) }
									</div>
								);
							} ) }
						</div>
					) : (
						<p>
							{ __(
								'No usable provider is active yet.',
								'ip-location-block'
							) }
						</p>
					) }
					{ activeNative && <QuotaDetails quota={ quota } /> }
					{ activeLocal.length > 0 && (
						<Button
							variant="link"
							href={ betaUrl( {
								tab: 'settings',
								view: 'advanced',
								section: 'database',
							} ) }
						>
							{ __(
								'Manage local databases in Advanced settings',
								'ip-location-block'
							) }
						</Button>
					) }
				</div>

				<section className="ilb-provider-choice ilb-provider-choice--recommended">
					<div className="ilb-provider-choice__heading">
						<div>
							<span className="ilb-provider-choice__recommended">
								{ __( 'Recommended', 'ip-location-block' ) }
							</span>
							<h3>{ NATIVE }</h3>
						</div>
						{ activeNative && (
							<span className="ilb-provider-choice__selected">
								{ __( 'Selected', 'ip-location-block' ) }
							</span>
						) }
					</div>
					<p>
						{ __(
							'Country, IPv6 and ASN lookups with the upgrade path for state and city precision.',
							'ip-location-block'
						) }
					</p>
					<div className="ilb-provider-choice__form">
						<TextControl
							__nextHasNoMarginBottom
							type="password"
							label={ __( 'API key', 'ip-location-block' ) }
							value={ getCredential( NATIVE ) }
							onChange={ ( value ) =>
								setCredential( NATIVE, value )
							}
							autoComplete="off"
						/>
						<div className="ilb-provider-choice__actions">
							<Button
								variant="primary"
								isBusy={ testing === NATIVE }
								disabled={ !! testing }
								onClick={ () => runTest( NATIVE ) }
							>
								{ activeNative
									? __(
											'Test connection',
											'ip-location-block'
									  )
									: __(
											'Test and use',
											'ip-location-block'
									  ) }
							</Button>
							<Button
								variant="link"
								href={
									providers.find(
										( item ) => item.name === NATIVE
									)?.link
								}
								target="_blank"
								rel="noreferrer"
							>
								{ __( 'Get an API key', 'ip-location-block' ) }
							</Button>
						</div>
					</div>
				</section>

				{ alternatives.length > 0 && (
					<details className="ilb-provider-alternatives">
						<summary>
							{ __(
								'Use another provider',
								'ip-location-block'
							) }
						</summary>
						<div className="ilb-provider-alternatives__body">
							<SelectControl
								__nextHasNoMarginBottom
								label={ __( 'Provider', 'ip-location-block' ) }
								value={ alternative }
								options={ alternatives.map( ( item ) => ( {
									label: item.name,
									value: item.name,
								} ) ) }
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
									disabled={ !! testing || ! alternative }
									onClick={ () =>
										alternativeMeta?.local
											? selectLocalProvider( alternative )
											: runTest( alternative )
									}
								>
									{ alternativeMeta?.local
										? __(
												'Use local provider',
												'ip-location-block'
										  )
										: __(
												'Test and use',
												'ip-location-block'
										  ) }
								</Button>
								{ providers.find(
									( item ) => item.name === alternative
								)?.link && (
									<Button
										variant="link"
										href={
											providers.find(
												( item ) =>
													item.name === alternative
											)?.link
										}
										target="_blank"
										rel="noreferrer"
									>
										{ __(
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
											providers.find(
												( item ) =>
													item.name === alternative
											)?.requests
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
		</Card>
	);
}
