<?php
/**
 * IP Location Block - Options
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

/**
 * Class IP_Location_Block_Opts
 */
class IP_Location_Block_Opts {

	const DEFAULT_REDIRECT_URL = 'https://blocked.iplocationblock.com/';

	/**
	 * Default values of option table to be cached into options database table.
	 * @var array
	 */
	private static $option_table = array(
		'version'              => IP_LOCATION_BLOCK_VERSION,// Version of this table (not package)
		'welcome'              => false, // @since 1.2.0
		// since version 1.0
		'providers'            => array(), // List of providers and API keys
		'comment'              => array(   // Message on the comment form
			'pos' => 0,       // Position (0:none, 1:top, 2:bottom)
			'msg' => null,    // Message text on comment form
		),
		'matching_rule'        => - 1,      // -1:neither, 0:white list, 1:black list
		'white_list'           => null,    // Comma separeted country code
		'black_list'           => 'ZZ',    // Comma separeted country code
		'timeout'              => 30,      // Timeout in second
		'response_code'        => 403,     // Response code
		'save_statistics'      => true,    // Record validation statistics
		'clean_uninstall'      => true,    // Remove all savings from DB
		'simulate'             => false,   // just simulate, never block
		// since version 1.1
		'cache_hold'           => true,    // Record IP address cache
		'cache_time'           => HOUR_IN_SECONDS, // @since 3.5
		// since version 3.0.0
		'cache_time_gc'        => 900,     // Cache garbage collection time
		'request_ua'           => null,    // since version 3.0.7
		// since version 1.2, 1.3
		'login_fails'          => - 1,      // Limited number of login attempts
		'validation'           => array(   // Action hook for validation
			'comment'   => false,   // Validate on comment post
			'login'     => 1,       // Validate on login
			'admin'     => 1,       // Validate on admin (1:country 2:ZEP)
			'ajax'      => 0,       // Validate on ajax/post (1:country 2:ZEP)
			'xmlrpc'    => 1,       // Validate on xmlrpc (1:country 2:close)
			'proxy'     => null,    // $_SERVER variables for IPs
			'reclogs'   => 1,       // 1:blocked 2:passed 3:unauth 4:auth 5:all 6:blocked/passed
			'postkey'   => 'action,comment,log,pwd,FILES', // Keys in $_POST, $_FILES
			// since version 1.3.1
			'maxlogs'   => 500,     // Max number of rows for validation logs
			'backup'    => null,    // Absolute path to directory for backup logs
			// since version 3.0.13
			'explogs'   => 7,       // expiration time for logs [days]
			// since version 2.1.0
			'plugins'   => 0,       // Validate on wp-content/plugins (1:country 2:ZEP)
			'themes'    => 0,       // Validate on wp-content/themes (1:country 2:ZEP)
			// since version 2.2.9
			'timing'    => 0,       // 0:init, 1:mu-plugins, 2:drop-in
			'recdays'   => 30,      // Number of days for validation statistics
			// since version 3.0.0
			'includes'  => 3,       // for wp-includes/
			'uploads'   => 3,       // for UPLOADS/uploads
			'languages' => 3,       // for WP_CONTENT_DIR/language
			'public'    => 0,       // Validate on public facing pages
			// since version 3.0.3
			'restapi'   => 3,       // for get_rest_url()
			'mimetype'  => 0,       // 0:disable, 1:white_list, 2:black_list
			// since version 3.0.18
			'metadata'  => false,
		),
		'update'               => array(   // Updating IP address DB
			'auto'  => true,    // Auto updating of DB files
			'retry' => 0,       // Number of retry to download
			'cycle' => 30,      // Updating cycle (days)
		),
		// since version 3.0.9, 3.0.17
		'priority'             => array( 0, PHP_INT_MAX ), // 0:high, 1:low
		// since version 2.2.0
		'anonymize'            => true,    // Anonymize IP address to hide privacy
		'signature'            => '../,/wp-config.php,/passwd', // malicious signature
		'extra_ips'            => array(   // Additional IP validation
			'white_list' => null,    // White list of IP addresses
			'black_list' => null,    // Black list of IP addresses
		),
		'IP2Location'          => array(   // IP2Location
			// since version 2.2.2
			'ipv4_path' => null,    // Path to IPv4 DB file
			'ipv6_path' => null,    // Path to IPv6 DB file
			// since version 2.2.1
			'ipv4_last' => 0,       // Last-Modified of DB file
			'ipv6_last' => 0,       // Last-Modified of DB file
		),
		// since version 3.0.8
		'GeoLite2'             => array(   // Maxmind
			'ip_path'  => null,    // GeoLite2 DB: Path
			'ip_last'  => null,    // GeoLite2 DB: Last-Modified
			'asn_path' => null,    // GeoLite2 ASN DB: Path
			'asn_last' => null,    // GeoLite2 ASN DB: Last-Modified
		),
		// since version 2.2.3
		'api_dir'              => null,    // Path to geo-location API
		// since version 2.2.5
		'exception'            => array(   // list of exceptional
			'plugins'   => array(), // for pliugins
			'themes'    => array(), // for themes
			// since version 3.0.0
			'admin'     => array(), // for wp-admin
			'public'    => array(   // for public facing pages
				'bbp-new-topic',
				'bbp-edit-topic',
				'bbp-new-reply',
				'bbp-edit-reply',
			),
			'includes'  => array(), // for wp-includes/
			'uploads'   => array(), // for UPLOADS/uploads
			'languages' => array(), // for wp-content/language
			// since version 3.0.3
			'restapi'   => array(), // for get_rest_url()
		),
		// since version 2.2.7
		'api_key'              => array(   // API key

		),
		// since version 2.2.8
		'login_action'         => array(      // Actions for wp-login.php
			'login'        => true,
			'register'     => true,
			'resetpass'    => true,
			'lostpassword' => true,
			'postpass'     => true,
		),
		// since version 3.0.0
		'response_msg'         => 'Sorry, your request cannot be accepted.', // message on blocking
		'redirect_uri'         => self::DEFAULT_REDIRECT_URL,   // redirection on blocking
		'network_wide'         => false,      // settings page on network dashboard
		'public'               => array(
			'matching_rule' => - 1,      // -1:follow, 0:white list, 1:black list
			'white_list'    => null,    // Comma separeted country code
			'black_list'    => 'ZZ',    // Comma separeted country code
			'target_rule'   => 0,       // 0:all requests, 1:specify the target
			'target_pages'  => array(), // blocking target of pages
			'target_posts'  => array(), // blocking target of post types
			'target_cates'  => array(), // blocking target of categories
			'target_tags'   => array(), // blocking target of tags
			'ua_list'       => "Google:HOST,bot:HOST,slurp:HOST\nspider:HOST,archive:HOST,*:FEED\nembed.ly:HOST,Twitterbot:US,Facebot:US",
			// since version 3.0.3
			'dnslkup'       => false,   // use DNS reverse lookup
			'response_code' => 307,     // better for AdSense
			'redirect_uri'  => self::DEFAULT_REDIRECT_URL,    // home
			'response_msg'  => 'Sorry, your request cannot be accepted.', // message on blocking
			// since version 3.0.10
			'behavior'      => false    // Bad behavior
		),
		// since version 3.0.3
		'mimetype'             => array(
			'white_list' => array(),
			// key and value
			'black_list' => "asp,aspx,cgi,exe,js,jsp,php,php3,php4,php5,pl,py,pht,phtml,html,htm,shtml,htaccess,sh,svg,gz,zip,rar,tar",
			// comma separated extension
			// since version 3.0.4
			'capability' => array( 'upload_files' ),
		),
		// since version 3.0.5
		'live_update'          => array(
			'in_memory' => 0,       // -1:unavailable, 0:file, 1:memory
		),
		// since version 3.0.10
		'behavior'             => array(
			'view' => 7,       // More than 7 page view in 5 seconds
			'time' => 5,       // More than 7 page view in 5 seconds
		),
		// since version 3.0.13
		'restrict_api'         => false,       // Do not send IP address to external APIs
		// since version 3.0.14
		'login_link'           => array(
			'link' => null,    // key of login link
			'hash' => null,    // hash of 'link'
		),
		// since version 3.0.18
		'monitor'              => array(
			'updated_option'     => false,
			'update_site_option' => false,
		),
		'metadata'             => array(
			'pre_update_option'      => array( 'siteurl', 'admin_email', 'users_can_register', 'default_role' ),
			// @since  0.2.0.0 `manage_options`
			'pre_update_site_option' => array( 'siteurl', 'admin_email', 'registration' ),
			// @since 3.0.0 `manage_network_options`
		),
		'use_asn'              => 0,
		'migrated_from_legacy' => false,
		'cache_compat_dismissed' => false,
	);

