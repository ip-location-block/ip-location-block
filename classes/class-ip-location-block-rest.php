<?php
/**
 * IP Location Block - REST API (v1)
 *
 * Backs the React (Beta) admin. Routes are registered on `rest_api_init`
 * (this class is loaded on every request, unlike the admin class which is
 * is_admin()-only). Auth is the standard `wp_rest` nonce (validated by WP
 * core cookie auth before the permission_callback) plus a capability check.
 * Every handler delegates to an existing service method so business logic
 * is unchanged.
 *
 * @package IP_Location_Block
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class IP_Location_Block_Rest {

	const NS = 'ip-location-block/v1';

	/**
	 * Capability gate shared by every route. The `wp_rest` nonce is checked
	 * by WordPress before this runs; here we only assert the capability.
	 *
	 * @return bool
	 */
	public static function permission() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_network_options' );
	}

	/**
	 * Register all v1 routes.
	 */
	public static function register_routes() {
		$perm = array( __CLASS__, 'permission' );

		// Settings (read + save).
		register_rest_route( self::NS, '/settings', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_settings' ),
				'permission_callback' => $perm,
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'update_settings' ),
				'permission_callback' => $perm,
			),
		) );

		register_rest_route( self::NS, '/geolocation/search', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'search_geolocation' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/settings/defaults', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_defaults' ),
			'permission_callback' => $perm,
		) );

		// Actions.
		register_rest_route( self::NS, '/database/update', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'update_database' ),
			'permission_callback' => $perm,
		) );

		// Statistics.
		register_rest_route( self::NS, '/statistics', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_statistics' ),
				'permission_callback' => $perm,
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'clear_statistics' ),
				'permission_callback' => $perm,
			),
		) );

		// Dynamic option data for the "advanced" settings fields (read-only).
		register_rest_route( self::NS, '/providers', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_providers' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/database/status', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_database_status' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/content', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_content' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/exceptions', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_exceptions' ),
			'permission_callback' => $perm,
		) );

		// Validation logs.
		register_rest_route( self::NS, '/logs', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_logs' ),
				'permission_callback' => $perm,
				'args'                => array(
					'hook' => array( 'type' => 'string', 'required' => false ),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'clear_logs' ),
				'permission_callback' => $perm,
				'args'                => array(
					'hook' => array( 'type' => 'string', 'required' => false ),
				),
			),
		) );

		register_rest_route( self::NS, '/logs/entries', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'delete_log_entries' ),
			'permission_callback' => $perm,
		) );

		// Append selected IPs/ASNs to the extra-IP white/black list.
		register_rest_route( self::NS, '/list', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'add_to_list' ),
			'permission_callback' => $perm,
		) );

		// Per-blog blocked counts (network admin only).
		register_rest_route( self::NS, '/network/stats', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_network_stats' ),
			'permission_callback' => $perm,
		) );
	}

	/* -----------------------------------------------------------------------
	 * Handlers — each delegates to an existing static service method.
	 * --------------------------------------------------------------------- */

	public static function get_settings() {
		return rest_ensure_response( IP_Location_Block::get_option() );
	}

	/**
	 * Save settings. The React app POSTs the full settings object, which is run
	 * through the exact classic sanitizer for parity. Reusing the admin instance
	 * is safe here: `init` has already fired by the time this REST callback runs,
	 * so the admin constructor's `init` hook is a no-op (admin_init never runs).
	 */
	public static function update_settings( WP_REST_Request $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			return new WP_Error(
				'ilb_invalid_settings',
				__( 'Invalid settings payload.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( 'IP_Location_Block_Admin', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/class-ip-location-block-admin.php';
		}

		// Mirror validate_settings() minus the options-page nonce (REST auth
		// already gated this request via the wp_rest nonce + capability).
		$options = IP_Location_Block_Admin::get_instance()->sanitize_options( $input );

		// Providers: the classic sanitize is presence-based (built for the
		// partial form POST) and does not fit a full-object REST payload, so
		// honor the React map directly. Stored value: '' (off), '@' (on, no
		// key) or the API key. get_valid_providers() only checks !empty().
		if ( isset( $input['providers'] ) && is_array( $input['providers'] ) ) {
			$providers = array();
			foreach ( $input['providers'] as $name => $val ) {
				$providers[ (string) $name ] = is_string( $val )
					? sanitize_text_field( $val )
					: ( $val ? '@' : '' );
			}
			$options['providers'] = $providers;
		}

		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-opts.php';
		$file = IP_Location_Block_Opts::setup_validation_timing( $options );
		if ( is_wp_error( $file ) ) {
			$options['validation']['timing'] = 0;
		}

		delete_transient( IP_Location_Block::CRON_NAME );
		do_action( 'ip-location-block-settings-updated', $options, true );

		IP_Location_Block::update_option( $options );

		return rest_ensure_response( IP_Location_Block::get_option() );
	}

	/**
	 * External geolocation providers (with API-key support) + current selection.
	 * Internal/no-key providers (e.g. Cache) are omitted — they are managed
	 * elsewhere and default-on.
	 */
	public static function get_providers() {
		$settings = IP_Location_Block::get_option();
		$stored = isset( $settings['providers'] ) ? (array) $settings['providers'] : array();
		$all = IP_Location_Block_Provider::all();

		$out = array();
		foreach ( IP_Location_Block_Provider::get_providers( 'key', false, false, true ) as $name => $keyfield ) {
			if ( null === $keyfield ) {
				continue; // internal / no API key
			}
			$meta = isset( $all[ $name ] ) ? $all[ $name ] : array();
			$out[] = array(
				'name'     => $name,
				'value'    => isset( $stored[ $name ] ) ? $stored[ $name ] : '',
				'link'     => isset( $meta['link'] ) ? (string) $meta['link'] : '',
				'type'     => isset( $meta['type'] ) ? (string) $meta['type'] : '',
				'supports' => array(
					'ipv4' => (bool) IP_Location_Block_Provider::supports( $name, 'ipv4' ),
					'ipv6' => (bool) IP_Location_Block_Provider::supports( $name, 'ipv6' ),
					'asn'  => (bool) IP_Location_Block_Provider::supports( $name, array( 'asn', 'asn_database' ) ),
					'city' => (bool) IP_Location_Block_Provider::supports( $name, array( 'city' ) ),
				),
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Local database file status (read-only): path + whether the file exists +
	 * last-update timestamp, per configured provider.
	 */
	public static function get_database_status() {
		$s = IP_Location_Block::get_option();
		$rows = array();

		$entries = array(
			array( __( 'IP2Location IPv4', 'ip-location-block' ), 'IP2Location', 'ipv4_path', 'ipv4_last' ),
			array( __( 'IP2Location IPv6', 'ip-location-block' ), 'IP2Location', 'ipv6_path', 'ipv6_last' ),
			array( __( 'GeoLite2 (IP)', 'ip-location-block' ), 'GeoLite2', 'ip_path', 'ip_last' ),
			array( __( 'GeoLite2 (ASN)', 'ip-location-block' ), 'GeoLite2', 'asn_path', 'asn_last' ),
		);
		foreach ( $entries as $e ) {
			list( $label, $prov, $pathKey, $lastKey ) = $e;
			$path = isset( $s[ $prov ][ $pathKey ] ) ? $s[ $prov ][ $pathKey ] : '';
			$last = isset( $s[ $prov ][ $lastKey ] ) ? (int) $s[ $prov ][ $lastKey ] : 0;
			$rows[] = array(
				'label'  => $label,
				'path'   => $path,
				'exists' => $path && @file_exists( $path ),
				'last'   => $last ? date_i18n( get_option( 'date_format' ), $last ) : '',
			);
		}

		return rest_ensure_response( $rows );
	}

	/**
	 * WP content available as front-end blocking targets
	 * (public.target_pages / target_posts / target_cates / target_tags).
	 */
	public static function get_content() {
		$pages = array();
		foreach ( get_pages() as $p ) {
			$pages[] = array( 'value' => $p->post_name, 'label' => $p->post_title );
		}

		$posts = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			$posts[] = array( 'value' => $pt->name, 'label' => $pt->label );
		}

		$cates = array();
		foreach ( get_categories( array( 'hide_empty' => false ) ) as $c ) {
			$cates[] = array( 'value' => $c->slug, 'label' => $c->name );
		}

		$tags = array();
		foreach ( get_tags( array( 'hide_empty' => false ) ) as $t ) {
			$tags[] = array( 'value' => $t->slug, 'label' => $t->name );
		}

		return rest_ensure_response( compact( 'pages', 'posts', 'cates', 'tags' ) );
	}

	/**
	 * Installed plugins/themes available as validation exceptions
	 * (exception.plugins / exception.themes).
	 */
	public static function get_exceptions() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = (array) get_option( 'active_plugins', array() );
		$plugins = array();
		foreach ( get_plugins() as $file => $data ) {
			$slug = dirname( $file );
			$plugins[] = array(
				'value'  => '.' === $slug ? $file : $slug,
				'label'  => $data['Name'],
				'active' => in_array( $file, $active, true ),
			);
		}

		$themes = array();
		foreach ( wp_get_themes() as $slug => $theme ) {
			$themes[] = array(
				'value'  => $slug,
				'label'  => $theme->get( 'Name' ),
				'active' => $slug === get_stylesheet(),
			);
		}

		return rest_ensure_response( compact( 'plugins', 'themes' ) );
	}

	public static function get_defaults() {
		return rest_ensure_response( IP_Location_Block::get_default() );
	}

	/**
	 * Geolocation lookup for the Search tab. search_ip() reads $_POST['ip'],
	 * so populate it before delegating.
	 */
	public static function search_geolocation( WP_REST_Request $request ) {
		$provider = sanitize_text_field( (string) $request->get_param( 'provider' ) );
		$_POST['ip'] = sanitize_text_field( (string) $request->get_param( 'ip' ) );

		if ( ! class_exists( 'IP_Location_Block_Admin_Ajax', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';
		}

		return rest_ensure_response( IP_Location_Block_Admin_Ajax::search_ip( $provider ) );
	}

	/**
	 * Per-blog blocked counts across the network (network admin only).
	 * Maps IP_Location_Block_Admin_Ajax::restore_network() into flat rows.
	 */
	public static function get_network_stats( WP_REST_Request $request ) {
		if ( ! is_multisite() ) {
			return rest_ensure_response( array() );
		}

		$duration = (int) $request->get_param( 'duration' ); // 0=all,1=hour,2=day,3=week,4=month
		$offset   = (int) $request->get_param( 'offset' );
		$length   = $request->get_param( 'length' );
		$length   = null === $length ? 100 : (int) $length;

		if ( ! class_exists( 'IP_Location_Block_Admin_Ajax', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$hooks = array( 'comment', 'xmlrpc', 'login', 'admin', 'public' );
		$rows  = array();
		foreach ( IP_Location_Block_Admin_Ajax::restore_network( $duration, $offset, $length, false ) as $site => $counts ) {
			$row   = array( 'site' => (string) $site );
			$total = 0;
			foreach ( $hooks as $h ) {
				$v = isset( $counts[ $h ] ) ? (int) $counts[ $h ] : 0;
				$row[ $h ] = $v;
				$total    += $v;
			}
			$row['total'] = $total;
			$row['link']  = isset( $counts['link'] ) ? (string) $counts['link'] : '';
			$rows[]       = $row;
		}

		return rest_ensure_response( $rows );
	}

	/**
	 * Trigger a local-database download/update.
	 */
	public static function update_database() {
		return rest_ensure_response( IP_Location_Block::get_instance()->exec_update_db() );
	}

	public static function get_statistics() {
		$s = IP_Location_Block_Logs::restore_stat();

		// countries: { code: count } -> [ {code, count} ] desc
		$countries = array();
		if ( ! empty( $s['countries'] ) && is_array( $s['countries'] ) ) {
			arsort( $s['countries'] );
			foreach ( $s['countries'] as $code => $count ) {
				$countries[] = array( 'code' => (string) $code, 'count' => (int) $count );
			}
		}

		// daystats: { time: { comment,xmlrpc,login,admin,public } } -> [ {date, …, total} ] asc
		$hooks = array( 'comment', 'xmlrpc', 'login', 'admin', 'public' );
		$daily = array();
		if ( ! empty( $s['daystats'] ) && is_array( $s['daystats'] ) ) {
			ksort( $s['daystats'] );
			foreach ( $s['daystats'] as $time => $counts ) {
				$row = array( 'date' => (int) $time );
				$total = 0;
				foreach ( $hooks as $h ) {
					$v = isset( $counts[ $h ] ) ? (int) $counts[ $h ] : 0;
					$row[ $h ] = $v;
					$total += $v;
				}
				$row['total'] = $total;
				$daily[] = $row;
			}
		}

		return rest_ensure_response( array(
			'blocked'   => isset( $s['blocked'] ) ? (int) $s['blocked'] : 0,
			'unknown'   => isset( $s['unknown'] ) ? (int) $s['unknown'] : 0,
			'ipv4'      => isset( $s['IPv4'] ) ? (int) $s['IPv4'] : 0,
			'ipv6'      => isset( $s['IPv6'] ) ? (int) $s['IPv6'] : 0,
			'countries' => $countries,
			'daily'     => $daily,
		) );
	}

	public static function clear_statistics() {
		IP_Location_Block_Logs::clear_stat();

		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function get_logs( WP_REST_Request $request ) {
		$hook = $request->get_param( 'hook' );
		$rows = IP_Location_Block_Logs::restore_logs( $hook ? $hook : null );

		// restore_logs() returns raw positional rows:
		// [ No, hook, time, ip, code, city, state, result, asn, method, ua, headers, data ]
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'     => (int) $r[0],
				'target' => $r[1],
				'time'   => (int) $r[2],
				'ip'     => $r[3],
				'code'   => $r[4],
				'city'   => $r[5],
				'state'  => $r[6],
				'result' => $r[7],
				'asn'    => $r[8],
				'method' => $r[9],
				'ua'     => $r[10],
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Append IP/CIDR/ASN values to the extra-IP white or black list
	 * (bulk whitelist/blacklist from the Logs tab).
	 */
	public static function add_to_list( WP_REST_Request $request ) {
		$key = 'white' === $request->get_param( 'list' ) ? 'white_list' : 'black_list';
		$values = (array) $request->get_param( 'values' );

		$settings = IP_Location_Block::get_option();
		$current = isset( $settings['extra_ips'][ $key ] ) ? $settings['extra_ips'][ $key ] : '';
		$entries = array_filter( array_map( 'trim', preg_split( '/[,\r\n]+/', (string) $current ) ) );

		foreach ( $values as $v ) {
			$v = sanitize_text_field( $v );
			if ( '' !== $v && ! in_array( $v, $entries, true ) ) {
				$entries[] = $v;
			}
		}

		if ( ! isset( $settings['extra_ips'] ) || ! is_array( $settings['extra_ips'] ) ) {
			$settings['extra_ips'] = array();
		}
		$settings['extra_ips'][ $key ] = implode( ',', $entries );
		IP_Location_Block::update_option( $settings );

		return rest_ensure_response( array( 'success' => true, 'list' => $settings['extra_ips'][ $key ] ) );
	}

	/**
	 * Delete validation-log entries by IP address (bulk erase).
	 */
	public static function delete_log_entries( WP_REST_Request $request ) {
		$ips = array_map( 'sanitize_text_field', (array) $request->get_param( 'ips' ) );
		IP_Location_Block_Logs::delete_logs_entry( $ips );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function clear_logs( WP_REST_Request $request ) {
		$hook = $request->get_param( 'hook' );
		IP_Location_Block_Logs::clear_logs( $hook ? $hook : null );

		return rest_ensure_response( array( 'success' => true ) );
	}
}
