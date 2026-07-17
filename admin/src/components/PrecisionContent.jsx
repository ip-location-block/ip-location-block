/**
 * Shared copy + presentational pieces for the Standard vs Native precision
 * surfaces, used by both the section card (PrecisionUpsell) and the header
 * mode dropdown (ModeBadge) so wording, links and styling stay in sync.
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export const UPGRADE_URL =
	'https://iplocationblock.com/pricing/?utm_source=wordpress&utm_medium=site&utm_campaign=cloud';
export const PATTERNS_URL =
	'https://iplocationblock.com/codex/supported-geo-location-rule-formats/';

const Check = () => (
	<span className="ilb-check" aria-hidden="true">
		<span className="dashicons dashicons-yes" />
	</span>
);

/** The highlighted "what you get" card — the star of the upsell. */
export function NativeCard() {
	return (
		<div className="ilb-native-card">
			<div className="ilb-native-card__head">
				<span className="ilb-native-card__eyebrow">{ __( 'Native Mode', 'ip-location-block' ) }</span>
				<span className="ilb-recommended-pill">
					<span className="dashicons dashicons-star-filled" aria-hidden="true" />
					{ __( 'Recommended', 'ip-location-block' ) }
				</span>
			</div>
			<ul className="ilb-native-card__benefits">
				<li>
					<Check />
					<span>
						<strong>{ __( 'Country, city & state blocking', 'ip-location-block' ) }</strong> +{ ' ' }
						<a href={ PATTERNS_URL } target="_blank" rel="noreferrer">
							{ __( 'advanced patterns', 'ip-location-block' ) }
						</a>
					</span>
				</li>
				<li>
					<Check />
					<span>{ __( 'Improved data precision', 'ip-location-block' ) }</span>
				</li>
				<li>
					<Check />
					<span>{ __( 'Priority support (1–5 hr response)', 'ip-location-block' ) }</span>
				</li>
			</ul>
		</div>
	);
}

/** Muted "what you have now" strip. */
export function StandardStrip() {
	return (
		<p className="ilb-standard-strip">
			<span className="ilb-standard-strip__dot" aria-hidden="true" />
			{ __( 'Standard Mode (current): country blocking · 1–3 day support', 'ip-location-block' ) }
		</p>
	);
}

export function UpgradeNote() {
	return (
		<p className="ilb-precision__note">
			{ __(
				'Sign up for a key and enable the “IP Location Block” provider (and disable the others).',
				'ip-location-block'
			) }
		</p>
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
			<span className="dashicons dashicons-arrow-right-alt ilb-upgrade-btn__arrow" aria-hidden="true" />
		</Button>
	);
}
