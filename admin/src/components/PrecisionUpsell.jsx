/**
 * Standard -> Native precision upsell. Ports the classic status.php conversion
 * panel into the Geolocation API settings section. Three states driven by the
 * geolocation "mode":
 *   - native            : precision is on — reassure.
 *   - api on, not native : Standard Mode — tell them which providers to disable.
 *   - api off            : the free-user comparison + Upgrade CTA.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const UPGRADE_URL =
	'https://iplocationblock.com/pricing/?utm_source=wordpress&utm_medium=site&utm_campaign=cloud';
const PATTERNS_URL = 'https://iplocationblock.com/codex/supported-geo-location-rule-formats/';

const STANDARD = [
	__( 'Country blocking', 'ip-location-block' ),
	__( 'Normal data precision', 'ip-location-block' ),
	__( 'Normal support (1–3 day response)', 'ip-location-block' ),
];

export default function PrecisionUpsell( { mode } ) {
	if ( ! mode ) {
		return null; // still loading
	}

	// Native: precision already enabled.
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

	// API enabled but other providers are on -> Standard, tell them to disable.
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

	// Free user (native API not enabled) -> the comparison + Upgrade CTA.
	return (
		<div className="ilb-precision ilb-precision--upsell">
			<h4 className="ilb-precision__title">
				{ __( 'Unlock precision blocking (Native Mode)', 'ip-location-block' ) }
			</h4>
			<div className="ilb-precision__compare">
				<div className="ilb-precision__col">
					<h5>{ __( 'Standard Mode', 'ip-location-block' ) }</h5>
					<ul>
						{ STANDARD.map( ( t ) => (
							<li key={ t }>{ t }</li>
						) ) }
					</ul>
				</div>
				<div className="ilb-precision__col ilb-precision__col--native">
					<h5>{ __( 'Native Mode', 'ip-location-block' ) }</h5>
					<ul>
						<li>
							{ __( 'Country, city & state blocking', 'ip-location-block' ) } +{ ' ' }
							<a href={ PATTERNS_URL } target="_blank" rel="noreferrer">
								{ __( 'advanced patterns', 'ip-location-block' ) }
							</a>
						</li>
						<li>{ __( 'Improved data precision', 'ip-location-block' ) }</li>
						<li>{ __( 'Priority support (1–5 hr response)', 'ip-location-block' ) }</li>
					</ul>
				</div>
			</div>
			<p className="ilb-precision__note">
				{ __(
					'To upgrade to Native Mode, sign up for a key and enable the “IP Location Block” provider below (and disable the others).',
					'ip-location-block'
				) }
			</p>
			<Button variant="primary" href={ UPGRADE_URL } target="_blank" rel="noreferrer">
				{ __( 'Upgrade', 'ip-location-block' ) }
			</Button>
		</div>
	);
}
