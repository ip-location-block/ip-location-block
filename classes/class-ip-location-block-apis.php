<?php
/**
 * @deprecated 1.4.0 The provider subsystem was redesigned under src/ (PSR-4).
 *
 * This file is a tombstone. The former contents — the provider registry
 * (IP_Location_Block_Provider), the base geolocation API
 * (IP_Location_Block_API), the inline remote providers, the IP address cache
 * (IP_Location_Block_API_Cache) and the API service-type constants — now live
 * in src/Providers, src/Geolocation and the compat facades. The legacy class
 * names are restored as real facade classes (classmapped in compat/) and the
 * constants are re-declared in compat/legacy-aliases.php.
 *
 * The uploads-dir addon scan and the `ip-location-block-api-dir` filter were
 * removed outright (the registry is now sealed; register_addon() is a
 * deprecated no-op).
 *
 * @package IP_Location_Block
 */

defined( 'WPINC' ) || defined( 'WP_UNINSTALL_PLUGIN' ) || die;

require_once dirname( __DIR__ ) . '/compat/bootstrap.php';
