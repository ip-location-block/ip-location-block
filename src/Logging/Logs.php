<?php
/**
 * IP Location Block - Handling validation log
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

namespace IPLocationBlock\Logging;

use IPLocationBlock\Support\FileSystem;
use IPLocationBlock\Support\Util;
use IPLocationBlock\Core\Validator;

// varchar can not be exceeded over 255 before MySQL-5.0.3.
define( 'IP_LOCATION_BLOCK_MAX_STR_LEN', 255 );
define( 'IP_LOCATION_BLOCK_MAX_BIN_LEN', 512 );

// Cipher mode and type
define( 'IP_LOCATION_BLOCK_CIPHER_MODE', 2 ); // 0:before 3.0.13, 1:mysql default, 2:openssl
define( 'IP_LOCATION_BLOCK_CIPHER_TYPE', 'AES-256-CBC' ); // for openssl

/**
 * Class Logs
 *
 * Ported 1:1 from IP_Location_Block_Logs (classes/class-ip-location-block-logs.php).
 * The legacy name is kept working via class_alias in compat/legacy-aliases.php.
 */
class Logs {

	const TABLE_LOGS = 'ip_location_block_logs';
	const TABLE_STAT = 'ip_location_block_stat';

	// Initial statistics data
	private static $default = array(
		'blocked'   => 0,
		'unknown'   => 0,
		'IPv4'      => 0,
		'IPv6'      => 0,
		'countries' => array(),
		'providers' => array(),
		'daystats'  => array(),
	);

	// Cipher mode and key
	private static $cipher = array();

	// SQLite for Live update
	/**
	 * The PDO instance
	 * @var PDO|null
	 */
	private static $pdo = null;
	/**
	 * The pdo statement instance
	 * @var PDOStatement|null|false
	 */
	private static $stm = null;

	/**
	 * Create
	 *
	 * @internal creating mixed storage engine may cause troubles with some plugins.
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate(); // @since 3.5.0

		// for logs
		$table = $wpdb->prefix . self::TABLE_LOGS;
		$sql   = "CREATE TABLE IF NOT EXISTS `$table` (
			`No` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`time` int(10) unsigned NOT NULL DEFAULT '0',
			`ip` varbinary(96) NOT NULL,
			`asn` varchar(8) NULL,
			`hook` varchar(8) NOT NULL,
			`auth` int(10) unsigned NOT NULL DEFAULT '0',
			`code` varchar(2) NOT NULL DEFAULT 'ZZ',
    		`city` varchar(100) NULL,
    		`state` varchar(100) NULL,
			`result` varchar(8) NULL,
			`method` varchar(" . IP_LOCATION_BLOCK_MAX_STR_LEN . ") NOT NULL,
			`user_agent` varchar(" . IP_LOCATION_BLOCK_MAX_STR_LEN . ") NULL,
			`headers` varbinary(" . IP_LOCATION_BLOCK_MAX_BIN_LEN . ") NULL,
			`data` text NULL,
			PRIMARY KEY  (`No`),
			KEY `time` (`time`),
			KEY `hook` (`hook`)
		) $charset_collate";
		$wpdb->query( $sql ) or self::error( __LINE__ );

		// for statistics
		$table = $wpdb->prefix . self::TABLE_STAT;
		$sql   = "CREATE TABLE IF NOT EXISTS `$table` (
			`No` tinyint(4) unsigned NOT NULL AUTO_INCREMENT,
			`data` longtext NULL,
			PRIMARY KEY  (`No`)
		) $charset_collate";
		$wpdb->query( $sql ) or self::error( __LINE__ );

		// Create 1 record if not exists
		$sql = $wpdb->prepare(
			"INSERT INTO `$table` (`No`, `data`) VALUES (%d, %s)
			ON DUPLICATE KEY UPDATE No = No", 1, serialize( self::$default )
		) and $wpdb->query( $sql );

		// for IP address cache
		$table = $wpdb->prefix . Validator::CACHE_NAME;
		$sql   = "CREATE TABLE IF NOT EXISTS `$table` (
			`No` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`time` int(10) unsigned NOT NULL DEFAULT '0',
			`hook` varchar(8) NOT NULL,
			`ip` varbinary(96) UNIQUE NOT NULL,
			`asn` varchar(8) NULL,
			`code` varchar(2) NOT NULL DEFAULT 'ZZ',
    		`city` varchar(100) NULL,
    		`state` varchar(100) NULL,
			`auth` int(10) unsigned NOT NULL DEFAULT '0',
			`fail` int(10) unsigned NOT NULL DEFAULT '0',
			`reqs` int(10) unsigned NOT NULL DEFAULT '0',
			`last` int(10) unsigned NOT NULL DEFAULT '0',
			`view` int(10) unsigned NOT NULL DEFAULT '0',
			`host` varbinary(512) NULL,
			PRIMARY KEY  (`No`)
		) $charset_collate";
		$wpdb->query( $sql ) or self::error( __LINE__ );

		return true;
	}

	/**
	 * Upgrade
	 *
	 * @param $version
	 */
	public static function upgrade( $version ) {
		// Future upgrade?
	}

	/**
	 * Encrypts / Decrypts a string.
	 *
	 * @link https://php.net/manual/en/function.openssl-encrypt.php#119346
	 * @link https://mysqlserverteam.com/understand-and-satisfy-your-aes-encryption-needs-with-5-6-17/
	 *
	 * @param int $mode 0:before 3.0.12, 1:mysql default, 2:openssl
	 *
	 * @return array actual mode and cipher key
	 */
	private static function cipher_mode_key( $mode = IP_LOCATION_BLOCK_CIPHER_MODE ) {
		$mode and $mode = defined( 'OPENSSL_RAW_DATA' ) ? $mode : 1; // @since PHP 5.3.3

		if ( empty( self::$cipher['mode'] ) ) {
			// openssl
			if ( 2 === $mode ) {
				// `openssl_random_pseudo_bytes()` can not be used as an IV because of search function
				self::$cipher['iv']  = md5( NONCE_KEY . NONCE_SALT, true ); // 16 bytes (128 bits)
				self::$cipher['key'] = Util::hash_hmac(
					function_exists( 'hash' ) ? 'sha256' /* 32 bytes (256 bits) */ : 'sha1' /* 20 bytes (160 bits) */,
					AUTH_KEY, AUTH_SALT, true
				);
			} // mysql default
			elseif ( 1 === $mode ) {
				self::$cipher['key'] = md5( NONCE_KEY . NONCE_SALT, true ); // 16 bytes (128 bits)
			} // before 3.0.12
			else {
				self::$cipher['key'] = NONCE_KEY;
			}
		}

		return self::$cipher['mode'] = $mode;
	}

