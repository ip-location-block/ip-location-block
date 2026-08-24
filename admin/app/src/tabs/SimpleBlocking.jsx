/**
 * Simple blocking view — a guided alternative to the full settings accordion.
 *
 * It is a controlled view over the *same* settings object the Advanced view
 * edits; each control writes the underlying option keys, and the shared Save
 * bar persists them. Nothing here is a new setting.
 *
 * Front-end blocking lives in `public.*` (validate_public() copies those over
 * the globals for the public hook only, and skips logged-in users), so leaving
 * the global `matching_rule` at -1 keeps blocking front-end-only. Turning on
 * "also protect wp-admin & login" mirrors the rule to the globals and enables
 * the back-end validation hooks.
 */
import {
	Card,
	CardHeader,
	CardBody,
	ToggleControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ComboboxControl,
	Button,
	FormTokenField,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';

import { parseRules, serializeRules } from '../lib/rules';
import { syncDraft } from '../lib/preciseDraft';
import ProviderSetup from '../components/ProviderSetup';
import {
	ALL_CODES,
	SUGGESTIONS,
	countryLabel,
	expandTokens,
} from '../data/countries';
import { hasRegionList, regionList } from '../data/regions';
import { quotaBlocksProvider } from '../providerLogic';
import {
	isRedirectResponse,
	redirectDestinationStatus,
	sameRedirectDestination,
} from '../lib/blockedResponse';

// Sentinel option value: switches a State row with a bundled region list into
// free-text entry.
const CUSTOM_REGION = '__ilb_custom_region__';

const BACKEND_HOOKS = [ 'comment', 'xmlrpc', 'login', 'admin' ];
const boot = window.ipLocationBlockAdmin || {};

const COUNTRY_OPTIONS = ALL_CODES.map( ( cc ) => ( {
	label: countryLabel( cc ),
	value: cc,
} ) );

export default function SimpleBlocking( {
	settings,
	providers = [],
	providerStatus = null,
	onChange,
	providerAction,
} ) {
	const pub = settings.public || {};

	// --- derive view state from the settings object -------------------------
	const enabled = Number( settings?.validation?.public ) % 2 === 1;

	const rawMode = Number( pub.matching_rule );
	const mode = rawMode === 0 || rawMode === 1 ? rawMode : 1; // default: block-list
	const listKey = mode === 0 ? 'white_list' : 'black_list';
	const storedString = pub[ listKey ] || '';
	const rules = parseRules( storedString );

	// The precise-rule editor keeps LOCAL draft state so that empty, in-progress
	// rows — which serializeRules intentionally drops — survive rendering.
	// `syncDraft` rebuilds it only when the stored string changes from OUTSIDE
	// the editor (reset, preset, save + reload); edits made here are recognised
	// via `lastWrittenRef` and never clobber the draft.
	const [ draftPrecise, setDraftPrecise ] = useState(
		() => parseRules( storedString ).precise
	);
	const lastWrittenRef = useRef( storedString );
	useEffect( () => {
		setDraftPrecise( ( prev ) =>
			syncDraft( prev, storedString, lastWrittenRef.current )
		);
	}, [ storedString ] );

	const alsoBackend = Number( settings.matching_rule ) !== -1;

	const code = Number( pub.response_code );
	const whenBlocked = isRedirectResponse( code ) ? 'redirect' : 'message';
	const redirectDestination = String( pub.redirect_uri || '' );
	const protectedSiteUrl = boot.homeUrl || window.location.origin;
	const defaultRedirectUrl = String( boot.defaultRedirectUrl || '' );
	const redirectStatus = redirectDestinationStatus(
		redirectDestination,
		protectedSiteUrl
	);
	const usesHostedDefault = sameRedirectDestination(
		redirectDestination,
		defaultRedirectUrl
	);

	// `providers` may legitimately be an empty PHP array; never spread an array.
	const storedProviders =
		settings.providers && ! Array.isArray( settings.providers )
			? settings.providers
			: {};
	const [ providerState, setProviderState ] = useState( () => ( {
		ready: !! providerStatus?.ready,
		hasSelection: !! (
			providerStatus?.active?.length ||
			providerStatus?.providers?.some( ( item ) => item.active )
		),
		transitioning: false,
		activeNames: providerStatus?.active || [],
	} ) );
	useEffect( () => {
		setProviderState( ( current ) => ( {
			...current,
			ready: !! providerStatus?.ready,
			hasSelection: !! (
				providerStatus?.active?.length ||
				providerStatus?.providers?.some( ( item ) => item.active )
			),
			activeNames: providerStatus?.active || [],
		} ) );
	}, [ providerStatus ] );

	const nativeSelected = !! storedProviders[ 'IP Location Block' ];
	// Local (unsaved) native-only heuristic: native is selected and every other
	// real provider is unselected, so precision will be available once saved.
	const draftNative =
		nativeSelected &&
		Object.entries( storedProviders ).every(
			( [ name, value ] ) =>
				name === 'IP Location Block' || name === 'Cache' || ! value
		);
	// Precision is available when native is the sole (saved) source, when
	// native-first enforcement is active (a real key + precision rules, other
	// providers acting as country-level fallback), or in the native-only draft.
	const enforcedNative = !! providerStatus?.enforcedNative && nativeSelected;
	const quotaIssue = quotaBlocksProvider( providerStatus?.quota );
	const preciseAvailable = !! (
		nativeSelected &&
		! quotaIssue &&
		( providerStatus?.native || enforcedNative || draftNative )
	);

	// --- writers ------------------------------------------------------------
	const writeRules = ( nextMode, next, mirror = alsoBackend ) => {
		const str = serializeRules( next );
		// Record what the editor wrote so the draft reconcile treats the ensuing
		// re-render as self-originated (see syncDraft / lastWrittenRef).
		lastWrittenRef.current = str;
		onChange(
			`public.${ nextMode === 0 ? 'white_list' : 'black_list' }`,
			str
		);
		if ( mirror ) {
			onChange( nextMode === 0 ? 'white_list' : 'black_list', str );
		}
	};

	const setEnabled = ( on ) => {
		onChange( 'validation.public', on ? 1 : 0 );
		if ( on ) {
			// Must not stay at -1 or the front end just "follows" the global rule.
			if ( rawMode !== 0 && rawMode !== 1 ) {
				onChange( 'public.matching_rule', mode );
			}
			onChange( 'public.target_rule', 0 ); // all front-end requests
		}
	};

	const setMode = ( next ) => {
		const m = Number( next );
		onChange( 'public.matching_rule', m );
		// carry the current selection into the new list
		writeRules( m, { countries: rules.countries, precise: draftPrecise } );
		if ( alsoBackend ) {
			onChange( 'matching_rule', m );
		}
	};

	const setCountries = ( codes ) =>
		writeRules( mode, { countries: codes, precise: draftPrecise } );

	// Update the local draft AND serialize its non-empty subset into settings.
	const commitPrecise = ( nextDraft ) => {
		setDraftPrecise( nextDraft );
		writeRules( mode, {
			countries: rules.countries,
			precise: nextDraft,
		} );
	};

	const setAlsoBackend = ( on ) => {
		if ( on ) {
			onChange( 'matching_rule', mode );
			onChange(
				mode === 0 ? 'white_list' : 'black_list',
				serializeRules( {
					countries: rules.countries,
					precise: draftPrecise,
				} )
			);
			BACKEND_HOOKS.forEach( ( h ) =>
				onChange( `validation.${ h }`, 1 )
			);
		} else {
			onChange( 'matching_rule', -1 ); // front-end only
		}
	};

	const focusProviderSetup = () => {
		const heading = document.getElementById( 'ilb-provider-setup-title' );
		document
			.getElementById( 'ilb-provider-setup' )
			?.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		heading?.focus( { preventScroll: true } );
	};

	useEffect( () => {
		if ( window.location.hash !== '#ilb-provider-setup' ) {
			return undefined;
		}
		const frame = window.requestAnimationFrame( () => {
			const heading = document.getElementById(
				'ilb-provider-setup-title'
			);
			document
				.getElementById( 'ilb-provider-setup' )
				?.scrollIntoView( { block: 'start' } );
			heading?.focus( { preventScroll: true } );
		} );
		return () => window.cancelAnimationFrame( frame );
	}, [] );

	const updatePrecise = ( i, patch ) =>
		commitPrecise(
			draftPrecise.map( ( r, idx ) =>
				idx === i ? { ...r, ...patch } : r
			)
		);

	// The Name cell: a searchable region dropdown for State rows whose country
	// ships a bundled list (with a "Custom value…" escape hatch), otherwise a
	// free-text field. Kept to a SINGLE grid cell (see the 4-column grid).
	const nameField = ( row, i, disabled = false ) => {
		const hasList = row.level === 'State' && hasRegionList( row.country );
		const inList =
			hasList && regionList( row.country ).includes( row.value );
		const useCombo = hasList && ! row.custom && ( ! row.value || inList );

		if ( useCombo ) {
			return (
				<ComboboxControl
					__nextHasNoMarginBottom
					label={ __( 'Name', 'ip-location-block' ) }
					value={ row.value || null }
					disabled={ disabled }
					options={ [
						...regionList( row.country ).map( ( name ) => ( {
							label: name,
							value: name,
						} ) ),
						{
							label: __( 'Custom value…', 'ip-location-block' ),
							value: CUSTOM_REGION,
						},
					] }
					onChange={ ( v ) =>
						v === CUSTOM_REGION
							? updatePrecise( i, { custom: true, value: '' } )
							: updatePrecise( i, { value: v || '' } )
					}
				/>
			);
		}

		return (
			<>
				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Name', 'ip-location-block' ) }
					value={ row.value }
					disabled={ disabled }
					placeholder={
						row.level === 'City'
							? __( 'e.g. Seattle', 'ip-location-block' )
							: __( 'e.g. California', 'ip-location-block' )
					}
					onChange={ ( v ) => updatePrecise( i, { value: v } ) }
				/>
				{ hasList && (
					<Button
						variant="link"
						className="ilb-simple__name-switch"
						disabled={ disabled }
						onClick={ () =>
							updatePrecise( i, { custom: false, value: '' } )
						}
					>
						{ __(
							'Choose from the region list',
							'ip-location-block'
						) }
					</Button>
				) }
			</>
		);
	};

	return (
		<>
			<ProviderSetup
				settings={ settings }
				providers={ providers }
				status={ providerStatus }
				onChange={ onChange }
				onStateChange={ setProviderState }
				providerAction={ providerAction }
			/>
			<Card
				id="ilb-location-blocking"
				className="ilb-panel-shell ilb-settings-card ilb-settings-card--simple"
			>
				<CardHeader className="ilb-panel-shell__header">
					<h2 className="ilb-panel-shell__title">
						{ __( 'Location blocking', 'ip-location-block' ) }
					</h2>
				</CardHeader>
				<CardBody>
					<div className="ilb-simple">
						{ /* Enable ------------------------------------------------ */ }
						<div className="ilb-simple__row">
							<ToggleControl
								__nextHasNoMarginBottom
								label={ __(
									'Enable location blocking',
									'ip-location-block'
								) }
								help={ __(
									'Blocks visitors on your public site by their location. Logged-in users are never blocked.',
									'ip-location-block'
								) }
								checked={ enabled }
								disabled={
									! enabled && ! providerState.hasSelection
								}
								onChange={ setEnabled }
							/>
							{ ! enabled && ! providerState.hasSelection && (
								<div className="ilb-simple__provider-required">
									<p>
										{ __(
											'Verify a geolocation provider before turning on blocking.',
											'ip-location-block'
										) }
									</p>
									<Button
										variant="link"
										onClick={ focusProviderSetup }
									>
										{ __(
											'Set up provider',
											'ip-location-block'
										) }
									</Button>
								</div>
							) }
							{ providerState.hasSelection &&
								! providerState.ready &&
								! providerState.transitioning && (
									<div
										className="ilb-simple__provider-warning"
										role="status"
									>
										<span
											className="dashicons dashicons-warning"
											aria-hidden="true"
										/>
										<div>
											<strong>
												{ __(
													'The selected provider is not ready.',
													'ip-location-block'
												) }
											</strong>
											<p>
												{ __(
													'Cached country results can still be used. Visitors without a cached result are allowed until the provider is ready.',
													'ip-location-block'
												) }
											</p>
											<Button
												variant="link"
												onClick={ focusProviderSetup }
											>
												{ __(
													'Review provider',
													'ip-location-block'
												) }
											</Button>
										</div>
									</div>
								) }
						</div>

						{ enabled && (
							<>
								{ /* Action ---------------------------------------- */ }
								<div className="ilb-simple__row">
									<p className="ilb-simple__label">
										{ __( 'Action', 'ip-location-block' ) }
									</p>
									<div
										className="ilb-view-switch ilb-simple__mode-switch"
										role="group"
										aria-label={ __(
											'Action',
											'ip-location-block'
										) }
									>
										<button
											type="button"
											className="ilb-view-switch__option"
											aria-pressed={ mode === 1 }
											onClick={ () => setMode( 1 ) }
										>
											{ __(
												'Block these locations',
												'ip-location-block'
											) }
										</button>
										<button
											type="button"
											className="ilb-view-switch__option"
											aria-pressed={ mode === 0 }
											onClick={ () => setMode( 0 ) }
										>
											{ __(
												'Allow only these locations',
												'ip-location-block'
											) }
										</button>
									</div>
									<p className="ilb-simple__help">
										{ mode === 1
											? __(
													'Everyone except the listed locations is allowed.',
													'ip-location-block'
											  )
											: __(
													'Everyone except the listed locations is blocked.',
													'ip-location-block'
											  ) }
									</p>
								</div>

								{ /* Countries ------------------------------------- */ }
								<div className="ilb-simple__row">
									<FormTokenField
										__nextHasNoMarginBottom
										__experimentalExpandOnFocus
										label={ __(
											'Countries',
											'ip-location-block'
										) }
										value={ rules.countries.map(
											countryLabel
										) }
										suggestions={ SUGGESTIONS }
										onChange={ ( tokens ) =>
											setCountries(
												expandTokens( tokens )
											)
										}
									/>
									<p className="ilb-simple__help">
										{ __(
											'Start typing a country name or code. “EU (European Union)” adds all 27 member states.',
											'ip-location-block'
										) }
									</p>
								</div>

								{ /* Precise rules --------------------------------- */ }
								<div className="ilb-simple__row">
									<p className="ilb-simple__label">
										{ __(
											'Regional rules',
											'ip-location-block'
										) }
									</p>

									{ ! preciseAvailable && (
										<div
											id="ilb-regional-rules-status"
											className="ilb-simple__gate"
											role="status"
										>
											<span
												className="dashicons dashicons-warning"
												aria-hidden="true"
											/>
											<div>
												<strong>
													{ __(
														'Regional rules are paused.',
														'ip-location-block'
													) }
												</strong>
												<p className="ilb-simple__gate-copy">
													{ quotaIssue
														? __(
																'Country rules remain active. Your regional rules stay saved but cannot run until IP Location Block is ready.',
																'ip-location-block'
														  )
														: providerState.ready
														? __(
																'Country rules remain active. Your regional rules stay saved, but the current provider supports country-level blocking only.',
																'ip-location-block'
														  )
														: __(
																'Cached country results can still be used. Your regional rules stay saved until a state/region provider is ready.',
																'ip-location-block'
														  ) }
												</p>
												{ quotaIssue &&
												providerStatus?.quota
													?.upgradeUrl ? (
													<Button
														variant="secondary"
														href={
															providerStatus.quota
																.upgradeUrl
														}
														target="_blank"
														rel="noreferrer"
														className="ilb-simple__gate-action"
													>
														{ providerStatus.quota
															.status ===
														'key_upgrade_required'
															? __(
																	'Upgrade API key',
																	'ip-location-block'
															  )
															: __(
																	'View plans',
																	'ip-location-block'
															  ) }
													</Button>
												) : (
													<Button
														variant="secondary"
														onClick={
															focusProviderSetup
														}
														className="ilb-simple__gate-action"
													>
														{ __(
															'Unlock regional blocking',
															'ip-location-block'
														) }
													</Button>
												) }
											</div>
										</div>
									) }
									{ enforcedNative && preciseAvailable && (
										<p className="ilb-simple__precise-info">
											<span
												className="dashicons dashicons-info-outline"
												aria-hidden="true"
											/>
											{ __(
												'The IP Location Block provider is prioritized automatically for these rules; your other providers act as country-level fallback.',
												'ip-location-block'
											) }
										</p>
									) }
									<div
										className={ `ilb-simple__precise-editor${
											preciseAvailable ? '' : ' is-paused'
										}` }
										aria-disabled={ ! preciseAvailable }
										aria-describedby={
											preciseAvailable
												? undefined
												: 'ilb-regional-rules-status'
										}
									>
										{ draftPrecise.map( ( row, i ) => (
											<div
												className="ilb-simple__precise-row"
												key={ i }
											>
												<SelectControl
													__nextHasNoMarginBottom
													label={ __(
														'Country',
														'ip-location-block'
													) }
													value={ row.country }
													disabled={
														! preciseAvailable
													}
													options={ COUNTRY_OPTIONS }
													onChange={ ( v ) =>
														updatePrecise( i, {
															country: v,
														} )
													}
												/>
												<SelectControl
													__nextHasNoMarginBottom
													label={ __(
														'Level',
														'ip-location-block'
													) }
													value={ row.level }
													disabled={
														! preciseAvailable
													}
													options={ [
														{
															label: __(
																'State / Region',
																'ip-location-block'
															),
															value: 'State',
														},
														{
															label: __(
																'City',
																'ip-location-block'
															),
															value: 'City',
														},
													] }
													onChange={ ( v ) =>
														updatePrecise( i, {
															level: v,
														} )
													}
												/>
												<div className="ilb-simple__name-cell">
													{ nameField(
														row,
														i,
														! preciseAvailable
													) }
												</div>
												<Button
													variant="tertiary"
													isDestructive
													disabled={
														! preciseAvailable
													}
													onClick={ () =>
														commitPrecise(
															draftPrecise.filter(
																( _, idx ) =>
																	idx !== i
															)
														)
													}
												>
													{ __(
														'Remove',
														'ip-location-block'
													) }
												</Button>
											</div>
										) ) }
										<Button
											variant="secondary"
											disabled={ ! preciseAvailable }
											onClick={ () =>
												commitPrecise( [
													...draftPrecise,
													{
														country: 'US',
														level: 'State',
														value: '',
													},
												] )
											}
										>
											{ __(
												'Add a regional rule',
												'ip-location-block'
											) }
										</Button>
										<p className="ilb-simple__help">
											{ __(
												'Names must exactly match the administrative area returned by the provider. Verify the exact spelling on the Search tab.',
												'ip-location-block'
											) }
										</p>
									</div>
								</div>

								{ /* Scope ----------------------------------------- */ }
								<div className="ilb-simple__row">
									<ToggleControl
										__nextHasNoMarginBottom
										label={ __(
											'Also protect wp-admin & login',
											'ip-location-block'
										) }
										help={ __(
											'Off: only your public site is filtered. On: the same rule also guards wp-admin, login, XML-RPC and comments.',
											'ip-location-block'
										) }
										checked={ alsoBackend }
										onChange={ setAlsoBackend }
									/>
								</div>

								{ /* Response -------------------------------------- */ }
								<div className="ilb-simple__row">
									<SelectControl
										__nextHasNoMarginBottom
										label={ __(
											'When a visitor is blocked',
											'ip-location-block'
										) }
										value={ whenBlocked }
										options={ [
											{
												label: __(
													'Redirect them away',
													'ip-location-block'
												),
												value: 'redirect',
											},
											{
												label: __(
													'Show a “blocked” message',
													'ip-location-block'
												),
												value: 'message',
											},
										] }
										onChange={ ( v ) =>
											onChange(
												'public.response_code',
												v === 'redirect' ? 307 : 403
											)
										}
									/>

									<div className="ilb-simple__blocked-response">
										{ whenBlocked === 'redirect' ? (
											<>
												<TextControl
													__next40pxDefaultSize
													__nextHasNoMarginBottom
													type="url"
													label={ __(
														'Send blocked visitors to',
														'ip-location-block'
													) }
													value={
														redirectDestination
													}
													onChange={ ( value ) =>
														onChange(
															'public.redirect_uri',
															value
														)
													}
													aria-invalid={
														! redirectStatus.valid
													}
													aria-describedby={
														redirectStatus.valid
															? 'ilb-simple-redirect-help'
															: 'ilb-simple-redirect-error ilb-simple-redirect-help'
													}
												/>

												{ usesHostedDefault && (
													<span className="ilb-simple__hosted-label">
														{ __(
															'IP Location Block hosted page',
															'ip-location-block'
														) }
													</span>
												) }

												{ ! redirectStatus.valid && (
													<p
														id="ilb-simple-redirect-error"
														className="ilb-simple__field-error"
														role="alert"
													>
														{ redirectStatus.reason ===
														'same_host'
															? __(
																	'This URL uses the same hostname as your protected site. Use a separate domain or subdomain.',
																	'ip-location-block'
															  )
															: __(
																	'Enter a full http or https URL on a separate domain or subdomain.',
																	'ip-location-block'
															  ) }
													</p>
												) }

												<p
													id="ilb-simple-redirect-help"
													className="ilb-simple__help"
												>
													{ __(
														'Use a separate domain or subdomain that is not protected by this plugin. Links back to protected pages on this site will still be blocked.',
														'ip-location-block'
													) }
												</p>

												<div className="ilb-simple__response-actions">
													{ redirectStatus.valid && (
														<Button
															variant="link"
															href={
																redirectStatus
																	.url.href
															}
															target="_blank"
															rel="noopener noreferrer"
														>
															{ __(
																'Preview destination',
																'ip-location-block'
															) }
															<span
																className="dashicons dashicons-external"
																aria-hidden="true"
															/>
														</Button>
													) }
													{ ! usesHostedDefault &&
														defaultRedirectUrl && (
															<Button
																variant="link"
																onClick={ () =>
																	onChange(
																		'public.redirect_uri',
																		defaultRedirectUrl
																	)
																}
															>
																{ __(
																	'Use hosted default',
																	'ip-location-block'
																) }
															</Button>
														) }
												</div>
											</>
										) : (
											<TextareaControl
												__nextHasNoMarginBottom
												label={ __(
													'Blocked message',
													'ip-location-block'
												) }
												value={ pub.response_msg || '' }
												help={ __(
													'Shown with a 403 Forbidden response. Leave blank to use the standard Forbidden message.',
													'ip-location-block'
												) }
												onChange={ ( value ) =>
													onChange(
														'public.response_msg',
														value
													)
												}
											/>
										) }
									</div>
								</div>

								{ /* Cache ----------------------------------------- */ }
								<div className="ilb-simple__row">
									<ToggleControl
										__nextHasNoMarginBottom
										label={ __(
											'Bypass full-page cache',
											'ip-location-block'
										) }
										help={ __(
											'Turn on if a caching plugin serves your pages, otherwise every visitor may get one cached response and blocking will look inconsistent.',
											'ip-location-block'
										) }
										checked={ !! pub.cache_bypass }
										onChange={ ( v ) =>
											onChange( 'public.cache_bypass', v )
										}
									/>
								</div>
							</>
						) }
					</div>
				</CardBody>
			</Card>
		</>
	);
}
