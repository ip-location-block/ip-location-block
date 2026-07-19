<?php
/**
 * IP Location Block - Admin class
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

// Test for restore_network()
define( 'TEST_RESTORE_NETWORK', false );
define( 'TEST_NETWORK_BLOG_COUNT', 30 );

/**
 * Class IP_Location_Block_Admin_Ajax
 */
class IP_Location_Block_Admin_Ajax {

	/**
	 * Admin ajax sub functions
	 *
	 * @param string $which name of the geolocation api provider (should be validated by whitelist)
	 *
	 * @return string[]
	 */
	public static function search_ip( $which ) {
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-lkup.php';

		$options = IP_Location_Block::get_option();

		// check format
		$ip  = isset( $_POST['ip'] ) ? sanitize_text_Field( trim( $_POST['ip'] ) ) : '';
		$geo = null;
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			// get option settings and compose request headers
			$tmp = IP_Location_Block::get_request_headers( $options );

			// create object for provider and get location
			if ( $geo = IP_Location_Block_API::get_instance( $which, $options ) ) {
				// gather geo details
				$start          = microtime( true );
				$res            = $geo->get_location( $ip, $tmp );
				$res['geoTime'] = sprintf( '%.1f [msec]', ( microtime( true ) - $start ) * 1000.0 );
				// gather asn details
				if ( $geo->supports( 'asn' ) ) {
					$start = microtime( true );
					$inf   = $geo->get_location( $ip, array( 'asn' => true ) );
					$tmp   = microtime( true ) - $start;
					if ( ! empty( $inf['asn'] ) ) {
						$res['asn']     = esc_html( $inf['asn'] );
						$res['asnTime'] = sprintf( '%.1f [msec]', $tmp * 1000.0 );
					}
				}
				// gather host dns
				$tmp            = microtime( true );
				$res['dns']     = esc_html( IP_Location_Block_Lkup::gethostbyaddr( $ip ) );
				$res['dnsTime'] = sprintf( '%.1f [msec]', ( microtime( true ) - $tmp ) * 1000.0 );

			} else {
				$res = array( 'errorMessage' => 'Unknown service.' );
			}
		} else {
			$res = array( 'errorMessage' => 'Invalid IP address.' );
		}