	/**
	 * Decrypt
	 *
	 * @param $data
	 *
	 * @return false|string
	 */
	private static function decrypt( $data ) {
		return openssl_decrypt( $data, IP_LOCATION_BLOCK_CIPHER_TYPE, self::$cipher['key'], OPENSSL_RAW_DATA, self::$cipher['iv'] ); // @since PHP 5.3.0
	}

	/**
	 * Encrypt
	 *
	 * @param $data
	 *
	 * @return false|string
	 */
	private static function encrypt( $data ) {
		return openssl_encrypt( $data, IP_LOCATION_BLOCK_CIPHER_TYPE, self::$cipher['key'], OPENSSL_RAW_DATA, self::$cipher['iv'] ); // @since PHP 5.3.0
	}

	/**
	 * Encrypt ip
	 *
	 * @param $ip
	 *
	 * @return false|mixed|string
	 */
	private static function encrypt_ip( $ip ) {
		return isset( self::$cipher[ $ip ] ) ? self::$cipher[ $ip ] : self::$cipher[ $ip ] = self::encrypt( $ip );
	}

	/**
	 * Delete specific tables
	 *
	 * @param string $which
	 */
	public static function delete_tables( $which = 'all' ) {
		global $wpdb;
		$tables = array( self::TABLE_LOGS, self::TABLE_STAT, Validator::CACHE_NAME );

		foreach ( $tables as $table ) {
			if ( 'all' === $which || $table === $which ) {
				$table = $wpdb->prefix . $table;
				$wpdb->query( "DROP TABLE IF EXISTS `$table`" ) or self::error( __LINE__ );
			}
		}
	}

