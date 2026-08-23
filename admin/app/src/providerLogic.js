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

const providerMap = ( settings = {} ) =>
	settings.providers && ! Array.isArray( settings.providers )
		? settings.providers
		: {};

export const protectionEnabled = ( settings = {} ) =>
	Number( settings.matching_rule ) !== -1 ||
	Number( settings?.validation?.public ) % 2 === 1;

export const clearPublicProtection = ( value ) => {
	const numeric = Number( value ) || 0;
	return Math.abs( numeric ) % 2 === 1 ? numeric - 1 : numeric;
};

/**
 * Resolve the providers that are selected and ready in the current draft.
 * The REST status describes the saved settings, while provider metadata and
 * readyOverrides cover implicit local providers and newly verified drafts.
 *
 * @param {Object}      settings       Current settings draft.
 * @param {Array}       providers      Provider catalog.
 * @param {Object|null} status         Saved provider status.
 * @param {Object}      readyOverrides Readiness for verified draft providers.
 * @return {string[]} Names of ready providers in the draft.
 */
export const readyProviderNames = (
	settings = {},
	providers = [],
	status = null,
	readyOverrides = {}
) => {
	const map = providerMap( settings );
	const statuses = new Map(
		( status?.providers || [] ).map( ( item ) => [ item.name, item ] )
	);
	const metadata = new Map(
		providers.map( ( item ) => [ item.name, item ] )
	);
	const names = new Set( [
		...Object.keys( map ),
		...metadata.keys(),
		...statuses.keys(),
	] );

	return [ ...names ].filter( ( name ) => {
		if ( name === 'Cache' ) {
			return false;
		}

		const provider = metadata.get( name );
		const providerStatus = statuses.get( name );
		const selected = Object.prototype.hasOwnProperty.call( map, name )
			? !! map[ name ]
			: !! ( provider?.selected || providerStatus?.active );
		if ( ! selected ) {
			return false;
		}
		if ( Object.prototype.hasOwnProperty.call( readyOverrides, name ) ) {
			return !! readyOverrides[ name ];
		}
		return !! (
			providerStatus?.ready ||
			( provider?.local && provider.databaseReady )
		);
	} );
};

export const providerDisconnectImpact = (
	settings = {},
	providers = [],
	status = null,
	readyOverrides = {},
	provider = ''
) => {
	const fallbackNames = readyProviderNames(
		settings,
		providers,
		status,
		readyOverrides
	).filter( ( name ) => name !== provider );

	return {
		provider,
		fallbackNames,
		disablesProtection: fallbackNames.length === 0,
		protectionWasEnabled: protectionEnabled( settings ),
	};
};

/**
 * Stage a provider disconnect without persisting it. Only provider and
 * protection fields are touched; every stored rule remains intact.
 *
 * @param {Object} settings Current settings draft.
 * @param {string} provider Provider to disconnect.
 * @param {Object} impact   Calculated disconnect consequences.
 * @return {{next: Object, snapshot: Object}} Staged settings and undo data.
 */
export const stageProviderDisconnect = (
	settings = {},
	provider = '',
	impact = { disablesProtection: true }
) => {
	const currentProviders = providerMap( settings );
	const snapshot = {
		providers: { ...currentProviders },
		matchingRule: settings.matching_rule,
		validationPublic: settings?.validation?.public,
	};
	const next = {
		...settings,
		providers: { ...currentProviders, [ provider ]: '' },
	};

	if ( impact.disablesProtection ) {
		next.matching_rule = -1;
		next.validation = {
			...( settings.validation || {} ),
			public: clearPublicProtection( settings?.validation?.public ),
		};
	}

	return { next, snapshot };
};

export const restoreProviderDisconnect = ( settings = {}, snapshot = {} ) => ( {
	...settings,
	providers: { ...( snapshot.providers || {} ) },
	matching_rule: snapshot.matchingRule,
	validation: {
		...( settings.validation || {} ),
		public: snapshot.validationPublic,
	},
} );

export const formatAllowance = (
	allowance,
	labels = { unavailable: 'Not available', unlimited: 'Unlimited' }
) => {
	if ( ! allowance || ! allowance.total ) {
		return labels.unavailable || 'Not available';
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
