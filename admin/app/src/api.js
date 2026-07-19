/**
 * REST client for the Beta admin. wp-api-fetch (enqueued as a dependency)
 * auto-configures the site REST root + wp_rest nonce; we also register the
 * bootstrap nonce explicitly for robustness.
 */
import apiFetch from '@wordpress/api-fetch';

const boot = window.ipLocationBlockBeta || {};
const ns = boot.restNamespace || 'ip-location-block/v1';
const settingsScope = boot.isNetwork ? 'network' : 'site';

if ( boot.nonce && apiFetch.createNonceMiddleware ) {
	apiFetch.use( apiFetch.createNonceMiddleware( boot.nonce ) );
}

export const getSettings = () =>
	apiFetch( {
		path: `/${ ns }/settings?scope=${ encodeURIComponent(
			settingsScope
		) }`,
	} );

export const saveSettings = ( data, scope = settingsScope ) =>
	apiFetch( {
		path: `/${ ns }/settings?scope=${ encodeURIComponent( scope ) }`,
		method: 'POST',
		data,
	} );

export const getDefaults = () =>
	apiFetch( { path: `/${ ns }/settings/defaults` } );

export const getSettingsContext = () =>
	apiFetch( {
		path: `/${ ns }/settings/context?scope=${ encodeURIComponent(
			settingsScope
		) }`,
	} );

export const exportSettings = ( settings ) =>
	apiFetch( {
		path: `/${ ns }/settings/export`,
		method: 'POST',
		data: { settings },
	} );

export const importSettings = ( data ) =>
	apiFetch( {
		path: `/${ ns }/settings/import`,
		method: 'POST',
		data: { data },
	} );

export const getLegacySettings = () =>
	apiFetch( { path: `/${ ns }/settings/legacy` } );

export const generateEmergencyLoginLink = ( scope = settingsScope ) =>
	apiFetch( {
		path: `/${ ns }/emergency-login-link?scope=${ encodeURIComponent(
			scope
		) }`,
		method: 'POST',
	} );

export const deleteEmergencyLoginLink = ( scope = settingsScope ) =>
	apiFetch( {
		path: `/${ ns }/emergency-login-link?scope=${ encodeURIComponent(
			scope
		) }`,
		method: 'DELETE',
	} );

export const updateDatabase = () =>
	apiFetch( { path: `/${ ns }/database/update`, method: 'POST' } );

export const getProviders = () => apiFetch( { path: `/${ ns }/providers` } );

export const getProviderStatus = () =>
	apiFetch( { path: `/${ ns }/providers/status` } );

export const testProvider = ( provider, credential = '' ) =>
	apiFetch( {
		path: `/${ ns }/providers/test`,
		method: 'POST',
		data: { provider, credential },
	} );

export const getDatabaseStatus = () =>
	apiFetch( { path: `/${ ns }/database/status` } );

export const getContent = () => apiFetch( { path: `/${ ns }/content` } );

export const getExceptions = () => apiFetch( { path: `/${ ns }/exceptions` } );

export const getDetectedExceptions = ( target ) =>
	apiFetch( {
		path: `/${ ns }/exceptions/detected?target=${ encodeURIComponent(
			target
		) }`,
	} );

export const getStatistics = () => apiFetch( { path: `/${ ns }/statistics` } );

export const clearStatistics = () =>
	apiFetch( { path: `/${ ns }/statistics`, method: 'DELETE' } );

export const getLogStatistics = () =>
	apiFetch( { path: `/${ ns }/statistics/logs` } );

export const getCache = () => apiFetch( { path: `/${ ns }/cache` } );

export const clearCache = () =>
	apiFetch( { path: `/${ ns }/cache`, method: 'DELETE' } );

export const eraseCacheEntries = ( hashes ) =>
	apiFetch( {
		path: `/${ ns }/cache/entries`,
		method: 'DELETE',
		data: { hashes },
	} );

export const getLogs = ( hook ) =>
	apiFetch( {
		path:
			`/${ ns }/logs` +
			( hook ? `?hook=${ encodeURIComponent( hook ) }` : '' ),
	} );

export const updateLiveLogs = ( action, hook ) =>
	apiFetch( {
		path: `/${ ns }/logs/live`,
		method: 'POST',
		data: { action, hook },
	} );

export const resetLiveLogs = () =>
	apiFetch( { path: `/${ ns }/logs/live/reset`, method: 'POST' } );

export const clearLogs = ( hook ) =>
	apiFetch( {
		path:
			`/${ ns }/logs` +
			( hook ? `?hook=${ encodeURIComponent( hook ) }` : '' ),
		method: 'DELETE',
	} );

export const eraseLogEntries = ( ips ) =>
	apiFetch( {
		path: `/${ ns }/logs/entries`,
		method: 'DELETE',
		data: { ips },
	} );

export const addToList = ( list, values, type = 'ip' ) =>
	apiFetch( {
		path: `/${ ns }/list`,
		method: 'POST',
		data: { list, type, values },
	} );

export const searchIp = ( ip, provider ) =>
	apiFetch( {
		path: `/${ ns }/geolocation/search`,
		method: 'POST',
		data: { ip, provider },
	} );

// Multi-provider search (additive): returns { results: [ { provider, result } ] }.
export const searchIpMulti = ( ip, providers ) =>
	apiFetch( {
		path: `/${ ns }/geolocation/search`,
		method: 'POST',
		data: { ip, providers },
	} );

export const getNetworkStats = ( duration = 0 ) =>
	apiFetch( {
		path: `/${ ns }/network/stats?duration=${ encodeURIComponent(
			duration
		) }`,
	} );

export const scanCountry = ( source = 'client' ) =>
	apiFetch( {
		path: `/${ ns }/geolocation/scan?source=${ encodeURIComponent(
			source
		) }`,
	} );

export const getMode = () => apiFetch( { path: `/${ ns }/geolocation/mode` } );

export const getDiagnostics = () =>
	apiFetch( { path: `/${ ns }/diagnostics` } );

export const setDiagnosticAcknowledgement = ( id, acknowledged ) =>
	apiFetch( {
		path: `/${ ns }/diagnostics/acknowledgements`,
		method: 'POST',
		data: { id, acknowledged },
	} );

export const getDiagnosticEnvironment = () =>
	apiFetch( { path: `/${ ns }/diagnostics/environment` } );

export const diagnoseDatabaseTables = () =>
	apiFetch( {
		path: `/${ ns }/diagnostics/database-tables`,
		method: 'POST',
	} );

export const getAttributions = () =>
	apiFetch( { path: `/${ ns }/attributions` } );

export const applyPreset = ( preset ) =>
	apiFetch( {
		path: `/${ ns }/settings/preset`,
		method: 'POST',
		data: { preset },
	} );