		return $res;
	}

	/**
	 * Get country code from providers
	 *
	 * @param array $which 'ip_client' or 'ip_server' (not in use)
	 *
	 * @return array
	 */
	public static function scan_country( $which ) {
		// scan all the country code using selected APIs
		$options   = IP_Location_Block::get_option();
		$ip        = IP_Location_Block::get_ip_address();
		$args      = IP_Location_Block::get_request_headers( $options );
		$type      = IP_Location_Block_Provider::get_providers( 'type', false, false );
		$providers = IP_Location_Block_Provider::get_valid_providers( $options, false, false );

		$res['IP address'] = esc_html( $ip );

		foreach ( $providers as $provider ) {
			if ( $geo = IP_Location_Block_API::get_instance( $provider, $options ) ) {
				$ret              = $geo->get_location( $ip, $args );
				$res[ $provider ] = array(
					'type' => $type[ $provider ],
					'code' => esc_html(
						false === $ret ? __( 'n/a', 'ip-location-block' ) : (
						! empty( $ret['errorMessage'] ) ? $ret['errorMessage'] : (
						! empty( $ret['countryCode'] ) ? $ret['countryCode'] :
							__( 'n/a', 'ip-location-block' ) ) )
					),
				);
			}
		}

		return $res;
	}

	/**
	 * Insert array
	 *
	 * @param $base_array
	 * @param $insert_value
	 * @param null $position
	 */
	private static function array_insert( &$base_array, $insert_value, $position = null ) {
		if ( ! is_array( $insert_value ) ) {
			$insert_value = array( $insert_value );
		}

		$position   = is_null( $position ) ? count( $base_array ) : intval( $position );
		$base_array = array_merge( array_splice( $base_array, 0, $position ), $insert_value, $base_array );
	}

	/**
	 * Export logs from MySQL DB
	 *
	 * @param string $which 'comment', 'xmlrpc', 'login', 'admin' or 'public'
	 */
	public static function export_logs( $which ) {
		$csv  = '#';
		$time = $_SERVER['REQUEST_TIME'];

		$csv .= implode( ',', array(
				__( 'Time', 'ip-location-block' ),
				__( 'IP address', 'ip-location-block' ),
				__( 'Code', 'ip-location-block' ),
				__( 'City', 'ip-location-block' ),
				__( 'State', 'ip-location-block' ),
				__( 'ASN', 'ip-location-block' ),
				__( 'Target', 'ip-location-block' ),
				__( 'Result', 'ip-location-block' ),
				__( 'Request', 'ip-location-block' ),
				__( 'User agent', 'ip-location-block' ),
				__( 'HTTP headers', 'ip-location-block' ),
				__( '$_POST data', 'ip-location-block' ),
			) ) . PHP_EOL;

		foreach ( IP_Location_Block_Logs::restore_logs( $which ) as $data ) {
			$hook = array_shift( $data ); // remove `No`
			$hook = array_shift( $data ); // extract `hook`
			self::array_insert( $data, $hook, 3 );
			$data[0] = IP_Location_Block_Util::localdate( $data[0], 'Y-m-d H:i:s' );
			$data[9] = str_replace( ',', '‚', $data[9] ); // &#044; --> &#130;
			$data[10] = str_replace( ',', '‚', $data[10] ); // &#044; --> &#130;
			$data[11] = str_replace( ',', '‚', $data[11] ); // &#044; --> &#130;
			$csv     .= implode( ',', $data ) . PHP_EOL;
		}

		// Send as file
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . IP_Location_Block::PLUGIN_NAME . '_' . IP_Location_Block_Util::localdate( $time, 'Y-m-d_H-i-s' ) . '.csv"' );
		header( 'Pragma: public' );
		header( 'Expires: 0' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv;
	}

	/**
	 * Format logs from rows array
	 *
	 * @param $rows
	 *
	 * @return array
	 */
	private static function format_logs( $rows ) {
		$options = IP_Location_Block::get_option();
		$res     = array();

		foreach ( $rows as $row ) {
			array_shift( $row ); // remove `No`
			$row = array_map( 'esc_html', $row );

			if ( $options['anonymize'] && false === strpos( $row[2], '***' ) ) {
				$row[2] = IP_Location_Block_Util::anonymize_ip( $row[2], true );
				$row[8] = IP_Location_Block_Util::anonymize_ip( $row[8], false );
			}

			$res[] = array(
				/*  0 Checkbox     */
				'',
				/*  1 Time (raw)   */
				$row[1],
				/*  2 Date         */
				'&rsquo;' . IP_Location_Block_Util::localdate( $row[1], 'y-m-d H:i:s' ),
				/*  3 IP address   */
				'<span><a href="#!">' . $row[2] . '</a></span>',
				/*  4 Country code */
				'<span>' . $row[3] . '</span>',
				/*  5 City    */
				'<span>' . $row[4] . '</span>',
				/*  6 State    */
				'<span>' . $row[5] . '</span>',
				/*  7 AS number    */
				'<span>' . $row[7] . '</span>',
				/*  8 Target       */
				'<span>' . $row[0] . '</span>',
				/*  9 Status       */
				'<span>' . $row[6] . '</span>',
				/*  10 Request      */
				'<span>' . $row[8] . '</span>',
				/*  11 User agent   */
				'<span>' . $row[9] . '</span>',
				/* 12 HTTP headers */
				'<span>' . $row[10] . '</span>',
				/* 13 $_POST data  */
				'<span>' . $row[11] . '</span>',
			);
		}

		return $res;
	}

	/**
	 * Restore logs from MySQL DB
	 *
	 * @param string $which 'comment', 'xmlrpc', 'login', 'admin' or 'public'
	 *
	 * @return array[]
	 */
	public static function restore_logs( $which ) {
		return array(
			'data' => self::format_logs(
				apply_filters( 'ip-location-block-logs', IP_Location_Block_Logs::restore_logs( $which ) )
			)
		); // DataTables requires `data`
	}

	/**
	 * Catch and release the authority for live log
	 *
	 * @return bool|WP_Error
	 */
	public static function catch_live_log() {
		$user = IP_Location_Block_Util::get_current_user_id();
		$auth = IP_Location_Block::get_live_log();

		if ( $auth === false || $user === (int) $auth ) {
			set_transient( IP_Location_Block::PLUGIN_NAME . '-live-log', $user, IP_Location_Block_Admin::TIMEOUT_LIVE_UPDATE );

			return true;
		} else {
			$info = get_userdata( $auth );

			return new WP_Error( 'Warn', sprintf( __( 'The user %s (user ID: %d) is in use.', 'ip-location-block' ), $info->user_login, $auth ) );
		}
	}

	/**
	 * Releases live log
	 * @return bool|WP_Error
	 */
	public static function release_live_log() {
		if ( is_wp_error( $result = self::catch_live_log() ) ) {
			return $result;
		}
		delete_transient( IP_Location_Block::PLUGIN_NAME . '-live-log' );

		return true;
	}

	/**
	 * Restore and reset live log in SQLite
	 * @return bool
	 */
	public static function reset_live_log() {
		return IP_Location_Block_Logs::reset_sqlite_db();
	}

	/**
	 * Restores live log
	 *
	 * @param $hook
	 * @param $settings
	 *
	 * @return array|array[]|bool|WP_Error
	 */
	public static function restore_live_log( $hook, $settings ) {
		if ( is_wp_error( $ret = self::catch_live_log() ) ) {
			return $ret;
		}

		if ( ! is_wp_error( $res = IP_Location_Block_Logs::restore_live_log( $hook, $settings ) ) ) {
			return array( 'data' => self::format_logs( apply_filters( 'ip-location-block-logs', $res ) ) );
		} else {
			return array( 'error' => $res->get_error_message() );
		}
	}

	/**
	 * Export IP address in cache from MySQL DB
	 *
	 * @param bool $anonymize
	 */
	public static function export_cache( $anonymize = true ) {
		$csv  = '#';
		$time = $_SERVER['REQUEST_TIME'];

		$csv .= implode( ',', array(
				__( 'IP address', 'ip-location-block' ),
				__( 'Code', 'ip-location-block' ),
				__( 'City', 'ip-location-block' ),
				__( 'State', 'ip-location-block' ),
				__( 'ASN', 'ip-location-block' ),
				__( 'Host name', 'ip-location-block' ),
				__( 'Target', 'ip-location-block' ),
				__( 'Failure / Total', 'ip-location-block' ),
				__( 'Elapsed[sec]', 'ip-location-block' ),
			) ) . PHP_EOL;

		foreach ( IP_Location_Block_Logs::restore_cache() as $key => $val ) {
			if ( $anonymize ) {
				$key         = IP_Location_Block_Util::anonymize_ip( $key, true );
				$val['host'] = IP_Location_Block_Util::anonymize_ip( $val['host'], false );
			}

			$csv .= implode( ',', array(
					/* IP address      */ $key,
					/* Country code    */ $val['code'],
					/* City            */ $val['city'],
					/* State           */ $val['state'],
					/* AS number       */ $val['asn'],
					/* Host name       */ $val['host'],
					/* Target          */ $val['hook'],
					/* Failure / Total */ sprintf( '%d / %d', (int) $val['fail'], (int) $val['reqs'] ),
					/* Elapsed[sec]    */ $time - (int) $val['time'],
				) ) . PHP_EOL;
		}

		// Send as file
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . IP_Location_Block::PLUGIN_NAME . '_' . IP_Location_Block_Util::localdate( $time, 'Y-m-d_H-i-s' ) . '.csv"' );
		header( 'Pragma: public' );
		header( 'Expires: 0' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv;
	}

	/**
	 * Restore cache from MySQL DB
	 *
	 * @param bool $anonymize
	 *
	 * @return array[]
	 */
	public static function restore_cache( $anonymize = true ) {
		$res  = array();
		$time = $_SERVER['REQUEST_TIME'];

		foreach ( IP_Location_Block_Logs::restore_cache() as $key => $val ) {
			if ( $anonymize ) {
				$key         = IP_Location_Block_Util::anonymize_ip( $key, true );
				$val['host'] = IP_Location_Block_Util::anonymize_ip( $val['host'], false );
			}

			$res[] = array(
				/* Checkbox     */
				'',
				/* IP address   */
				'<span><a href="#!" data-hash="' . esc_attr( $val['hash'] ) . '">' . esc_html( $key ) . '</a></span>',
				/* Country code */
				'<span>' . esc_html( $val['code'] ) . '</span>',
				/* Country code */
				'<span>' . esc_html( $val['city'] ) . '</span>',
				/* Country code */
				'<span>' . esc_html( $val['state'] ) . '</span>',
				/* AS number    */
				'<span>' . esc_html( $val['asn'] ) . '</span>',
				/* Host name    */
				'<span>' . esc_html( $val['host'] ) . '</span>',
				/* Target       */
				'<span>' . esc_html( $val['hook'] ) . '</span>',
				/* Fails/Calls  */
				'<span>' . sprintf( '%d / %d', (int) $val['fail'], (int) $val['reqs'] ) . '</span>',
				/* Elapsed[sec] */
				'<span>' . ( $time - (int) $val['time'] ) . '</span>',
			);
		}

		return array( 'data' => $res ); // DataTables requires `data`
	}

	/**
	 * Get the number of active sites on your installation
	 * @return int
	 */
	public static function get_network_count() {
		if ( ! defined( 'TEST_RESTORE_NETWORK' ) or ! TEST_RESTORE_NETWORK ):
			if ( is_plugin_active_for_network( IP_LOCATION_BLOCK_BASE ) ) {
				return get_blog_count(); // get_sites( array( 'count' => TRUE ) ) @since 4.6
			} else {
				$count = 0;
				global $wpdb;
				foreach ( $wpdb->get_col( "SELECT `blog_id` FROM `$wpdb->blogs`" ) as $id ) {
					switch_to_blog( $id );
					is_plugin_active( IP_LOCATION_BLOCK_BASE ) and ++ $count;
					restore_current_blog();
				}

				return $count;
			}
		else:
			return TEST_NETWORK_BLOG_COUNT;
		endif;
	}

	/**
	 * Restore blocked per target in logs
	 *
	 * @param string $duration the number of selected duration
	 * @param int $offset the start of blog to restore logs
	 * @param int $length the number of blogs to restore logs from $offset
	 * @param int $literal JavaScript literal notation
	 *
	 * @return array
	 */
	public static function restore_network( $duration, $offset = 0, $length = 100, $literal = false ) {
		$zero = array(
			'comment' => 0,
			'xmlrpc'  => 0,
			'login'   => 0,
			'admin'   => 0,
			'public'  => 0,
		);

		$time = array(
			YEAR_IN_SECONDS,    // All
			HOUR_IN_SECONDS,    // Latest 1 hour
			DAY_IN_SECONDS,     // Latest 24 hours
			WEEK_IN_SECONDS,    // Latest 1 week
			30 * DAY_IN_SECONDS // Latest 1 month (MONTH_IN_SECONDS is since WP 4.4+)
		);

		$i        = 0;
		$length   += $offset;
		$json     = $count = array();
		$duration = isset( $time[ $duration ] ) ? $time[ $duration ] : $time[0];

		if ( ! defined( 'TEST_RESTORE_NETWORK' ) or ! TEST_RESTORE_NETWORK ):
			global $wpdb;
			foreach ( $wpdb->get_col( "SELECT `blog_id` FROM `$wpdb->blogs`" ) as $id ) {
				switch_to_blog( $id );

				if ( is_plugin_active( IP_LOCATION_BLOCK_BASE ) && $offset <= $i && $i < $length ) {
					// array of ( `time`, `ip`, `hook`, `code`, `method`, `data` )
					$name = get_bloginfo( 'name' );
					$logs = IP_Location_Block_Logs::get_recent_logs( $duration );

					$count[ $name ] = $zero;

					// Blocked hooks by time
					foreach ( $logs as $val ) {
						++ $count[ $name ][ $val['hook'] ];
					}

					// link over network
					$count[ $name ]['link'] = esc_url( add_query_arg(
						array( 'page' => IP_Location_Block::PLUGIN_NAME ),
						admin_url( 'options-general.php' )
					) );
				}

				restore_current_blog();
			}
		else:
			for ( $i = 0; $i < TEST_NETWORK_BLOG_COUNT; ++ $i ) {
				if ( $offset <= $i && $i < $length ) {
					$count[ 'site-' . $i ] = array(
						$i,
						$i,
						$i,
						$i,
						$i,
						esc_url( add_query_arg(
							array( 'page' => IP_Location_Block::PLUGIN_NAME, 'tab' => 1 ),
							admin_url( 'options-general.php' )
						) )
					);
				}
			}
		endif; // TEST_RESTORE_NETWORK

		if ( $literal ) {
			// https://stackoverflow.com/questions/17327022/create-line-chart-using-google-chart-api-and-json-for-datatable
			foreach ( $count as $key => $val ) {
				$json['rows'][]['c'] = array(
					array( 'v' => $key ),
					array( 'v' => $val['comment'] ),
					array( 'v' => $val['xmlrpc'] ),
					array( 'v' => $val['login'] ),
					array( 'v' => $val['admin'] ),
					array( 'v' => $val['public'] ),
					array( 'v' => $val['link'] ),
				);
			}
		} else {
			// https://developers.google.com/chart/interactive/docs/datatables_dataviews#arraytodatatable
			foreach ( $count as $key => $val ) {
				array_push( $json, array_merge( array( $key ), array_values( $val ) ) );
			}
		}

		return $json;
	}

	/**
	 * Validate json from the client and respond safe data
	 *
	 * @param $parent
	 */
	public static function validate_settings( $parent ) {


		// restore escaped characters (see wp_magic_quotes() in wp-includes/load.php)
		$json = json_decode(
			str_replace(
				array( '\\"', '\\\\', "\'" ),
				array( '"', '\\', "'" ),
				isset( $_POST['data'] ) ? sanitize_text_field( $_POST['data'] ) : ''
			), true
		);

		if ( null === $json ) {
			wp_die( 'Illegal JSON format.', '', array( 'response' => 500, 'back_link' => true ) );
		} // @Since 2.0.4

		// Convert json to setting data
		$input = self::json_to_settings( $json );

		// Integrate posted data into current settings because if can be a part of hole data
		$input = $parent->array_replace_recursive(
			$parent->preprocess_options( IP_Location_Block::get_option(), IP_Location_Block::get_default() ), $input
		);

		// Validate options and convert to json
		$output = $parent->sanitize_options( $input );
		$json   = self::json_unsafe_encode( self::settings_to_json( $output ) );

		mbstring_binary_safe_encoding(); // @since 3.7.0
		$length = strlen( $json );
		reset_mbstring_encoding(); // @since 3.7.0

		// Send json as file
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . IP_Location_Block::PLUGIN_NAME . '-settings.json"' );
		header( 'Pragma: public' );
		header( 'Expires: 0' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Content-Length: ' . $length );
		echo $json;
	}

	/**
	 * Convert json associative array to settings array
	 *
	 */
	private static function json_to_settings( $input ) {
		$settings = $m = array();
		$prfx     = IP_Location_Block::OPTION_NAME;

		try {
			foreach ( $input as $key => $val ) {
				if ( preg_match( "/{$prfx}\[(.+?)\](?:\[(.+?)\](?:\[(.+?)\])?)?/", $key, $m ) ) {
					switch ( count( $m ) ) {
						case 2:
							$settings[ $m[1] ] = $val;
							break;

						case 3:
							$settings[ $m[1] ][ $m[2] ] = $val;
							break;

						case 4:
							if ( is_numeric( $m[3] ) ) {
								if ( empty( $settings[ $m[1] ][ $m[2] ] ) ) {
									$settings[ $m[1] ][ $m[2] ] = 0;
								}
								$settings[ $m[1] ][ $m[2] ] |= $val;
							} else { // [*]:checkbox
								$settings[ $m[1] ][ $m[2] ][ $m[3] ] = $val;
							}
							break;

						default:
							throw new Exception();
					}
				}
			}
		} catch ( Exception $e ) { // should be returned as ajax response
			wp_die( sprintf( __( 'illegal format at %s. Please delete the corresponding line and try again.', 'ip-location-block' ), print_r( @$m[0], true ) ) );
		}

		return $settings;
	}

	/**
	 * Convert settings array to json associative array
	 *
	 * @param $input
	 * @param bool $overwrite
	 *
	 * @return array
	 */
	public static function settings_to_json( $input, $overwrite = true ) {
		// [*]:list of checkboxes, [$]:comma separated text to array, [%]:associative array
		$keys = array(
			'[version]',
			'[matching_rule]',
			'[white_list]',
			'[black_list]',
			'[extra_ips][white_list]',
			'[extra_ips][black_list]',
			'[anonymize]',
			'[restrict_api]',  // 3.0.13
			'[simulate]',      // 3.0.14
			'[use_asn]',
			'[signature]',
			'[timeout]',
			'[login_fails]',
			'[response_code]',
			'[response_msg]',       // 3.0.0
			'[redirect_uri]',       // 3.0.0
			'[validation][timing]', // 2.2.9
			'[validation][proxy]',
			'[validation][comment]',
			'[validation][xmlrpc]',
			'[validation][login]',
			'[login_action][login]',        // 2.2.8
			'[login_action][register]',     // 2.2.8
			'[login_action][resetpass]',    // 2.2.8
			'[login_action][lostpassword]', // 2.2.8
			'[login_action][postpass]',     // 2.2.8
			'[validation][admin][1]',
			'[validation][admin][2]',
			'[validation][ajax][1]',
			'[validation][ajax][2]',
			'[validation][plugins]',
			'[validation][themes]',
			'[validation][includes]',    // 3.0.0
			'[validation][uploads]',     // 3.0.0
			'[validation][languages]',   // 3.0.0
			'[validation][public]',      // 3.0.0
			'[validation][restapi]',     // 3.0.3
			'[validation][mimetype]',    // 3.0.3
			'[validation][metadata]',    // 3.0.18
			'[exception][plugins][*]',   // 2.2.5
			'[exception][themes][*]',    // 2.2.5
			'[exception][admin][$]',     // 3.0.0
			'[exception][public][$]',    // 3.0.0
			'[exception][includes][$]',  // 3.0.0
			'[exception][uploads][$]',   // 3.0.0
			'[exception][languages][$]', // 3.0.0
			'[exception][restapi][$]',   // 3.0.3
			'[public][matching_rule]',   // 3.0.0
			'[public][white_list]',      // 3.0.0
			'[public][black_list]',      // 3.0.0
			'[public][target_rule]',     // 3.0.0
			'[public][target_pages][$]', // 3.0.0
			'[public][target_posts][$]', // 3.0.0
			'[public][target_cates][$]', // 3.0.0
			'[public][target_tags][$]',  // 3.0.0
			'[public][ua_list]',         // 3.0.0
			'[public][dnslkup]',         // 3.0.3
			'[public][response_code]',   // 3.0.3
			'[public][response_msg]',    // 3.0.3
			'[public][redirect_uri]',    // 3.0.3
			'[public][behavior]',        // 3.0.10
			'[public][cache_bypass]',    // 1.3.9
			'[behavior][time]',          // 3.0.10
			'[behavior][view]',          // 3.0.10
			'[save_statistics]',
			'[validation][recdays]',     // 2.2.9
			'[validation][reclogs]',
			'[validation][maxlogs]',
			'[validation][explogs]',     // 3.0.12
			'[validation][postkey]',
			'[update][auto]',
			'[cache_time_gc]',           // 3.0.0
			'[cache_hold]',
			'[cache_time]',
			'[comment][pos]',
			'[comment][msg]',
			'[clean_uninstall]',
			'[network_wide]',            // 3.0.0
			'[mimetype][white_list][%]', // 3.0.3
			'[mimetype][black_list]',    // 3.0.3
			'[mimetype][capability][$]', // 3.0.4
			'[Maxmind][use_asn]',        // 3.0.4
			'[live_update][in_memory]',  // 3.0.5
			'[monitor][updated_option]',             // 3.0.18
			'[monitor][update_site_option]',         // 3.0.18
			'[metadata][pre_update_option][$]',      // 3.0.17
			'[metadata][pre_update_site_option][$]', // 3.0.17
		);
		$json = array();
		$prfx = IP_Location_Block::OPTION_NAME;

		// add providers
		foreach ( array_keys( IP_Location_Block_Provider::get_providers( 'key' ) ) as $key ) {
			$keys[] = '[providers][' . $key . ']';
		}

		foreach ( $keys as $key ) {
			if ( preg_match( "/\[(.+?)\](?:\[(.+?)\](?:\[(.+?)\])?)?/", $key, $m ) ) {
				switch ( count( $m ) ) {
					case 2:
						if ( isset( $input[ $m[1] ] ) ) {
							$json[ $prfx . '[' . $m[1] . ']' ] = strval( $input[ $m[1] ] );
						}
						break;

					case 3:
						if ( '%' === $m[2] ) { // [%]:associative array
							foreach ( isset( $input[ $m[1] ] ) ? $input[ $m[1] ] : array() as $key => $val ) {
								$json[ $prfx . '[' . $m[1] . '][' . $key . ']' ] = $val;
							}
							break;
						}
						if ( isset( $input[ $m[1] ][ $m[2] ] ) || $overwrite ) {
							$json[ $prfx . '[' . $m[1] . '][' . $m[2] . ']' ] = (
							isset( $input[ $m[1] ][ $m[2] ] ) &&
							'@' !== $input[ $m[1] ][ $m[2] ] ?
								strval( $input[ $m[1] ][ $m[2] ] ) : ''
							);
						}
						break;

					case 4:
						if ( is_numeric( $m[3] ) ) {
							if ( isset( $input[ $m[1] ][ $m[2] ] ) ) {
								$json[ $prfx . '[' . $m[1] . '][' . $m[2] . ']' . '[' . $m[3] . ']' ] =
									strval( $input[ $m[1] ][ $m[2] ] ) & (int) $m[3];
							}
						} elseif ( isset( $input[ $m[1] ][ $m[2] ] ) ) {
							if ( '*' === $m[3] ) { // [*]:checkbox
								foreach ( $input[ $m[1] ][ $m[2] ] as $val ) {
									$json[ $prfx . '[' . $m[1] . '][' . $m[2] . ']' . '[' . $val . ']' ] = '1';
								}
							} elseif ( '%' === $m[3] ) { // [%]:associative array
								foreach ( $input[ $m[1] ][ $m[2] ] as $key => $val ) {
									$json[ $prfx . '[' . $m[1] . '][' . $m[2] . ']' . '[' . $key . ']' ] = $val;
								}
							} elseif ( is_array( $input[ $m[1] ][ $m[2] ] ) ) { // [$]:comma separated text to array
								$json[ $prfx . '[' . $m[1] . '][' . $m[2] . ']' ] = implode( ',', $input[ $m[1] ][ $m[2] ] );
							}
						}
						break;
				}
			}
		}

		return $json;
	}


	/**
	 * The recommended "Best for Back-end" override set. Applied on top of the
	 * current settings (only these keys change).
	 *
	 * @return array
	 */
	public static function preferred_overrides() {
		return array(
			'login_fails' => 10,      // Limited number of login attempts
			'validation'  => array(   // Action hook for validation
				'comment'  => true,    // Validate on comment post
				'login'    => 1,       // Validate on login
				'admin'    => 1,       // Validate on admin (1:country)
				'ajax'     => 1,       // Validate on ajax/post (1:country)
				'xmlrpc'   => 1,       // Validate on xmlrpc (1:country 2:close)
				'postkey'  => 'action,comment,log,pwd,FILES', // Keys in $_POST and $_FILES
				'plugins'  => 0,       // wp-content/plugins (WP-ZEP removed)
				'themes'   => 0,       // wp-content/themes (WP-ZEP removed)
				'timing'   => 1,       // 0:init, 1:mu-plugins, 2:drop-in
				'mimetype' => 1,       // 0:disable, 1:white_list, 2:black_list
			),
			'signature'   => "../,/wp-config.php,/passwd\ncurl,wget,eval,base64\nselect:.5,where:.5,union:.5\nload_file:.5,create:.6,password:.4",
		);
	}

	/**
	 * Make preferred settings with formatted json
	 * @return array
	 */
	public static function preferred_to_json() {
		return self::settings_to_json(
			self::preferred_overrides(),
			false // should not overwrite the existing parameters
		);
	}

	/**
	 * Encode json without JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	 * Note: It should not be rendered via jQuery .html() at client side
	 *
	 * @param $data
	 *
	 * @return false|string|string[]|null
	 */
	private static function json_unsafe_encode( $data ) {
		if ( version_compare( PHP_VERSION, '5.4', '>=' ) ) {
			$opts = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
			if ( function_exists( 'wp_json_encode' ) ) // @since 4.1.0
			{
				$json = wp_json_encode( $data, $opts );
			} else {
				$json = json_encode( $data, $opts );
			}
		} else { // JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES are not supported in PHP 5.3 and under
			$json = self::json_unescaped_unicode( $data );
			$json = preg_replace(
				array( '!{"!', '!":!', '!("?),"!', '!"}!', '!\\\\/!' ),
				array( '{' . PHP_EOL . '    "', '": ', '$1,' . PHP_EOL . '    "', '"' . PHP_EOL . '}', '/' ),
				$json
			);
		}

		return $json;
	}

	/**
	 * Fallback function for PHP 5.3 and under
	 * @link https://qiita.com/keromichan16/items/5ff45a77fb0d48e046cc
	 * @link https://stackoverflow.com/questions/16498286/why-does-the-php-json-encode-function-convert-utf-8-strings-to-hexadecimal-entit/
	 *
	 * @param $input
	 *
	 * @return string|string[]|null
	 */
	private static function json_unescaped_unicode( $input ) {
		return preg_replace_callback(
			'/(?:\\\\u[0-9a-zA-Z]{4})++/',
			array( __CLASS__, 'convert_encoding' ),
			json_encode( $input )
		);
	}

	/**
	 * Fallback function for PHP 5.3 and under
	 *
	 * @param $matches
	 *
	 * @return false|string|string[]|null
	 */
	private static function convert_encoding( $matches ) {
		return mb_convert_encoding(
			pack( 'H*', str_replace( '\\u', '', $matches[0] ) ), 'UTF-8', 'UTF-16'
		);
	}

	/**
	 * Get blocked action and pages
	 *
	 * @param string $which 'page', 'action', 'plugin', 'theme'
	 *
	 * @return array
	 */
	private static function get_blocked_queries( $which ) {
		$result = array();

		switch ( $which ) {
			case 'page':
			case 'action':
				$dir = IP_Location_Block_Util::slashit( str_replace( site_url(), '', admin_url() ) ); /* `/wp-admin/` */

				foreach ( IP_Location_Block_Logs::search_blocked_logs( 'method', $dir ) as $log ) {
					foreach ( array( 'method', 'data' ) as $key ) {
						if ( preg_match( '!' . $which . '=([\-\w]+)!', $log[ $key ], $matches ) ) {
							$result += array( $matches[1] => $which );
						}
					}
				}
				break;

			case 'plugins':
			case 'themes':
				// make a list of installed plugins/themes
				if ( 'plugins' === $which ) {
					$key = array();
					foreach ( get_plugins() as $pat => $log ) {
						$pat   = explode( '/', $pat, 2 );
						$key[] = $pat[0];
					}
				} else {
					$key = wp_get_themes();
				}

				$dir = 'plugins' === $which ? plugins_url() : get_theme_root_uri();
				$dir = IP_Location_Block_Util::slashit( str_replace( site_url(), '', $dir ) );
				$pat = preg_quote( $dir, '!' ); /* `/wp-content/(plugins|themes)/` */

				foreach ( IP_Location_Block_Logs::search_blocked_logs( 'method', $dir ) as $log ) {
					if ( preg_match( '!' . $pat . '(.+?)/!', $log['method'], $matches ) && in_array( $matches[1], $key, true ) ) {
						$result += array( $matches[1] => $which );
					}
				}
				break;
		}

		return $result;
	}

	/**
	 * Get slug in blocked requests for exceptions
	 *
	 * @param $target
	 *
	 * @return array|string[]
	 */
	public static function find_exceptions( $target ) {
		switch ( $target ) {
			case 'find-admin':
				$res = array();
				foreach ( array( 'action', 'page' ) as $which ) {
					$res += self::get_blocked_queries( $which );
				}

				return $res;

			case 'find-plugins':
				return self::get_blocked_queries( 'plugins' );

			case 'find-themes':
				return self::get_blocked_queries( 'themes' );
		}

		return array();
	}

	/**
	 *  Get debug information related to WordPress
	 * @return array
	 */
	public static function get_wp_info() {
		return \IPLocationBlock\Diagnostics\SystemInfo::collect();
	}

}
