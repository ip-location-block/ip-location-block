/**
 * Compact Native/Standard status control. The provider card owns the full
 * product story; this panel only explains the current state and points there.
 */
import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { ProviderJourneyLink } from './PrecisionContent';

const PANEL_ID = 'ilb-mode-panel';

export default function ModeBadge( { mode } ) {
	const [ open, setOpen ] = useState( false );
	const ref = useRef();

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const onDown = ( event ) => {
			if ( ref.current && ! ref.current.contains( event.target ) ) {
				setOpen( false );
			}
		};
		const onKey = ( event ) => {
			if ( event.key === 'Escape' ) {
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
	const enforced = ! native && !! mode.enforced;

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
								'Premium geolocation data and state/region rules are active.',
								'ip-location-block'
							) }
						</p>
					</div>
				</div>
			);
		}
		if ( enforced ) {
			return (
				<div className="ilb-mode-panel__status ilb-mode-panel__status--info">
					<span
						className="dashicons dashicons-info-outline"
						aria-hidden="true"
					/>
					<div>
						<strong>
							{ __(
								'Native provider prioritized',
								'ip-location-block'
							) }
						</strong>
						<p>
							{ __(
								'Native Mode provides premium data and powers regional rules first; other providers remain country-level fallbacks.',
								'ip-location-block'
							) }
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
						'Upgrade to premium geolocation data for better country accuracy and state or region precision.',
						'ip-location-block'
					) }
				</p>
				<div className="ilb-mode-panel__actions">
					<ProviderJourneyLink onClick={ () => setOpen( false ) } />
				</div>
			</div>
		);
	};

	return (
		<div className="ilb-mode-dd" ref={ ref }>
			<button
				type="button"
				className={ `ilb-mode ilb-mode--${
					native ? 'native' : enforced ? 'enforced' : 'standard'
				}` }
				onClick={ () => setOpen( ( value ) => ! value ) }
				aria-expanded={ open }
				aria-controls={ PANEL_ID }
				title={ __(
					'View geolocation mode details.',
					'ip-location-block'
				) }
			>
				<span className="ilb-mode__dot" aria-hidden="true" />
				{ native
					? __( 'Native Mode', 'ip-location-block' )
					: enforced
					? __( 'Native (enforced)', 'ip-location-block' )
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
