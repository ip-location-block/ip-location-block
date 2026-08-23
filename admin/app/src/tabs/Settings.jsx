/**
 * Settings tab. Two views over the same settings object:
 *  - Simple:   a guided blocking card (SimpleBlocking) for the common case.
 *  - Advanced: the full 7-section form driven by settingsSchema.
 * Both edit the same state and share the Save bar, so switching views never
 * loses edits. Fresh installs land on Simple; the choice is remembered locally.
 */
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import {
	getSettings,
	saveSettings,
	getContent,
	getExceptions,
	getProviders,
	getProviderStatus,
	getDatabaseStatus,
	getMode,
	getSettingsContext,
} from '../api';
import { SECTIONS } from './settingsSchema';
import { saveWarnings } from '../settingsLogic';
import { setPath } from './paths';
import SettingsField from './SettingsField';
import SimpleBlocking from './SimpleBlocking';
import ScanCountry from '../components/ScanCountry';
import SaveToastRegion from '../components/SaveToastRegion';
import ProviderDisconnectDialog from '../components/ProviderDisconnectDialog';
import { queryParam, replaceViewInUrl } from '../navigation';
import {
	providerDisconnectImpact,
	restoreProviderDisconnect,
	stageProviderDisconnect,
} from '../providerLogic';
import {
	isRedirectResponse,
	redirectDestinationStatus,
} from '../lib/blockedResponse';

const STORAGE_KEY = 'ilbSettingsMode';
const LEGACY_STORAGE_KEY = 'ilbBetaSettingsMode';
const boot = window.ipLocationBlockAdmin || {};

export const advancedGuideUrl = ( docsPath, sectionKey ) => {
	const url = new URL(
		docsPath,
		boot.docsUrl || 'https://iplocationblock.com/docs/'
	);
	url.searchParams.set( 'utm_source', 'plugin' );
	url.searchParams.set( 'utm_medium', 'admin' );
	url.searchParams.set( 'utm_campaign', 'advanced_panel' );
	url.searchParams.set( 'utm_content', sectionKey );

	return url.toString();
};

const readStoredMode = () => {
	try {
		let v = window.localStorage.getItem( STORAGE_KEY );
		if ( v === null ) {
			// One-time fallback: migrate the pre-1.4.0 "Beta" key.
			const legacy = window.localStorage.getItem( LEGACY_STORAGE_KEY );
			if ( legacy !== null ) {
				try {
					window.localStorage.setItem( STORAGE_KEY, legacy );
					window.localStorage.removeItem( LEGACY_STORAGE_KEY );
				} catch {
					// storage write may fail — the fallback value still applies
				}
				v = legacy;
			}
		}
		return v === 'simple' || v === 'advanced' ? v : null;
	} catch {
		return null;
	}
};

const storeMode = ( mode ) => {
	try {
		window.localStorage.setItem( STORAGE_KEY, mode );
	} catch {
		// storage unavailable — the choice just won't persist
	}
};

// An install with no country rule and no front-end blocking has never been
// configured; start those on the guided view.
const looksUnconfigured = ( s ) =>
	Number( s.matching_rule ) === -1 &&
	Number( s?.validation?.public ) % 2 !== 1;

function SettingsGroup( {
	group,
	settings,
	sources,
	onChange,
	onReplace,
	onRefreshSources,
	providerAction,
} ) {
	const hasHeader = group.title || group.action;

	return (
		<section
			className={ `ilb-settings-group${
				hasHeader ? '' : ' ilb-settings-group--plain'
			}` }
			aria-labelledby={
				group.title ? `ilb-settings-group-${ group.key }` : undefined
			}
		>
			{ hasHeader && (
				<div className="ilb-settings-group__header">
					{ group.title && (
						<h3 id={ `ilb-settings-group-${ group.key }` }>
							{ group.title }
						</h3>
					) }
					{ group.action === 'scan-country' && (
						<div className="ilb-scan-actions">
							<ScanCountry />
							{ sources.context?.features
								?.serverScanAvailable && (
								<ScanCountry source="server" />
							) }
						</div>
					) }
				</div>
			) }

			<div className="ilb-settings-group__fields">
				{ group.fields.map( ( field ) => (
					<SettingsField
						key={ field.path }
						field={ field }
						settings={ settings }
						sources={ sources }
						onChange={ onChange }
						onReplace={ onReplace }
						onRefreshSources={ onRefreshSources }
						providerAction={ providerAction }
					/>
				) ) }
			</div>
		</section>
	);
}