	/**
	 * Diagnose tables
	 */
	public static function diag_tables() {
		global $wpdb;
		$tables = array( self::TABLE_LOGS, self::TABLE_STAT, Validator::CACHE_NAME );

		foreach ( $tables as $table ) {
			$table = $wpdb->prefix . $table;
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
				self::error( __LINE__, sprintf( __( 'Creating a DB table %s had failed. Once de-activate this plugin, and then activate again.', 'ip-location-block' ), $table ) );

				return false;
			}

			$result = $wpdb->get_results( "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table'", ARRAY_A );
			foreach ( empty( $result ) ? array() : $result as $col ) {
				if ( in_array( $col['COLUMN_NAME'], array(
						'ip',
						'host'
					), true ) && 'varbinary' !== $col['DATA_TYPE'] ) {
					self::error( __LINE__, sprintf( __( 'Column type in %s unmatched. Once de-activate this plugin, and then activate again.', 'ip-location-block' ), $table ) );

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Clear log data
	 *
	 * @param null $hook
	 */
	public static function clear_logs( $hook = null ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_LOGS;

		if ( in_array( $hook, array( 'comment', 'login', 'admin', 'xmlrpc', 'public' ), true ) ) {
			$sql = $wpdb->prepare( "DELETE FROM `$table` WHERE `hook` = %s", $hook )
			and $wpdb->query( $sql ) or self::error( __LINE__ );
		} else {
			$wpdb->query( "TRUNCATE TABLE `$table`" ) or self::error( __LINE__ );
		}
	}

	/**
	 * Clear statistics data.
	 *
	 */
	public static function clear_stat() {
		self::record_stat( self::$default );
	}

	/**
	 * Restore statistics data.
	 * @return array|mixed
	 */
	public static function restore_stat() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_STAT;
		$data = $wpdb->get_results( "SELECT * FROM `$table`", ARRAY_A ) or self::error( __LINE__ );

		return empty( $data ) ? self::$default : unserialize( $data[0]['data'] ) + self::$default;
	}

	/**
	 * Record statistics data.
	 *
	 * @param $stat
	 */
	public static function record_stat( $stat ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_STAT;

		if ( ! is_array( $stat ) ) {
			$stat = self::$default;
		}

		$sql = $wpdb->prepare( "UPDATE `$table` SET `data` = %s", serialize( $stat ) )
		and $wpdb->query( $sql ) or self::error( __LINE__ );
	}

	/**
	 * Validate string whether utf8
	 *
	 * @param $str
	 *
	 * @return string|string[]|null
	 * @see  wp_check_invalid_utf8() in wp-includes/formatting.php
	 * @link https://core.trac.wordpress.org/browser/trunk/src/wp-includes/formatting.php
	 */
	private static function validate_utf8( $str ) {
		$str = (string) $str;
		if ( 0 === strlen( $str ) ) {
			return '';
		}

		// Store the site charset as a static to avoid multiple calls to get_option()
		static $is_utf8 = null;
		if ( $is_utf8 === null ) {
			$is_utf8 = array_key_exists(
				get_option( 'blog_charset' ),
				array( 'utf8' => null, 'utf-8' => null, 'UTF8' => null, 'UTF-8' => null )
			);
		}

		// handle utf8 only
		if ( ! $is_utf8 ) {
			return '…';
		}

		// Check support for utf8 in the installed PCRE library
		static $utf8_pcre = null;
		if ( $utf8_pcre === null ) {
			$utf8_pcre = preg_match( '/^./u', 'a' );
		}

		// if no support then reject $str for safety
		if ( ! $utf8_pcre ) {
			return '…';
		}

		// preg_match fails when it encounters invalid UTF8 in $str
		if ( 1 === preg_match( '/^./us', $str ) ) {
			// remove utf8mb4 4 bytes character
			// @see strip_invalid_text() in wp-includes/wp-db.php
			$regex = '/(?:\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})/';

			return preg_replace( $regex, '', $str );
		}

		return '…';
	}

	/**
	 * Truncate string as utf8
	 *
	 * @see https://jetpack.wp-a2z.org/oik_api/mbstring_binary_safe_encoding/
	 * @link https://core.trac.wordpress.org/browser/trunk/src/wp-includes/formatting.php
	 * @link https://core.trac.wordpress.org/browser/trunk/src/wp-includes/functions.php
	 *
	 * @param $str
	 * @param null $regexp
	 * @param string $replace
	 * @param int $len
	 *
	 * @return string|string[]|null
	 */
	private static function truncate_utf8( $str, $regexp = null, $replace = '', $len = IP_LOCATION_BLOCK_MAX_STR_LEN ) {
		// remove unnecessary characters
		$str = preg_replace( '/[\x00-\x1f\x7f]/', '', $str );
		if ( $regexp ) {
			$str = preg_replace( $regexp, $replace, $str );
		}

		// limit the length of the string
		if ( function_exists( 'mb_strcut' ) ) {
			mbstring_binary_safe_encoding(); // @since 3.7.0
			if ( strlen( $str ) > $len ) {
				$str = mb_strcut( $str, 0, $len ) . '…';
			}
			reset_mbstring_encoding(); // @since 3.7.0
		} else { // https://core.trac.wordpress.org/ticket/25259
			mbstring_binary_safe_encoding(); // @since 3.7.0
			$original = strlen( $str );
			$str      = substr( $str, 0, $len );
			$length   = strlen( $str );
			reset_mbstring_encoding(); // @since 3.7.0

			if ( $length !== $original ) {
				// bit pattern from seems_utf8() in wp-includes/formatting.php
				static $code = array(
					array( 0x80, 0x00 ), // 1byte  0bbbbbbb
					array( 0xE0, 0xC0 ), // 2bytes 110bbbbb
					array( 0xF0, 0xE0 ), // 3bytes 1110bbbb
					array( 0xF8, 0xF0 ), // 4bytes 11110bbb
					array( 0xFC, 0xF8 ), // 5bytes 111110bb
					array( 0xFE, 0xFC ), // 6bytes 1111110b
				);

				// truncate extra characters
				$len = min( $length, 6 );
				for ( $i = 0; $i < $len; ++ $i ) {
					$c = ord( $str[ $length - 1 - $i ] );
					for ( $j = $i; $j < 6; ++ $j ) {
						if ( ( $c & $code[ $j ][0] ) == $code[ $j ][1] ) {
							mbstring_binary_safe_encoding(); // @since 3.7.0
							$str = substr( $str, 0, $length - (int) ( $j > 0 ) - $i );
							reset_mbstring_encoding(); // @since 3.7.0

							// validate whole characters
							$str = self::validate_utf8( $str );

							return '…' !== $str ? $str . '…' : '…';
						}
					}
				}

				// $str may not fit utf8
				return '…';
			}
		}

		// validate whole characters
		return self::validate_utf8( $str );
	}

	/**
	 * Get data
	 *
	 * These data must be sanitized before rendering
	 */
	private static function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? self::truncate_utf8( $_SERVER['HTTP_USER_AGENT'] ) : '';
	}

	/**
	 * Return the http headers
	 *
	 * @param $anonymize
	 *
	 * @return string|string[]|null
	 */
	private static function get_http_headers( $anonymize ) {
		$exclusions = array(
			'HTTP_ACCEPT'          => true,
			'HTTP_ACCEPT_CHARSET'  => true,
			'HTTP_ACCEPT_ENCODING' => true,
			'HTTP_ACCEPT_LANGUAGE' => true,
			'HTTP_CACHE_CONTROL'   => true,
			'HTTP_CONNECTION'      => true,
			'HTTP_COOKIE'          => true,
			'HTTP_HOST'            => true,
			'HTTP_PRAGMA'          => true,
			'HTTP_USER_AGENT'      => true,
		);

		// select headers to hold in the logs
		$headers = array();
		foreach ( array_keys( $_SERVER ) as $key ) {
			if ( 'HTTP_' === substr( $key, 0, 5 ) && empty( $exclusions[ $key ] ) ) {
				$headers[] = $key . '=' . $_SERVER[ $key ];
			}
		}

		$headers = self::truncate_utf8( implode( ',', $headers ) );

		return $anonymize ? Util::anonymize_ip( $headers, false ) : $headers;
	}

	/**
	 * Return the post data
	 *
	 * @param $hook
	 * @param $validate
	 * @param $settings
	 *
	 * @return string|string[]|null
	 */
	private static function get_post_data( $hook, $validate, $settings ) {
		// condition of masking password
		$mask_pwd = ( Validator::is_passed( $validate['result'] ) || Validator::is_failed( $validate['result'] ) );

		// XML-RPC
		if ( 'xmlrpc' === $hook ) {
			$posts = self::truncate_utf8(
				file_get_contents( 'php://input' ), '!\s*([<>])\s*!', '$1'
			);

			// mask the password
			if ( $mask_pwd &&
			     preg_match_all( '/<string>(\S*?)<\/string>/', $posts, $matches ) >= 2 &&
			     strpos( $matches[1][1], home_url() ) !== 0 ) { // except pingback
				$posts = str_replace( $matches[1][1], '***', $posts );
			}

			return $posts;
		} // post data
		else {
			$keys  = explode( ',', $settings['validation']['postkey'] );
			$data  = array();
			$posts = $_POST;

			// uploading files
			if ( ! empty( $_FILES ) ) {
				$files_info     = sanitize_text_field( print_r( $_FILES, true ) );
				$posts['FILES'] = str_replace( PHP_EOL, ' ', $files_info );
				if ( ! in_array( 'FILES', $keys, true ) ) {
					array_push( $keys, 'FILES' );
				}
			}

			// mask the password
			if ( ! empty( $posts['pwd'] ) && $mask_pwd ) {
				$posts['pwd'] = '***';
			}

			// primarily: $_POST keys
			foreach ( $keys as $key ) {
				if ( array_key_exists( $key, $posts ) ) {
					if ( ! is_scalar( $posts[ $key ] ) ) {
						continue;
					}
					$value = sanitize_text_field( $posts[ $key ] );
					array_push( $data, $key . '=' . $value );
				}
			}

			// secondary: rest of the keys in $_POST
			foreach ( array_keys( $_POST ) as $key ) {
				if ( ! in_array( $key, $keys, true ) ) {
					$key = sanitize_key( $key );
					array_push( $data, $key );
				}
			}

			return self::truncate_utf8( implode( ',', $data ), '/\s+/', ' ' );
		}
	}

	/**
	 * Backup the validation log to text files
	 *
	 * $path should be absolute to the directory and should not be within the public_html.
	 *
	 * @param $hook
	 * @param $validate
	 * @param $method
	 * @param $agent
	 * @param $heads
	 * @param $posts
	 * @param $path
	 */
	private static function backup_logs( $hook, $validate, $method, $agent, $heads, $posts, $path ) {
		if ( validate_file( $path ) === 0 ) {
			file_put_contents(
				Util::slashit( $path ) . Validator::PLUGIN_NAME . date( '-Y-m' ) . '.log',
				sprintf( "%d,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s,%s\n",
					$_SERVER['REQUEST_TIME'],
					$validate['ip'],
					$validate['asn'],
					$hook,
					$validate['auth'],
					$validate['code'],
					$validate['city'],
					$validate['state'],
					$validate['result'],
					$method,
					str_replace( ',', '‚', $agent ), // &#044; --> &#130;
					str_replace( ',', '‚', $heads ), // &#044; --> &#130;
					str_replace( ',', '‚', $posts )  // &#044; --> &#130;
				),
				FILE_APPEND | LOCK_EX
			);
		}
	}

	/**
	 * Open and close sqlite database for live log
	 *
	 * The absolute path to the database can be set via filter hook `ip-location-block-live-log`.
	 *
	 * @see https://php.net/manual/en/pdo.connections.php
	 * @see https://php.net/manual/en/features.persistent-connections.php
	 * @see https://www.sqlite.org/sharedcache.html#shared_cache_and_in_memory_databases
	 *
	 * @param int $id ID of the blog
	 * @param bool $dsn data source name for PDO, TRUE for `in_memory`, FALSE for file
	 *
	 * @return PDO|WP_Error
	 */
	private static function open_sqlite_db( $id, $dsn = false ) {
		// For the sake of emergency, register the shutdown function
		self::$pdo = self::$stm = null;
		register_shutdown_function( array( self::class, 'close_sqlite_db' ) );

		// The temporary dir name
		$tmp_d = Util::get_temp_dir();
		if ( is_wp_error( $tmp_d ) ) {
			return $tmp_d;
		}

		// Set data source name
		$db_path = $dsn ? ':memory:' : ( $tmp_d . Validator::PLUGIN_NAME . "-" . $id . ".sqlite" );
		$id      = apply_filters( 'ip-location-block-live-log', $db_path );

		try {
			$pdo = new \PDO( 'sqlite:' . $id, null, null, array(
				\PDO::ATTR_PERSISTENT => (bool) $dsn, // https://www.sqlite.org/inmemorydb.html
				\PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_TIMEOUT    => 3, // reduce `SQLSTATE[HY000]: General error: 5 database is locked`
			) );
		} catch ( \PDOException $e ) {
			return new \WP_Error( 'Warn', $e->getMessage() );
		}

		$pdo->exec( "CREATE TABLE IF NOT EXISTS " . self::TABLE_LOGS . " (
			No INTEGER PRIMARY KEY AUTOINCREMENT,
			blog_id integer DEFAULT 1 NOT NULL,
			time bigint NOT NULL,
			ip varchar(40) NOT NULL,
			asn varchar(8) NULL,
			hook varchar(8) NOT NULL,
			auth integer DEFAULT 0 NOT NULL,
			code varchar(2) DEFAULT 'ZZ' NOT NULL,
			city varchar(100) NULL,
			state varchar(100) NULL,
			result varchar(8) NULL,
			method varchar(" . IP_LOCATION_BLOCK_MAX_STR_LEN . ") NOT NULL,
			user_agent varchar(" . IP_LOCATION_BLOCK_MAX_STR_LEN . ") NULL,
			headers varchar(" . IP_LOCATION_BLOCK_MAX_STR_LEN . ") NULL,
			data text NULL
		);" ); // int or FALSE

		return $pdo;
	}

	/**
	 * Close sql lite db
	 */
	public static function close_sqlite_db() {
		if ( self::$pdo && ! is_wp_error( self::$pdo ) ) {
			try {
				if ( self::$pdo->inTransaction() ) {
					@self::$pdo->rollBack(); // `@` is just for the exception without valid transaction
				}
			} catch ( \Exception $e ) {
				error_log('IP Location Block: '.$e->getMessage());
			}
			self::$stm = null;
			self::$pdo = null;
		}
	}

	/**
	 * Reset sql lite db
	 * @return bool
	 */
	public static function reset_sqlite_db() {
		$fs = FileSystem::init( __FUNCTION__ );

		$dir = Util::get_temp_dir();
		if ( is_wp_error( $dir ) ) {
			return true;
		}

		if ( false !== ( $files = $fs->dirlist( $dir ) ) ) {
			foreach ( array_keys( $files ) as $file ) {
				if ( false !== strpos( $file, Validator::PLUGIN_NAME ) ) {
					$fs->delete( $dir . $file );
				}
			}
		}

		return true;
	}

	/**
	 * Record the validation log
	 *
	 * This function record the user agent string and post data.
	 * The security policy for these data is as follows.
	 *
	 *   1. Record only utf8 under the condition that the site charset is utf8
	 *   2. Record by limiting the length of the string
	 *   3. Mask the password if it is authenticated
	 *
	 * @param string $hook type of log name
	 * @param array $validate validation results
	 * @param array $settings option settings
	 * @param bool $block
	 */
	public static function record_logs( $hook, $validate, $settings, $block = true ) {
		$record = $settings['validation'][ $hook ] ? apply_filters( 'ip-location-block-record-logs', (int) $settings['validation']['reclogs'], $hook, $validate ) : 0;
		$record = ( 1 === $record && $block ) || // blocked
		          ( 6 === $record && ( $block || Validator::is_blocked( Validator::validate_lookup_result( null, $validate, $settings ) ) ) ) || // blocked or qualified
		          ( 2 === $record && ! $block ) || // passed
		          ( 3 === $record && ! $validate['auth'] ) || // unauthenticated
		          ( 4 === $record && $validate['auth'] ) || // authenticated
		          ( 5 === $record ); // all

		// get data
		$agent  = self::get_user_agent();
		$heads  = self::get_http_headers( $settings['anonymize'] );
		$posts  = self::get_post_data( $hook, $validate, $settings );
		$method = Util::get_request_method();
		$port   = isset( $_SERVER['SERVER_PORT'] ) ? $_SERVER['SERVER_PORT'] : 'UNKNOWN_PORT';
		$method = $method . '[' . $port . ']:' . $_SERVER['REQUEST_URI'];

		// mark if any uploaded files exist
		if ( ! empty( $_FILES ) ) {
			$validate['result'] .= '^';
		}

		// anonymize ip address
		if ( $settings['anonymize'] ) {
			$validate['ip'] = Util::anonymize_ip( $validate['ip'] );
		}

		if ( $record ) {
			global $wpdb;
			$table = $wpdb->prefix . self::TABLE_LOGS;

			// limit the number of rows
			if ( 1 ):
				// Can't start transaction on the assumption that the storage engine is innoDB.
				// So there are some cases where logs are excessively deleted.
				$count = (int) $wpdb->get_var( "SELECT count(*) FROM `$table`" );
				$sql = $wpdb->prepare(
					"DELETE FROM `$table` ORDER BY `No` ASC LIMIT %d",
					max( 0, $count - (int) $settings['validation']['maxlogs'] + 1 )
				) and $wpdb->query( $sql ) or self::error( __LINE__ );
			else:
				// This SQL slows down on MySQL 5.7.23 and keeps +2 extra rows on MySQL 5.0.67
				// @see https://teratail.com/questions/14584
				$sql = $wpdb->prepare(
					"DELETE FROM `$table` WHERE NOT EXISTS(
					SELECT * FROM (
						SELECT * FROM `$table` t2 ORDER BY t2.time DESC LIMIT %d
					) t3 WHERE t3.No = $table.No 
				)",
					$settings['validation']['maxlogs'] - 1
				) and $wpdb->query( $sql ) or self::error( __LINE__ );
			endif;
			// insert into DB
			if ( 2 === self::cipher_mode_key() ) {
				$sql = $wpdb->prepare(
					"INSERT INTO `$table`
					(`time`, `ip`, `asn`, `hook`, `auth`, `code`, `city`, `state`, `result`, `method`, `user_agent`, `headers`, `data`)
					VALUES (%d, %s, %s, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s)",
					$_SERVER['REQUEST_TIME'],
					self::encrypt_ip( $validate['ip'] ),
					$validate['asn'],
					$hook,
					$validate['auth'],
					$validate['code'],
					$validate['city'],
					$validate['state'],
					$validate['result'],
					$method,
					$agent,
					self::encrypt( $heads ),
					$posts
				) and $wpdb->query( $sql ) or self::error( __LINE__ );
			} else {
				$sql = $wpdb->prepare(
					"INSERT INTO `$table`
					(`time`, `ip`, `asn`, `hook`, `auth`, `code`, `city`, `state`, `result`, `method`, `user_agent`, `headers`, `data`)
					VALUES (%d, AES_ENCRYPT(%s, %s), %s, %s, %d, %s, %s, %s, %s, %s, %s, AES_ENCRYPT(%s, %s), %s)",
					$_SERVER['REQUEST_TIME'],
					$validate['ip'],
					self::$cipher['key'],
					$validate['asn'],
					$hook,
					$validate['auth'],
					$validate['code'],
					$validate['city'],
					$validate['state'],
					$validate['result'],
					$method,
					$agent,
					$heads,
					self::$cipher['key'],
					$posts
				) and $wpdb->query( $sql ) or self::error( __LINE__ );
			}

			// backup logs to text files
			if ( $dir = apply_filters( 'ip-location-block-backup-dir', $settings['validation']['backup'], $hook ) ) {
				self::backup_logs( $hook, $validate, $method, $agent, $heads, $posts, $dir );
			}
		}

		if ( Validator::get_live_log() ) {
			// skip self command
			global $pagenow;

			$action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
			$cmd    = isset( $_POST['cmd'] ) ? sanitize_text_field( $_POST['cmd'] ) : '';

			if ( 'admin-ajax.php' === $pagenow && 'ip_location_block' === $action && 0 === strpos( $cmd, 'live-' ) ) {
				return;
			}

			// database file not available
			if ( is_wp_error( self::$pdo = self::open_sqlite_db( $id = get_current_blog_id(), $settings['live_update']['in_memory'] ) ) ) {
				self::error( __LINE__, self::$pdo->get_error_message() );

				return;
			}

			try {
				self::$stm = self::$pdo->prepare( // possibly throw an PDOException
					'INSERT INTO ' . self::TABLE_LOGS . ' (blog_id, time, ip, asn, hook, auth, code, city, state, result, method, user_agent, headers, data) ' .
					'VALUES      ' . ' (      ?,    ?,  ?,   ?,    ?,    ?,    ?,      ?,      ?,      ?,      ?,          ?,       ?,    ?);'
				); // example: https://php.net/manual/en/pdo.lobs.php
				self::$stm->bindParam( 1, $id, \PDO::PARAM_INT );
				self::$stm->bindParam( 2, $_SERVER['REQUEST_TIME'], \PDO::PARAM_INT );
				self::$stm->bindParam( 3, $validate['ip'], \PDO::PARAM_STR );
				self::$stm->bindParam( 4, $validate['asn'], \PDO::PARAM_STR );
				self::$stm->bindParam( 5, $hook, \PDO::PARAM_STR );
				self::$stm->bindParam( 6, $validate['auth'], \PDO::PARAM_INT );
				self::$stm->bindParam( 7, $validate['code'], \PDO::PARAM_STR );
				self::$stm->bindParam( 8, $validate['city'], \PDO::PARAM_STR );
				self::$stm->bindParam( 9, $validate['state'], \PDO::PARAM_STR );
				self::$stm->bindParam( 10, $validate['result'], \PDO::PARAM_STR );
				self::$stm->bindParam( 11, $method, \PDO::PARAM_STR );
				self::$stm->bindParam( 12, $agent, \PDO::PARAM_STR );
				self::$stm->bindParam( 13, $heads, \PDO::PARAM_STR );
				self::$stm->bindParam( 14, $posts, \PDO::PARAM_STR );
				self::$pdo->beginTransaction(); // possibly throw an PDOException
				self::$stm->execute();          // TRUE or FALSE
				self::$pdo->commit();           // possibly throw an PDOException
				self::$stm->closeCursor();      // TRUE or FALSE
			} catch ( \PDOException $e ) {
				if(self::$pdo->inTransaction()) {
					@self::$pdo->rollBack(); // `@` is just for the exception without valid transaction
				}
				self::error( __LINE__, $e->getMessage() );
			}

			self::$stm = null; // explicitly close the connection
			self::$pdo = null; // explicitly close the connection
		}
	}

