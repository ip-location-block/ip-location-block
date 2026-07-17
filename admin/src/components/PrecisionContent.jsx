/**
 * Shared copy for the Standard vs Native precision surfaces, used by both the
 * section card (PrecisionUpsell) and the header mode dropdown (ModeBadge) so
 * the wording and links stay in sync.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export const UPGRADE_URL =
	'https://iplocationblock.com/pricing/?utm_source=wordpress&utm_medium=site&utm_campaign=cloud';
export const PATTERNS_URL =
	'https://iplocationblock.com/codex/supported-geo-location-rule-formats/';

export function StandardList() {
	return (
		<ul>
			<li>{ __( 'Country blocking', 'ip-location-block' ) }</li>
			<li>{ __( 'Normal data precision', 'ip-location-block' ) }</li>
			<li>{ __( 'Normal support (1–3 day response)', 'ip-location-block' ) }</li>
		</ul>
	);
}

export function NativeList() {
	return (
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
	);
}

export function UpgradeNote() {
	return (
		<p className="ilb-precision__note">
			{ __(
				'To upgrade to Native Mode, sign up for a key and enable the “IP Location Block” provider (and disable the others).',
				'ip-location-block'
			) }
		</p>
	);
}

export function UpgradeButton( props ) {
	return (
		<Button variant="primary" href={ UPGRADE_URL } target="_blank" rel="noreferrer" { ...props }>
			{ __( 'Upgrade', 'ip-location-block' ) }
		</Button>
	);
}
