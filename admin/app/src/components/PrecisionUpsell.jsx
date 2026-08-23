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
					'State/region rules are available through IP Location Block.',
					'ip-location-block'
				),
		  }
		: enforced
		? {
				className: 'info',
				icon: 'info-outline',
				title: __( 'Native provider prioritized', 'ip-location-block' ),
				message: __(
					'Regional rules use IP Location Block first; other providers remain country-level fallbacks.',
					'ip-location-block'
				),
		  }
		: {
				className: 'info',
				icon: 'location-alt',
				title: __(
					'Regional blocking is available',
					'ip-location-block'
				),
				message: __(
					'Native Mode adds rules for the state, province, or equivalent region returned for each country.',
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
			{ ! native && ! enforced && (
				<ProviderJourneyLink>
					{ __( 'Compare Native Mode', 'ip-location-block' ) }
				</ProviderJourneyLink>
			) }
		</div>
	);
}
