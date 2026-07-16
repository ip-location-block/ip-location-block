/**
 * REST client for the Beta admin. wp-api-fetch (enqueued as a dependency)
 * auto-configures the site REST root + wp_rest nonce; we also register the
 * bootstrap nonce explicitly for robustness.
 */
import apiFetch from '@wordpress/api-fetch';

const boot = window.ipLocationBlockBeta || {};
const ns = boot.restNamespace || 'ip-location-block/v1';

if ( boot.nonce && apiFetch.createNonceMiddleware ) {
	apiFetch.use( apiFetch.createNonceMiddleware( boot.nonce ) );
}

export const getSettings = () => apiFetch( { path: `/${ ns }/settings` } );

export const saveSettings = ( data ) =>
	apiFetch( { path: `/${ ns }/settings`, method: 'POST', data } );

export const getDefaults = () => apiFetch( { path: `/${ ns }/settings/defaults` } );

export const updateDatabase = () =>
	apiFetch( { path: `/${ ns }/database/update`, method: 'POST' } );

export const getProviders = () => apiFetch( { path: `/${ ns }/providers` } );

export const getDatabaseStatus = () => apiFetch( { path: `/${ ns }/database/status` } );

export const getContent = () => apiFetch( { path: `/${ ns }/content` } );

export const getExceptions = () => apiFetch( { path: `/${ ns }/exceptions` } );

export const getStatistics = () => apiFetch( { path: `/${ ns }/statistics` } );

export const clearStatistics = () =>
	apiFetch( { path: `/${ ns }/statistics`, method: 'DELETE' } );

export const getLogs = ( hook ) =>
	apiFetch( {
		path: `/${ ns }/logs` + ( hook ? `?hook=${ encodeURIComponent( hook ) }` : '' ),
	} );

export const clearLogs = ( hook ) =>
	apiFetch( {
		path: `/${ ns }/logs` + ( hook ? `?hook=${ encodeURIComponent( hook ) }` : '' ),
		method: 'DELETE',
	} );

export const eraseLogEntries = ( ips ) =>
	apiFetch( { path: `/${ ns }/logs/entries`, method: 'DELETE', data: { ips } } );

export const addToList = ( list, values ) =>
	apiFetch( { path: `/${ ns }/list`, method: 'POST', data: { list, values } } );

export const searchIp = ( ip, provider ) =>
	apiFetch( {
		path: `/${ ns }/geolocation/search`,
		method: 'POST',
		data: { ip, provider },
	} );

export const getNetworkStats = ( duration = 0 ) =>
	apiFetch( {
		path: `/${ ns }/network/stats?duration=${ encodeURIComponent( duration ) }`,
	} );
