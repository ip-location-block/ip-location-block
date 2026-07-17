/**
 * Header Native/Standard mode badge with an expandable dropdown, mirroring the
 * classic status.php top-right panel. The pill toggles a panel showing the
 * Standard-vs-Native comparison + Upgrade (or the current-state note). Copy is
 * shared with the section card via PrecisionContent.
 *
 * A small self-positioned dropdown (absolute, inside .ilb-app) is used instead
 * of a portaled Popover so anchoring is reliable in the header and the design
 * tokens resolve normally.
 */
import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { StandardList, NativeList, UpgradeNote, UpgradeButton } from './PrecisionContent';

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
		const onKey = ( e ) => e.key === 'Escape' && setOpen( false );
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
				<p className="ilb-mode-panel__note ilb-mode-panel__note--ok">
					<span className="dashicons dashicons-yes-alt" aria-hidden="true" />
					{ __(
						'Native Mode is active — precision blocking by state and city is enabled.',
						'ip-location-block'
					) }
				</p>
			);
		}
		if ( mode.apiEnabled && mode.others && mode.others.length ) {
			return (
				<p className="ilb-mode-panel__note ilb-mode-panel__note--warn">
					<span className="dashicons dashicons-warning" aria-hidden="true" />
					{ __(
						'You are in Standard Mode. To enable Native Mode, disable these providers:',
						'ip-location-block'
					) }{ ' ' }
					<em>{ mode.others.join( ', ' ) }</em>
				</p>
			);
		}
		return (
			<>
				<div className="ilb-mode-panel__section">
					<h5>{ __( 'Standard Mode', 'ip-location-block' ) }</h5>
					<StandardList />
				</div>
				<div className="ilb-mode-panel__section ilb-mode-panel__section--native">
					<h5>{ __( 'Native Mode', 'ip-location-block' ) }</h5>
					<NativeList />
				</div>
				<hr className="ilb-mode-panel__rule" />
				<UpgradeNote />
				<UpgradeButton className="ilb-mode-panel__cta" />
			</>
		);
	};

	return (
		<div className="ilb-mode-dd" ref={ ref }>
			<button
				type="button"
				className={ `ilb-mode ilb-mode--${ native ? 'native' : 'standard' }` }
				onClick={ () => setOpen( ( v ) => ! v ) }
				aria-expanded={ open }
				title={ __(
					'Native mode gives better precision and city/state level blocking.',
					'ip-location-block'
				) }
			>
				{ native
					? __( 'Native Mode', 'ip-location-block' )
					: __( 'Standard Mode', 'ip-location-block' ) }
				<span
					className={ `dashicons dashicons-arrow-${ open ? 'up' : 'down' }-alt2` }
					aria-hidden="true"
				/>
			</button>
			{ open && <div className="ilb-mode-panel">{ panel() }</div> }
		</div>
	);
}
