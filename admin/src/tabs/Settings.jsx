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
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	getSettings,
	saveSettings,
	getContent,
	getExceptions,
	getProviders,
	getDatabaseStatus,
} from '../api';
import { SECTIONS } from './settingsSchema';
import { setPath } from './paths';
import SettingsField from './SettingsField';
import SettingsActions from './SettingsActions';
import SimpleBlocking from './SimpleBlocking';

const STORAGE_KEY = 'ilbBetaSettingsMode';

const readStoredMode = () => {
	try {
		const v = window.localStorage.getItem( STORAGE_KEY );
		return v === 'simple' || v === 'advanced' ? v : null;
	} catch ( e ) {
		return null;
	}
};

const storeMode = ( mode ) => {
	try {
		window.localStorage.setItem( STORAGE_KEY, mode );
	} catch ( e ) {
		// storage unavailable — the choice just won't persist
	}
};

// An install with no country rule and no front-end blocking has never been
// configured; start those on the guided view.
const looksUnconfigured = ( s ) =>
	Number( s.matching_rule ) === -1 && ( Number( s?.validation?.public ) & 1 ) !== 1;

export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ sources, setSources ] = useState( {
		content: {},
		exceptions: {},
		providers: [],
		dbStatus: [],
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
			getDatabaseStatus(),
		] )
			.then( ( [ s, content, exceptions, providers, dbStatus ] ) => {
				setSettings( s );
				setSources( { content, exceptions, providers, dbStatus } );
				setMode( readStoredMode() || ( looksUnconfigured( s ) ? 'simple' : 'advanced' ) );
			} )
			.catch( ( e ) => setNotice( { status: 'error', msg: e.message } ) )
			.finally( () => setLoading( false ) );
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

	const onChange = ( path, value ) => setSettings( ( s ) => setPath( s, path, value ) );

	const onModeChange = ( next ) => {
		setMode( next );
		storeMode( next );
	};

	const onSave = () => {
		setSaving( true );
		setNotice( null );
		saveSettings( settings )
			.then( ( saved ) => {
				setSettings( saved );
				setNotice( { status: 'success', msg: __( 'Settings saved.', 'ip-location-block' ) } );
			} )
			.catch( ( e ) => setNotice( { status: 'error', msg: e.message } ) )
			.finally( () => setSaving( false ) );
	};

	const isSimple = mode === 'simple';

	return (
		<div className="ilb-settings">
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }

			<div className="ilb-settings__modebar">
				<ToggleGroupControl
					__nextHasNoMarginBottom
					hideLabelFromVision
					label={ __( 'Settings view', 'ip-location-block' ) }
					value={ mode }
					onChange={ onModeChange }
				>
					<ToggleGroupControlOption
						value="simple"
						label={ __( 'Simple', 'ip-location-block' ) }
					/>
					<ToggleGroupControlOption
						value="advanced"
						label={ __( 'Advanced', 'ip-location-block' ) }
					/>
				</ToggleGroupControl>
			</div>

			{ isSimple ? (
				<SimpleBlocking
					settings={ settings }
					providers={ sources.providers }
					onChange={ onChange }
				/>
			) : (
				<>
					<Panel>
						{ SECTIONS.map( ( section, i ) => (
							<PanelBody key={ section.key } title={ section.title } initialOpen={ i === 0 }>
								{ section.fields.map( ( field ) => (
									<SettingsField
										key={ field.path }
										field={ field }
										settings={ settings }
										sources={ sources }
										onChange={ onChange }
									/>
								) ) }
							</PanelBody>
						) ) }
					</Panel>

					<SettingsActions settings={ settings } onReplace={ setSettings } />
				</>
			) }

			<div className="ilb-settings__save">
				<Button variant="primary" isBusy={ saving } disabled={ saving } onClick={ onSave }>
					{ __( 'Save Changes', 'ip-location-block' ) }
				</Button>
			</div>
		</div>
	);
}
