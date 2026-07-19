/**
 * Floating edge switcher to the classic view. Mirrors the classic-side switcher
 * (same class + shared stylesheet) so the toggle feels like one control across
 * both interfaces. Self-persists via a nonce-guarded ?view=classic link.
 */
import { __ } from '@wordpress/i18n';

import { switchViewUrl } from '../navigation';

const boot = window.ipLocationBlockAdmin || {};

export default function ViewSwitcher() {
	if ( ! boot.viewSwitchNonce ) {
		return null;
	}

	const href = switchViewUrl( 'classic', boot.viewSwitchNonce );
	if ( ! href ) {
		return null;
	}

	return (
		<a
			className="ilb-view-switcher ilb-view-switcher--to-classic"
			href={ href }
			title={ __( 'Switch to Classic view', 'ip-location-block' ) }
		>
			<span
				className="dashicons dashicons-image-rotate"
				aria-hidden="true"
			/>
			<span className="ilb-view-switcher__label">
				{ __( 'Classic view', 'ip-location-block' ) }
			</span>
		</a>
	);
}
