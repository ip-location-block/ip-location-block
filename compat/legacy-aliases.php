<?php
/**
 * Legacy class aliases.
 *
 * Maps each old procedural class name onto its 1:1 namespaced port so that
 * existing integrations, the frozen classic admin, the deployed mu-plugin
 * copies and third-party code keep resolving the historic names. Every alias
 * is guarded so a real (not-yet-ported) legacy class of the same name always
 * wins — this phase only aliases the seven "support leaf" classes.
 *
 * class_alias() autoloads its target (the namespaced class), so requiring this
 * file eagerly loads the ports and runs their top-level side effects (e.g. the
 * IP_LOCATION_BLOCK_* constants defined in Logs).
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

defined( 'WPINC' ) || defined( 'WP_UNINSTALL_PLUGIN' ) || die;

$ip_location_block_aliases = array(
	// legacy name                => namespaced port
	'IP_Location_Block_Util'   => 'IPLocationBlock\\Support\\Util',
	'IP_Location_Block_FS'     => 'IPLocationBlock\\Support\\FileSystem',
	'IP_Location_Block_Lkup'   => 'IPLocationBlock\\Support\\Dns',
	'IP_Location_Block_Cron'   => 'IPLocationBlock\\Cron\\Scheduler',
	'IP_Location_Block_Opts'   => 'IPLocationBlock\\Settings\\Options',
	'IP_Location_Block_Logs'   => 'IPLocationBlock\\Logging\\Logs',
	'IP_Location_Block_Loader' => 'IPLocationBlock\\Core\\HookLoader',
);

foreach ( $ip_location_block_aliases as $ip_location_block_legacy => $ip_location_block_target ) {
	if ( ! class_exists( $ip_location_block_legacy, false ) ) {
		class_alias( $ip_location_block_target, $ip_location_block_legacy );
	}
}

unset( $ip_location_block_aliases, $ip_location_block_legacy, $ip_location_block_target );

/*----------------------------------------------------------------------------*
 * Legacy constants.
 *
 * The provider subsystem redesign (phase 3) tombstoned classes/class-ip-location
 * -block-apis.php and the two db-provider files, which used to define these on
 * every request. The classic admin and the REST layer still read them via
 * defined()/concatenation, so they are re-declared here (eager, once per
 * request) to preserve the historic contract. Facade class names are NOT
 * aliased — IP_Location_Block_Provider / _API / _API_Cache are real classes
 * (classmapped in compat/). Per-provider legacy class names
 * (IP_Location_Block_API_ipinfoio, …) are intentionally NOT aliased either —
 * IP_Location_Block_API::get_instance() covers all real usage.
 *----------------------------------------------------------------------------*/

// Service type flags (were defined at the top of apis.php).
defined( 'IP_LOCATION_BLOCK_API_TYPE_IPV4' ) || define( 'IP_LOCATION_BLOCK_API_TYPE_IPV4', 1 );
defined( 'IP_LOCATION_BLOCK_API_TYPE_IPV6' ) || define( 'IP_LOCATION_BLOCK_API_TYPE_IPV6', 2 );
defined( 'IP_LOCATION_BLOCK_API_TYPE_BOTH' ) || define( 'IP_LOCATION_BLOCK_API_TYPE_BOTH', 3 );

// IP2Location database URLs and paths (were defined in the db-provider file).
defined( 'IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT' ) || define( 'IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT', 'IP2LOCATION-LITE-DB1.BIN' );
defined( 'IP_LOCATION_BLOCK_IP2LOC_IPV6_DAT' ) || define( 'IP_LOCATION_BLOCK_IP2LOC_IPV6_DAT', 'IP2LOCATION-LITE-DB1.IPV6.BIN' );
defined( 'IP_LOCATION_BLOCK_IP2LOC_IPV4_ZIP' ) || define( 'IP_LOCATION_BLOCK_IP2LOC_IPV4_ZIP', 'https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.BIN.ZIP' );
defined( 'IP_LOCATION_BLOCK_IP2LOC_IPV6_ZIP' ) || define( 'IP_LOCATION_BLOCK_IP2LOC_IPV6_ZIP', 'https://download.ip2location.com/lite/IP2LOCATION-LITE-DB1.IPV6.BIN.ZIP' );
defined( 'IP_LOCATION_BLOCK_IP2LOC_DOWNLOAD' ) || define( 'IP_LOCATION_BLOCK_IP2LOC_DOWNLOAD', 'https://lite.ip2location.com/database/ip-country' );

// GeoLite2 (MaxMind) database URLs and paths (were defined in the db-provider file).
defined( 'IP_LOCATION_BLOCK_GEOLITE2_DB_IP' ) || define( 'IP_LOCATION_BLOCK_GEOLITE2_DB_IP', 'GeoLite2-Country.mmdb' );
defined( 'IP_LOCATION_BLOCK_GEOLITE2_DB_ASN' ) || define( 'IP_LOCATION_BLOCK_GEOLITE2_DB_ASN', 'GeoLite2-ASN.mmdb' );
defined( 'IP_LOCATION_BLOCK_GEOLITE2_ZIP_IP' ) || define( 'IP_LOCATION_BLOCK_GEOLITE2_ZIP_IP', 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=%s&suffix=tar.gz' );
defined( 'IP_LOCATION_BLOCK_GEOLITE2_ZIP_ASN' ) || define( 'IP_LOCATION_BLOCK_GEOLITE2_ZIP_ASN', 'https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-ASN&license_key=%s&suffix=tar.gz' );
defined( 'IP_LOCATION_BLOCK_GEOLITE2_DOWNLOAD' ) || define( 'IP_LOCATION_BLOCK_GEOLITE2_DOWNLOAD', 'https://dev.maxmind.com/geoip/geoip2/geolite2/' );
