/**
 * Pure provider helpers shared by the guided settings UI and its tests.
 */

const BLOCKING_QUOTA_STATES = new Set( [
	'exhausted',
	'rate_limited',
	'key_upgrade_required',
] );

export const quotaBlocksProvider = ( quota ) =>
	!! quota && BLOCKING_QUOTA_STATES.has( quota.status );

export const makeExclusiveProviderMap = (
	current = {},
	registeredNames = [],
	provider,
	credential = ''
) => {
	const next = { ...current };
	new Set( [ ...Object.keys( next ), ...registeredNames ] ).forEach(
		( name ) => {
			if ( name !== 'Cache' ) {
				next[ name ] = '';
			}
		}
	);
	next[ provider ] = credential || '@';
	return next;
};

export const providerNames = ( providers = [], status = null ) => [
	...new Set( [
		...providers.map( ( provider ) => provider.name ),
		...( status?.providers || [] ).map( ( provider ) => provider.name ),
	] ),
];

export const activeProviderStatuses = ( status = null ) =>
	( status?.providers || [] ).filter( ( provider ) => provider.active );

export const formatAllowance = (
	allowance,
	labels = { unlimited: 'Unlimited' }
) => {
	if ( ! allowance || ! allowance.total ) {
		return '—';
	}
	if ( allowance.total < 0 ) {
		return labels.unlimited;
	}
	const value = Number( allowance.total ).toLocaleString();
	return allowance.term ? `${ value } / ${ allowance.term }` : value;
};

export const quotaSummary = (
	quota,
	labels = {
		unavailable: 'Quota unavailable',
		unlimited: 'Unlimited',
		remaining: ( value ) => `${ value } remaining`,
	}
) => {
	if ( ! quota ) {
		return '';
	}
	if ( quota.status === 'unavailable' ) {
		return labels.unavailable;
	}
	if ( quota.unlimited || quota.status === 'unlimited' ) {
		return labels.unlimited;
	}
	if ( quota.total !== null && quota.total !== undefined ) {
		return labels.remaining( Number( quota.total ).toLocaleString() );
	}
	return '';
};
