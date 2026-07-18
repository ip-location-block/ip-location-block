/**
 * Compact Native/Standard status control. Its anchored panel shows either the
 * current mode guidance or a short upgrade path without leaving the header.
 */
import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	PrecisionBenefits,
	PrecisionLearnLink,
	UpgradeButton,
} from './PrecisionContent';

const PANEL_ID = 'ilb-mode-panel';

export default function ModeBadge( { mode } ) {
	const [ open, setOpen ] = useState( false );
	const ref = useRef();

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const onDown = ( e ) => {
			if ( ref.current && ! ref.current.contains( e.target ) ) {
				setOpen( false );
			}
		};
		const onKey = ( e ) => {
			if ( e.key === 'Escape' ) {
				setOpen( false );
				ref.current?.querySelector( '.ilb-mode' )?.focus();
			}
		};
		document.addEventListener( 'mousedown', onDown );
		document.addEventListener( 'keydown', onKey );
		return () => {
			document.removeEventListener( 'mousedown', onDown );
			document.removeEventListener( 'keydown', onKey );
		};
	}, [ open ] );

	if ( ! mode ) {
		return null;
	}
	const native = !! mode.native;

	const panel = () => {
		if ( native ) {
			return (
				<div className="ilb-mode-panel__status ilb-mode-panel__status--success">
					<span
						className="dashicons dashicons-yes-alt"
						aria-hidden="true"
					/>
					<div>
						<strong>
							{ __( 'Native Mode active', 'ip-location-block' ) }
						</strong>
						<p>
							{ __(
								'City and state rules are available.',
								'ip-location-block'
							) }
						</p>
					</div>
				</div>
			);
		}
		if ( mode.apiEnabled && mode.others && mode.others.length ) {
			return (
				<div className="ilb-mode-panel__status ilb-mode-panel__status--warning">
					<span
						className="dashicons dashicons-warning"
						aria-hidden="true"
					/>
					<div>
						<strong>
							{ __( 'Standard Mode', 'ip-location-block' ) }
						</strong>
						<p>
							{ __(
								'Disable these providers to use Native Mode:',
								'ip-location-block'
							) }{ ' ' }
							<em>{ mode.others.join( ', ' ) }</em>
						</p>
					</div>
				</div>
			);
		}
		return (
			<div className="ilb-mode-panel__upgrade">
				<h2>{ __( 'Standard Mode', 'ip-location-block' ) }</h2>
				<p>
					{ __(
						'Standard Mode supports country-level rules. Native Mode adds city and state rules with more precise location data.',
						'ip-location-block'
					) }
				</p>
				<PrecisionBenefits compact />
				<div className="ilb-mode-panel__actions">
					<UpgradeButton />
					<PrecisionLearnLink />
				</div>
			</div>
		);
	};

	return (
		<div className="ilb-mode-dd" ref={ ref }>
			<button
				type="button"
				className={ `ilb-mode ilb-mode--${
					native ? 'native' : 'standard'
				}` }
				onClick={ () => setOpen( ( v ) => ! v ) }
				aria-expanded={ open }
				aria-controls={ PANEL_ID }
				title={ __(
					'Native mode gives better precision and city/state level blocking.',
					'ip-location-block'
				) }
			>
				<span className="ilb-mode__dot" aria-hidden="true" />
				{ native
					? __( 'Native Mode', 'ip-location-block' )
					: __( 'Standard Mode', 'ip-location-block' ) }
				<span
					className={ `dashicons dashicons-arrow-${
						open ? 'up' : 'down'
					}-alt2` }
					aria-hidden="true"
				/>
			</button>
			{ open && (
				<div
					id={ PANEL_ID }
					className="ilb-mode-panel"
					role="region"
					aria-label={ __(
						'Geolocation mode details',
						'ip-location-block'
					) }
				>
					{ panel() }
				</div>
			) }
		</div>
	);
}
