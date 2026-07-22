<?php
/**
 * @deprecated 1.4.0 Ported to IPLocationBlock\Providers\Local\Ip2LocationProvider.
 *
 * This file is a tombstone. The IP2Location provider now lives in src/ (PSR-4)
 * and resolves lookups through the SCOPED IP2Location library in
 * vendor_prefixed/. The legacy geo-object surface is served by the compat
 * adapter returned from IP_Location_Block_API::get_instance( 'IP2Location', … );
 * the IP_LOCATION_BLOCK_IP2LOC_* constants are re-declared in
 * compat/legacy-aliases.php. The bundled vendor/ tree was removed.
 *
 * @package IP_Location_Block
 */

defined( 'WPINC' ) || defined( 'WP_UNINSTALL_PLUGIN' ) || die;

require_once dirname( __DIR__, 3 ) . '/compat/bootstrap.php';