	/**
	 * Restore the live log
	 *
	 * @note need to get transient before calling this function
	 *
	 * @param $hook
	 * @param $settings
	 *
	 * @return array|WP_Error
	 */
	public static function restore_live_log( $hook, $settings ) {
		if ( is_wp_error( self::$pdo = self::open_sqlite_db( $id = get_current_blog_id(), $settings['live_update']['in_memory'] ) ) ) {
			return new \WP_Error( 'Warn', self::$pdo->get_error_message() );
		}

		try {
			self::$pdo->beginTransaction(); // possibly throw an PDOException
			if ( self::$stm = self::$pdo->query( "SELECT No, hook, time, ip, code, city, state, result, asn, method, user_agent, headers, data FROM " . self::TABLE_LOGS . " WHERE blog_id = $id;" ) ) {
				$result = self::$stm->fetchAll( \PDO::FETCH_NUM ); // array or FALSE
				self::$pdo->exec( "DELETE FROM " . self::TABLE_LOGS . " WHERE blog_id = $id;" ); // int or FALSE
			}
			self::$pdo->commit();      // possibly throw an PDOException
			self::$stm->closeCursor(); // TRUE or FALSE
		} catch ( \PDOException $e ) {
			@self::$pdo->rollBack(); // `@` is just for the exception without valid transaction
			$result = new \WP_Error( 'Warn', __FILE__ . '(' . __LINE__ . ') ' . $e->getMessage() );
		}

		self::$stm = null; // explicitly close the connection
		self::$pdo = null; // explicitly close the connection

		return ! empty( $result ) ? $result : array();
	}

