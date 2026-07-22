/* eslint-disable no-alert, no-nested-ternary */
import { useState, useRef } from '@wordpress/element';
import { Button, Notice, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	applyPreset,
	deleteEmergencyLoginLink,
	diagnoseDatabaseTables,
	exportSettings,
	generateEmergencyLoginLink,
	getDefaults,
	getLegacySettings,
	importSettings,
	resetLiveLogs,
} from '../api';
import { betaUrl } from '../navigation';

const copyText = async ( value ) => {
	if ( window.navigator.clipboard?.writeText ) {
		return window.navigator.clipboard.writeText( value );
	}
	const input = document.createElement( 'textarea' );
	input.value = value;
	input.setAttribute( 'readonly', '' );
	input.style.position = 'fixed';
	input.style.opacity = '0';
	document.body.appendChild( input );
	input.select();
	document.execCommand( 'copy' );
	document.body.removeChild( input );
};

export default function SettingsActions( {
	settings,
	context,
	onReplace,
	onContextChange,
} ) {
	const [ busy, setBusy ] = useState( '' );
	const [ message, setMessage ] = useState( null );
	const [ emergencyUrl, setEmergencyUrl ] = useState( '' );
	const [ copied, setCopied ] = useState( false );
	const fileRef = useRef();
	const scope = context?.scope?.current || 'site';
	const emergency = context?.emergencyLogin || {
		configured: false,
		state: 'not_configured',
	};

	const finish = ( id, promise, success, replace = false ) => {
		setBusy( id );
		setMessage( null );
		return promise
			.then( ( response ) => {
				if ( replace ) {
					onReplace( response.settings || response );
				}
				setMessage( { status: 'success', text: success } );
				return response;
			} )
			.catch( ( error ) => {
				setMessage( { status: 'error', text: error.message } );
				throw error;
			} )
			.finally( () => setBusy( '' ) );
	};

	const generate = () => {
		if (
			emergency.configured &&
			! window.confirm(
				__(
					'Regenerating invalidates the current emergency link. Continue?',
					'ip-location-block'
				)
			)
		) {
			return;
		}
		finish(
			'emergency-generate',
			generateEmergencyLoginLink( scope ),
			__(
				'Emergency link generated. Bookmark or copy it now; the key is not shown again.',
				'ip-location-block'
			)
		)
			.then( ( response ) => {
				setEmergencyUrl( response.url );
				return onContextChange?.();
			} )
			.catch( () => {} );
	};

	const removeEmergency = () => {
		if (
			! window.confirm(
				__(
					'Delete the current emergency link? Any saved bookmark will stop working.',
					'ip-location-block'
				)
			)
		) {
			return;
		}
		finish(
			'emergency-delete',
			deleteEmergencyLoginLink( scope ),
			__( 'Emergency link deleted.', 'ip-location-block' )
		)
			.then( () => {
				setEmergencyUrl( '' );
				return onContextChange?.();
			} )
			.catch( () => {} );
	};

	const downloadExport = () => {
		setBusy( 'export' );
		setMessage( null );
		exportSettings( settings )
			.then( ( response ) => {
				const blob = new window.Blob(
					[ JSON.stringify( response.data, null, 2 ) ],
					{
						type: 'application/json',
					}
				);
				const url = window.URL.createObjectURL( blob );
				const anchor = document.createElement( 'a' );
				anchor.href = url;
				anchor.download = response.filename;
				anchor.click();
				window.URL.revokeObjectURL( url );
				setMessage( {
					status: 'success',
					text: __( 'Settings exported.', 'ip-location-block' ),
				} );
			} )
			.catch( ( error ) =>
				setMessage( { status: 'error', text: error.message } )
			)
			.finally( () => setBusy( '' ) );
	};

	const readImport = ( event ) => {
		const file = event.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		const reader = new window.FileReader();
		reader.onload = () => {
			try {
				const data = JSON.parse( reader.result );
				finish(
					'import',
					importSettings( data ),
					__(
						'Settings imported. Review them, then Save Changes.',
						'ip-location-block'
					),
					true
				).catch( () => {} );
			} catch {
				setMessage( {
					status: 'error',
					text: __(
						'Could not parse the selected JSON file.',
						'ip-location-block'
					),
				} );
			}
		};
		reader.readAsText( file );
		event.target.value = '';
	};

	const migrate = () => {
		if (
			! window.confirm(
				__(
					'Load the detected IP Geo Block settings into this form? Nothing is saved until you press Save Changes.',
					'ip-location-block'
				)
			)
		) {
			return;
		}
		finish(
			'legacy',
			getLegacySettings(),
			__( 'Legacy settings loaded for review.', 'ip-location-block' ),
			true
		).catch( () => {} );
	};

	const developerAction = ( id, request, success ) => {
		if (
			! window.confirm(
				__( 'Run this maintenance action now?', 'ip-location-block' )
			)
		) {
			return;
		}
		finish( id, request(), success ).catch( () => {} );
	};

	return (
		<div className="ilb-plugin-tools">
			{ message && (
				<Notice
					status={ message.status }
					onRemove={ () => setMessage( null ) }
				>
					{ message.text }
				</Notice>
			) }

			<section className="ilb-plugin-tools__section">
				<div>
					<h3>{ __( 'Emergency access', 'ip-location-block' ) }</h3>
					<p>
						{ emergency.state === 'ready'
							? __(
									'An emergency login link is configured and ready.',
									'ip-location-block'
							  )
							: emergency.state === 'outdated'
							? __(
									'The configured emergency login link is outdated and must be regenerated.',
									'ip-location-block'
							  )
							: __(
									'Create a private login URL you can use if location rules lock you out.',
									'ip-location-block'
							  ) }
					</p>
				</div>
				<div className="ilb-plugin-tools__actions">
					<Button
						variant={
							emergency.configured ? 'secondary' : 'primary'
						}
						isBusy={ busy === 'emergency-generate' }
						disabled={ !! busy }
						onClick={ generate }
					>
						{ emergency.configured
							? __( 'Regenerate link', 'ip-location-block' )
							: __( 'Generate link', 'ip-location-block' ) }
					</Button>
					{ emergency.configured && (
						<Button
							variant="tertiary"
							isDestructive
							isBusy={ busy === 'emergency-delete' }
							disabled={ !! busy }
							onClick={ removeEmergency }
						>
							{ __( 'Delete link', 'ip-location-block' ) }
						</Button>
					) }
				</div>
				{ emergencyUrl && (
					<div className="ilb-emergency-link">
						<TextControl
							__nextHasNoMarginBottom
							label={ __(
								'New emergency login URL',
								'ip-location-block'
							) }
							value={ emergencyUrl }
							readOnly
						/>
						<div className="ilb-plugin-tools__actions">
							<Button
								variant="secondary"
								onClick={ () => {
									copyText( emergencyUrl ).then( () => {
										setCopied( true );
										window.setTimeout(
											() => setCopied( false ),
											2000
										);
									} );
								} }
							>
								{ copied
									? __( 'Copied', 'ip-location-block' )
									: __( 'Copy URL', 'ip-location-block' ) }
							</Button>
							<Button
								variant="link"
								href={ emergencyUrl }
								target="_blank"
								rel="noreferrer"
							>
								{ __( 'Open login page', 'ip-location-block' ) }
							</Button>
						</div>
						<p>
							{ __(
								'Bookmark this URL now. For security, its key is not stored in readable form and cannot be displayed again.',
								'ip-location-block'
							) }
						</p>
					</div>
				) }
			</section>

			<section className="ilb-plugin-tools__section">
				<div>
					<h3>
						{ __(
							'Configuration backup and presets',
							'ip-location-block'
						) }
					</h3>
					<p>
						{ __(
							'Exports can contain provider API keys. Store exported files securely.',
							'ip-location-block'
						) }
					</p>
				</div>
				<div className="ilb-plugin-tools__actions">
					<Button
						variant="secondary"
						isBusy={ busy === 'export' }
						disabled={ !! busy }
						onClick={ downloadExport }
					>
						{ __( 'Export settings', 'ip-location-block' ) }
					</Button>
					<Button
						variant="secondary"
						disabled={ !! busy }
						onClick={ () => fileRef.current.click() }
					>
						{ __( 'Import settings', 'ip-location-block' ) }
					</Button>
					<Button
						variant="primary"
						isBusy={ busy === 'preferred' }
						disabled={ !! busy }
						onClick={ () =>
							finish(
								'preferred',
								applyPreset( 'preferred' ),
								__(
									'Recommended back-end settings loaded for review.',
									'ip-location-block'
								),
								true
							).catch( () => {} )
						}
					>
						{ __( 'Best for Back-end', 'ip-location-block' ) }
					</Button>
					<Button
						variant="tertiary"
						isBusy={ busy === 'defaults' }
						disabled={ !! busy }
						onClick={ () => {
							if (
								window.confirm(
									__(
										'Load default settings into the form?',
										'ip-location-block'
									)
								)
							) {
								finish(
									'defaults',
									getDefaults(),
									__(
										'Defaults loaded for review.',
										'ip-location-block'
									),
									true
								).catch( () => {} );
							}
						} }
					>
						{ __( 'Load defaults', 'ip-location-block' ) }
					</Button>
					<input
						ref={ fileRef }
						type="file"
						accept="application/json,.json"
						hidden
						onChange={ readImport }
					/>
				</div>
			</section>

			{ context?.legacyMigration?.available && (
				<section className="ilb-plugin-tools__section is-highlighted">
					<div>
						<h3>
							{ __(
								'Migrate from IP Geo Block',
								'ip-location-block'
							) }
						</h3>
						<p>
							{ __(
								'Previous plugin settings were detected. Load the converted values into this form before saving.',
								'ip-location-block'
							) }
						</p>
					</div>
					<Button
						variant="secondary"
						isBusy={ busy === 'legacy' }
						disabled={ !! busy }
						onClick={ migrate }
					>
						{ __( 'Load legacy settings', 'ip-location-block' ) }
					</Button>
				</section>
			) }

			<section className="ilb-plugin-tools__section">
				<div>
					<h3>
						{ __( 'Diagnostics and support', 'ip-location-block' ) }
					</h3>
					<p>
						{ __(
							'View configuration checks and the complete support report, including active plugins and environment details.',
							'ip-location-block'
						) }
					</p>
				</div>
				<Button
					variant="secondary"
					href={ betaUrl( { tab: 'diagnostics' } ) }
				>
					{ __( 'Open Diagnostics', 'ip-location-block' ) }
				</Button>
			</section>

			{ context?.features?.debug && (
				<section className="ilb-plugin-tools__section">
					<div>
						<h3>
							{ __(
								'Developer maintenance',
								'ip-location-block'
							) }
						</h3>
						<p>
							{ __(
								'These controls are available because IP_LOCATION_BLOCK_DEBUG is enabled.',
								'ip-location-block'
							) }
						</p>
					</div>
					<div className="ilb-plugin-tools__actions">
						<Button
							variant="secondary"
							isBusy={ busy === 'tables' }
							disabled={ !! busy }
							onClick={ () =>
								developerAction(
									'tables',
									diagnoseDatabaseTables,
									__(
										'Database tables checked.',
										'ip-location-block'
									)
								)
							}
						>
							{ __(
								'Diagnose database tables',
								'ip-location-block'
							) }
						</Button>
						<Button
							variant="secondary"
							isBusy={ busy === 'live-reset' }
							disabled={ !! busy || ! context.features.pdoSqlite }
							onClick={ () =>
								developerAction(
									'live-reset',
									resetLiveLogs,
									__(
										'Live log database reset.',
										'ip-location-block'
									)
								)
							}
						>
							{ __(
								'Reset live log database',
								'ip-location-block'
							) }
						</Button>
					</div>
				</section>
			) }
		</div>
	);
}
