/**
 * Standard -> Native precision callout for the Geolocation API settings
 * section. States driven by the geolocation "mode":
 *   - native            : precision is on — reassure.
 *   - enforced          : native prioritized automatically alongside other
 *                         providers — reassure, no action needed.
 *   - api on, not native : Standard Mode — tell them which providers to disable.
 *   - api off            : the free-user comparison + Upgrade CTA.
 * Copy is shared with the header dropdown via PrecisionContent.
 */
import { __ } from '@wordpress/i18n';

import {
	PrecisionBenefits,
	PrecisionLearnLink,
	UpgradeButton,
} from './PrecisionContent';

export default function PrecisionUpsell( { mode } ) {
	if ( ! mode ) {
		return null; // still loading
	}

	if ( mode.native ) {
		return (
			<div className="ilb-precision-status ilb-precision-status--success">
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
							'City and state rules are available through the IP Location Block provider.',
							'ip-location-block'
						) }
					</p>
				</div>
			</div>
		);
	}

	if ( mode.enforced ) {
		return (
			<div className="ilb-precision-status ilb-precision-status--info">
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
							'City and state rules are enforced automatically. The IP Location Block provider is used first while precision rules exist; your other providers act as country-level fallback, so there is nothing to disable.',
							'ip-location-block'
						) }
					</p>
				</div>
			</div>
		);
	}

	if ( mode.apiEnabled && mode.others && mode.others.length ) {
		return (
			<div className="ilb-precision-status ilb-precision-status--warning">
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
							'To use Native Mode, disable these other geolocation providers:',
							'ip-location-block'
						) }{ ' ' }
						<em>{ mode.others.join( ', ' ) }</em>
					</p>
				</div>
			</div>
		);
	}

	return (
		<div className="ilb-precision-callout">
			<div className="ilb-precision-callout__content">
				<h4>
					{ __(
						'Need city or state blocking?',
						'ip-location-block'
					) }
				</h4>
				<p>
					{ __(
						'Native Mode adds precise city and state rules. Add an IP Location Block API key and turn off other geolocation providers to use it.',
						'ip-location-block'
					) }
				</p>
				<PrecisionBenefits />
			</div>
			<div className="ilb-precision-callout__actions">
				<UpgradeButton />
				<PrecisionLearnLink />
			</div>
		</div>
	);
}