	/**
	 * Restore the validation log
	 *
	 * @param null $hook type of log name
	 * @param int $upgrade
	 *
	 * @return array log data
	 */
	public static function restore_logs( $hook = null, $upgrade = IP_LOCATION_BLOCK_CIPHER_MODE ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_LOGS;

		$mode = self::cipher_mode_key( $upgrade );

		if ( 2 === $mode ) { // openssl
			$sql = "SELECT `No`, `hook`, `time`, `ip`, `code`, `city`, `state`, `result`, `asn`, `method`, `user_agent`, `headers`, `data` FROM `$table`";
		} else { // mysql default
			$sql = $wpdb->prepare(
				"SELECT `No`, `hook`, `time`, `ip`, AES_DECRYPT(`ip`, %s), `code`, `city`, `state`, `result`, `asn`, `method`, `user_agent`, `headers`, AES_DECRYPT(`headers`, %s), `data` FROM `$table`",
				self::$cipher['key'], self::$cipher['key']
			);
		}

		if ( in_array( $hook, array( 'comment', 'login', 'admin', 'xmlrpc', 'public' ), true ) ) {
			$sql .= $wpdb->prepare( " WHERE `hook` = %s ORDER BY `time` DESC", $hook );
		} else {
			$sql .= " ORDER BY `time` DESC";
		} // " ORDER BY `hook`, `time` DESC";

		$result = $sql ? $wpdb->get_results( $sql, ARRAY_N ) : array();

		foreach ( $result as $key => $val ) {
			if ( 2 === $mode ) {
				$result[ $key ][3] = self::decrypt( $val[3] );
				$result[ $key ][11] = self::decrypt( $val[11] );
			} elseif ( ctype_print( $result[ $key ][3] ) ) {
				array_splice( $result[ $key ], 4, 1 ); // remove encrypted `ip`
				array_splice( $result[ $key ], 12, 1 ); // remove encrypted `headers`
			} else {
				array_splice( $result[ $key ], 3, 1 ); // keep decrypted `ip`
				array_splice( $result[ $key ], 11, 1 ); // keep decrypted `headers`
			}
		}

		return $result;
	}

