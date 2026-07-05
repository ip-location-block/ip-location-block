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

	public static function get_statistics() {
		return rest_ensure_response( IP_Location_Block_Logs::restore_stat() );
	}

	public static function clear_statistics() {
		IP_Location_Block_Logs::clear_stat();

		return rest_ensure_response( array( 'success' => true ) );
	}

	public static function get_logs( WP_REST_Request $request ) {
		$hook = $request->get_param( 'hook' );

		return rest_ensure_response( IP_Location_Block_Logs::restore_logs( $hook ? $hook : null ) );
	}

	public static function clear_logs( WP_REST_Request $request ) {
		$hook = $request->get_param( 'hook' );
		IP_Location_Block_Logs::clear_logs( $hook ? $hook : null );

		return rest_ensure_response( array( 'success' => true ) );
	}
}