	/**
	 * Initialize the mime types
	 * @return array
	 */
	public static function get_default() {
		// https://developer.wordpress.org/reference/functions/wp_get_mime_types/
		// https://codex.wordpress.org/Uploading_Files#About_Uploading_Files_on_Dashboard
		self::$option_table['mimetype']['white_list'] = array(
			// Image formats.
			'jpg|jpeg|jpe'    => 'image/jpeg',
			'gif'             => 'image/gif',
			'png'             => 'image/png',
			'ico'             => 'image/x-icon',

			// Video formats.
			'wmv'             => 'video/x-ms-wmv',
			'avi'             => 'video/avi',
			'mov|qt'          => 'video/quicktime',
			'mpeg|mpg|mpe'    => 'video/mpeg',
			'mp4|m4v'         => 'video/mp4',
			'ogv'             => 'video/ogg',
			'3gp|3gpp'        => 'video/3gpp',
			'3g2|3gp2'        => 'video/3gpp2',

			// Audio formats.
			'mp3|m4a|m4b'     => 'audio/mpeg',
			'wav'             => 'audio/wav',
			'ogg|oga'         => 'audio/ogg',

			// Misc application formats.
			'pdf'             => 'application/pdf',
			'psd'             => 'application/octet-stream',

			// MS Office formats.
			'doc'             => 'application/msword',
			'pot|pps|ppt'     => 'application/vnd.ms-powerpoint',
			'xla|xls|xlt|xlw' => 'application/vnd.ms-excel',
			'docx'            => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xlsx'            => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'pptx'            => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'ppsx'            => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
			'odt'             => 'application/vnd.oasis.opendocument.text',
		);

		return self::$option_table;
	}


