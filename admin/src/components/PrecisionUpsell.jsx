/**
 * Standard -> Native precision upsell card for the Geolocation API settings
 * section. Three states driven by the geolocation "mode":
 *   - native            : precision is on — reassure.
 *   - api on, not native : Standard Mode — tell them which providers to disable.
 *   - api off            : the free-user comparison + Upgrade CTA.
 * Copy is shared with the header dropdown via PrecisionContent.
 */
import { __ } from '@wordpress/i18n';

import { StandardList, NativeList, UpgradeNote, UpgradeButton } from './PrecisionContent';

export default function PrecisionUpsell( { mode } ) {
	if ( ! mode ) {
		return null; // still loading
	}

	if ( mode.native ) {
		return (
			<div className="ilb-precision ilb-precision--native">
				<span className="dashicons dashicons-yes-alt" aria-hidden="true" />
				<div>
					<strong>{ __( 'Native Mode active', 'ip-location-block' ) }</strong>
					<p>
						{ __(
							'Precision blocking by state and city is enabled through the IP Location Block provider.',
							'ip-location-block'
						) }
					</p>
				</div>
			</div>
		);
	}

	if ( mode.apiEnabled && mode.others && mode.others.length ) {
		return (
			<div className="ilb-precision ilb-precision--warn">
				<span className="dashicons dashicons-warning" aria-hidden="true" />
				<div>
					<strong>{ __( 'Standard Mode', 'ip-location-block' ) }</strong>
					<p>
						{ __(
							'Precision blocking by state/city will not work while other providers are enabled. To switch to Native Mode, disable:',
							'ip-location-block'
						) }{ ' ' }
						<em>{ mode.others.join( ', ' ) }</em>
					</p>
				</div>
			</div>
		);
	}

	return (
		<div className="ilb-precision ilb-precision--upsell">
			<h4 className="ilb-precision__title">
				{ __( 'Unlock precision blocking (Native Mode)', 'ip-location-block' ) }
			</h4>
			<div className="ilb-precision__compare">
				<div className="ilb-precision__col">
					<h5>{ __( 'Standard Mode', 'ip-location-block' ) }</h5>
					<StandardList />
				</div>
				<div className="ilb-precision__col ilb-precision__col--native">
					<h5>{ __( 'Native Mode', 'ip-location-block' ) }</h5>
					<NativeList />
				</div>
			</div>
			<UpgradeNote />
			<UpgradeButton />
		</div>
	);
}
