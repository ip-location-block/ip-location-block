<?php

/**
 * Structured health checks shared by the classic and React admin screens.
 *
 * @package IP_Location_Block
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class IP_Location_Block_Diagnostics {

	const ACK_OPTION = 'ip_location_block_diagnostic_acknowledgements';

	/**
	 * Run every health check without changing plugin settings or notice queues.
	 *
	 * @param array|null $settings Plugin settings.
	 *
	 * @return array
	 */
	public static function run( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : IP_Location_Block::get_option();
		$checks   = array();
		$provider = IP_Location_Block_Rest::get_provider_status_data( $settings );
		$updating = get_transient( IP_Location_Block::CRON_NAME );

		self::add_check(
			$checks,
			'wordpress-version',
			version_compare( get_bloginfo( 'version' ), '3.7.0', '<' ) ? 'critical' : 'pass',
			'system',
			__( 'WordPress compatibility', 'ip-location-block' ),
			version_compare( get_bloginfo( 'version' ), '3.7.0', '<' )
				? __( 'IP Location Block requires WordPress 3.7 or newer.', 'ip-location-block' )
				: __( 'The installed WordPress version is supported.', 'ip-location-block' )
		);

		self::add_provider_checks( $checks, $settings, $provider );
		self::add_rule_checks( $checks, $settings, $provider, $updating );
		self::add_lockout_check( $checks, $settings, $updating );
		self::add_compatibility_checks( $checks, $settings );

		$report = self::finalize( $checks );
		$report['emergencyAccess'] = self::emergency_access_status( $settings );

		return $report;
	}

	/**
	 * Persist or clear a site-level acknowledgement for an advisory.
	 *
	 * @param string $id           Check identifier.
	 * @param bool   $acknowledged Desired state.
	 *
	 * @return bool|WP_Error
	 */
	public static function set_acknowledgement( $id, $acknowledged ) {
		$id      = sanitize_key( $id );
		$allowed = self::acknowledgeable_ids();

		if ( ! in_array( $id, $allowed, true ) ) {
			return new WP_Error(
				'ip_location_block_invalid_diagnostic',
				__( 'This diagnostic cannot be acknowledged.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$stored = get_option( self::ACK_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		if ( $acknowledged ) {
			$stored[ $id ] = time();
		} else {
			unset( $stored[ $id ] );
		}

		update_option( self::ACK_OPTION, $stored, false );

		// Keep the classic cache-host notice dismissal in sync.
		if ( 'full-page-cache-conflict' === $id ) {
			$settings                           = IP_Location_Block::get_option();
			$settings['cache_compat_dismissed'] = (bool) $acknowledged;
			IP_Location_Block::update_option( $settings );
		}

		return true;
	}

	/**
	 * Environment information used by support. This deliberately runs only
	 * when its dedicated REST endpoint is requested because DNS and filesystem
	 * checks can be relatively expensive.
	 *
	 * @return array
	 */
	public static function environment() {
		if ( ! class_exists( 'IP_Location_Block_Admin_Ajax', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';
		}

		$raw = IP_Location_Block_Admin_Ajax::get_wp_info();
		$raw = is_array( $raw ) ? $raw : array();

		$system_keys = array(
			'Server:', 'MySQL:', 'PHP:', 'PHP SAPI:', 'Memory limit:',
			'Peak usage:', 'WordPress:', 'Multisite:',
		);
		$runtime_keys = array(
			'File system:', 'Temp folder:', 'Process owner:', 'File owner:',
			'Umask:', 'Zlib:', 'ZipArchive:', 'PECL phar:', 'BC Math:',
			'mb_strcut:', 'OpenSSL:', 'SQLite(PDO):', 'DNS lookup:', 'User agent:',
		);

		$sections = array(
			'system'       => array(
				'id'    => 'system',
				'title' => __( 'System', 'ip-location-block' ),
				'rows'  => array(
					array( 'label' => __( 'IP Location Block:', 'ip-location-block' ), 'value' => IP_LOCATION_BLOCK_VERSION ),
				),
			),
			'runtime'      => array(
				'id'    => 'runtime',
				'title' => __( 'Runtime and storage', 'ip-location-block' ),
				'rows'  => array(),
			),
			'software'     => array(
				'id'    => 'software',
				'title' => __( 'Active theme and plugins', 'ip-location-block' ),
				'rows'  => array(),
			),
			'blocked_self' => array(
				'id'    => 'blocked-self',
				'title' => __( 'Recent blocked requests from your IP', 'ip-location-block' ),
				'rows'  => array(),
			),
		);

		foreach ( $raw as $label => $value ) {
			$row = array(
				// The classic report escapes values before inserting them into a
				// textarea. React renders text safely on its own, so decode those
				// entities to avoid displaying names such as "Foo &amp; Bar".
				'label' => wp_specialchars_decode( (string) $label, ENT_QUOTES ),
				'value' => is_scalar( $value )
					? wp_specialchars_decode( (string) $value, ENT_QUOTES )
					: wp_json_encode( $value ),
			);
			if ( in_array( $label, $system_keys, true ) ) {
				$sections['system']['rows'][] = $row;
			} elseif ( in_array( $label, $runtime_keys, true ) ) {
				$sections['runtime']['rows'][] = $row;
			} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}/', (string) $label ) ) {
				$sections['blocked_self']['rows'][] = $row;
			} else {
				$sections['software']['rows'][] = $row;
			}
		}

		return array(
			'generatedAt' => gmdate( 'c' ),
			'sections'    => array_values( array_filter( $sections, static function ( $section ) {
				return ! empty( $section['rows'] );
			} ) ),
		);
	}

	/**
	 * Registered provider attribution metadata.
	 *
	 * @return array
	 */
	public static function attributions() {
		$settings = IP_Location_Block::get_option();
		$active   = IP_Location_Block_Provider::get_valid_providers( $settings, false, false, false );
		$result   = array();

		foreach ( IP_Location_Block_Provider::all() as $name => $meta ) {
			if ( 'Cache' === $name ) {
				continue;
			}
			$result[] = array(
				'name'   => (string) $name,
				'link'   => isset( $meta['link'] ) ? esc_url_raw( $meta['link'] ) : '',
				'type'   => isset( $meta['type'] ) ? wp_strip_all_tags( (string) $meta['type'] ) : '',
				'local'  => ! empty( $meta['local'] ),
				'active' => in_array( $name, $active, true ),
			);
		}

		return $result;
	}

	private static function add_provider_checks( &$checks, $settings, $provider ) {
		$provider_action = self::settings_action(
			__( 'Review providers', 'ip-location-block' ),
			'provider',
			4
		);
		$problems = array();

		foreach ( $provider['providers'] as $item ) {
			if ( ! $item['selected'] || '' === $item['reason'] || 'privacy_restriction' === $item['reason'] ) {
				continue;
			}
			$reason = $item['reason'];
			if ( 'missing_key' === $reason ) {
				$reason = __( 'API key is missing', 'ip-location-block' );
			} elseif ( 'database_missing' === $reason ) {
				$reason = __( 'database file is missing', 'ip-location-block' );
			} elseif ( 'not_tested' === $reason ) {
				$reason = __( 'provider has not been tested', 'ip-location-block' );
			} elseif ( 'key_upgrade_required' === $reason ) {
				$reason = __( 'API key must be upgraded', 'ip-location-block' );
			} elseif ( in_array( $reason, array( 'exhausted', 'rate_limited' ), true ) ) {
				$reason = __( 'request quota is unavailable', 'ip-location-block' );
			}
			$problems[] = sprintf( '%1$s — %2$s', $item['name'], $reason );
		}

		self::add_check(
			$checks,
			'provider-readiness',
			$provider['ready'] ? ( empty( $problems ) ? 'pass' : 'warning' ) : 'critical',
			'providers',
			__( 'Geolocation provider readiness', 'ip-location-block' ),
			$provider['ready']
				? ( empty( $problems )
					? __( 'At least one configured provider is ready to identify visitors.', 'ip-location-block' )
					: __( 'A provider is available, but one or more selected providers need attention.', 'ip-location-block' ) )
				: __( 'No usable geolocation provider is ready. Blocking results may be unavailable after cached records expire.', 'ip-location-block' ),
			$problems,
			array( $provider_action )
		);

		$active_native = in_array( 'IP Location Block', $provider['active'], true );
		$others        = array_values( array_diff( $provider['active'], array( 'IP Location Block' ) ) );
		$mixed         = $active_native && ! empty( $others );
		self::add_check(
			$checks,
			'native-mixed-providers',
			$mixed ? 'warning' : 'pass',
			'providers',
			__( 'Native provider mode', 'ip-location-block' ),
			$mixed
				? __( 'IP Location Block is enabled with other providers. Disable the other providers to use Native Mode and consistent city/state results.', 'ip-location-block' )
				: __( 'The provider selection does not conflict with Native Mode.', 'ip-location-block' ),
			$others,
			array( $provider_action ),
			true
		);

		$has_local = false;
		foreach ( $provider['providers'] as $item ) {
			if ( $item['active'] && $item['local'] && $item['ready'] ) {
				$has_local = true;
				break;
			}
		}
		$external_only = $provider['ready'] && ! $has_local && ! $active_native;
		self::add_check(
			$checks,
			'external-only-provider',
			$external_only ? 'warning' : 'pass',
			'providers',
			__( 'Local provider fallback', 'ip-location-block' ),
			$external_only
				? __( 'Only third-party remote providers are ready. A local database or the IP Location Block provider can reduce lookup latency and external dependencies.', 'ip-location-block' )
				: __( 'A local or native provider is available, or no remote fallback is currently needed.', 'ip-location-block' ),
			array(),
			array( $provider_action ),
			true
		);

		$quota = isset( $provider['quota'] ) && is_array( $provider['quota'] ) ? $provider['quota'] : null;
		$blocked_quota = $quota && in_array( $quota['status'], array( 'exhausted', 'rate_limited', 'key_upgrade_required' ), true );
		$quota_actions = array();
		if ( $blocked_quota ) {
			$quota_actions[] = self::external_action(
				'key_upgrade_required' === $quota['status']
					? __( 'Upgrade API key', 'ip-location-block' )
					: __( 'Manage account', 'ip-location-block' ),
				'key_upgrade_required' === $quota['status']
					? 'https://app.iplocationblock.com/upgrade-api-key'
					: ( ! empty( $quota['accountUrl'] ) ? $quota['accountUrl'] : 'https://app.iplocationblock.com/login' )
			);
		}
		self::add_check(
			$checks,
			'native-provider-quota',
			$blocked_quota ? ( $provider['ready'] ? 'warning' : 'critical' ) : 'pass',
			'providers',
			__( 'IP Location Block provider quota', 'ip-location-block' ),
			$blocked_quota
				? ( ! empty( $quota['message'] ) ? $quota['message'] : __( 'The provider cannot currently answer requests.', 'ip-location-block' ) )
				: __( 'The native provider has no blocking quota issue.', 'ip-location-block' ),
			array(),
			$quota_actions
		);
	}

	private static function add_rule_checks( &$checks, $settings, $provider, $updating ) {
		$rule_action = self::settings_action(
			__( 'Review validation rules', 'ip-location-block' ),
			'validation-rule',
			0
		);
		$matching = isset( $settings['matching_rule'] ) ? (int) $settings['matching_rule'] : -1;
		$downloading = false !== $updating && 'done' !== $updating;

		self::add_check(
			$checks,
			'matching-rule',
			-1 === $matching ? ( $downloading ? 'info' : 'warning' ) : 'pass',
			'rules',
			__( 'Country matching rule', 'ip-location-block' ),
			-1 === $matching
				? ( $downloading
					? __( 'Local geolocation databases are being downloaded. Select a matching rule after the update completes.', 'ip-location-block' )
					: __( 'No country matching rule is selected, so country blocking is not configured.', 'ip-location-block' ) )
				: __( 'A country matching rule is configured.', 'ip-location-block' ),
			array(),
			array( $rule_action )
		);

		$feature     = '';
		$rule_values = '';
		if ( $matching >= 0 ) {
			$key         = 0 === $matching ? 'white_list' : 'black_list';
			$rule_values = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
			if ( false !== strpos( $rule_values, ':City:' ) ) {
				$feature = 'city';
			} elseif ( false !== strpos( $rule_values, ':State:' ) ) {
				$feature = 'state';
			} elseif ( false !== strpos( $rule_values, ':' ) ) {
				$feature = 'city';
			} elseif ( false !== stripos( $rule_values, 'AS' ) ) {
				$feature = 'asn';
			}
		}
		$unsupported = array();
		if ( $feature ) {
			foreach ( $provider['active'] as $name ) {
				if ( ! IP_Location_Block_Provider::supports( $name, 'asn' === $feature ? array( 'asn', 'asn_database' ) : $feature ) ) {
					$unsupported[] = $name;
				}
			}
		}
		self::add_check(
			$checks,
			'rule-provider-capability',
			! empty( $unsupported ) ? 'warning' : 'pass',
			'rules',
			__( 'Rule and provider compatibility', 'ip-location-block' ),
			! empty( $unsupported )
				? sprintf( __( 'The active providers listed below do not support the %s-level rules currently configured.', 'ip-location-block' ), $feature )
				: __( 'Configured country rules are supported by the active providers.', 'ip-location-block' ),
			$unsupported,
			array( $rule_action, self::settings_action( __( 'Review providers', 'ip-location-block' ), 'provider', 4 ) )
		);

		$invalid       = array();
		$asn_lists     = array();
		$extra         = isset( $settings['extra_ips'] ) && is_array( $settings['extra_ips'] ) ? $settings['extra_ips'] : array();
		$list_labels   = array(
			'white_list' => __( 'Whitelist of extra IPs', 'ip-location-block' ),
			'black_list' => __( 'Blacklist of extra IPs', 'ip-location-block' ),
		);
		foreach ( $list_labels as $key => $label ) {
			$values = empty( $extra[ $key ] ) ? array() : IP_Location_Block_Util::multiexplode( array( ',', "\n" ), $extra[ $key ] );
			foreach ( $values as $raw ) {
				$value = trim( $raw );
				if ( '' === $value ) {
					continue;
				}
				$parts = explode( '/', $value, 2 );
				$host  = $parts[0];
				$is_asn = ! empty( $settings['use_asn'] ) && preg_match( '/^AS\d+$/i', $host );
				$is_v4  = filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
				$is_v6  = filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
				$mask_ok = true;
				if ( isset( $parts[1] ) ) {
					$mask_ok = ctype_digit( $parts[1] ) && (int) $parts[1] <= ( $is_v6 ? 128 : 32 );
				}
				if ( ! ( $is_asn || $is_v4 || $is_v6 ) || ! $mask_ok ) {
					$invalid[] = sprintf( '%1$s: %2$s', $label, $value );
				}
				if ( $is_asn ) {
					$asn_lists[ $key ] = $label;
				}
			}
		}
		self::add_check(
			$checks,
			'extra-ip-rule-format',
			! empty( $invalid ) ? 'warning' : 'pass',
			'rules',
			__( 'Extra IP rule format', 'ip-location-block' ),
			! empty( $invalid )
				? __( 'One or more extra IP rules are invalid and may not be applied.', 'ip-location-block' )
				: __( 'Extra IP allow and block rules use valid formats.', 'ip-location-block' ),
			$invalid,
			array( $rule_action )
		);

		$asn_unsupported = array();
		if ( ! empty( $asn_lists ) ) {
			foreach ( $provider['active'] as $name ) {
				if ( ! IP_Location_Block_Provider::supports( $name, array( 'asn', 'asn_database' ) ) ) {
					$asn_unsupported[] = $name;
				}
			}
		}
		self::add_check(
			$checks,
			'extra-asn-provider-support',
			! empty( $asn_unsupported ) ? 'warning' : 'pass',
			'rules',
			__( 'ASN rule support', 'ip-location-block' ),
			! empty( $asn_unsupported )
				? __( 'ASN rules are configured, but the providers listed below cannot resolve ASN data.', 'ip-location-block' )
				: __( 'Configured ASN rules are supported by the active providers.', 'ip-location-block' ),
			$asn_unsupported,
			array( $rule_action, self::settings_action( __( 'Review providers', 'ip-location-block' ), 'provider', 4 ) )
		);
	}

	private static function add_lockout_check( &$checks, $settings, $updating ) {
		$status  = 'pass';
		$message = __( 'The current administrator IP is not expected to be blocked at login.', 'ip-location-block' );
		$actions = array();
		$details = array();

		if ( false === $updating && ! empty( $settings['validation']['login'] ) ) {
			$validate = IP_Location_Block::get_instance()->validate_ip( 'login', $settings, true, false );
			$result   = is_array( $validate ) && isset( $validate['result'] ) ? $validate['result'] : '';
			if ( 'limited' === $result ) {
				$status  = 'critical';
				$message = __( 'If you log out now, the current IP may be unable to log in because the failed-login limit has been reached.', 'ip-location-block' );
				$actions[] = self::statistics_action( __( 'Review this IP in cache', 'ip-location-block' ) );
			} elseif ( in_array( $result, array( 'blocked', 'extra' ), true ) ) {
				$status  = 'critical';
				$message = ! empty( $settings['matching_rule'] )
					? __( 'If you log out now, the current country code or IP address is blocked by the blacklist.', 'ip-location-block' )
					: __( 'If you log out now, the current country code or IP address is not allowed by the whitelist.', 'ip-location-block' );
				if ( isset( $validate['code'] ) && 'ZZ' === $validate['code'] ) {
					$actions[] = self::settings_action( __( 'Review local databases', 'ip-location-block' ), 'database', 5 );
					$actions[] = self::statistics_action( __( 'Review this IP in cache', 'ip-location-block' ) );
				} else {
					$actions[] = self::settings_action( __( 'Review validation rules', 'ip-location-block' ), 'validation-rule', 0 );
				}
			}
			if ( isset( $validate['code'] ) && '' !== $validate['code'] ) {
				$details[] = sprintf( __( 'Detected country code: %s', 'ip-location-block' ), $validate['code'] );
			}
		} elseif ( false !== $updating ) {
			$status  = 'info';
			$message = __( 'The self-lockout check is paused while local databases are updating.', 'ip-location-block' );
		}

		self::add_check(
			$checks,
			'current-ip-lockout',
			$status,
			'security',
			__( 'Logout and login safety', 'ip-location-block' ),
			$message,
			$details,
			$actions
		);
	}

	private static function add_compatibility_checks( &$checks, $settings ) {
		$login_link = isset( $settings['login_link'] ) && is_array( $settings['login_link'] ) ? $settings['login_link'] : array();
		$configured = ! empty( $login_link['link'] );
		$outdated = $configured && ! IP_Location_Block_Util::verify_link(
			$login_link['link'],
			isset( $login_link['hash'] ) ? $login_link['hash'] : ''
		);
		self::add_check(
			$checks,
			'emergency-login-link',
			$outdated ? 'warning' : 'pass',
			'security',
			__( 'Emergency login link', 'ip-location-block' ),
			$outdated
				? __( 'The emergency login link is outdated. Delete it, generate a new one, and update saved bookmarks.', 'ip-location-block' )
				: ( $configured
					? __( 'The emergency login link is configured and current.', 'ip-location-block' )
					: __( 'An emergency login link has not been configured.', 'ip-location-block' ) ),
			array(),
			$outdated ? array( self::settings_action( __( 'Manage emergency login link', 'ip-location-block' ), 'others', 7 ) ) : array()
		);

		$database_schedule = wp_next_scheduled( IP_Location_Block::CRON_NAME, array( false ) );
		$database_missing  = ! empty( $settings['update']['auto'] ) && ! $database_schedule;
		self::add_check(
			$checks,
			'database-update-schedule',
			$database_missing ? 'warning' : 'pass',
			'system',
			__( 'Local database update schedule', 'ip-location-block' ),
			$database_missing
				? __( 'Automatic database updates are enabled, but their WP-Cron event is missing.', 'ip-location-block' )
				: __( 'The local database update schedule is available or automatic updates are disabled.', 'ip-location-block' ),
			array(),
			array( self::settings_action( __( 'Review local databases', 'ip-location-block' ), 'database', 5 ) )
		);

		$cleanup_missing = ! wp_next_scheduled( IP_Location_Block::CACHE_NAME );
		self::add_check(
			$checks,
			'cache-cleanup-schedule',
			$cleanup_missing ? 'warning' : 'pass',
			'system',
			__( 'Cache and log cleanup schedule', 'ip-location-block' ),
			$cleanup_missing
				? __( 'The WP-Cron event that removes expired cache and log records is missing.', 'ip-location-block' )
				: __( 'Expired cache and log records have a scheduled cleanup event.', 'ip-location-block' ),
			array(),
			array( self::settings_action( __( 'Review recording settings', 'ip-location-block' ), 'recording', 3 ) )
		);

		$timing_conflict = ! empty( $settings['validation']['timing'] ) && is_plugin_active( 'ip-geo-allow/index.php' );
		self::add_check(
			$checks,
			'ip-geo-allow-timing',
			$timing_conflict ? 'warning' : 'pass',
			'compatibility',
			__( 'Validation timing compatibility', 'ip-location-block' ),
			$timing_conflict
				? __( 'The mu-plugin validation timing is incompatible with IP Geo Allow. Select the init action hook.', 'ip-location-block' )
				: __( 'No validation-timing plugin conflict was detected.', 'ip-location-block' ),
			array(),
			array( self::settings_action( __( 'Review validation timing', 'ip-location-block' ), 'validation-rule', 0 ) )
		);

		$cache_host = '';
		$article    = '';
		if ( ! empty( $settings['validation']['public'] ) && ( (int) $settings['validation']['public'] & 1 ) ) {
			if ( function_exists( 'is_wpe' ) && '1' === (string) is_wpe() ) {
				$cache_host = 'WP Engine';
				$article    = 'https://iplocationblock.com/codex/compatibility-with-wpengine/';
			} elseif ( isset( $_SERVER['KINSTA_CACHE_ZONE'] ) ) {
				$cache_host = 'Kinsta';
				$article    = 'https://iplocationblock.com/codex/compatibility-with-kinsta/';
			}
		}
		self::add_check(
			$checks,
			'full-page-cache-conflict',
			$cache_host ? 'warning' : 'pass',
			'compatibility',
			__( 'Full-page cache compatibility', 'ip-location-block' ),
			$cache_host
				? sprintf( __( '%s server-level page caching can run before PHP and bypass front-end geolocation checks.', 'ip-location-block' ), $cache_host )
				: __( 'No known server-level cache conflict was detected.', 'ip-location-block' ),
			array(),
			$article ? array( self::external_action( __( 'Read compatibility guide', 'ip-location-block' ), $article ) ) : array(),
			true
		);
	}

	private static function emergency_access_status( $settings ) {
		$link       = isset( $settings['login_link'] ) && is_array( $settings['login_link'] ) ? $settings['login_link'] : array();
		$configured = ! empty( $link['link'] );
		$valid      = $configured && ! empty( $link['hash'] ) && IP_Location_Block_Util::verify_link( $link['link'], $link['hash'] );
		$state      = ! $configured ? 'not_configured' : ( $valid ? 'ready' : 'outdated' );

		return array(
			'state'      => $state,
			'configured' => (bool) $configured,
			'valid'      => $configured ? (bool) $valid : null,
			'manage'     => self::settings_action( __( 'Manage emergency access', 'ip-location-block' ), 'others', 7 ),
		);
	}

	private static function add_check( &$checks, $id, $status, $category, $title, $message, $details = array(), $actions = array(), $acknowledgeable = false ) {
		$checks[] = array(
			'id'              => sanitize_key( $id ),
			'status'          => $status,
			'category'        => $category,
			'title'           => wp_strip_all_tags( $title ),
			'message'         => wp_strip_all_tags( $message ),
			'details'         => array_values( array_map( 'strval', is_array( $details ) ? $details : array() ) ),
			'actions'         => array_values( is_array( $actions ) ? $actions : array() ),
			'acknowledgeable' => (bool) $acknowledgeable && 'pass' !== $status,
			'acknowledged'    => false,
		);
	}

	private static function finalize( $checks ) {
		$acknowledged = self::acknowledgements();
		$counts       = array(
			'critical'     => 0,
			'warning'      => 0,
			'info'         => 0,
			'passed'       => 0,
			'acknowledged' => 0,
		);

		foreach ( $checks as &$check ) {
			$check['acknowledged'] = $check['acknowledgeable'] && isset( $acknowledged[ $check['id'] ] );
			if ( 'pass' === $check['status'] ) {
				$counts['passed']++;
			} elseif ( $check['acknowledged'] ) {
				$counts['acknowledged']++;
			} elseif ( isset( $counts[ $check['status'] ] ) ) {
				$counts[ $check['status'] ]++;
			}
		}
		unset( $check );

		$status = $counts['critical'] ? 'critical' : ( $counts['warning'] ? 'warning' : 'healthy' );

		return array(
			'status'    => $status,
			'counts'    => $counts,
			'checkedAt' => gmdate( 'c' ),
			'checks'    => array_values( $checks ),
		);
	}

	private static function acknowledgements() {
		$stored = get_option( self::ACK_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$settings = IP_Location_Block::get_option();
		if ( ! empty( $settings['cache_compat_dismissed'] ) ) {
			$stored['full-page-cache-conflict'] = true;
		}

		return $stored;
	}

	private static function acknowledgeable_ids() {
		return array(
			'native-mixed-providers',
			'external-only-provider',
			'full-page-cache-conflict',
		);
	}

	private static function settings_action( $label, $section, $classic_section ) {
		return array(
			'type'   => 'internal',
			'label'  => $label,
			'target' => array(
				'tab'     => 'settings',
				'view'    => 'advanced',
				'section' => $section,
			),
			'url'    => self::classic_url( 0, $classic_section ),
		);
	}

	private static function statistics_action( $label ) {
		return array(
			'type'   => 'internal',
			'label'  => $label,
			'target' => array(
				'tab' => 'statistics',
				's'   => IP_Location_Block::get_ip_address(),
			),
			'url'    => self::classic_url( 1, 2 ),
		);
	}

	private static function classic_settings_action( $label, $section ) {
		return array(
			'type'  => 'classic',
			'label' => $label,
			'url'   => self::classic_url( 0, $section ),
		);
	}

	private static function external_action( $label, $url ) {
		return array(
			'type'  => 'external',
			'label' => $label,
			'url'   => esc_url_raw( $url ),
		);
	}

	private static function classic_url( $tab, $section ) {
		$settings = IP_Location_Block::get_option();
		$network  = is_multisite() && ! empty( $settings['network_wide'] );
		$base     = $network ? network_admin_url( 'admin.php' ) : admin_url( 'options-general.php' );
		$url      = add_query_arg(
			array(
				'page' => IP_Location_Block::PLUGIN_NAME,
				'tab'  => (int) $tab,
				'sec'  => (int) $section,
			),
			$base
		);

		return esc_url_raw( $url . '#' . IP_Location_Block::PLUGIN_NAME . '-section-' . (int) $section );
	}
}
