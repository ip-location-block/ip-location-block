import { __ } from '@wordpress/i18n';

import { ProviderJourneyLink } from './PrecisionContent';

export default function PrecisionUpsell( { mode, settings } ) {
	if ( ! mode ) {
		return null;
	}

	const nativeSelected = !! settings?.providers?.[ 'IP Location Block' ];
	const native = !! mode.native && nativeSelected;
	const enforced = !! mode.enforced && nativeSelected;
	const state = native
		? {
				className: 'success',
				icon: 'yes-alt',
				title: __( 'Native Mode active', 'ip-location-block' ),
				message: __(
					'Premium geolocation data and state/region rules are active.',
					'ip-location-block'
				),
		  }
		: enforced
		? {
				className: 'info',
				icon: 'info-outline',
				title: __( 'Native provider prioritized', 'ip-location-block' ),
				message: __(
					'Native Mode provides premium data and powers regional rules first; other providers remain country-level fallbacks.',
					'ip-location-block'
				),
		  }
		: {
				className: 'info',
				icon: 'location-alt',
				title: __(
					'Better accuracy and regional precision are available',
					'ip-location-block'
				),
				message: __(
					'Native Mode uses premium, frequently updated geolocation data for better country accuracy and state, province, or equivalent regional precision.',
					'ip-location-block'
				),
		  };

	return (
		<div
			className={ `ilb-precision-status ilb-precision-status--${ state.className }` }
		>
			<span
				className={ `dashicons dashicons-${ state.icon }` }
				aria-hidden="true"
			/>
			<div>
				<strong>{ state.title }</strong>
				<p>{ state.message }</p>
			</div>
			{ ! native && ! enforced && <ProviderJourneyLink /> }
		</div>
	);
}
