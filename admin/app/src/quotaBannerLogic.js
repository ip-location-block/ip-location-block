/**
 * Pure helpers for the native-provider quota upsell banner.
 *
 * The banner shows a persistent, full-width warning whenever the native
 * provider's key reports a blocking status. It is dismissible PER INCIDENT:
 * the dismissal is stored client-side keyed by a fingerprint of the situation
 * (status + plan fields — the credential fingerprint is not exposed to the
 * browser), so a NEW incident re-shows the banner after an earlier dismissal.
 * `checkedAt` is deliberately excluded so the periodic quota refresh does not
 * re-show a banner the user already dismissed for the same incident.
 */

const STORAGE_KEY = 'ilbBetaQuotaBannerDismissed';

const BLOCKING_STATES = new Set( [
	'exhausted',
	'key_upgrade_required',
	'rate_limited',
] );

export const quotaBlocking = ( quota ) =>
	!! quota && BLOCKING_STATES.has( quota.status );

// A stable identity for the current blocking incident. Empty when nothing is
// blocking (so there is nothing to show or dismiss).
export const incidentKey = ( quota ) => {
	if ( ! quotaBlocking( quota ) ) {
		return '';
	}
	const part = ( value ) =>
		value === null || value === undefined ? '' : String( value );
	return [
		quota.status,
		part( quota.planName ),
		part( quota.limit ),
		part( quota.oneTime ),
		part( quota.total ),
		part( quota.recurring ),
	].join( '|' );
};

const readStore = ( storage ) => {
	try {
		return ( storage || window.localStorage ).getItem( STORAGE_KEY );
	} catch {
		return null;
	}
};

export const isDismissed = ( quota, storage ) => {
	const key = incidentKey( quota );
	if ( ! key ) {
		return true; // nothing to show === treated as already handled
	}
	return readStore( storage ) === key;
};

export const dismissQuotaBanner = ( quota, storage ) => {
	const key = incidentKey( quota );
	if ( ! key ) {
		return;
	}
	try {
		( storage || window.localStorage ).setItem( STORAGE_KEY, key );
	} catch {
		// storage unavailable — the banner just won't stay dismissed
	}
};

export const shouldShowBanner = ( quota, storage ) =>
	quotaBlocking( quota ) && ! isDismissed( quota, storage );
