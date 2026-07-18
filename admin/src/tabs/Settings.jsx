/**
 * Settings tab. Two views over the same settings object:
 *  - Simple:   a guided blocking card (SimpleBlocking) for the common case.
 *  - Advanced: the full 7-section form driven by settingsSchema.
 * Both edit the same state and share the Save bar, so switching views never
 * loses edits. Fresh installs land on Simple; the choice is remembered locally.
 */
import { useState, useEffect } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
import { setPath } from './paths';
import SettingsField from './SettingsField';
import SimpleBlocking from './SimpleBlocking';
import ScanCountry from '../components/ScanCountry';
import { queryParam } from '../navigation';

const STORAGE_KEY = 'ilbBetaSettingsMode';

const readStoredMode = () => {
	try {
		const v = window.localStorage.getItem( STORAGE_KEY );
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
					/>
				) ) }
			</div>
		</section>
	);
}

export default function Settings() {
	const requestedView = queryParam( 'view' );
	const requestedSection = queryParam( 'section' );
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
	const [ notice, setNotice ] = useState( null );

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
						( requestedView === 'advanced' ? 'advanced' : null ) ||
							readStoredMode() ||
							( looksUnconfigured( s ) ? 'simple' : 'advanced' )
					);
				}
			)
			.catch( ( e ) => setNotice( { status: 'error', msg: e.message } ) )
			.finally( () => setLoading( false ) );
	}, [ requestedView ] );

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

	const onSave = () => {
		setSaving( true );
		setNotice( null );
		saveSettings( settings, sources.context?.scope?.current || 'site' )
			.then( ( saved ) => {
				setSettings( saved );
				setNotice( {
					status: 'success',
					msg: __( 'Settings saved.', 'ip-location-block' ),
				} );
				window.dispatchEvent(
					new CustomEvent( 'ip-location-block-settings-saved', {
						detail: { settings: saved },
					} )
				);

				refreshRuntimeSources().catch( () => {
					// Saved successfully; provider status will refresh next load.
				} );
			} )
			.catch( ( e ) => setNotice( { status: 'error', msg: e.message } ) )
			.finally( () => setSaving( false ) );
	};

	const isSimple = mode === 'simple';

	return (
		<div className="ilb-settings">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.msg }
				</Notice>
			) }

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

			{ isSimple ? (
				<SimpleBlocking
					settings={ settings }
					providers={ sources.providers }
					providerStatus={ sources.providerStatus }
					onChange={ onChange }
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
					disabled={ saving }
					onClick={ onSave }
				>
					{ __( 'Save Changes', 'ip-location-block' ) }
				</Button>
			</div>
		</div>
	);
}
