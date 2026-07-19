import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export const UPGRADE_URL =
	'https://iplocationblock.com/pricing/?utm_source=wordpress&utm_medium=site&utm_campaign=cloud';
export const PATTERNS_URL =
	'https://iplocationblock.com/codex/supported-geo-location-rule-formats/';

export function PrecisionBenefits( { compact = false } ) {
	return (
		<ul
			className={ `ilb-precision-benefits${
				compact ? ' is-compact' : ''
			}` }
		>
			<li>{ __( 'City and state rules', 'ip-location-block' ) }</li>
			<li>{ __( 'More precise location data', 'ip-location-block' ) }</li>
			<li>{ __( 'Priority support', 'ip-location-block' ) }</li>
		</ul>
	);
}

export function PrecisionLearnLink() {
	return (
		<a
			href={ PATTERNS_URL }
			target="_blank"
			rel="noreferrer"
			className="ilb-precision-learn"
		>
			{ __( 'Learn about advanced rules', 'ip-location-block' ) }
		</a>
	);
}

export function UpgradeButton( { className = '', ...props } ) {
	return (
		<Button
			variant="primary"
			href={ UPGRADE_URL }
			target="_blank"
			rel="noreferrer"
			className={ `ilb-upgrade-btn ${ className }`.trim() }
			{ ...props }
		>
			{ __( 'Upgrade to Native', 'ip-location-block' ) }
		</Button>
	);
}
