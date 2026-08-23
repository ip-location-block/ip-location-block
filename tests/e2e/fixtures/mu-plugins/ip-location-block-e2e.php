<?php
/**
 * Plugin Name: IP Location Block E2E Helper
 * Description: Allows token-authenticated Playwright requests to provide a test IP address.
 *
 * This fixture is copied into an isolated QA site's mu-plugins directory. It is
 * excluded from release packages and remains inert unless ILB_E2E_ENABLED is true.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ILB_E2E_ENABLED' ) || true !== ILB_E2E_ENABLED ) {
	return;
}

if ( ! defined( 'IP_LOCATION_BLOCK_DEBUG' ) ) {
	define( 'IP_LOCATION_BLOCK_DEBUG', true );
}

/**
 * Return whether this request carries the per-run E2E token.
 *
 * @return bool
 */
function ip_location_block_e2e_is_authorized() {
	$provided_token = isset( $_SERVER['HTTP_X_ILB_TEST_TOKEN'] )
		? (string) $_SERVER['HTTP_X_ILB_TEST_TOKEN']
		: '';
	$token_file     = getenv( 'ILB_E2E_TOKEN_FILE' ) ?: '/run/secrets/ilb_e2e_token';

	if ( '' === $provided_token || ! is_readable( $token_file ) ) {
		return false;
	}

	$expected_token = trim( (string) file_get_contents( $token_file ) );

	return '' !== $expected_token && hash_equals( $expected_token, $provided_token );
}

// This health check runs before normal plugins. It remains reachable even when
// the seeded pre-upgrade settings intentionally block every public request.
if ( isset( $_GET['ilb-e2e-health'] )
	&& '1' === (string) $_GET['ilb-e2e-health']
	&& ip_location_block_e2e_is_authorized() ) {
	header( 'X-ILB-E2E-QA: enabled' );
	header( 'Content-Type: text/plain; charset=utf-8' );
	status_header( 200 );
	echo 'IP Location Block isolated E2E site';
	exit;
}

add_filter(
	'wp_headers',
	static function ( $headers ) {
		$headers['X-ILB-E2E-QA'] = 'enabled';

		return $headers;
	},
	PHP_INT_MAX
);

add_filter(
	'ip-location-block-ip-addr',
	static function ( $ip_address ) {
		$candidate = isset( $_SERVER['HTTP_X_ILB_TEST_IP'] )
			? trim( (string) $_SERVER['HTTP_X_ILB_TEST_IP'] )
			: '';

		if ( '' === $candidate || ! ip_location_block_e2e_is_authorized() ) {
			return $ip_address;
		}

		return false !== filter_var( $candidate, FILTER_VALIDATE_IP )
			? $candidate
			: $ip_address;
	},
	PHP_INT_MAX
);

// Keep the authenticated QA administrator reachable while testing restrictive
// legacy settings. Both the opt-in header and the secret token are required.
$ip_location_block_e2e_admin_bypass = static function ( $validate ) {
	if ( isset( $_SERVER['HTTP_X_ILB_TEST_ADMIN'] )
		&& '1' === (string) $_SERVER['HTTP_X_ILB_TEST_ADMIN']
		&& ip_location_block_e2e_is_authorized()
		&& is_array( $validate ) ) {
		$validate['result'] = 'passed';
	}

	return $validate;
};

add_filter( 'ip-location-block-admin', $ip_location_block_e2e_admin_bypass, PHP_INT_MAX, 2 );
add_filter( 'ip-location-block-login', $ip_location_block_e2e_admin_bypass, PHP_INT_MAX, 2 );
