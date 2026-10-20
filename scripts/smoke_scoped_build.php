<?php
/**
 * Smoke test for the scoped vendor tree. verify_scoped_build.sh runs it with
 * `php -n` (no php.ini, so normally no bcmath extension) to prove that:
 *   - no global bc*() polyfill is declared (1.4.0-1.4.2 shipped one that other
 *     plugins detected with function_exists() and that always threw);
 *   - the scoped phpseclib BigInteger finds its engines;
 *   - IPLocationBlock\Support\BcMath returns correct results either way.
 *
 * Usage: php -n scripts/smoke_scoped_build.php [plugin-dir]
 */

$dir = isset( $argv[1] ) ? $argv[1] : dirname( __DIR__ );
require $dir . '/vendor_prefixed/vendor/autoload.php';
require $dir . '/src/Support/BcMath.php';

$fail = static function ( $message ) {
	fwrite( STDERR, "smoke_scoped_build.php: $message\n" );
	exit( 1 );
};

if ( ! extension_loaded( 'bcmath' ) && function_exists( 'bccomp' ) ) {
	$fail( 'a global bccomp() polyfill is declared; other plugins would detect and call it.' );
}

try {
	new IPLocationBlock\Vendor\phpseclib3\Math\BigInteger( 1 );

	$checks = array(
		'comp' => array( IPLocationBlock\Support\BcMath::comp( '10', '100' ), -1 ),
		'pow'  => array( IPLocationBlock\Support\BcMath::pow( '2', '112' ), '5192296858534827628530496329220096' ),
		'add'  => array( IPLocationBlock\Support\BcMath::add( '340282366920938463463374607431768211455', '1', 0 ), '340282366920938463463374607431768211456' ),
	);
} catch ( Throwable $e ) {
	$fail( get_class( $e ) . ': ' . $e->getMessage() );
}

foreach ( $checks as $name => $check ) {
	if ( $check[0] !== $check[1] ) {
		$fail( "BcMath::$name() returned " . var_export( $check[0], true ) . ', expected ' . var_export( $check[1], true ) );
	}
}

echo 'OK: scoped BigInteger and BcMath work ' . ( extension_loaded( 'bcmath' ) ? 'with native bcmath' : 'without bcmath' ) . "; no global bc*() declared.\n";
