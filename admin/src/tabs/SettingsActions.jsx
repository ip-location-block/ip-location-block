/**
 * Action buttons for the Settings tab: export/import (client-side, from the
 * current form state) and download-database / load-defaults (REST). Import and
 * "load defaults" replace the in-memory form; the user then reviews and Saves.
 */
import { useState, useRef } from '@wordpress/element';
import { Card, CardBody, Button, Notice, Flex, __experimentalHeading as Heading } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getDefaults, updateDatabase, applyPreset } from '../api';

export default function SettingsActions( { settings, onReplace } ) {
	const [ busy, setBusy ] = useState( '' );
	const [ msg, setMsg ] = useState( null );
	const fileRef = useRef();

	const exportSettings = () => {
		const blob = new window.Blob( [ JSON.stringify( settings, null, 2 ) ], {
			type: 'application/json',
		} );
		const url = window.URL.createObjectURL( blob );
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = 'ip-location-block-settings.json';
		a.click();
		window.URL.revokeObjectURL( url );
	};

	const importSettings = ( e ) => {
		const file = e.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const reader = new window.FileReader();
		reader.onload = () => {
			try {
				const data = JSON.parse( reader.result );
				if ( data && typeof data === 'object' && data.validation ) {
					onReplace( data );
					setMsg( { status: 'success', msg: __( 'Settings imported — review and Save.', 'ip-location-block' ) } );
				} else {
					setMsg( { status: 'error', msg: __( 'Not a valid settings file.', 'ip-location-block' ) } );
				}
			} catch ( err ) {
				setMsg( { status: 'error', msg: __( 'Could not parse the file.', 'ip-location-block' ) } );
			}
		};
		reader.readAsText( file );
		e.target.value = '';
	};

	const run = ( id, promise, okMsg ) => {
		setBusy( id );
		setMsg( null );
		promise
			.then( ( res ) => {
				if ( id === 'defaults' || id === 'preferred' ) {
					onReplace( res );
				}
				setMsg( { status: 'success', msg: okMsg } );
			} )
			.catch( ( e ) => setMsg( { status: 'error', msg: e.message } ) )
			.finally( () => setBusy( '' ) );
	};

	return (
		<Card style={ { marginTop: '16px' } }>
			<CardBody>
				<Heading level={ 3 }>{ __( 'Tools', 'ip-location-block' ) }</Heading>
				{ msg && (
					<Notice status={ msg.status } onRemove={ () => setMsg( null ) }>
						{ msg.msg }
					</Notice>
				) }
				<Flex justify="flex-start" gap={ 3 } wrap>
					<Button variant="secondary" onClick={ exportSettings }>
						{ __( 'Export settings', 'ip-location-block' ) }
					</Button>
					<Button variant="secondary" onClick={ () => fileRef.current.click() }>
						{ __( 'Import settings', 'ip-location-block' ) }
					</Button>
					<Button
						variant="primary"
						isBusy={ busy === 'preferred' }
						disabled={ !! busy }
						onClick={ () =>
							run(
								'preferred',
								applyPreset( 'preferred' ),
								__( 'Recommended back-end settings applied — review and Save.', 'ip-location-block' )
							)
						}
					>
						{ __( 'Best for Back-end', 'ip-location-block' ) }
					</Button>
					<Button
						variant="secondary"
						isBusy={ busy === 'defaults' }
						disabled={ !! busy }
						onClick={ () =>
							run( 'defaults', getDefaults(), __( 'Defaults loaded — review and Save.', 'ip-location-block' ) )
						}
					>
						{ __( 'Load default settings', 'ip-location-block' ) }
					</Button>
					<Button
						variant="secondary"
						isBusy={ busy === 'db' }
						disabled={ !! busy }
						onClick={ () =>
							run( 'db', updateDatabase(), __( 'Database update triggered.', 'ip-location-block' ) )
						}
					>
						{ __( 'Download database', 'ip-location-block' ) }
					</Button>
					<input
						ref={ fileRef }
						type="file"
						accept="application/json,.json"
						style={ { display: 'none' } }
						onChange={ importSettings }
					/>
				</Flex>
			</CardBody>
		</Card>
	);
}
