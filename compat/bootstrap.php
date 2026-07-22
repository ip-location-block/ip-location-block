<?php
/**
 * Backward-compatibility bootstrap.
 *
 * Entry point for every legacy code path that still reaches for the old
 * `classes/class-ip-location-block-*.php` files (the frozen classic admin, the
 * deployed mu-plugin copies, uninstall.php, third-party direct requires). Each
 * of those files is now a 4-line tombstone that requires THIS file.
 *
 * Responsibilities (registers NO WordPress hooks):
 *   1. Define the three plugin constants when they are missing (notably
 *      IP_LOCATION_BLOCK_VERSION under WP_UNINSTALL_PLUGIN, where the main file
 *      never runs — this fixes the historic uninstall fatal in Opts).
 *   2. Load the composer autoloaders when the namespaced code is not yet
 *      reachable (uninstall / mu-plugin contexts).
 *   3. Register the legacy class aliases (compat/legacy-aliases.php).
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

defined( 'WPINC' ) || defined( 'WP_UNINSTALL_PLUGIN' ) || die;

// Run exactly once regardless of how many tombstones require us.
if ( defined( 'IP_LOCATION_BLOCK_COMPAT' ) ) {
	return;
}
define( 'IP_LOCATION_BLOCK_COMPAT', true );

$ip_location_block_root = dirname( __DIR__ );

/*----------------------------------------------------------------------------*
 * 1. Plugin constants (only when the main file has not already defined them).
 *----------------------------------------------------------------------------*/
if ( ! defined( 'IP_LOCATION_BLOCK_PATH' ) ) {
	define( 'IP_LOCATION_BLOCK_PATH', rtrim( $ip_location_block_root, '/\\' ) . '/' );
}

if ( ! defined( 'IP_LOCATION_BLOCK_BASE' ) ) {
	define( 'IP_LOCATION_BLOCK_BASE', basename( $ip_location_block_root ) . '/ip-location-block.php' );
}

if ( ! defined( 'IP_LOCATION_BLOCK_VERSION' ) ) {
	// Derive from the main plugin header so there is a single source of truth.
	$ip_location_block_ver  = '0';
	$ip_location_block_main = $ip_location_block_root . '/ip-location-block.php';
	if ( is_readable( $ip_location_block_main )
		&& preg_match( '/^[\s*]*Version:\s*(.+)$/mi', (string) file_get_contents( $ip_location_block_main ), $ip_location_block_m ) ) {
		$ip_location_block_ver = trim( $ip_location_block_m[1] );
	}
	define( 'IP_LOCATION_BLOCK_VERSION', $ip_location_block_ver );
	unset( $ip_location_block_ver, $ip_location_block_main, $ip_location_block_m );
}

/*----------------------------------------------------------------------------*
 * 2. Composer autoloaders (scoped vendor first, then the main autoloader).
 *    Only needed when the namespaced code is not yet reachable — e.g. the
 *    uninstall or mu-plugin contexts, where the main file's own autoloader
 *    block never ran. require_once makes the normal-page-load case a no-op.
 *----------------------------------------------------------------------------*/
if ( ! class_exists( 'IPLocationBlock\\Support\\Util', false ) ) {
	$ip_location_block_scoped = $ip_location_block_root . '/vendor_prefixed/vendor/autoload.php';
	if ( file_exists( $ip_location_block_scoped ) ) {
		require_once $ip_location_block_scoped;
	}
	$ip_location_block_vendor = $ip_location_block_root . '/vendor/autoload.php';
	if ( file_exists( $ip_location_block_vendor ) ) {
		require_once $ip_location_block_vendor;
	}
	unset( $ip_location_block_scoped, $ip_location_block_vendor );
}

unset( $ip_location_block_root );

/*----------------------------------------------------------------------------*
 * 3. Legacy class aliases.
 *----------------------------------------------------------------------------*/
require_once __DIR__ . '/legacy-aliases.php';
