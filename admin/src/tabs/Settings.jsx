/**
 * Settings tab — the full 7-section form, driven by settingsSchema. Loads the
 * settings object via REST, edits in place, and saves the whole object back
 * through the classic sanitizer. Fields flagged `advanced` (provider table,
 * WP-content pickers, exception discovery) show a placeholder until their
 * supporting REST endpoints are built.
 */
import { useState, useEffect } from '@wordpress/element';
import { Panel, PanelBody, Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getSettings, saveSettings, getContent, getExceptions } from '../api';
import { SECTIONS } from './settingsSchema';
import { setPath } from './paths';
import SettingsField from './SettingsField';

export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ sources, setSources ] = useState( { content: {}, exceptions: {} } );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		Promise.all( [ getSettings(), getContent(), getExceptions() ] )
			.then( ( [ s, content, exceptions ] ) => {
				setSettings( s );
				setSources( { content, exceptions } );
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

	return (
		<div className="ilb-settings">
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.msg }
				</Notice>
			) }

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

			<div className="ilb-settings__save">
				<Button variant="primary" isBusy={ saving } disabled={ saving } onClick={ onSave }>
					{ __( 'Save Changes', 'ip-location-block' ) }
				</Button>
			</div>
		</div>
	);
}
