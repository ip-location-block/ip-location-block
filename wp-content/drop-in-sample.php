<?php
/**
 * Drop-in for IP Location Block custom filters
 *
 * This file should be renamed to `drop-in.php` and placed in
 * `wp-content/uploads/ip-location-block/dropins/`.
 *
 * Since 1.4.0 the plugin internals are namespaced (IPLocationBlock\), but the
 * `IP_Location_Block` class used below IS the supported public API and is
 * guaranteed through all 1.x releases. Note: registering custom geolocation
 * providers (IP_Location_Block_Provider::register_addon) is no longer
 * supported as of 1.4.0; hook usage like the examples below is unaffected.
 * Integrators can hook the `ip-location-block-deprecated` action to audit
 * deprecated API usage.
 *
 * @package   IP_Location_Block
 * @author    darkog <dg@darkog.com>
 * @license   GPL-3.0
 * @see      http://iplocationblock.com/codex/my-custom-functions-in-functions-php-doesnt-work/
 * @see       https://iplocationblock.com/?codex-category=actions-and-filters
 * @example   Use `IP_Location_Block::add_filter()` instead of `add_filter()`
 */
class_exists( 'IP_Location_Block', FALSE ) or die;

/**
 * Enables some debug features on dashboard
 *
 */
// define( 'IP_LOCATION_BLOCK_DEBUG', true );

/**
 * Example: Returns "404 Not found" to hide login page.
 *
 * @param  int $code HTTP status code.
 * @return int modified HTTP status code.
 */
/* -- ADD `/` TO THE TOP OR END OF THIS LINE TO ACTIVATE THE FOLLOWINGS -- *
function my_login_status( $code ) {
	return 404;
}

IP_Location_Block::add_filter( 'ip-location-block-login-status', 'my_login_status', 10, 1 );
//*/

/**
 * Example: Change mode of recording log according to the target.
 *
 * @param  int    $mode 1:blocked 2:passed 3:unauth 4:auth 5:all
 * @param  string $hook 'comment', 'xmlrpc', 'login', 'admin', 'public'
 * @param  array  'ip', 'auth', 'code', 'result'
 * @return int    $mode modefied recording mode.
 */
/* -- ADD `/` TO THE TOP OR END OF THIS LINE TO ACTIVATE THE FOLLOWINGS -- *
function my_record_logs( $mode, $hook, $validate ) {
	// Countries where you want to supress recording logs.
	$whitelist = array(
		'JP',
	);

	// Suppress recording logs in case of whitelisted countries on public facing pages.
	if ( 'public' !== $hook || in_array( $validate['code'], $whitelist, TRUE ) ) {
		return 1; // Only when blocked
	}
	else {
		return 3; // Unauthenticated user
	}
}

IP_Location_Block::add_filter( 'ip-location-block-record-logs', 'my_record_logs', 10, 3 );
//*/
