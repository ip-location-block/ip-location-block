/**
 * Pure helpers for the Search tab: which providers are offered, the default
 * anonymize state, and IP masking that mirrors PHP Util::anonymize_ip (strict).
 */

/**
 * Only providers currently selected/configured in settings are offered. Local
 * providers whose database file is missing are still listed but disabled with a
 * reason, so the picker never silently omits a configured provider.
 *
 * @param {Array} list The /providers payload.
 * @return {Array} [{ name, disabled, reason }]
 */
export const configuredProviders = ( list = [] ) =>
	( list || [] )
		.filter( ( provider ) => provider && provider.selected )
		.map( ( provider ) => {
			const dbMissing =
				!! provider.local && provider.databaseReady === false;
			return {
				name: provider.name,
				disabled: dbMissing,
				reason: dbMissing ? 'database_missing' : '',
			};
		} );

// Default selection: every configured provider that can actually be queried.
export const defaultSelectedProviders = ( list = [] ) =>
	configuredProviders( list )
		.filter( ( provider ) => ! provider.disabled )
		.map( ( provider ) => provider.name );

// Mirror tab-geolocation.php:99-109 — the search preview masks by default when
// either privacy setting is on.
export const defaultAnonymize = ( settings ) =>
	!! ( settings && ( settings.anonymize || settings.restrict_api ) );

/**
 * Mask an IP for display, replicating PHP Util::anonymize_ip( $ip, true ):
 * the final group of hex/`*` characters (with any leading colon) becomes `***`.
 *
 * @param {string} value The IP address to mask.
 * @return {string} The masked value.
 */
export const maskIp = ( value ) => {
	if ( ! value ) {
		return value;
	}
	return String( value ).replace( /(:)*[0-9a-f*]{0,4}$/, '$1***' );
};