	/**
	 * Upgrade options table
	 */
	public static function upgrade() {

		$settings = IP_Location_Block::get_option();
		$version  = isset( $settings['version'] ) ? $settings['version'] : '';

		// Upgrade settings to 1.0.4 (Drop use_asn from provider array in favor of use_asn global setting)
		if ( version_compare( $version, '1.0.4' ) < 0 ) {
			if ( isset( $settings['Maxmind'] ) ) {
				$settings['use_asn'] = isset( $settings['Maxmind']['use_asn'] ) ? (int) $settings['Maxmind']['use_asn'] : 0;
				unset( $settings['Maxmind'] );
			}
			if ( isset( $settings['GeoLite2']['use_asn'] ) ) {
				unset( $settings['GeoLite2']['use_asn'] );
			}
		}

		// Upgrade the mu files
		if ( version_compare( $version, '1.1.1' ) < 0 ) {
			IP_Location_Block_Opts::upgrade_validation_timing_mu_plugin( $settings );
		}

		// Upgrade cache table
		if ( version_compare( $version, '1.2.0' ) < 0 ) {
			global $wpdb;
			$table = $wpdb->prefix . IP_Location_Block::CACHE_NAME;
			$wpdb->query( "ALTER TABLE $table ADD `state` VARCHAR(100) DEFAULT NULL AFTER `code`" );
			$wpdb->query( "ALTER TABLE $table ADD `city` VARCHAR(100) DEFAULT NULL AFTER `code`" );
			$table = $wpdb->prefix . IP_Location_Block_Logs::TABLE_LOGS;
			$wpdb->query( "ALTER TABLE $table ADD `state` VARCHAR(100) DEFAULT NULL AFTER `code`" );
			$wpdb->query( "ALTER TABLE $table ADD `city` VARCHAR(100) DEFAULT NULL AFTER `code`" );
		}

		// Upgrade SQLite
		if ( version_compare( $version, '1.2.1' ) < 0 ) {
			IP_Location_Block_Logs::reset_sqlite_db();
		}

		// Upgrade settings
		if ( version_compare( $version, '1.3.0' ) < 0 ) {
			if ( empty( $settings['public']['redirect_uri'] ) && ( $settings['public']['response_code'] >= 300 && $settings['public']['response_code'] <= 399 ) ) {
				$settings['public']['redirect_uri'] = self::DEFAULT_REDIRECT_URL;
			}
			if ( strpos( $settings['redirect_uri'], 'hmpg.net' ) !== false ) {
				$settings['redirect_uri'] = self::DEFAULT_REDIRECT_URL;
			}
			if ( strpos( $settings['public']['redirect_uri'], 'hmpg.net' ) !== false ) {
				$settings['public']['redirect_uri'] = self::DEFAULT_REDIRECT_URL;
			}
		}

		// Upgrade the mu files
		if ( version_compare( $version, '1.3.6' ) < 0 ) {
			IP_Location_Block_Opts::upgrade_validation_timing_mu_plugin( $settings );
		}

		// Strip WP-ZEP bit from stored settings (WP-ZEP deprecated)
		if ( version_compare( $version, '1.3.8' ) < 0 ) {
			foreach ( array( 'admin', 'ajax', 'plugins', 'themes' ) as $tmp ) {
				$settings['validation'][ $tmp ] &= ~2;
			}
		}

		// Update Settings
		$settings['version']    = IP_LOCATION_BLOCK_VERSION;
		$settings['request_ua'] = trim( str_replace( array( 'InfiniteWP' ), '', isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' ) );

		IP_Location_Block::update_option( $settings );

	}

	/**
	 * Convert old options from IP Location Block to IP Location Block
	 */
	public static function get_legacy_settings() {

		$settings = get_option( 'ip_geo_block_settings' );
		if ( empty( $settings ) ) {
			return null;
		}

		$default = IP_Location_Block::get_default();
		$version = $settings['version'];

		// refresh if it's too old
		if ( version_compare( $version, '2.0.0' ) < 0 ) {
			$settings = $default;
		}

		if ( version_compare( $version, '2.1.0' ) < 0 ) {
			foreach ( array( 'plugins', 'themes' ) as $tmp ) {
				$settings['validation'][ $tmp ] = $default['validation'][ $tmp ];
			}
		}

		if ( version_compare( $version, '2.2.0' ) < 0 ) {
			foreach ( array( 'anonymize', 'signature', 'extra_ips', 'rewrite' ) as $tmp ) {
				$settings[ $tmp ] = $default[ $tmp ];
			}

			foreach ( array( 'admin', 'ajax' ) as $tmp ) {
				if ( $settings['validation'][ $tmp ] == 2 ) {
					$settings['validation'][ $tmp ] = 3;
				} // WP-ZEP + Block by country
			}
		}

		if ( version_compare( $version, '2.2.1' ) < 0 ) {
			foreach ( array( 'Maxmind', 'IP2Location' ) as $tmp ) {
				$settings[ $tmp ] = $default[ $tmp ];
			}
		}

		if ( version_compare( $version, '2.2.2' ) < 0 ) {
			$tmp             = get_option( 'ip_geo_block_statistics' );
			$tmp['daystats'] = array();
			IP_Location_Block_Logs::record_stat( $tmp );
			delete_option( 'ip_geo_block_statistics' ); // @since 1.2.0

			foreach ( array( 'maxmind', 'ip2location' ) as $tmp ) {
				unset( $settings[ $tmp ] );
			}
		}

		if ( version_compare( $version, '2.2.3' ) < 0 ) {
			$settings['api_dir'] = $default['api_dir'];
		}

		if ( version_compare( $version, '2.2.5' ) < 0 ) {
			// https://wordpress.org/support/topic/compatibility-with-ag-custom-admin
			$arr = array();
			foreach ( explode( ',', $settings['signature'] ) as $tmp ) {
				$tmp = trim( $tmp );
				if ( 'wp-config.php' === $tmp || 'passwd' === $tmp ) {
					$tmp = '/' . $tmp;
				}
				array_push( $arr, $tmp );
			}
			$settings['signature'] = implode( ',', $arr );
			foreach ( array( 'plugins', 'themes' ) as $tmp ) {
				$settings['exception'][ $tmp ] = $default['exception'][ $tmp ];
			}
		}

		if ( version_compare( $version, '2.2.6' ) < 0 ) {
			$settings['signature']               = str_replace( " ", "\n", $settings['signature'] );
			$settings['extra_ips']['white_list'] = str_replace( " ", "\n", $settings['extra_ips']['white_list'] );
			$settings['extra_ips']['black_list'] = str_replace( " ", "\n", $settings['extra_ips']['black_list'] );

			foreach ( array( 'plugins', 'themes' ) as $tmp ) {
				$arr = array_keys( $settings['exception'][ $tmp ] );
				if ( ! empty( $arr ) && ! is_numeric( $arr[0] ) ) {
					$settings['exception'][ $tmp ] = $arr;
				}
			}
		}

		if ( version_compare( $version, '2.2.7' ) < 0 ) {
			$settings['api_key'] = $default['api_key'];
		}

		if ( version_compare( $version, '2.2.8' ) < 0 ) {
			$settings['login_action'] = $default['login_action'];
			// Block by country (register, lost password)
			if ( 2 === (int) $settings['validation']['login'] ) {
				$settings['login_action']['login'] = false;
			}
		}

		if ( version_compare( $version, '2.2.9' ) < 0 ) {
			$settings['validation']['timing']  = $default['validation']['timing'];
			$settings['validation']['recdays'] = $default['validation']['recdays'];
		}

		if ( version_compare( $version, '3.0.0' ) < 0 ) {
			foreach ( array( 'cache_time_gc', 'response_msg', 'redirect_uri', 'network_wide', 'public' ) as $tmp ) {
				$settings[ $tmp ] = $default[ $tmp ];
			}

			foreach ( array( 'public', 'includes', 'uploads', 'languages' ) as $tmp ) {
				$settings['validation'][ $tmp ] = $default['validation'][ $tmp ];
				$settings['rewrite'][ $tmp ]    = $default['rewrite'][ $tmp ];
				$settings['exception'][ $tmp ]  = $default['exception'][ $tmp ];
			}

			$settings['exception']['admin'] = $default['exception']['admin'];
		}

		if ( version_compare( $version, '3.0.1' ) < 0 ) {
			delete_transient( 'ip_geo_block_cache' );
		} // @since  0.2.8

		if ( version_compare( $version, '3.0.3' ) < 0 ) {
			$settings['exception']['restapi']      = $default['exception']['restapi'];
			$settings['validation']['restapi']     = $default['validation']['restapi'];
			$settings['validation']['mimetype']    = $default['validation']['mimetype'];
			$settings['public']['redirect_uri']    = $default['public']['redirect_uri'];
			$settings['public']['response_msg']    = $default['public']['response_msg'];
			$settings['public']['response_code']   = $default['public']['response_code'];
			$settings['public']['dnslkup']         = true;
			$settings['public']['ua_list']         = str_replace( '*:HOST=embed.ly', 'embed.ly:HOST', $settings['public']['ua_list'] );
			$settings['login_action']['resetpass'] = @$settings['login_action']['resetpasss'];
			$settings['mimetype']                  = $default['mimetype'];
			unset(
				$settings['rewrite']['public'], // unused @3.0.0
				$settings['rewrite']['content'], // unused @3.0.0
				$settings['login_action']['resetpasss']  // mis-spelled
			);
		}

		if ( version_compare( $version, '3.0.4.1' ) < 0 ) {
			if ( ! isset( $settings['Maxmind']['use_asn'] ) ) {
				$settings['Maxmind']['use_asn']     = 0; // disable
				$settings['Maxmind']['asn4_path']   = $default['Maxmind']['asn4_path'];
				$settings['Maxmind']['asn4_last']   = $default['Maxmind']['asn4_last'];
				$settings['Maxmind']['asn6_path']   = $default['Maxmind']['asn6_path'];
				$settings['Maxmind']['asn6_last']   = $default['Maxmind']['asn6_last'];
				$settings['mimetype']['capability'] = $default['mimetype']['capability'];
			}
		}

		if ( version_compare( $version, '3.0.5' ) < 0 ) {
			$settings['live_update'] = $default['live_update'];
		}

		if ( version_compare( $version, '3.0.8' ) < 0 ) {
			$settings['timeout']             = $default['timeout'];
			$settings['GeoLite2']            = $default['Geolite2'];
			$settings['GeoLite2']['use_asn'] = $settings['Maxmind']['use_asn'];
		}

		if ( version_compare( $version, '3.0.10' ) < 0 ) {
			$settings['behavior']           = $default['behavior'];
			$settings['public']['behavior'] = $default['public']['behavior'];
		}

		if ( version_compare( $version, '3.0.11' ) < 0 ) {
			// change the size of some database columns
			$settings['cache_hold'] = $default['cache_hold'];
			IP_Location_Block_Logs::delete_tables( 'ip_geo_block_cache' );
		}

		if ( version_compare( $version, '3.0.13' ) < 0 ) {
			$settings['validation']['maxlogs'] = $default['validation']['maxlogs'];
			$settings['validation']['explogs'] = $default['validation']['explogs'];
			$settings['restrict_api']          = $settings['anonymize'];
		}

		if ( version_compare( $version, '3.0.14' ) < 0 ) {
			$settings['login_link'] = $default['login_link'];
		}

		if ( version_compare( $version, '3.0.16' ) < 0 ) {
			$settings['simulate'] = $settings['public']['simulate'];
			unset( $settings['public']['simulate'] );
		}

		if ( version_compare( $version, '3.0.17' ) < 0 ) {
			$settings['priority'] = $default['priority'];
		}

		if ( version_compare( $version, '3.0.18' ) < 0 ) {
			$settings['validation']['metadata'] = $default['validation']['metadata'];
			$settings['monitor']                = $default['monitor'];
			$settings['metadata']               = $default['metadata'];
			IP_Location_Block::update_metadata( null );

			self::setup_validation_timing( $settings );
		}

		// IP Location Block.
		if ( isset( $settings['rewrite'] ) ) {
			unset( $settings['rewrite'] );
		}

		// Remove maxmind settings
		if ( isset( $settings['Maxmind'] ) ) {
			if ( isset( $settings['Maxmind']['use_asn'] ) ) {
				$settings['use_asn'] = (int) $settings['Maxmind']['use_asn'];
			} else {
				$settings['use_asn'] = 0;
			}
			unset( $settings['Maxmind'] );
		}

		// Reset databases
		if ( isset( $settings['GeoLite2'] ) || isset( $settings['IP2Location'] ) ) {
			// Reset GeoLite settings
			if ( isset( $settings['GeoLite2'] ) ) {
				$settings['GeoLite2'] = self::$option_table['GeoLite2'];
			}
			// Reset IP2Location settings
			if ( isset( $settings['IP2Location'] ) ) {
				$settings['IP2Location'] = self::$option_table['IP2Location'];
			}
		}

		return $settings;
	}

	/**
	 * Check if mu plugin exists
	 *
	 * @param  string  $prefix
	 *
	 * @return int
	 */
	public static function get_validation_timing( $prefix = '' ) {
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-file.php';
		$fs = IP_Location_Block_FS::init( __FUNCTION__ );

		return $fs->exists( WPMU_PLUGIN_DIR . '/' . $prefix . 'ip-location-block-mu.php' ) ? 1 : 0;
	}

	/**
	 * Activate / Deactivate Must-use plugin / Advanced cache
	 *
	 * @param  string  $prefix
	 *
	 * @return bool|string
	 */
	private static function remove_mu_plugin( $prefix = '' ) {
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-file.php';
		$fs = IP_Location_Block_FS::init( __FUNCTION__ );

		if ( $fs->exists( $src = WPMU_PLUGIN_DIR . '/' . $prefix . 'ip-location-block-mu.php' ) ) {
			return $fs->delete( $src ) ? true : $src;
		}

		return true;
	}

	/**
	 * Setup validation timing and replace mu plugin
	 *
	 * @param  null  $settings
	 * @param  string  $prefix
	 *
	 * @return bool|WP_Error
	 */
	public static function setup_validation_timing( $settings = null, $prefix = '' ) {

		switch ( $settings ? (int) $settings['validation']['timing'] : 0 ) {
			case 0: // init
				if ( true !== ( $src = self::remove_mu_plugin() ) ) {
					return new WP_Error( 404, sprintf( __( 'Unable to remove %s. Please check your file system permissions.', 'ip-location-block' ), '<code>' . $src . '</code>' ) );
				}
				break;

			case 1: // mu-plugins
				$src = IP_Location_Block_Util::slashit( IP_LOCATION_BLOCK_PATH ) . 'wp-content/mu-plugins/ip-location-block-mu.php';
				$dst = IP_Location_Block_Util::slashit( WPMU_PLUGIN_DIR ) . $prefix . 'ip-location-block-mu.php';

				require_once IP_Location_Block_Util::slashit( IP_LOCATION_BLOCK_PATH ) . 'classes/class-ip-location-block-file.php';

				$fs = IP_Location_Block_FS::init( __FUNCTION__ );

				if ( ! $fs->is_dir( WPMU_PLUGIN_DIR ) ) {
					$fs->mkdir( WPMU_PLUGIN_DIR );
				}

				if ( ! $fs->is_dir( WPMU_PLUGIN_DIR ) || ! $fs->copy( $src, $dst, true ) ) {
					return new WP_Error( 404, sprintf( __( 'Unable to write in %s. Please check your file system permissions.', 'ip-location-block' ), '<code>' . $src . '</code>' ) );
				}

				break;
		}

		return true;
	}


	/**
	 * Re-setup the validation timing mu-plugin
	 *
	 * @param $settings
	 * @param $prefix
	 *
	 * @return void
	 */
	public static function upgrade_validation_timing_mu_plugin( $settings = null, $prefix = '' ) {
		self::remove_mu_plugin( $prefix );
		self::setup_validation_timing( $settings, $prefix );
	}

}