export default function Settings() {
	// Deep-link parameters select the initial render only. Internal view changes
	// update the URL, but must never refetch and overwrite the shared draft.
	const requestedView = useRef( queryParam( 'view' ) ).current;
	const requestedSection = useRef( queryParam( 'section' ) ).current;
	const [ settings, setSettings ] = useState( null );
	const [ sources, setSources ] = useState( {
		content: {},
		exceptions: {},
		providers: [],
		providerStatus: null,
		dbStatus: [],
		context: null,
	} );
	const [ mode, setMode ] = useState( null ); // resolved once settings load
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ saveNotices, setSaveNotices ] = useState( [] );
	const [ readyOverrides, setReadyOverrides ] = useState( {} );
	const [ disconnectRequest, setDisconnectRequest ] = useState( null );
	const [ pendingProviderAction, setPendingProviderAction ] =
		useState( null );
	const noticeSequence = useRef( 0 );
	const dismissSaveNotice = useCallback( ( id ) => {
		setSaveNotices( ( current ) =>
			current.filter( ( notice ) => notice.id !== id )
		);
	}, [] );

	useEffect( () => {
		Promise.all( [
			getSettings(),
			getContent(),
			getExceptions(),
			getProviders(),
			getProviderStatus().catch( () => null ),
			getDatabaseStatus(),
			getMode().catch( () => null ),
			getSettingsContext(),
		] )
			.then(
				( [
					s,
					content,
					exceptions,
					providers,
					providerStatus,
					dbStatus,
					geoMode,
					context,
				] ) => {
					setSettings( s );
					setSources( {
						content,
						exceptions,
						providers,
						providerStatus,
						dbStatus,
						mode: geoMode,
						context,
					} );
					setMode(
						( requestedView === 'advanced' ||
						requestedView === 'simple'
							? requestedView
							: null ) ||
							readStoredMode() ||
							( looksUnconfigured( s ) ? 'simple' : 'advanced' )
					);
				}
			)
			.catch( () => setSettings( null ) )
			.finally( () => setLoading( false ) );
	}, [ requestedView ] );

	useEffect( () => {
		const openProviderSetup = () => {
			setMode( 'simple' );
			storeMode( 'simple' );
			replaceViewInUrl( 'simple', 'ilb-provider-setup' );
			window.requestAnimationFrame( () =>
				window.requestAnimationFrame( () => {
					document
						.getElementById( 'ilb-provider-setup' )
						?.scrollIntoView( {
							behavior: 'smooth',
							block: 'start',
						} );
					document
						.getElementById( 'ilb-provider-setup-title' )
						?.focus( { preventScroll: true } );
				} )
			);
		};
		window.addEventListener(
			'ip-location-block-open-provider-setup',
			openProviderSetup
		);
		return () =>
			window.removeEventListener(
				'ip-location-block-open-provider-setup',
				openProviderSetup
			);
	}, [] );

	if ( loading ) {
		return <Spinner />;
	}
	if ( ! settings ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __( 'Failed to load settings.', 'ip-location-block' ) }
			</Notice>
		);
	}

	const onChange = ( path, value ) =>
		setSettings( ( s ) => setPath( s, path, value ) );

	const onModeChange = ( next ) => {
		setMode( next );
		storeMode( next );
		replaceViewInUrl( next );
	};

	const reportProviderReady = ( provider, ready = true ) =>
		setReadyOverrides( ( current ) => ( {
			...current,
			[ provider ]: !! ready,
		} ) );

	const requestProviderDisconnect = ( provider ) => {
		if ( pendingProviderAction ) {
			return;
		}
		setDisconnectRequest( {
			provider,
			impact: providerDisconnectImpact(
				settings,
				sources.providers,
				sources.providerStatus,
				readyOverrides,
				provider
			),
		} );
	};

	const confirmProviderDisconnect = () => {
		if ( ! disconnectRequest ) {
			return;
		}
		const { provider, impact } = disconnectRequest;
		const { next, snapshot } = stageProviderDisconnect(
			settings,
			provider,
			impact
		);
		setSettings( next );
		setPendingProviderAction( {
			provider,
			impact,
			snapshot,
			readyOverrides: { ...readyOverrides },
		} );
		setReadyOverrides( ( current ) => ( {
			...current,
			[ provider ]: false,
		} ) );
		setDisconnectRequest( null );
	};

	const undoProviderDisconnect = () => {
		if ( ! pendingProviderAction ) {
			return;
		}
		setSettings( ( current ) =>
			restoreProviderDisconnect( current, pendingProviderAction.snapshot )
		);
		setReadyOverrides( pendingProviderAction.readyOverrides );
		setPendingProviderAction( null );
	};

	const providerAction = {
		pending: pendingProviderAction,
		readyOverrides,
		reportReady: reportProviderReady,
		requestDisconnect: requestProviderDisconnect,
		connected: ( providers, providerStatus ) => {
			setSettings( ( current ) => ( {
				...current,
				providers: { ...( providers || {} ) },
			} ) );
			setSources( ( current ) => ( {
				...current,
				providerStatus: providerStatus || current.providerStatus,
			} ) );
			setReadyOverrides( {} );
			setPendingProviderAction( null );
			refreshRuntimeSources().catch( () => {
				// The provider is connected; runtime metadata refreshes next load.
			} );
		},
		undo: undoProviderDisconnect,
	};

	const refreshRuntimeSources = () =>
		Promise.all( [
			getProviders(),
			getProviderStatus().catch( () => null ),
			getDatabaseStatus(),
			getMode().catch( () => null ),
			getSettingsContext(),
		] ).then(
			( [ providers, providerStatus, dbStatus, geoMode, context ] ) => {
				setSources( ( current ) => ( {
					...current,
					providers,
					providerStatus,
					dbStatus,
					mode: geoMode,
					context,
				} ) );
			}
		);

	const isSimple = mode === 'simple';
	const publicBlockingEnabled =
		Number( settings?.validation?.public ) % 2 === 1;
	const redirectActive = isRedirectResponse(
		settings?.public?.response_code
	);
	const redirectStatus = redirectDestinationStatus(
		settings?.public?.redirect_uri,
		boot.homeUrl || window.location.origin
	);
	const simpleRedirectInvalid =
		isSimple &&
		publicBlockingEnabled &&
		redirectActive &&
		! redirectStatus.valid;

	const onSave = () => {
		if ( simpleRedirectInvalid ) {
			return;
		}
		setSaving( true );
		setSaveNotices( [] );
		saveSettings( settings, sources.context?.scope?.current || 'site' )
			.then( ( saved ) => {
				setSettings( saved );
				setPendingProviderAction( null );
				setReadyOverrides( {} );
				const warningNotices = saveWarnings( saved ).map(
					( warning ) => ( {
						id: `warning-${ ++noticeSequence.current }`,
						status: 'warning',
						message: warning.message,
						persistent: true,
					} )
				);
				setSaveNotices( [
					...warningNotices,
					{
						id: `success-${ ++noticeSequence.current }`,
						status: 'success',
						message: __( 'Settings saved.', 'ip-location-block' ),
						persistent: false,
					},
				] );
				window.dispatchEvent(
					new CustomEvent( 'ip-location-block-settings-saved', {
						detail: { settings: saved },
					} )
				);

				refreshRuntimeSources().catch( () => {
					// Saved successfully; provider status will refresh next load.
				} );
			} )
			.catch( ( e ) =>
				setSaveNotices( [
					{
						id: `error-${ ++noticeSequence.current }`,
						status: 'error',
						message: e.message,
						persistent: true,
					},
				] )
			)
			.finally( () => setSaving( false ) );
	};

	return (
		<div className="ilb-settings">
			<SaveToastRegion
				notices={ saveNotices }
				onRemove={ dismissSaveNotice }
			/>

			<div className="ilb-settings__modebar">
				<div
					className="ilb-view-switch"
					role="group"
					aria-label={ __( 'Settings view', 'ip-location-block' ) }
				>
					<button
						type="button"
						className="ilb-view-switch__option"
						aria-pressed={ mode === 'simple' }
						onClick={ () => onModeChange( 'simple' ) }
					>
						{ __( 'Simple', 'ip-location-block' ) }
					</button>
					<button
						type="button"
						className="ilb-view-switch__option"
						aria-pressed={ mode === 'advanced' }
						onClick={ () => onModeChange( 'advanced' ) }
					>
						{ __( 'Advanced', 'ip-location-block' ) }
					</button>
				</div>
			</div>

			{ pendingProviderAction && (
				<Notice
					status="warning"
					isDismissible={ false }
					className="ilb-provider-pending"
				>
					<div className="ilb-provider-pending__content">
						<span>
							{ sprintf(
								/* translators: %s: provider name. */
								__(
									'%s will be disconnected when you save.',
									'ip-location-block'
								),
								pendingProviderAction.provider
							) }
							{ pendingProviderAction.impact
								.protectionWasEnabled &&
								pendingProviderAction.impact
									.disablesProtection &&
								` ${ __(
									'Protection will also be turned off.',
									'ip-location-block'
								) }` }
						</span>
						<Button
							variant="link"
							onClick={ undoProviderDisconnect }
						>
							{ __( 'Undo', 'ip-location-block' ) }
						</Button>
					</div>
				</Notice>
			) }

			{ isSimple ? (
				<SimpleBlocking
					settings={ settings }
					providers={ sources.providers }
					providerStatus={ sources.providerStatus }
					onChange={ onChange }
					providerAction={ providerAction }
				/>
			) : (
				<>
					<Panel className="ilb-panel-shell ilb-settings__advanced-panel">
						{ SECTIONS.map( ( section, i ) => (
							<PanelBody
								key={ section.key }
								title={ section.title }
								initialOpen={
									requestedSection
										? requestedSection === section.key
										: i === 0
								}
								className={ `ilb-panel-section ilb-settings-section ilb-settings-section--${ section.key }` }
							>
								<div className="ilb-settings-section__guide">
									<a
										href={ advancedGuideUrl(
											section.docsPath,
											section.key
										) }
										target="_blank"
										rel="noreferrer noopener"
										aria-label={ sprintf(
											/* translators: %s: Advanced settings section title. */
											__(
												'Open %s guide in a new tab',
												'ip-location-block'
											),
											section.title
										) }
									>
										<span
											className="dashicons dashicons-book-alt"
											aria-hidden="true"
										/>
										{ __( 'Guide', 'ip-location-block' ) }
									</a>
								</div>
								{ section.groups.map( ( group ) => (
									<SettingsGroup
										key={ group.key }
										group={ group }
										settings={ settings }
										sources={ sources }
										onChange={ onChange }
										onReplace={ setSettings }
										onRefreshSources={
											refreshRuntimeSources
										}
										providerAction={ providerAction }
									/>
								) ) }
							</PanelBody>
						) ) }
					</Panel>
				</>
			) }

			<div className="ilb-settings__save">
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ saving || simpleRedirectInvalid }
					aria-describedby={
						simpleRedirectInvalid
							? 'ilb-simple-redirect-error'
							: undefined
					}
					onClick={ onSave }
				>
					{ __( 'Save Changes', 'ip-location-block' ) }
				</Button>
			</div>

			<ProviderDisconnectDialog
				request={ disconnectRequest }
				onCancel={ () => setDisconnectRequest( null ) }
				onConfirm={ confirmProviderDisconnect }
			/>
		</div>
	);
}