	/**
	 * Search logs by specific IP address
	 *
	 * @param $ip
	 * @param $settings
	 *
	 * @return array|object|null
	 */
	public static function search_logs( $ip, $settings ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_LOGS;

		if ( $settings['anonymize'] ) {
			$ip = Util::anonymize_ip( $ip );
		}

		$mode = self::cipher_mode_key();

		if ( 2 === $mode ) { // openssl
			$sql = $wpdb->prepare(
				"SELECT `time`, `ip`, `asn`, `hook`, `auth`, `code`, `city`, `state`, `result`, `method`, `user_agent`, `headers`, `data` FROM `$table` WHERE `ip` = %s",
				self::encrypt_ip( $ip )
			) and $result = $wpdb->get_results( $sql, ARRAY_N ) or self::error( __LINE__ );
		} else { // mysql default
			$sql = $wpdb->prepare(
				"SELECT `time`, `ip`, AES_DECRYPT(`ip`, %s), `asn`, `hook`, `auth`, `code`, `city`, `state`, `result`, `method`, `user_agent`, `headers`, AES_DECRYPT(`headers`, %s), `data` FROM `$table` WHERE `ip` = AES_ENCRYPT(%s, %s)",
				self::$cipher['key'], self::$cipher['key'], $ip, self::$cipher['key']
			) and $result = $wpdb->get_results( $sql, ARRAY_N ) or self::error( __LINE__ );
		}

		foreach ( empty( $result ) ? array() : $result as $key => $val ) {
			if ( 2 === $mode ) {
				$result[ $key ][1] = self::decrypt( $val[1] );
				$result[ $key ][11] = self::decrypt( $val[11] );
			} elseif ( ctype_print( $result[ $key ][2] ) ) {
				array_splice( $result[ $key ], 3, 1 ); // remove encrypted `ip`
				array_splice( $result[ $key ], 12, 1 ); // remove encrypted `headers`
			} else {
				array_splice( $result[ $key ], 2, 1 ); // keep decrypted `ip`
				array_splice( $result[ $key ], 11, 1 ); // keep decrypted `headers`
			}

			$result[ $key ] = array_combine(
				array(
					'time',
					'ip',
					'asn',
					'hook',
					'auth',
					'code',
					'city',
					'state',
					'result',
					'method',
					'user_agent',
					'headers',
					'data'
				),
				$result[ $key ]
			);
		}

		return $result;
	}

	/**
	 * Delete entries with the specific IP address
	 *
	 * @param array $entry
	 *
	 * @return bool
	 */
	public static function delete_logs_entry( $entry = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_LOGS;

		$mode = self::cipher_mode_key();

		foreach ( array_unique( (array) $entry ) as $ip ) {
			if ( 2 === $mode ) {
				$sql = $wpdb->prepare( "DELETE FROM `$table` WHERE `ip` = %s OR `ip` = %s",
					self::encrypt_ip( $ip ), $ip
				) and $result = ( false !== $wpdb->query( $sql ) ) or self::error( __LINE__ );
			} else {
				$sql = $wpdb->prepare( "DELETE FROM `$table` WHERE `ip` = AES_ENCRYPT(%s, %s) OR `ip` = %s",
					$ip, self::$cipher['key'], $ip
				) and $result = ( false !== $wpdb->query( $sql ) ) or self::error( __LINE__ );
			}
		}

		return isset( $result ) ? $result : false;
	}

	/**
	 * Get logs for a specified duration in the past
	 *
	 * @param float|int $duration
	 *
	 * @return array|object|null
	 */
	public static function get_recent_logs( $duration = YEAR_IN_SECONDS ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_LOGS;

		$mode = self::cipher_mode_key();

		if ( 2 === $mode ) {
			$sql = $wpdb->prepare(
				"SELECT `time`, `ip`, `asn`, `hook`, `code`, `city`, `state`, `method`, `data` FROM `$table` WHERE `time` > %d",
				$_SERVER['REQUEST_TIME'] - $duration
			) and $result = $wpdb->get_results( $sql, ARRAY_N ) or self::error( __LINE__ );
		} else {
			$sql = $wpdb->prepare(
				"SELECT `time`, `ip`, AES_DECRYPT(`ip`, %s), `asn`, `hook`, `code`, `city`, `state`, `method`, `data` FROM `$table` WHERE `time` > %d",
				self::$cipher['key'], $_SERVER['REQUEST_TIME'] - $duration
			) and $result = $wpdb->get_results( $sql, ARRAY_N ) or self::error( __LINE__ );
		}

		$result = empty( $result ) ? array() : $result;
		foreach ( $result as $key => $val ) {
			if ( 2 === $mode ) {
				$result[ $key ][1] = self::decrypt( $val[1] );
			} elseif ( ctype_print( $result[ $key ][1] ) ) {
				array_splice( $result[ $key ], 2, 1 ); // remove encrypted `ip`
			} else {
				array_splice( $result[ $key ], 1, 1 ); // keep decrypted `ip`
			}

			$result[ $key ] = array_combine(
				array( 'time', 'ip', 'asn', 'hook', 'code', 'city', 'state', 'method', 'data' ),
				$result[ $key ]
			);
		}

		return $result;
	}

