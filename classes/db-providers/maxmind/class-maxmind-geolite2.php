<?php
/**
 * @deprecated 1.4.0 Ported to IPLocationBlock\Providers\Local\GeoLite2Provider.
 *
 * This file is a tombstone. The GeoLite2 provider now lives in src/ (PSR-4) and
 * resolves lookups through the SCOPED GeoIp2/MaxMind libraries in
 * vendor_prefixed/. The legacy geo-object surface is served by the compat
 * adapter returned from IP_Location_Block_API::get_instance( 'GeoLite2', … ) (or
 * the 'Maxmind' alias); the IP_LOCATION_BLOCK_GEOLITE2_* constants are
 * re-declared in compat/legacy-aliases.php. The bundled vendor/ tree was
 * removed.
 *
 * @package IP_Location_Block
 */

defined( 'WPINC' ) || defined( 'WP_UNINSTALL_PLUGIN' ) || die;

require_once dirname( __DIR__, 3 ) . '/compat/bootstrap.php';
