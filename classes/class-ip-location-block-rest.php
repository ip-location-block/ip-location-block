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

		// Settings (read). Saving is added with the Settings tab (needs the
		// admin sanitizer, which is not loaded on REST requests).
		register_rest_route( self::NS, '/settings', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_settings' ),
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