	/**
	 * Search blocked requests
	 *
	 * @param string $key 'method' or 'data'
	 * @param string $search search string
	 *
	 * @return array|object|null
	 */
	public static function search_blocked_logs( $key, $search ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_LOGS;

		self::cipher_mode_key();

		$sql = $wpdb->prepare(
			"SELECT `time`, `ip`, AES_DECRYPT(`ip`, %s), `asn`, `hook`, `code`, `method`, `data` FROM `$table` " .
			"WHERE `result` NOT LIKE '%%pass%%' AND `" . $key . "` LIKE '%%%s%%'", self::$cipher['key'], $search
		) and $result = $wpdb->get_results( $sql, ARRAY_N ) or self::error( __LINE__ );

		$result = empty( $result ) ? array() : $result;


		$params = array( 'time', 'ip', 'asn', 'hook', 'code', 'city', 'state', 'method', 'data' );

		foreach ( $result as $key => $val ) {
			if ( ctype_print( $result[ $key ][1] ) ) {
				array_splice( $result[ $key ], 2, 1 );
			} // remove encrypted `ip`
			else {
				array_splice( $result[ $key ], 1, 1 );
			} // keep decrypted `ip`

			if ( is_array( $result[ $key ] ) && ( count( $result[ $key ] ) !== count( $params ) ) ) {
				continue;
			}

			$result[ $key ] = array_combine(
				array( 'time', 'ip', 'asn', 'hook', 'code', 'city', 'state', 'method', 'data' ),
				$result[ $key ]
			);
		}

		return $result;
	}

	/**
	 * Update statistics.
	 *
	 * @param $hook
	 * @param $validate
	 * @param $settings
	 */
	public static function update_stat( $hook, $validate, $settings ) {
		// Restore statistics
		$stat = self::restore_stat();

		// Count provider
		$provider = isset( $validate['provider'] ) ? $validate['provider'] : 'Cache'; // asign `Cache` if no provider is available
		if ( empty( $stat['providers'][ $provider ] ) ) {
			$stat['providers'][ $provider ] = array( 'count' => 0, 'time' => 0.0 );
		}

		$stat['providers'][ $provider ]['count'] ++; // undefined in auth_fail()
		$stat['providers'][ $provider ]['time'] += (float) ( isset( $validate['time'] ) ? $validate['time'] : 0 );

		// Blocked by type of IP address
		if ( Validator::is_blocked( $validate['result'] ) ) {
			if ( filter_var( $validate['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				++ $stat['IPv4'];
			} elseif ( filter_var( $validate['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				++ $stat['IPv6'];
			}

			// Increment blocked
			++ $stat['blocked'];

			// Increment country stats
			if ( ! isset( $stat['countries'][ $validate['code'] ] ) ) {
				$stat['countries'][ $validate['code'] ] = 0;
			}
			$stat['countries'][ $validate['code'] ] ++;

			// Increment day stats
			$time = mktime( 0, 0, 0 );
			if ( ! isset( $stat['daystats'][ $time ][ $hook ] ) ) {
				$stat['daystats'][ $time ][ $hook ] = 0;
			}
			$stat['daystats'][ $time ][ $hook ] ++;
		}

		if ( count( $stat['daystats'] ) > max( 30, min( 365, (int) $settings['validation']['recdays'] ) ) ) {
			reset( $stat['daystats'] ); // pointer to the top
			unset( $stat['daystats'][ key( $stat['daystats'] ) ] ); // unset at the top
		}

		// Record statistics
		self::record_stat( $stat );
	}

	/**
	 * Clear IP address cache.
	 */
	public static function clear_cache() {
		global $wpdb;
		$table = $wpdb->prefix . Validator::CACHE_NAME;

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) ) {
			return;
		}

		$wpdb->query( "TRUNCATE TABLE `$table`" ) or self::error( __LINE__ );
	}

	/**
	 * Restore cache
	 * @return array|null
	 */
	public static function restore_cache() {
		global $wpdb;
		$table = $wpdb->prefix . Validator::CACHE_NAME;

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) ) {
			return null;
		}

		$mode = self::cipher_mode_key();

		if ( 2 === $mode ) {
			$sql = "SELECT `time`, `hook`, `ip`, `asn`, `code`, `city`, `state`, `auth`, `fail`, `reqs`, `last`, `view`, `host` FROM `$table`";
			$result = $wpdb->get_results( $sql, ARRAY_N ) or self::error( __LINE__ );
		} else {
			$sql = $wpdb->prepare(
				"SELECT `time`, `hook`, AES_DECRYPT(`ip`, %s), `asn`, `code`, `city`, `state`, `auth`, `fail`, `reqs`, `last`, `view`, AES_DECRYPT(`host`, %s), `ip` FROM `$table`",
				self::$cipher['key'], self::$cipher['key']
			) and $result = $wpdb->get_results( $sql, ARRAY_N ) or self::error( __LINE__ );
		}

		// transform DB to cache format
		$cache = $hash = array();
		foreach ( empty( $result ) ? array() : $result as $key => $val ) {
			if ( 2 === $mode ) {
				$val[13] = $val[2]; // `hash`
				$val[2]  = self::decrypt( $val[2] ); // `ip`
				$val[12] = self::decrypt( $val[12] ); // `host`
			}

			// make the encrypted `ip` hashed so that it can be specified as a key
			$val = array_combine( array(
				'time',
				'hook',
				'ip',
				'asn',
				'code',
				'city',
				'state',
				'auth',
				'fail',
				'reqs',
				'last',
				'view',
				'host',
				'hash'
			), $val );

			$val['hash'] = bin2hex( $val['hash'] );

			$ip = $val['ip'];
			unset( $val['ip'] );
			$cache[ $ip ] = $val;
		}

		// sort by 'time'
		foreach ( $cache as $key => $val ) {
			$hash[ $key ] = $val['time'];
		}

		array_multisort( $hash, SORT_DESC, $cache );

		return $cache;
	}

