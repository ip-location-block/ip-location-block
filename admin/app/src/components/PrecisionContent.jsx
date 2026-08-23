import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { betaUrl } from '../navigation';

export const UPGRADE_URL = 'https://iplocationblock.com/pricing/';
export const PATTERNS_URL =
	'https://iplocationblock.com/docs/blocking-rules/state-region/';

export const regionalUpgradeUrl = ( content = 'provider-card' ) => {
	const url = new window.URL( UPGRADE_URL );
	url.searchParams.set( 'utm_source', 'wordpress' );
	url.searchParams.set( 'utm_medium', 'site' );
	url.searchParams.set( 'utm_campaign', 'cloud' );
	url.searchParams.set( 'utm_content', content );
	return url.toString();
};

export const providerSetupUrl = () => {
	const url = new window.URL(
		betaUrl( { tab: 'settings', view: 'simple', section: null } )
	);
	url.hash = 'ilb-provider-setup';
	return url.toString();
};

export function RegionalBenefits() {
	return (
		<div
			className="ilb-regional-benefits"
			aria-label={ __( 'Benefits', 'ip-location-block' ) }
		>
			<span>{ __( 'Regional rules', 'ip-location-block' ) }</span>
			<span>{ __( 'IPv6 + ASN', 'ip-location-block' ) }</span>
			<span>{ __( 'Built for this plugin', 'ip-location-block' ) }</span>
		</div>
	);
}

export function ProviderJourneyLink( {
	children = __( 'See Native Mode', 'ip-location-block' ),
	className = '',
	onClick,
	...props
} ) {
	const openProviderJourney = ( event ) => {
		onClick?.( event );
		if (
			! event.defaultPrevented &&
			document.querySelector( '.ilb-settings' )
		) {
			event.preventDefault();
			window.dispatchEvent(
				new CustomEvent( 'ip-location-block-open-provider-setup' )
			);
		}
	};

	return (
		<a
			href={ providerSetupUrl() }
			className={ `ilb-provider-journey-link ${ className }`.trim() }
			onClick={ openProviderJourney }
			{ ...props }
		>
			{ children }
		</a>
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

export function UpgradeButton( {
	className = '',
	content = 'provider-card',
	children = __( 'Unlock regional blocking', 'ip-location-block' ),
	...props
} ) {
	return (
		<Button
			variant="primary"
			href={ regionalUpgradeUrl( content ) }
			target="_blank"
			rel="noreferrer"
			className={ `ilb-upgrade-btn ${ className }`.trim() }
			{ ...props }
		>
			{ children }
		</Button>
	);
}
