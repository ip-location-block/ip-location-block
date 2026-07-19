<?php
/**
 * IP Location Block - System information (diagnostics support data)
 *
 * Supplies the REST Diagnostics environment report and the classic admin
 * debug info panel (the latter via the one-line delegate in
 * admin/legacy/includes/class-admin-ajax.php), so neither code path needs to
 * pull in the frozen classic admin from front-end-reachable code.
 *
 * @package IP_Location_Block
 */

namespace IPLocationBlock\Diagnostics;

use IPLocationBlock\Core\Validator;
use IPLocationBlock\Logging\Logs;
use IPLocationBlock\Support\Dns;
use IPLocationBlock\Support\FileSystem;
use IPLocationBlock\Support\Util;

class SystemInfo {

	public static function collect() {
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-lkup.php';
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-file.php';
		$fs = FileSystem::init( __FUNCTION__ );

		// DNS reverse lookup
		$key = microtime( true );
		$val = Dns::gethostbyaddr( '8.8.8.8' );
		$key = microtime( true ) - $key;

		// MySQL (supress WordPress error: Unknown system variable 'block_encryption_mode')
		$ver = $GLOBALS['wpdb']->get_var( 'SELECT @@GLOBAL.version' );
		$bem = $GLOBALS['wpdb']->get_var( 'SELECT @@GLOBAL.block_encryption_mode' ); // `aes-128-ecb` @since MySQL 5.6.17

		// Human readable size, Proces owner
		// https://gist.github.com/mehdichaouch/341a151dd5f469002a021c9396aa2615
		// https://secure.php.net/manual/function.get-current-user.php#57624
		// https://secure.php.net/manual/function.posix-getpwuid.php#82387
		$siz = array( 'B', 'K', 'M', 'G', 'T', 'P' );
		$usr = function_exists( 'posix_getpwuid' ) ? posix_getpwuid( posix_geteuid() ) : array( 'name' => getenv( 'USERNAME' ) );

		// Server, PHP, WordPress
		$tmp_d = Util::get_temp_dir();
		$res   = array_map( 'esc_html', array(
			'Server:'        => $_SERVER['SERVER_SOFTWARE'],
			'MySQL:'         => $ver . ( defined( 'IP_LOCATION_BLOCK_DEBUG' ) && IP_LOCATION_BLOCK_DEBUG && $bem ? ' (' . $bem . ')' : '' ),
			'PHP:'           => PHP_VERSION,
			'PHP SAPI:'      => php_sapi_name(),
			'Memory limit:'  => ini_get( 'memory_limit' ),
			'Peak usage:'    => @round( ( $m = memory_get_peak_usage() ) / pow( 1024, ( $i = floor( log( $m, 1024 ) ) ) ), 2 ) . $siz[ $i ],
			'WordPress:'     => $GLOBALS['wp_version'],
			'Multisite:'     => is_multisite() ? 'yes' : 'no',
			'File system:'   => $fs->get_method(),
			'Temp folder:'   => is_wp_error( $tmp_d ) ? $tmp_d->get_error_message() : $tmp_d,
			'Process owner:' => $usr['name'],
			'File owner:'    => get_current_user(), // Gets the name of the owner of the current PHP script
			'Umask:'         => sprintf( '%o', umask() ^ 511 /* 0777 */ ),
			'Zlib:'          => function_exists( 'gzopen' ) ? 'yes' : 'no',
			'ZipArchive:'    => class_exists( 'ZipArchive', false ) ? 'yes' : 'no',
			'PECL phar:'     => class_exists( 'PharData', false ) ? 'yes' : 'no',
			'BC Math:'       => ( extension_loaded( 'gmp' ) ? 'gmp ' : '' ) . ( function_exists( 'bcadd' ) ? 'yes' : 'no' ),
			'mb_strcut:'     => function_exists( 'mb_strcut' ) ? 'yes' : 'no', // @since PHP 4.0.6
			'OpenSSL:'       => defined( 'OPENSSL_RAW_DATA' ) ? 'yes' : 'no', // @since PHP 5.3.3
			'SQLite(PDO):'   => extension_loaded( 'pdo_sqlite' ) ? 'yes' : 'no',
			'DNS lookup:'    => ( '8.8.8.8' !== $val ? 'available' : 'n/a' ) . sprintf( ' [%.1f msec]', $key * 1000.0 ),
			'User agent:'    => $_SERVER['HTTP_USER_AGENT'],
		) );

		// Child and parent themes
		$activated = wp_get_theme(); // @since 3.4.0
		$res       += array( esc_html( $activated->get( 'Name' ) ) => esc_html( $activated->get( 'Version' ) ) );

		if ( $installed = $activated->get( 'Template' ) ) {
			$activated = wp_get_theme( $installed );
			$res       += array( esc_html( $activated->get( 'Name' ) ) => esc_html( $activated->get( 'Version' ) ) );
		}

		// Plugins
		$installed = get_plugins(); // @since 1.5.0
		$activated = get_site_option( 'active_sitewide_plugins' ); // @since  0.2.8.0
		! is_array( $activated ) and $activated = array();
		$activated = array_merge( $activated, array_fill_keys( get_option( 'active_plugins' ), true ) );

		foreach ( $installed as $key => $val ) {
			if ( isset( $activated[ $key ] ) ) {
				$res += array( esc_html( $val['Name'] ) => esc_html( $val['Version'] ) );
			}
		}

		// Blocked self requests
		$installed = array_reverse( Logs::search_logs( Validator::get_ip_address(), Validator::get_option() ) );
		foreach ( $installed as $val ) {
			if ( Validator::is_blocked( $val['result'] ) ) {
				// hide port and nonce
				$method = preg_replace( '/\[\d+\]/', '', $val['method'] );
				$method = preg_replace( '/(' . Validator::get_auth_key() . ')(?:=|%3D)([\w]+)/', '$1=...', $method );

				// add post data
				$query = array();
				foreach ( explode( ',', $val['data'] ) as $str ) {
					false !== strpos( $str, '=' ) and $query[] = $str;
				}

				if ( ! empty( $query ) ) {
					$method .= '(' . implode( ',', $query ) . ')';
				}

				$res += array(
					esc_html( Util::localdate( $val['time'], 'Y-m-d H:i:s' ) ) =>
						esc_html( str_pad( $val['result'], 8 ) . $method )
				);
			}
		}

		return $res;
	}

}