	/**
	 * Search cache by specific IP address
	 *
	 * @param $ip
	 *
	 * @return array|null
	 */
	public static function search_cache( $ip ) {
		global $wpdb;
		$table = $wpdb->prefix . Validator::CACHE_NAME;

		$mode = self::cipher_mode_key();

		if ( 2 === $mode ) {
			$sql = $wpdb->prepare(
				"SELECT `time`, `hook`, `asn`, `code`, `city`, `state`, `auth`, `fail`, `reqs`, `last`, `view`, `host` FROM `$table` " .
				"WHERE `ip` = %s", self::encrypt_ip( $ip )
			) and $result = $wpdb->get_results( $sql, ARRAY_N ) or self::error( __LINE__ );
		} else {
			$sql = $wpdb->prepare(
				"SELECT `time`, `hook`, `asn`, `code`, `city`, `state`, `auth`, `fail`, `reqs`, `last`, `view`, AES_DECRYPT(`host`, %s) FROM `$table` " .
				"WHERE `ip` = AES_ENCRYPT(%s, %s)", self::$cipher['key'], $ip, self::$cipher['key']
			) and $result = $wpdb->get_results( $sql, ARRAY_N ) or self::error( __LINE__ );
		}

		if ( ! empty( $result[0] ) ) {
			if ( 2 === $mode ) {
				$result[0][11] = self::decrypt( $result[0][11] );
			} // decrypt `host`

			$result[0]       = array_combine( array(
				'time',
				'hook',
				'asn',
				'code',
				'city',
				'state',
				'auth',
				'fail',
				'reqs',
				'last',
				'view',
				'host'
			), $result[0] );
			$result[0]['ip'] = $ip;

			return $result[0];
		} else {
			return null;
		}
	}

	/**
	 * Update cache
	 *
	 * @param $cache
	 */
	public static function update_cache( $cache ) {
		global $wpdb;
		$table = $wpdb->prefix . Validator::CACHE_NAME;

		if ( 2 === self::cipher_mode_key() ) {
			$sql = $wpdb->prepare(
				"INSERT INTO `$table`
				(`time`, `hook`, `ip`, `asn`, `code`, `city`, `state`, `auth`, `fail`, `reqs`, `last`, `view`, `host`)
				VALUES (%d, %s, %s, %s, %s, %s, %s, %d, %d, %d, %d, %d, %s)
				ON DUPLICATE KEY UPDATE
				`time` = VALUES(`time`),
				`hook` = VALUES(`hook`),
				`auth` = VALUES(`auth`),
				`code` = VALUES(`code`),
				`city` = VALUES(`city`),
				`state` = VALUES(`state`),
				`fail` = VALUES(`fail`),
				`reqs` = VALUES(`reqs`),
				`last` = VALUES(`last`),
				`view` = VALUES(`view`)",
				$cache['time'],
				$cache['hook'],
				self::encrypt_ip( $cache['ip'] ),
				$cache['asn'],
				$cache['code'],
				$cache['city'],
				$cache['state'],
				$cache['auth'],
				$cache['fail'],
				$cache['reqs'],
				$cache['last'],
				$cache['view'],
				self::encrypt( $cache['host'] )
			);
			$wpdb->query( $sql ) or self::error( __LINE__ );
		} else {
			$sql = $wpdb->prepare(
				"INSERT INTO `$table`
				(`time`, `hook`, `ip`, `asn`, `code`, `city`, `state`, `auth`, `fail`, `reqs`, `last`, `view`, `host`)
				VALUES (%d, %s, AES_ENCRYPT(%s, %s), %s, %s, %s, %s, %d, %d, %d, %d, %d, AES_ENCRYPT(%s, %s))
				ON DUPLICATE KEY UPDATE
				`time` = VALUES(`time`),
				`hook` = VALUES(`hook`),
				`auth` = VALUES(`auth`),
				`code` = VALUES(`code`),
				`city` = VALUES(`city`),
				`state` = VALUES(`state`),
				`fail` = VALUES(`fail`),
				`reqs` = VALUES(`reqs`),
				`last` = VALUES(`last`),
				`view` = VALUES(`view`)",
				$cache['time'],
				$cache['hook'],
				$cache['ip'],
				self::$cipher['key'],
				$cache['asn'],
				$cache['code'],
				$cache['city'],
				$cache['state'],
				$cache['auth'],
				$cache['fail'],
				$cache['reqs'],
				$cache['last'],
				$cache['view'],
				$cache['host'],
				self::$cipher['key']
			) and $wpdb->query( $sql ) or self::error( __LINE__ );
		}
	}

	/**
	 * Delete cache entry by IP address
	 *
	 * @param array $entry
	 *
	 * @return bool|void
	 */
	public static function delete_cache_entry( $entry = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . Validator::CACHE_NAME;

		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) ) {
			return;
		}

		$mode = self::cipher_mode_key();

		foreach ( empty( $entry ) ? array( Validator::get_ip_address() ) : $entry as $ip ) {
			// if specified ip address is anonymized, then extract the encripted value
			$ip = explode( ',', $ip );
			if ( empty( $ip[1] ) ) {
				$sql = $wpdb->prepare( "DELETE FROM `$table` WHERE `ip` = %s",
					2 === $mode ? self::encrypt_ip( $ip[0] ) : 'AES_ENCRYPT(' . $ip[0] . ', ' . self::$cipher['key'] . ')'
				);
			} else {
				$sql = $wpdb->prepare( "DELETE FROM `$table` WHERE `ip` = UNHEX(%s)", $ip[1] );
			}

			$sql and $result = ( false !== $wpdb->query( $sql ) ) or self::error( __LINE__ );
		}

		return isset( $result ) ? $result : false;
	}

	/**
	 * Delete expired cache
	 *
	 * @param $expired
	 */
	public static function delete_expired( $expired ) {
		global $wpdb;

		foreach ( array( self::TABLE_LOGS, Validator::CACHE_NAME ) as $key => $table ) {
			$table = $wpdb->prefix . $table;
			$sql = $wpdb->prepare( "DELETE FROM `$table` WHERE `time` < %d", $_SERVER['REQUEST_TIME'] - $expired[ $key ] )
			and false !== $wpdb->query( $sql ) or self::error( __LINE__ );
		}
	}

	/**
	 * SQL Error handling
	 *
	 * @param $line
	 * @param bool $msg
	 */
	private static function error( $line, $msg = false ) {
		if ( false === $msg ) {
			global $wpdb;
			$msg = $wpdb->last_error;
		}

		if ( $msg ) {
			error_log( $msg = __FILE__ . ' (' . $line . ') ' . $msg );
			// Contract-bound legacy identity — do not namespace: the frozen classic
			// admin (IP_Location_Block_Admin) exists only under its legacy name.
			if ( class_exists( 'IP_Location_Block_Admin', false ) ) {
				\IP_Location_Block_Admin::add_admin_notice( 'error', $msg );
			}
		}
	}

}
