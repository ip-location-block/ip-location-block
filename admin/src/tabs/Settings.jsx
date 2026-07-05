/**
 * Settings tab (pilot). Loads the full settings object via REST, edits a
 * representative subset of the "Validation rules and behavior" section, and
 * saves the whole object back through the classic sanitizer. Proves the
 * load → edit → save → reload round-trip; remaining sections/fields are
 * added incrementally.
 */
import { useState, useEffect } from '@wordpress/element';
import {
	Card,
	CardBody,
	SelectControl,
	TextControl,
	ToggleControl,
	Button,
	Notice,
	Spinner,
	__experimentalHeading as Heading,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getSettings, saveSettings } from '../api';

export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		getSettings()
			.then( ( s ) => setSettings( s ) )
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

	const set = ( key, value ) => setSettings( ( s ) => ( { ...s, [ key ]: value } ) );

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
		<Card>
			<CardBody>
				<Heading level={ 3 }>
					{ __( 'Validation rules and behavior', 'ip-location-block' ) }
				</Heading>

				{ notice && (
					<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
						{ notice.msg }
					</Notice>
				) }

				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Matching rule', 'ip-location-block' ) }
					value={ String( settings.matching_rule ) }
					options={ [
						{ label: __( 'Disable', 'ip-location-block' ), value: '-1' },
						{ label: __( 'White list', 'ip-location-block' ), value: '0' },
						{ label: __( 'Black list', 'ip-location-block' ), value: '1' },
					] }
					onChange={ ( v ) => set( 'matching_rule', Number( v ) ) }
				/>

				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'White list of country code', 'ip-location-block' ) }
					help={ __( 'Comma-separated ISO 3166-1 alpha-2 codes.', 'ip-location-block' ) }
					value={ settings.white_list || '' }
					onChange={ ( v ) => set( 'white_list', v ) }
				/>

				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Black list of country code', 'ip-location-block' ) }
					value={ settings.black_list || '' }
					onChange={ ( v ) => set( 'black_list', v ) }
				/>

				<TextControl
					__nextHasNoMarginBottom
					type="number"
					label={ __( 'Response code', 'ip-location-block' ) }
					value={ settings.response_code }
					onChange={ ( v ) => set( 'response_code', Number( v ) ) }
				/>

				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Response message', 'ip-location-block' ) }
					value={ settings.response_msg || '' }
					onChange={ ( v ) => set( 'response_msg', v ) }
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Simulation mode (log only, do not block)', 'ip-location-block' ) }
					checked={ !! settings.simulate }
					onChange={ ( v ) => set( 'simulate', v ) }
				/>

				<div style={ { marginTop: '16px' } }>
					<Button variant="primary" isBusy={ saving } disabled={ saving } onClick={ onSave }>
						{ __( 'Save Changes', 'ip-location-block' ) }
					</Button>
				</div>
			</CardBody>
		</Card>
	);
}
