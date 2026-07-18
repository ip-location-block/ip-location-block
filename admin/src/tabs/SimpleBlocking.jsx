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
	Button,
	FormTokenField,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

import { parseRules, serializeRules } from '../lib/rules';
import ProviderSetup from '../components/ProviderSetup';
import {
	ALL_CODES,
	SUGGESTIONS,
	countryLabel,
	codeFromToken,
} from '../data/countries';

const BACKEND_HOOKS = [ 'comment', 'xmlrpc', 'login', 'admin' ];

const COUNTRY_OPTIONS = ALL_CODES.map( ( cc ) => ( {
	label: countryLabel( cc ),
	value: cc,
} ) );

export default function SimpleBlocking( {
	settings,
	providers = [],
	providerStatus = null,
	onChange,
} ) {
	const pub = settings.public || {};

	// --- derive view state from the settings object -------------------------
	const enabled = Number( settings?.validation?.public ) % 2 === 1;

	const rawMode = Number( pub.matching_rule );
	const mode = rawMode === 0 || rawMode === 1 ? rawMode : 1; // default: block-list
	const listKey = mode === 0 ? 'white_list' : 'black_list';
	const rules = parseRules( pub[ listKey ] || '' );

	const alsoBackend = Number( settings.matching_rule ) !== -1;

	const code = Number( pub.response_code );
	const whenBlocked = code >= 300 && code < 400 ? 'redirect' : 'message';

	// `providers` may legitimately be an empty PHP array; never spread an array.
	const storedProviders =
		settings.providers && ! Array.isArray( settings.providers )
			? settings.providers
			: {};
	const [ providerReady, setProviderReady ] = useState(
		!! providerStatus?.ready
	);
	useEffect( () => {
		setProviderReady( !! providerStatus?.ready );
	}, [ providerStatus ] );

	const nativeSelected = !! storedProviders[ 'IP Location Block' ];
	const draftNative =
		nativeSelected &&
		Object.entries( storedProviders ).every(
			( [ name, value ] ) =>
				name === 'IP Location Block' || name === 'Cache' || ! value
		);
	const preciseAvailable = !! ( providerStatus?.native || draftNative );

	// --- writers ------------------------------------------------------------
	const writeRules = ( nextMode, next, mirror = alsoBackend ) => {
		const str = serializeRules( next );
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
		writeRules( m, rules ); // carry the current selection into the new list
		if ( alsoBackend ) {
			onChange( 'matching_rule', m );
		}
	};

	const setCountries = ( codes ) =>
		writeRules( mode, { ...rules, countries: codes } );
	const setPrecise = ( precise ) => writeRules( mode, { ...rules, precise } );

	const setAlsoBackend = ( on ) => {
		if ( on ) {
			onChange( 'matching_rule', mode );
			onChange(
				mode === 0 ? 'white_list' : 'black_list',
				serializeRules( rules )
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

	const updatePrecise = ( i, patch ) =>
		setPrecise(
			rules.precise.map( ( r, idx ) =>
				idx === i ? { ...r, ...patch } : r
			)
		);

	return (
		<>
			<ProviderSetup
				settings={ settings }
				providers={ providers }
				status={ providerStatus }
				onChange={ onChange }
				onReadyChange={ setProviderReady }
			/>
			<Card className="ilb-panel-shell ilb-settings-card ilb-settings-card--simple">
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
								disabled={ ! enabled && ! providerReady }
								onChange={ setEnabled }
							/>
							{ ! enabled && ! providerReady && (
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
												'Block these countries',
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
												'Allow only these countries',
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
												tokens
													.map( codeFromToken )
													.filter( Boolean )
											)
										}
									/>
									<p className="ilb-simple__help">
										{ __(
											'Start typing a country name or code.',
											'ip-location-block'
										) }
									</p>
								</div>

								{ /* Precise rules --------------------------------- */ }
								<div className="ilb-simple__row">
									<p className="ilb-simple__label">
										{ __(
											'Precise rules (state / city)',
											'ip-location-block'
										) }
									</p>

									{ ! preciseAvailable ? (
										<div className="ilb-simple__gate">
											<span
												className="dashicons dashicons-lock"
												aria-hidden="true"
											/>
											<div>
												<p className="ilb-simple__gate-copy">
													{ __(
														'State- and city-level blocking needs the “IP Location Block” geolocation provider, the only one that returns state/city data (a premium key is required for that data).',
														'ip-location-block'
													) }
												</p>
												<Button
													variant="secondary"
													onClick={
														focusProviderSetup
													}
													className="ilb-simple__gate-action"
												>
													{ __(
														'Set up Native provider',
														'ip-location-block'
													) }
												</Button>
											</div>
										</div>
									) : (
										<>
											{ rules.precise.map( ( row, i ) => (
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
														options={
															COUNTRY_OPTIONS
														}
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
														options={ [
															{
																label: __(
																	'State',
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
													<TextControl
														__nextHasNoMarginBottom
														label={ __(
															'Name',
															'ip-location-block'
														) }
														value={ row.value }
														placeholder={ __(
															'e.g. California',
															'ip-location-block'
														) }
														onChange={ ( v ) =>
															updatePrecise( i, {
																value: v,
															} )
														}
													/>
													<Button
														variant="tertiary"
														isDestructive
														onClick={ () =>
															setPrecise(
																rules.precise.filter(
																	(
																		_,
																		idx
																	) =>
																		idx !==
																		i
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
												onClick={ () =>
													setPrecise( [
														...rules.precise,
														{
															country: 'US',
															level: 'State',
															value: '',
														},
													] )
												}
											>
												{ __(
													'Add a precise rule',
													'ip-location-block'
												) }
											</Button>
										</>
									) }
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
