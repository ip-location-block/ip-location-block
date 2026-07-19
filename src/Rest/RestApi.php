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

namespace IPLocationBlock\Rest;

class RestApi {

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
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_settings' ),
				'permission_callback' => $perm,
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'update_settings' ),
				'permission_callback' => $perm,
			),
		) );

		register_rest_route( self::NS, '/geolocation/search', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'search_geolocation' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/settings/defaults', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_defaults' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/settings/context', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_settings_context' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/settings/export', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'export_settings' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/settings/import', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'import_settings' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/settings/legacy', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_legacy_settings' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/emergency-login-link', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'generate_emergency_login_link' ),
				'permission_callback' => $perm,
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_emergency_login_link' ),
				'permission_callback' => $perm,
			),
		) );

		// Actions.
		register_rest_route( self::NS, '/database/update', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'update_database' ),
			'permission_callback' => $perm,
		) );

		// Statistics.
		register_rest_route( self::NS, '/statistics', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_statistics' ),
				'permission_callback' => $perm,
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'clear_statistics' ),
				'permission_callback' => $perm,
			),
		) );

		register_rest_route( self::NS, '/statistics/logs', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_log_statistics' ),
			'permission_callback' => $perm,
		) );

		// IP cache browser and actions used by the Statistics tab.
		register_rest_route( self::NS, '/cache', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_cache' ),
				'permission_callback' => $perm,
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'clear_cache' ),
				'permission_callback' => $perm,
			),
		) );

		register_rest_route( self::NS, '/cache/entries', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'delete_cache_entries' ),
			'permission_callback' => $perm,
		) );

		// Dynamic option data for the "advanced" settings fields (read-only).
		register_rest_route( self::NS, '/providers', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_providers' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/providers/status', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_provider_status' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/providers/test', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'test_provider' ),
			'permission_callback' => $perm,
			'args'                => array(
				'provider'   => array( 'type' => 'string', 'required' => true ),
				'credential' => array( 'type' => 'string', 'required' => false ),
			),
		) );
		register_rest_route( self::NS, '/database/status', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_database_status' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/content', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_content' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/exceptions', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_exceptions' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/exceptions/detected', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_detected_exceptions' ),
			'permission_callback' => $perm,
			'args'                => array(
				'target' => array( 'type' => 'string', 'required' => true ),
			),
		) );

		// Validation logs.
		register_rest_route( self::NS, '/logs', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_logs' ),
				'permission_callback' => $perm,
				'args'                => array(
					'hook' => array( 'type' => 'string', 'required' => false ),
				),
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'clear_logs' ),
				'permission_callback' => $perm,
				'args'                => array(
					'hook' => array( 'type' => 'string', 'required' => false ),
				),
			),
		) );

		register_rest_route( self::NS, '/logs/entries', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'delete_log_entries' ),
			'permission_callback' => $perm,
		) );

		register_rest_route( self::NS, '/logs/live', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'live_logs' ),
			'permission_callback' => $perm,
			'args'                => array(
				'action' => array( 'type' => 'string', 'required' => true ),
				'hook'   => array( 'type' => 'string', 'required' => false ),
			),
		) );
		register_rest_route( self::NS, '/logs/live/reset', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'reset_live_logs' ),
			'permission_callback' => $perm,
		) );

		// Append selected IPs/ASNs to the extra-IP white/black list.
		register_rest_route( self::NS, '/list', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'add_to_list' ),
			'permission_callback' => $perm,
		) );

		// Per-blog blocked counts (network admin only).
		register_rest_route( self::NS, '/network/stats', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_network_stats' ),
			'permission_callback' => $perm,
		) );

		// Dismiss an admin notice (the classic handler lives in admin.js, which
		// the Beta screen does not load).
		register_rest_route( self::NS, '/notices/dismiss', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'dismiss_notice' ),
			'permission_callback' => $perm,
		) );

		// Structured health checks for the React Diagnostics page.
		register_rest_route( self::NS, '/diagnostics', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_diagnostics' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/diagnostics/acknowledgements', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'set_diagnostic_acknowledgement' ),
			'permission_callback' => $perm,
			'args'                => array(
				'id'           => array( 'type' => 'string', 'required' => true ),
				'acknowledged' => array( 'type' => 'boolean', 'required' => true ),
			),
		) );
		register_rest_route( self::NS, '/diagnostics/environment', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_diagnostic_environment' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/diagnostics/database-tables', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'diagnose_database_tables' ),
			'permission_callback' => $perm,
		) );
		register_rest_route( self::NS, '/attributions', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_attributions' ),
			'permission_callback' => $perm,
		) );

		// Scan the current IP against every enabled provider (country verdicts).
		register_rest_route( self::NS, '/geolocation/scan', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'scan_country' ),
			'permission_callback' => $perm,
			'args'                => array(
				'source' => array( 'type' => 'string', 'required' => false ),
			),
		) );

		// Geolocation "mode": Native (IP Location Block only) vs Standard.
		register_rest_route( self::NS, '/geolocation/mode', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_mode' ),
			'permission_callback' => $perm,
		) );

		// Compute a settings preset (default / preferred) without saving it.
		register_rest_route( self::NS, '/settings/preset', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'get_preset' ),
			'permission_callback' => $perm,
		) );
	}

	/**
	 * Persist dismissal of an admin notice. Mirrors the classic
	 * `dismiss-notice` admin-ajax command (the flag lives in the plugin's
	 * settings, so it is per-site rather than per-user).
	 */
	public static function dismiss_notice( \WP_REST_Request $request ) {
		$id = sanitize_text_field( (string) $request->get_param( 'id' ) );

		if ( 'welcome' !== $id ) {
			return new \WP_Error(
				'ilb_unknown_notice',
				__( 'Unknown notice.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$settings            = \IP_Location_Block::get_option();
		$settings['welcome'] = true;
		\IP_Location_Block::update_option( $settings );

		return rest_ensure_response( array( 'dismissed' => true ) );
	}

	/**
	 * Current site and current-IP health report.
	 */
	public static function get_diagnostics() {
		return rest_ensure_response( \IP_Location_Block_Diagnostics::run() );
	}

	/**
	 * Acknowledge or restore one non-critical advisory.
	 */
	public static function set_diagnostic_acknowledgement( \WP_REST_Request $request ) {
		$result = \IP_Location_Block_Diagnostics::set_acknowledgement(
			(string) $request->get_param( 'id' ),
			rest_sanitize_boolean( $request->get_param( 'acknowledged' ) )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( \IP_Location_Block_Diagnostics::run() );
	}

	/**
	 * Support information is intentionally separate from the lightweight
	 * diagnostics summary so it is only generated when requested.
	 */
	public static function get_diagnostic_environment() {
		return rest_ensure_response( \IP_Location_Block_Diagnostics::environment() );
	}

	/**
	 * Complete registered provider attribution catalog.
	 */
	public static function get_attributions() {
		return rest_ensure_response( \IP_Location_Block_Diagnostics::attributions() );
	}

	/**
	 * Country verdict from every enabled provider for the current IP.
	 * Reshapes \IP_Location_Block_Admin_Ajax::scan_country() into
	 * { ip, providers: [ { name, type, code } ] }.
	 */
	public static function scan_country( \WP_REST_Request $request ) {
		$source = sanitize_key( (string) $request->get_param( 'source' ) );
		$source = 'server' === $source ? 'server' : 'client';
		$settings = \IP_Location_Block::get_option();
		$ip = 'server' === $source
			? \IP_Location_Block_Util::get_server_ip()
			: \IP_Location_Block::get_ip_address( $settings );

		if ( empty( $ip ) || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return new \WP_Error(
				'ilb_scan_ip_unavailable',
				__( 'The requested IP address is unavailable.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$args       = \IP_Location_Block::get_request_headers( $settings );
		$types      = \IP_Location_Block_Provider::get_providers( 'type', false, false );
		$enabled    = \IP_Location_Block_Provider::get_valid_providers( $settings, false, false );
		$providers  = array();
		foreach ( $enabled as $name ) {
			$geo = \IP_Location_Block_API::get_instance( $name, $settings );
			if ( ! $geo ) {
				continue;
			}
			try {
				$result = $geo->get_location( $ip, $args );
			} catch ( \Exception $exception ) {
				$result = array( 'errorMessage' => $exception->getMessage() );
			}
			$providers[] = array(
				'name' => (string) $name,
				'type' => isset( $types[ $name ] ) ? (string) $types[ $name ] : '',
				'code' => false === $result
					? __( 'n/a', 'ip-location-block' )
					: ( ! empty( $result['errorMessage'] )
						? (string) $result['errorMessage']
						: ( ! empty( $result['countryCode'] ) ? (string) $result['countryCode'] : __( 'n/a', 'ip-location-block' ) ) ),
			);
		}

		return rest_ensure_response( array(
			'source'    => $source,
			'ip'        => (string) $ip,
			'providers' => $providers,
		) );
	}

	/**
	 * Geolocation "mode" signals for the precision upsell.
	 * Native = the IP Location Block provider is the only valid one.
	 */
	public static function get_mode() {
		$settings = \IP_Location_Block::get_option();
		$valid    = array_values( \IP_Location_Block_Provider::get_valid_providers( $settings, false, false, false ) );
		$stored   = isset( $settings['providers'] ) && is_array( $settings['providers'] ) ? $settings['providers'] : array();
		$key      = isset( $stored['IP Location Block'] ) ? $stored['IP Location Block'] : '';

		$others = array_values( array_filter( $valid, static function ( $name ) {
			return 'IP Location Block' !== $name && 'Cache' !== $name;
		} ) );

		return rest_ensure_response( array(
			'native'     => (bool) \IP_Location_Block_Provider::is_native( $settings ),
			'apiEnabled' => in_array( 'IP Location Block', $valid, true ),
			'apiKey'     => ( '' !== $key && '@' !== $key ),
			'others'     => $others,
		) );
	}

	/**
	 * Compute a settings preset WITHOUT saving it. The React app applies the
	 * returned object to the live form so the user can review and Save.
	 *  - default   : the plugin defaults.
	 *  - preferred : the "Best for Back-end" overrides merged over current.
	 */
	public static function get_preset( \WP_REST_Request $request ) {
		$preset = sanitize_key( (string) $request->get_param( 'preset' ) );

		if ( 'default' === $preset ) {
			return rest_ensure_response( self::public_settings( \IP_Location_Block::get_default() ) );
		}

		if ( 'preferred' === $preset ) {
			if ( ! class_exists( 'IP_Location_Block_Admin_Ajax', false ) ) {
				require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';
			}
			$merged = array_replace_recursive(
				\IP_Location_Block::get_option(),
				\IP_Location_Block_Admin_Ajax::preferred_overrides()
			);

			return rest_ensure_response( self::public_settings( $merged ) );
		}

		return new \WP_Error(
			'ilb_unknown_preset',
			__( 'Unknown preset.', 'ip-location-block' ),
			array( 'status' => 400 )
		);
	}

	/* -----------------------------------------------------------------------
	 * Handlers — each delegates to an existing static service method.
	 * --------------------------------------------------------------------- */

	public static function get_settings( \WP_REST_Request $request ) {
		$scope = self::request_scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}

		return rest_ensure_response( self::public_settings( \IP_Location_Block::get_option() ) );
	}

	/**
	 * Return capability and runtime data needed to render settings without
	 * exposing operational secrets such as the emergency-login hash.
	 */
	public static function get_settings_context( \WP_REST_Request $request ) {
		$scope = self::request_scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		$settings   = \IP_Location_Block::get_option();
		$login      = self::emergency_login_status( $settings );
		$server_ip  = \IP_Location_Block_Util::get_server_ip();
		$client_ip  = \IP_Location_Block::get_ip_address( $settings );
		$mime_types = array();

		foreach ( \IP_Location_Block_Util::get_allowed_mime_types() as $extension => $mime ) {
			$mime_types[] = array(
				'extension' => (string) $extension,
				'mime'      => (string) $mime,
			);
		}

		$legacy = get_option( 'ip_geo_block_settings' );
		$database_schedule = wp_next_scheduled( \IP_Location_Block::CRON_NAME, array( false ) );
		$cleanup_schedule  = wp_next_scheduled( \IP_Location_Block::CACHE_NAME );

		return rest_ensure_response( array(
			'emergencyLogin' => $login,
			'legacyMigration' => array(
				'available' => ! empty( $legacy ) && empty( $settings['migrated_from_legacy'] ),
			),
			'features' => array(
				'debug'               => self::debug_enabled(),
				'pdoSqlite'           => extension_loaded( 'pdo_sqlite' ),
				'serverScanAvailable' => ! empty( $server_ip ) &&
					false !== filter_var( $server_ip, FILTER_VALIDATE_IP ) &&
					$server_ip !== $client_ip &&
					! \IP_Location_Block_Util::is_private_ip( $server_ip ),
			),
			'schedules' => array(
				'database' => self::schedule_status( $database_schedule, ! empty( $settings['update']['auto'] ) ),
				'cleanup'  => self::schedule_status( $cleanup_schedule, true ),
			),
			'mimeTypes' => $mime_types,
			'scope' => array(
				'isMultisite'     => is_multisite(),
				'isNetworkAdmin'  => 'network' === $scope,
				'networkActive'   => is_multisite() && is_plugin_active_for_network( IP_LOCATION_BLOCK_BASE ),
				'current'         => $scope,
			),
		) );
	}

	/**
	 * Save settings. The React app POSTs the full settings object, which is run
	 * through the exact classic sanitizer for parity. Reusing the admin instance
	 * is safe here: `init` has already fired by the time this REST callback runs,
	 * so the admin constructor's `init` hook is a no-op (admin_init never runs).
	 */
	public static function update_settings( \WP_REST_Request $request ) {
		$input = $request->get_json_params();
		if ( ! is_array( $input ) ) {
			return new \WP_Error(
				'ilb_invalid_settings',
				__( 'Invalid settings payload.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$scope = self::request_scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}

		$options = self::sanitize_settings_payload( $input );

		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-opts.php';
		$file = \IP_Location_Block_Opts::setup_validation_timing( $options );
		if ( is_wp_error( $file ) ) {
			$options['validation']['timing'] = 0;
		}

		delete_transient( \IP_Location_Block::CRON_NAME );
		do_action( 'ip-location-block-settings-updated', $options, true );

		$result = self::persist_settings( $options, $scope );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( self::public_settings( \IP_Location_Block::get_option( false ) ) );
	}

	/**
	 * Export a sanitized draft in the same flattened format as the classic UI.
	 */
	public static function export_settings( \WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$draft   = isset( $payload['settings'] ) && is_array( $payload['settings'] )
			? $payload['settings']
			: \IP_Location_Block::get_option();
		$options = self::sanitize_settings_payload( $draft );

		if ( ! class_exists( 'IP_Location_Block_Admin_Ajax', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';
		}

		return rest_ensure_response( array(
			'filename' => \IP_Location_Block::PLUGIN_NAME . '-settings.json',
			'data'     => \IP_Location_Block_Admin_Ajax::settings_to_json( $options ),
		) );
	}

	/**
	 * Normalize classic flattened JSON or a nested Beta settings object. The
	 * result is returned to the form for review and is not persisted here.
	 */
	public static function import_settings( \WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$data    = isset( $payload['data'] ) ? $payload['data'] : null;
		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'ilb_invalid_settings_file',
				__( 'The settings file must contain a JSON object.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$input  = $data;
		$prefix = \IP_Location_Block::OPTION_NAME . '[';
		$flat   = false;
		foreach ( array_keys( $data ) as $key ) {
			if ( 0 === strpos( (string) $key, $prefix ) ) {
				$flat = true;
				break;
			}
		}

		if ( $flat ) {
			$parsed = array();
			parse_str( http_build_query( $data ), $parsed );
			$input = isset( $parsed[ \IP_Location_Block::OPTION_NAME ] )
				? $parsed[ \IP_Location_Block::OPTION_NAME ]
				: array();
		}

		if ( empty( $input ) || ! is_array( $input ) || ( ! isset( $input['validation'] ) && ! isset( $input['matching_rule'] ) ) ) {
			return new \WP_Error(
				'ilb_invalid_settings_file',
				__( 'This is not a recognized IP Location Block settings file.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( array(
			'settings' => self::public_settings( self::sanitize_settings_payload( $input ) ),
			'format'   => $flat ? 'classic' : 'beta',
		) );
	}

	/**
	 * Preview converted IP Geo Block settings without saving them.
	 */
	public static function get_legacy_settings() {
		$current = \IP_Location_Block::get_option();
		if ( ! empty( $current['migrated_from_legacy'] ) || empty( get_option( 'ip_geo_block_settings' ) ) ) {
			return new \WP_Error(
				'ilb_legacy_settings_unavailable',
				__( 'No unmigrated IP Geo Block settings were found.', 'ip-location-block' ),
				array( 'status' => 404 )
			);
		}

		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-opts.php';
		$legacy = \IP_Location_Block_Opts::get_legacy_settings( false );
		if ( empty( $legacy ) ) {
			return new \WP_Error(
				'ilb_legacy_settings_unavailable',
				__( 'No previous settings were found.', 'ip-location-block' ),
				array( 'status' => 404 )
			);
		}

		$legacy = array_replace_recursive( \IP_Location_Block::get_default(), $legacy );
		$legacy['version']              = IP_LOCATION_BLOCK_VERSION;
		$legacy['migrated_from_legacy'] = true;
		$legacy['login_link']           = isset( $current['login_link'] ) ? $current['login_link'] : array( 'link' => null, 'hash' => null );

		return rest_ensure_response( array( 'settings' => self::public_settings( $legacy ) ) );
	}

	public static function generate_emergency_login_link( \WP_REST_Request $request ) {
		$scope = self::request_scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		if ( ! class_exists( 'IP_Location_Block_Admin', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/class-ip-location-block-admin.php';
		}

		$url = \IP_Location_Block_Util::generate_link(
			\IP_Location_Block_Admin::get_instance(),
			'network' === $scope
		);

		return rest_ensure_response( array(
			'url'    => $url,
			'status' => self::emergency_login_status( \IP_Location_Block::get_option( false ) ),
		) );
	}

	public static function delete_emergency_login_link( \WP_REST_Request $request ) {
		$scope = self::request_scope( $request );
		if ( is_wp_error( $scope ) ) {
			return $scope;
		}
		if ( ! class_exists( 'IP_Location_Block_Admin', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/class-ip-location-block-admin.php';
		}

		\IP_Location_Block_Util::delete_link(
			\IP_Location_Block_Admin::get_instance(),
			'network' === $scope
		);

		return rest_ensure_response( array(
			'status' => self::emergency_login_status( \IP_Location_Block::get_option( false ) ),
		) );
	}

	/**
	 * Sanitize a full React payload while preserving operational values that
	 * are deliberately never sent to the browser.
	 *
	 * @param array $input Settings draft.
	 *
	 * @return array
	 */
	private static function sanitize_settings_payload( $input ) {
		if ( ! class_exists( 'IP_Location_Block_Admin', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/class-ip-location-block-admin.php';
		}

		$current = \IP_Location_Block::get_option();
		if ( isset( $current['login_link'] ) ) {
			$input['login_link'] = $current['login_link'];
		}

		// A raw one-item signature is valid in the React form. The classic
		// sanitizer treats every comma-less value as its private encoded form,
		// so add a disposable delimiter before delegating.
		if ( isset( $input['signature'] ) && is_string( $input['signature'] ) && false === strpos( $input['signature'], ',' ) ) {
			$input['signature'] .= ',';
		}

		$options = \IP_Location_Block_Admin::get_instance()->sanitize_options( $input );

		// The classic provider sanitizer is presence-based because it receives a
		// partial form POST. React sends the complete provider map instead.
		if ( isset( $input['providers'] ) && is_array( $input['providers'] ) ) {
			$providers = array();
			$catalog   = \IP_Location_Block_Provider::get_providers( 'key', false, false, true );
			foreach ( $input['providers'] as $name => $value ) {
				$name = sanitize_text_field( (string) $name );
				if ( ! array_key_exists( $name, $catalog ) ) {
					continue;
				}
				$providers[ $name ] = is_string( $value )
					? sanitize_text_field( $value )
					: ( $value ? '@' : '' );
			}
			$options['providers'] = $providers;
		}

		$options['login_link'] = isset( $current['login_link'] )
			? $current['login_link']
			: array( 'link' => null, 'hash' => null );

		return $options;
	}

	private static function public_settings( $settings ) {
		if ( isset( $settings['login_link'] ) ) {
			unset( $settings['login_link'] );
		}

		return $settings;
	}

	private static function emergency_login_status( $settings ) {
		$link       = isset( $settings['login_link'] ) && is_array( $settings['login_link'] ) ? $settings['login_link'] : array();
		$configured = ! empty( $link['link'] );
		$valid      = $configured && ! empty( $link['hash'] ) && \IP_Location_Block_Util::verify_link( $link['link'], $link['hash'] );

		return array(
			'configured' => (bool) $configured,
			'valid'      => $configured ? (bool) $valid : null,
			'state'      => ! $configured ? 'not_configured' : ( $valid ? 'ready' : 'outdated' ),
		);
	}

	private static function schedule_status( $timestamp, $expected ) {
		$timestamp = $timestamp ? (int) $timestamp : null;

		return array(
			'timestamp' => $timestamp,
			'formatted' => $timestamp ? \IP_Location_Block_Util::localdate( $timestamp ) : '',
			'status'    => $timestamp ? 'scheduled' : ( $expected ? 'missing' : 'disabled' ),
		);
	}

	private static function debug_enabled() {
		return defined( 'IP_LOCATION_BLOCK_DEBUG' ) && IP_LOCATION_BLOCK_DEBUG;
	}

	/**
	 * Validate the explicit settings scope. Network changes are never inferred
	 * from a saved option or capability alone.
	 */
	private static function request_scope( \WP_REST_Request $request ) {
		$scope = sanitize_key( (string) $request->get_param( 'scope' ) );
		$scope = $scope ? $scope : 'site';
		if ( ! in_array( $scope, array( 'site', 'network' ), true ) ) {
			return new \WP_Error(
				'ilb_invalid_settings_scope',
				__( 'Invalid settings scope.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}
		if ( 'network' === $scope && ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) ) {
			return new \WP_Error(
				'ilb_network_settings_forbidden',
				__( 'You are not allowed to manage network settings.', 'ip-location-block' ),
				array( 'status' => 403 )
			);
		}
		if ( 'site' === $scope && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'ilb_site_settings_forbidden',
				__( 'You are not allowed to manage site settings.', 'ip-location-block' ),
				array( 'status' => 403 )
			);
		}

		return $scope;
	}

	private static function persist_settings( $settings, $scope ) {
		if ( 'network' === $scope ) {
			if ( ! class_exists( 'IP_Location_Block_Admin', false ) ) {
				require_once IP_LOCATION_BLOCK_PATH . 'admin/class-ip-location-block-admin.php';
			}
			if ( ! \IP_Location_Block_Admin::get_instance()->update_multisite_settings( $settings ) ) {
				return new \WP_Error(
					'ilb_network_settings_failed',
					__( 'The settings could not be saved to every site.', 'ip-location-block' ),
					array( 'status' => 500 )
				);
			}
			// Keep the request-local cache synchronized with the main site.
			\IP_Location_Block::update_option( $settings );
			return true;
		}

		\IP_Location_Block::update_option( $settings );
		return true;
	}

	/**
	 * Complete configurable provider catalog. Cache is omitted, but local and
	 * keyless providers are first-class choices in both settings modes.
	 */
	public static function get_providers() {
		$settings = \IP_Location_Block::get_option();
		$stored = isset( $settings['providers'] ) ? (array) $settings['providers'] : array();
		$all = \IP_Location_Block_Provider::all();

		$out = array();
		foreach ( \IP_Location_Block_Provider::get_providers( 'key', false, false, true ) as $name => $keyfield ) {
			$meta = isset( $all[ $name ] ) ? $all[ $name ] : array();
			$auth = 'optional';
			if ( isset( $meta['api_auth'] ) && \IP_Location_Block_Provider::API_AUTH_REQUIRED === $meta['api_auth'] ) {
				$auth = 'required';
			} elseif ( isset( $meta['api_auth'] ) && \IP_Location_Block_Provider::API_AUTH_NOT_REQUIRED === $meta['api_auth'] ) {
				$auth = 'none';
			}
			$out[] = array(
				'name'        => $name,
				'value'       => isset( $stored[ $name ] ) ? $stored[ $name ] : '',
				'selected'    => self::provider_is_selected( $name, $meta, $stored ),
				'link'        => isset( $meta['link'] ) ? (string) $meta['link'] : '',
				'type'        => isset( $meta['type'] ) ? (string) $meta['type'] : '',
				'requests'    => isset( $meta['requests'] ) && is_array( $meta['requests'] ) ? array(
					'total' => (int) $meta['requests']['total'],
					'term'  => isset( $meta['requests']['term'] ) ? (string) $meta['requests']['term'] : '',
				) : null,
				'recommended' => ( 'IP Location Block' === $name ),
				'local'       => ! empty( $meta['local'] ),
				'databaseReady' => ! empty( $meta['local'] ) ? self::local_provider_ready( $name, $settings ) : null,
				'auth'        => $auth,
				'supports'    => array(
					'ipv4'  => (bool) \IP_Location_Block_Provider::supports( $name, 'ipv4' ),
					'ipv6'  => (bool) \IP_Location_Block_Provider::supports( $name, 'ipv6' ),
					'asn'   => (bool) \IP_Location_Block_Provider::supports( $name, array( 'asn', 'asn_database' ) ),
					'city'  => (bool) \IP_Location_Block_Provider::supports( $name, array( 'city' ) ),
					'state' => (bool) \IP_Location_Block_Provider::supports( $name, array( 'state' ) ),
				),
			);
		}

		return rest_ensure_response( $out );
	}

	/**
	 * Whether a provider is selected by the stored provider-map convention.
	 * Providers whose registry key is null (IP2Location) are implicit until an
	 * explicit empty value disables them.
	 *
	 * @param string $name
	 * @param array  $meta
	 * @param array  $stored
	 *
	 * @return bool
	 */
	public static function provider_is_selected( $name, $meta, $stored ) {
		if ( array_key_exists( $name, $stored ) ) {
			return ! empty( $stored[ $name ] );
		}

		return array_key_exists( 'key', $meta ) && null === $meta['key'];
	}

	/**
	 * Resolve whether a local database can currently answer an IPv4 lookup.
	 *
	 * @param string $name
	 * @param array  $settings
	 *
	 * @return bool
	 */
	public static function local_provider_ready( $name, $settings ) {
		if ( 'IP2Location' === $name ) {
			$path = isset( $settings['IP2Location']['ipv4_path'] ) ? $settings['IP2Location']['ipv4_path'] : '';
			if ( empty( $path ) && defined( 'IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT' ) ) {
				$path = trailingslashit( \IP_Location_Block_Util::get_databases_storage_dir( 'IP2Location' ) ) . IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT;
			}
			$path = apply_filters( 'ip-location-block-ip2location-path', $path );
			return ! empty( $path ) && @file_exists( $path );
		}

		if ( 'GeoLite2' === $name ) {
			$path = isset( $settings['GeoLite2']['ip_path'] ) ? $settings['GeoLite2']['ip_path'] : '';
			if ( empty( $path ) && defined( 'IP_LOCATION_BLOCK_GEOLITE2_DB_IP' ) ) {
				$path = trailingslashit( \IP_Location_Block_Util::get_databases_storage_dir( 'GeoLite2' ) ) . IP_LOCATION_BLOCK_GEOLITE2_DB_IP;
			}
			$path = apply_filters( 'ip-location-block-geolite2-path', $path );
			return ! empty( $path ) && @file_exists( $path );
		}

		return true;
	}

	/**
	 * Read a successful provider verification for the exact credential.
	 *
	 * @param string $provider
	 * @param string $credential
	 *
	 * @return array|false
	 */
	public static function get_provider_verification( $provider, $credential ) {
		if ( '' === $credential || '@' === $credential ) {
			return false;
		}

		$fingerprint = \IP_Location_Block_Provider::credential_fingerprint( $provider, $credential );
		$verified    = get_transient( 'ip_location_block_verify_' . substr( $fingerprint, 0, 40 ) );

		return is_array( $verified ) ? $verified : false;
	}

	/**
	 * Persist only successful verification metadata; credentials are represented
	 * by an HMAC fingerprint in the transient name.
	 *
	 * @param string $provider
	 * @param string $credential
	 *
	 * @return array
	 */
	private static function remember_provider_verification( $provider, $credential ) {
		$fingerprint = \IP_Location_Block_Provider::credential_fingerprint( $provider, $credential );
		$verified    = array(
			'provider'   => $provider,
			'verifiedAt' => time(),
		);

		set_transient( 'ip_location_block_verify_' . substr( $fingerprint, 0, 40 ), $verified, DAY_IN_SECONDS );

		return $verified;
	}

	/**
	 * Provider readiness, verification and quota for the guided settings view.
	 */
	public static function get_provider_status_data( $settings = null ) {
		$settings  = is_array( $settings ) ? $settings : \IP_Location_Block::get_option();
		$stored    = isset( $settings['providers'] ) && is_array( $settings['providers'] ) ? $settings['providers'] : array();
		$catalog   = \IP_Location_Block_Provider::all();
		$protected = (int) ( isset( $settings['matching_rule'] ) ? $settings['matching_rule'] : -1 ) !== -1 ||
			( isset( $settings['validation']['public'] ) && ( (int) $settings['validation']['public'] & 1 ) === 1 );

		$native_key      = isset( $stored['IP Location Block'] ) ? (string) $stored['IP Location Block'] : '';
		$native_selected = isset( $catalog['IP Location Block'] ) && self::provider_is_selected( 'IP Location Block', $catalog['IP Location Block'], $stored );
		$quota           = null;
		if ( $native_selected && empty( $settings['restrict_api'] ) && '' !== $native_key && '@' !== $native_key ) {
			$quota = \IP_Location_Block_Provider::get_native_quota_status( $native_key );
		}

		$quota_blocking = $quota && in_array( $quota['status'], array( 'exhausted', 'rate_limited', 'key_upgrade_required' ), true );
		$providers      = array();
		$active         = array();
		$ready          = false;

		foreach ( $catalog as $name => $meta ) {
			if ( 'Cache' === $name ) {
				continue;
			}

			$selected   = self::provider_is_selected( $name, $meta, $stored );
			$local      = ! empty( $meta['local'] );
			$is_active  = $selected && ( $local || empty( $settings['restrict_api'] ) );
			$credential = isset( $stored[ $name ] ) ? (string) $stored[ $name ] : '';
			$auth       = isset( $meta['api_auth'] ) ? (int) $meta['api_auth'] : \IP_Location_Block_Provider::API_AUTH_OPTIONAL;
			$key_ready  = \IP_Location_Block_Provider::API_AUTH_REQUIRED !== $auth || ( '' !== $credential && '@' !== $credential );
			$verified   = self::get_provider_verification( $name, $credential );
			$usable     = $local ? self::local_provider_ready( $name, $settings ) : $key_ready;
			$is_ready   = $is_active && $usable && ( $local || $verified || $protected );

			if ( 'IP Location Block' === $name && $quota_blocking ) {
				$is_ready = false;
			}

			$reason = '';
			if ( $selected && ! $is_active ) {
				$reason = 'privacy_restriction';
			} elseif ( $selected && ! $key_ready ) {
				$reason = 'missing_key';
			} elseif ( $selected && $local && ! $usable ) {
				$reason = 'database_missing';
			} elseif ( $selected && ! $verified && ! $protected && ! $local ) {
				$reason = 'not_tested';
			} elseif ( 'IP Location Block' === $name && $quota_blocking ) {
				$reason = $quota['status'];
			}

			if ( $is_active ) {
				$active[] = $name;
			}
			$ready = $ready || $is_ready;

			$providers[] = array(
				'name'       => (string) $name,
				'selected'   => (bool) $selected,
				'active'     => (bool) $is_active,
				'local'      => (bool) $local,
				'ready'      => (bool) $is_ready,
				'verified'   => (bool) $verified,
				'verifiedAt' => $verified ? (int) $verified['verifiedAt'] : null,
				'reason'     => $reason,
				'allowance'  => isset( $meta['requests'] ) && is_array( $meta['requests'] ) ? $meta['requests'] : null,
			);
		}

		return array(
			'active'    => $active,
			'providers' => $providers,
			'ready'     => (bool) $ready,
			'native'    => (bool) \IP_Location_Block_Provider::is_native( $settings ),
			'protected' => (bool) $protected,
			'quota'     => $quota,
		);
	}

	/**
	 * Provider readiness, verification and quota for the guided settings view.
	 */
	public static function get_provider_status() {
		return rest_ensure_response( self::get_provider_status_data() );
	}

	/**
	 * Test a candidate provider credential without saving it. A successful test
	 * is cached for 24 hours and can be consumed after the settings are saved.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function test_provider( \WP_REST_Request $request ) {
		$provider   = sanitize_text_field( (string) $request->get_param( 'provider' ) );
		$credential = sanitize_text_field( (string) $request->get_param( 'credential' ) );
		$catalog    = \IP_Location_Block_Provider::all();

		if ( 'Cache' === $provider || ! isset( $catalog[ $provider ] ) ) {
			return new \WP_Error(
				'ip_location_block_unknown_provider',
				__( 'Unknown geolocation provider.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$meta = $catalog[ $provider ];
		$auth = isset( $meta['api_auth'] ) ? (int) $meta['api_auth'] : \IP_Location_Block_Provider::API_AUTH_OPTIONAL;
		if ( ( 'IP Location Block' === $provider || \IP_Location_Block_Provider::API_AUTH_REQUIRED === $auth ) && '' === $credential ) {
			return new \WP_Error(
				'ip_location_block_missing_provider_key',
				__( 'Enter an API key before testing this provider.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$options = \IP_Location_Block::get_option();
		if ( ! isset( $options['providers'] ) || ! is_array( $options['providers'] ) ) {
			$options['providers'] = array();
		}
		$options['providers'][ $provider ] = '' !== $credential ? $credential : '@';

		$geo = \IP_Location_Block_API::get_instance( $provider, $options );
		if ( ! $geo ) {
			return new \WP_Error(
				'ip_location_block_provider_unavailable',
				__( 'This provider is not available in the current installation.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$args          = \IP_Location_Block::get_request_headers( $options );
		$args['fresh'] = true;
		try {
			$result = $geo->get_location( '8.8.8.8', $args );
		} catch ( \Exception $exception ) {
			$result = array( 'errorMessage' => $exception->getMessage() );
		}

		$country = is_array( $result ) && isset( $result['countryCode'] ) ? strtoupper( (string) $result['countryCode'] ) : '';
		if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
			$message = is_array( $result ) && ! empty( $result['errorMessage'] )
				? (string) $result['errorMessage']
				: __( 'The provider did not return a valid country result.', 'ip-location-block' );

			return rest_ensure_response( array(
				'provider' => $provider,
				'ok'       => false,
				'message'  => $message,
			) );
		}

		$quota = null;
		if ( 'IP Location Block' === $provider ) {
			$quota = \IP_Location_Block_Provider::get_native_quota_status( $credential, true );
			if ( in_array( $quota['status'], array( 'exhausted', 'rate_limited', 'key_upgrade_required' ), true ) ) {
				return rest_ensure_response( array(
					'provider'    => $provider,
					'ok'          => false,
					'countryCode' => $country,
					'message'     => $quota['message'],
					'quota'       => $quota,
				) );
			}
		}

		$verified = self::remember_provider_verification( $provider, '' !== $credential ? $credential : '@' );

		return rest_ensure_response( array(
			'provider'    => $provider,
			'ok'          => true,
			'countryCode' => $country,
			'verifiedAt'  => (int) $verified['verifiedAt'],
			'quota'       => $quota,
		) );
	}

	/**
	 * Local database file status (read-only): path + whether the file exists +
	 * last-update timestamp, per configured provider.
	 */
	public static function get_database_status() {
		$s = \IP_Location_Block::get_option();
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
			if ( empty( $path ) && 'IP2Location' === $prov ) {
				if ( 'ipv4_path' === $pathKey && defined( 'IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT' ) ) {
					$path = trailingslashit( \IP_Location_Block_Util::get_databases_storage_dir( 'IP2Location' ) ) . IP_LOCATION_BLOCK_IP2LOC_IPV4_DAT;
					$path = apply_filters( 'ip-location-block-ip2location-path', $path );
				} elseif ( 'ipv6_path' === $pathKey && defined( 'IP_LOCATION_BLOCK_IP2LOC_IPV6_DAT' ) ) {
					$path = trailingslashit( \IP_Location_Block_Util::get_databases_storage_dir( 'IP2Location' ) ) . IP_LOCATION_BLOCK_IP2LOC_IPV6_DAT;
				}
			} elseif ( empty( $path ) && 'GeoLite2' === $prov ) {
				if ( 'ip_path' === $pathKey && defined( 'IP_LOCATION_BLOCK_GEOLITE2_DB_IP' ) ) {
					$path = trailingslashit( \IP_Location_Block_Util::get_databases_storage_dir( 'GeoLite2' ) ) . IP_LOCATION_BLOCK_GEOLITE2_DB_IP;
					$path = apply_filters( 'ip-location-block-geolite2-path', $path );
				} elseif ( 'asn_path' === $pathKey && defined( 'IP_LOCATION_BLOCK_GEOLITE2_DB_ASN' ) ) {
					$path = trailingslashit( \IP_Location_Block_Util::get_databases_storage_dir( 'GeoLite2' ) ) . IP_LOCATION_BLOCK_GEOLITE2_DB_ASN;
				}
			}
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
	 * Registered admin actions plus installed plugins/themes available as
	 * validation exceptions.
	 */
	public static function get_exceptions() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = array_unique( array_merge(
			(array) get_option( 'active_plugins', array() ),
			array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) )
		) );
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

		$admin = array();
		foreach ( \IP_Location_Block_Util::get_registered_actions( false, \IP_Location_Block::get_option() ) as $action => $access ) {
			$admin[] = array(
				'value'  => (string) $action,
				'label'  => (string) $action,
				'access' => (int) $access,
			);
		}
		usort( $admin, static function ( $left, $right ) {
			return strcasecmp( $left['label'], $right['label'] );
		} );

		return rest_ensure_response( compact( 'admin', 'plugins', 'themes' ) );
	}

	/**
	 * Discover exception candidates from blocked validation logs on demand.
	 */
	public static function get_detected_exceptions( \WP_REST_Request $request ) {
		$target = sanitize_key( (string) $request->get_param( 'target' ) );
		if ( ! in_array( $target, array( 'admin', 'plugins', 'themes' ), true ) ) {
			return new \WP_Error(
				'ilb_invalid_exception_target',
				__( 'Invalid exception target.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}
		if ( ! class_exists( 'IP_Location_Block_Admin_Ajax', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';
		}

		$found = \IP_Location_Block_Admin_Ajax::find_exceptions( 'find-' . $target );
		$items = array();
		foreach ( (array) $found as $value => $context ) {
			$items[] = array(
				'value'   => (string) $value,
				'context' => is_scalar( $context ) ? (string) $context : '',
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	public static function get_defaults() {
		return rest_ensure_response( self::public_settings( \IP_Location_Block::get_default() ) );
	}

	/**
	 * Geolocation lookup for the Search tab. search_ip() reads $_POST['ip'],
	 * so populate it before delegating.
	 */
	public static function search_geolocation( \WP_REST_Request $request ) {
		$provider = sanitize_text_field( (string) $request->get_param( 'provider' ) );
		$_POST['ip'] = sanitize_text_field( (string) $request->get_param( 'ip' ) );

		if ( ! class_exists( 'IP_Location_Block_Admin_Ajax', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';
		}

		return rest_ensure_response( \IP_Location_Block_Admin_Ajax::search_ip( $provider ) );
	}

	/**
	 * Per-blog blocked counts across the network (network admin only).
	 * Maps \IP_Location_Block_Admin_Ajax::restore_network() into flat rows.
	 */
	public static function get_network_stats( \WP_REST_Request $request ) {
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
		foreach ( \IP_Location_Block_Admin_Ajax::restore_network( $duration, $offset, $length, false ) as $site => $counts ) {
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
		return rest_ensure_response( \IP_Location_Block::get_instance()->exec_update_db() );
	}

	/**
	 * Debug-only maintenance operations retained from the classic settings UI.
	 */
	public static function diagnose_database_tables() {
		if ( ! self::debug_enabled() ) {
			return new \WP_Error(
				'ilb_debug_disabled',
				__( 'Developer diagnostics are disabled.', 'ip-location-block' ),
				array( 'status' => 403 )
			);
		}

		$healthy = \IP_Location_Block_Logs::diag_tables();
		if ( ! $healthy ) {
			\IP_Location_Block_Logs::create_tables();
			$healthy = \IP_Location_Block_Logs::diag_tables();
		}

		return rest_ensure_response( array( 'healthy' => (bool) $healthy ) );
	}

	public static function reset_live_logs() {
		if ( ! self::debug_enabled() ) {
			return new \WP_Error(
				'ilb_debug_disabled',
				__( 'Developer diagnostics are disabled.', 'ip-location-block' ),
				array( 'status' => 403 )
			);
		}
		if ( ! extension_loaded( 'pdo_sqlite' ) ) {
			return new \WP_Error(
				'ilb_sqlite_unavailable',
				__( 'The PDO SQLite extension is unavailable.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}
		if ( ! class_exists( 'IP_Location_Block_Admin_Ajax', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';
		}

		return rest_ensure_response( array(
			'success' => (bool) \IP_Location_Block_Admin_Ajax::reset_live_log(),
		) );
	}

	public static function get_statistics() {
		$s = \IP_Location_Block_Logs::restore_stat();
		$settings = \IP_Location_Block::get_option();

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
			$previous = 0;
			foreach ( $s['daystats'] as $time => $counts ) {
				while ( $previous && $time - $previous > DAY_IN_SECONDS ) {
					$previous += DAY_IN_SECONDS;
					$empty = array( 'date' => $previous, 'total' => 0 );
					foreach ( $hooks as $hook ) {
						$empty[ $hook ] = 0;
					}
					$daily[] = $empty;
				}
				$row = array( 'date' => (int) $time );
				$total = 0;
				foreach ( $hooks as $h ) {
					$v = isset( $counts[ $h ] ) ? (int) $counts[ $h ] : 0;
					$row[ $h ] = $v;
					$total += $v;
				}
				$row['total'] = $total;
				$daily[] = $row;
				$previous = (int) $time;
			}
		}

		$providers = array();
		foreach ( isset( $s['providers'] ) && is_array( $s['providers'] ) ? $s['providers'] : array() as $name => $provider ) {
			$calls = isset( $provider['count'] ) ? (int) $provider['count'] : 0;
			$time  = isset( $provider['time'] ) ? (float) $provider['time'] : 0.0;
			$providers[] = array(
				'name'      => (string) $name,
				'calls'     => $calls,
				'averageMs' => $calls > 0 ? round( 1000.0 * $time / $calls, 1 ) : 0.0,
			);
		}

		usort( $providers, static function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return rest_ensure_response( array(
			'blocked'   => isset( $s['blocked'] ) ? (int) $s['blocked'] : 0,
			'unknown'   => isset( $s['unknown'] ) ? (int) $s['unknown'] : 0,
			'ipv4'      => isset( $s['IPv4'] ) ? (int) $s['IPv4'] : 0,
			'ipv6'      => isset( $s['IPv6'] ) ? (int) $s['IPv6'] : 0,
			'countries' => $countries,
			'daily'     => $daily,
			'providers' => $providers,
			'features'  => array(
				'statistics' => ! empty( $settings['save_statistics'] ),
				'logs'       => ! empty( $settings['validation']['reclogs'] ),
				'cache'      => ! empty( $settings['cache_hold'] ),
				'asn'        => ! empty( $settings['use_asn'] ),
				'anonymize'  => ! empty( $settings['anonymize'] ),
			),
		) );
	}

	/**
	 * Classic Statistics-tab summaries derived from the last year of logs.
	 * Aggregation remains server-side so request bodies never reach the client.
	 */
	public static function get_log_statistics() {
		$counts = array(
			'countries' => array(),
			'asns'      => array(),
			'ips'       => array(),
			'slugs'     => array(),
		);

		foreach ( \IP_Location_Block_Logs::get_recent_logs( YEAR_IN_SECONDS ) as $log ) {
			$code = isset( $log['code'] ) ? (string) $log['code'] : '';
			$asn  = isset( $log['asn'] ) ? (string) $log['asn'] : '';
			$ip   = isset( $log['ip'] ) ? (string) $log['ip'] : '';

			self::increment_count( $counts['countries'], $code );
			self::increment_count( $counts['asns'], $asn );
			self::increment_count( $counts['ips'], $ip, $code );

			$method = isset( $log['method'] ) ? (string) $log['method'] : '';
			$data   = isset( $log['data'] ) ? (string) $log['data'] : '';
			self::increment_count( $counts['slugs'], self::log_slug( $method . ' ' . $data ) );
		}

		$settings = \IP_Location_Block::get_option();
		$ips = array();
		foreach ( self::rank_counts( $counts['ips'], 10 ) as $row ) {
			$parts = explode( "\n", $row['value'], 2 );
			$code  = isset( $parts[0] ) ? $parts[0] : '';
			$ip    = isset( $parts[1] ) ? $parts[1] : '';
			if ( ! empty( $settings['anonymize'] ) ) {
				$ip = \IP_Location_Block_Util::anonymize_ip( $ip );
			}
			$ips[] = array(
				'value'  => '[' . $code . '] ' . $ip,
				'search' => $ip,
				'count'  => $row['count'],
			);
		}

		return rest_ensure_response( array(
			'countries' => self::rank_counts( $counts['countries'], 10 ),
			'asns'      => self::rank_counts( $counts['asns'], 10 ),
			'ips'       => $ips,
			'slugs'     => self::rank_counts( $counts['slugs'] ),
		) );
	}

	/**
	 * Increment a summary count. IP rows include country as a private separator
	 * so they can be formatted after anonymization without exposing log payloads.
	 */
	private static function increment_count( &$counts, $value, $prefix = null ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return;
		}
		$key = null === $prefix ? $value : trim( (string) $prefix ) . "\n" . $value;
		if ( ! isset( $counts[ $key ] ) ) {
			$counts[ $key ] = 0;
		}
		++$counts[ $key ];
	}

	/**
	 * Turn a value=>count map into the REST representation used by ranked lists.
	 */
	private static function rank_counts( $counts, $limit = null ) {
		arsort( $counts );
		if ( null !== $limit ) {
			$counts = array_slice( $counts, 0, $limit, true );
		}

		$rows = array();
		foreach ( $counts as $value => $count ) {
			$rows[] = array( 'value' => (string) $value, 'count' => (int) $count );
		}

		return $rows;
	}

	/**
	 * Preserve the classic back-end slug extraction order and patterns.
	 */
	private static function log_slug( $value ) {
		$patterns = array(
			'#<methodName>(.*?)</methodName>#',
			'#(/wp-content/(?:plugins|themes)/.*?/)#',
			'#(/wp-admin/admin.*?\.php).*((?:page|action)=[-\w]+)#',
			'#(/wp-admin/(?!admin).*?\.php)#',
			'#(\[name\]\s*?=>\s*?[^\s]+)#',
			'#(/[^/]*\.php)[^/\w]#',
		);

		foreach ( $patterns as $index => $pattern ) {
			if ( preg_match( $pattern, $value, $matches ) ) {
				if ( 0 === $index ) {
					return '/xmlrpc.php ' . $matches[1];
				}
				if ( 2 === $index ) {
					return $matches[1] . ' ' . $matches[2];
				}
				if ( 5 === $index && false !== strpos( $value, '/wp-admin/' ) ) {
					continue;
				}
				return $matches[1];
			}
		}

		return '';
	}

	/**
	 * Structured cache rows. Hashes are opaque identifiers used for deletion;
	 * the displayed IP and host respect the plugin anonymization setting.
	 */
	public static function get_cache() {
		$settings = \IP_Location_Block::get_option();
		$now      = isset( $_SERVER['REQUEST_TIME'] ) ? (int) $_SERVER['REQUEST_TIME'] : time();
		$rows     = array();

		foreach ( (array) \IP_Location_Block_Logs::restore_cache() as $ip => $cache ) {
			$host = isset( $cache['host'] ) ? (string) $cache['host'] : '';
			if ( ! empty( $settings['anonymize'] ) ) {
				$ip   = \IP_Location_Block_Util::anonymize_ip( $ip, true );
				$host = \IP_Location_Block_Util::anonymize_ip( $host, false );
			}

			$rows[] = array(
				'id'       => isset( $cache['hash'] ) ? (string) $cache['hash'] : '',
				'ip'       => (string) $ip,
				'code'     => isset( $cache['code'] ) ? (string) $cache['code'] : '',
				'city'     => isset( $cache['city'] ) ? (string) $cache['city'] : '',
				'state'    => isset( $cache['state'] ) ? (string) $cache['state'] : '',
				'asn'      => isset( $cache['asn'] ) ? (string) $cache['asn'] : '',
				'host'     => $host,
				'target'   => isset( $cache['hook'] ) ? (string) $cache['hook'] : '',
				'failures' => isset( $cache['fail'] ) ? (int) $cache['fail'] : 0,
				'requests' => isset( $cache['reqs'] ) ? (int) $cache['reqs'] : 0,
				'elapsed'  => max( 0, $now - ( isset( $cache['time'] ) ? (int) $cache['time'] : $now ) ),
			);
		}

		return rest_ensure_response( array( 'rows' => $rows ) );
	}

	public static function clear_cache() {
		\IP_Location_Block_API_Cache::clear_cache();

		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function delete_cache_entries( \WP_REST_Request $request ) {
		$entries = array();
		foreach ( (array) $request->get_param( 'hashes' ) as $hash ) {
			$hash = sanitize_text_field( $hash );
			if ( '' !== $hash && 0 === strlen( $hash ) % 2 && ctype_xdigit( $hash ) ) {
				$entries[] = ',' . $hash;
			}
		}

		if ( empty( $entries ) ) {
			return new \WP_Error(
				'ilb_invalid_cache_entries',
				__( 'No valid cache entries were selected.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		\IP_Location_Block_Logs::delete_cache_entry( array_unique( $entries ) );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function clear_statistics() {
		\IP_Location_Block_Logs::clear_stat();

		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function get_logs( \WP_REST_Request $request ) {
		$hook = self::valid_log_hook( $request->get_param( 'hook' ) );
		if ( is_wp_error( $hook ) ) {
			return $hook;
		}

		$settings = \IP_Location_Block::get_option();
		$rows     = apply_filters(
			'ip-location-block-logs',
			\IP_Location_Block_Logs::restore_logs( $hook ? $hook : null )
		);

		return rest_ensure_response( array(
			'rows'     => self::format_log_rows( $rows, $settings, false ),
			'features' => self::log_features( $settings ),
			'live'     => array(
				'intervalSeconds'     => 5,
				'pauseTimeoutSeconds' => 60,
			),
		) );
	}

	/**
	 * Acquire, poll, pause, or release the SQLite-backed live log stream.
	 */
	public static function live_logs( \WP_REST_Request $request ) {
		$action = sanitize_key( (string) $request->get_param( 'action' ) );
		$hook   = self::valid_log_hook( $request->get_param( 'hook' ) );
		if ( is_wp_error( $hook ) ) {
			return $hook;
		}
		if ( ! in_array( $action, array( 'start', 'poll', 'pause', 'stop' ), true ) ) {
			return new \WP_Error(
				'ilb_invalid_live_action',
				__( 'Invalid live log action.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$settings = \IP_Location_Block::get_option();
		$features = self::log_features( $settings );
		if ( ! $features['recording'] || ! $features['live'] ) {
			return new \WP_Error(
				'ilb_live_unavailable',
				__( 'Live logs are unavailable. Enable validation log recording and the PDO SQLite extension.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( 'IP_Location_Block_Admin', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/class-ip-location-block-admin.php';
		}
		if ( ! class_exists( 'IP_Location_Block_Admin_Ajax', false ) ) {
			require_once IP_LOCATION_BLOCK_PATH . 'admin/includes/class-admin-ajax.php';
		}

		if ( 'stop' === $action ) {
			$result = \IP_Location_Block_Admin_Ajax::release_live_log();

			return is_wp_error( $result ) ? self::live_log_error( $result ) : rest_ensure_response( array( 'rows' => array() ) );
		}

		$result = \IP_Location_Block_Admin_Ajax::catch_live_log();
		if ( is_wp_error( $result ) ) {
			return self::live_log_error( $result );
		}

		if ( 'pause' === $action ) {
			return rest_ensure_response( array( 'rows' => array() ) );
		}

		$rows = \IP_Location_Block_Logs::restore_live_log( $hook ? $hook : null, $settings );
		if ( is_wp_error( $rows ) ) {
			return new \WP_Error( 'ilb_live_log_error', $rows->get_error_message(), array( 'status' => 500 ) );
		}

		$rows = apply_filters( 'ip-location-block-logs', $rows );

		return rest_ensure_response( array(
			'rows' => self::format_log_rows( $rows, $settings, true ),
		) );
	}

	private static function live_log_error( \WP_Error $error ) {
		return new \WP_Error( 'ilb_live_log_in_use', $error->get_error_message(), array( 'status' => 409 ) );
	}

	private static function valid_log_hook( $hook ) {
		$hook = sanitize_key( (string) $hook );
		if ( '' !== $hook && ! in_array( $hook, array( 'comment', 'login', 'admin', 'xmlrpc', 'public' ), true ) ) {
			return new \WP_Error(
				'ilb_invalid_log_target',
				__( 'Invalid log target.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		return $hook;
	}

	private static function log_features( $settings ) {
		return array(
			'recording' => ! empty( $settings['validation']['reclogs'] ),
			'asn'       => ! empty( $settings['use_asn'] ),
			'anonymize' => ! empty( $settings['anonymize'] ),
			'live'      => extension_loaded( 'pdo_sqlite' ),
		);
	}

	/**
	 * Convert the database's positional log rows into a stable REST shape.
	 */
	private static function format_log_rows( $rows, $settings, $live ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			$row        = array_pad( array_values( (array) $row ), 13, '' );
			$ip         = (string) $row[3];
			$method     = (string) $row[9];
			$user_agent = (string) $row[10];
			$headers    = (string) $row[11];
			$post_data  = (string) $row[12];

			if ( ! empty( $settings['anonymize'] ) ) {
				$ip         = \IP_Location_Block_Util::anonymize_ip( $ip, true );
				$method     = \IP_Location_Block_Util::anonymize_ip( $method, false );
				$user_agent = \IP_Location_Block_Util::anonymize_ip( $user_agent, false );
				$headers    = \IP_Location_Block_Util::anonymize_ip( $headers, false );
				$post_data  = \IP_Location_Block_Util::anonymize_ip( $post_data, false );
			}

			$target  = preg_replace( '/&sup[123];/', '', (string) $row[1] );
			$result  = (string) $row[7];
			$code    = (string) $row[4];
			$lists   = self::log_lists_for_target( $target, $settings );
			$context = \IP_Location_Block::is_listed( $code, $lists['white'] ) ? 'whitelist' : (
				\IP_Location_Block::is_listed( $code, $lists['black'] ) ? 'blacklist' : 'none'
			);

			$out[] = array(
				'id'          => $live ? 'live-' . (string) $row[0] : (int) $row[0],
				'target'      => $target,
				'time'        => (int) $row[2],
				'ip'          => $ip,
				'code'        => $code,
				'city'        => (string) $row[5],
				'state'       => (string) $row[6],
				'result'      => $result,
				'verdict'     => \IP_Location_Block::is_passed( $result ) ? 'passed' : 'blocked',
				'listContext' => $context,
				'asn'         => (string) $row[8],
				'method'      => $method,
				'userAgent'   => $user_agent,
				'headers'     => $headers,
				'postData'    => $post_data,
			);
		}

		return $out;
	}

	private static function log_lists_for_target( $target, $settings ) {
		if ( 'public' === $target && isset( $settings['public']['matching_rule'] ) && 0 <= (int) $settings['public']['matching_rule'] ) {
			return array(
				'white' => isset( $settings['public']['white_list'] ) ? (string) $settings['public']['white_list'] : '',
				'black' => isset( $settings['public']['black_list'] ) ? (string) $settings['public']['black_list'] : '',
			);
		}
		return array(
			'white' => isset( $settings['white_list'] ) ? (string) $settings['white_list'] : '',
			'black' => isset( $settings['black_list'] ) ? (string) $settings['black_list'] : '',
		);
	}

	/**
	 * Append IP/CIDR/ASN values to the extra-IP white or black list
	 * (bulk whitelist/blacklist from the Logs tab).
	 */
	public static function add_to_list( \WP_REST_Request $request ) {
		$list = sanitize_key( (string) $request->get_param( 'list' ) );
		$type = sanitize_key( (string) $request->get_param( 'type' ) );
		$type = $type ? $type : 'ip';
		if ( ! in_array( $list, array( 'white', 'black' ), true ) || ! in_array( $type, array( 'ip', 'asn' ), true ) ) {
			return new \WP_Error(
				'ilb_invalid_list_action',
				__( 'Invalid list action.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$key = 'white' === $list ? 'white_list' : 'black_list';
		$values = (array) $request->get_param( 'values' );

		$settings = \IP_Location_Block::get_option();
		$current = isset( $settings['extra_ips'][ $key ] ) ? $settings['extra_ips'][ $key ] : '';
		$entries = array_filter( array_map( 'trim', preg_split( '/[,\r\n]+/', (string) $current ) ) );

		foreach ( $values as $v ) {
			$v = self::normalize_list_value( sanitize_text_field( $v ), $type );
			if ( '' !== $v && ! in_array( $v, $entries, true ) ) {
				$entries[] = $v;
			}
		}

		if ( ! isset( $settings['extra_ips'] ) || ! is_array( $settings['extra_ips'] ) ) {
			$settings['extra_ips'] = array();
		}
		$settings['extra_ips'][ $key ] = implode( ',', $entries );
		\IP_Location_Block::update_option( $settings );

		return rest_ensure_response( array( 'success' => true, 'list' => $settings['extra_ips'][ $key ] ) );
	}

	/**
	 * Validate bulk-list values and mirror the classic anonymized-IP conversion.
	 */
	private static function normalize_list_value( $value, $type ) {
		$value = trim( (string) $value );
		if ( 'asn' === $type ) {
			$value = strtoupper( $value );
			return preg_match( '/^AS\d+$/', $value ) ? $value : '';
		}

		if ( preg_match( '/\.\*+$/', $value ) ) {
			$value = preg_replace( '/\.\*+$/', '.0/24', $value );
		} elseif ( false !== strpos( $value, ':' ) && preg_match( '/\*+$/', $value ) ) {
			$value = preg_replace_callback( '/\*+$/', static function ( $matches ) {
				return str_repeat( '0', strlen( $matches[0] ) );
			}, $value ) . '/116';
		}

		$parts = explode( '/', $value, 2 );
		$ip = $parts[0];
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		if ( 1 === count( $parts ) ) {
			return $ip;
		}

		$prefix = filter_var( $parts[1], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		$max    = false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? 32 : 128;
		return false !== $prefix && $prefix <= $max ? $ip . '/' . $prefix : '';
	}

	/**
	 * Delete validation-log entries by IP address (bulk erase).
	 */
	public static function delete_log_entries( \WP_REST_Request $request ) {
		$ips = array_map( 'sanitize_text_field', (array) $request->get_param( 'ips' ) );
		\IP_Location_Block_Logs::delete_logs_entry( $ips );

		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function clear_logs( \WP_REST_Request $request ) {
		$hook = $request->get_param( 'hook' );
		\IP_Location_Block_Logs::clear_logs( $hook ? $hook : null );

		return rest_ensure_response( array( 'success' => true ) );
	}
}
