<?php
/**
 * IP Location Block - React (Beta) admin.
 *
 * Registers a separate "IP Location Block (Beta)" menu that mounts the React
 * app (admin/build/). The classic admin is untouched and remains the default;
 * this Beta UI is opt-in until it reaches parity and is promoted.
 *
 * Requires WordPress 5.0+ (wp-element / React). On older WP the menu is hidden.
 *
 * @package IP_Location_Block
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class IP_Location_Block_Beta {

	const SLUG = 'ip-location-block-beta';

	private static $instance = null;
	private $hook = '';

	public static function get_instance() {
		return self::$instance ? self::$instance : ( self::$instance = new self );
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		if ( is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'add_menu' ) );
		}
	}

	/**
	 * The Beta UI needs the wp-element (React) runtime shipped since WP 5.0.
	 */
	public static function is_supported() {
		return version_compare( get_bloginfo( 'version' ), '5.0', '>=' );
	}

	public function add_menu() {
		if ( ! self::is_supported() ) {
			return;
		}

		$this->hook = add_submenu_page(
			'options-general.php',
			__( 'IP Location Block (Beta)', 'ip-location-block' ),
			__( 'IP Location Block (Beta)', 'ip-location-block' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);

		if ( $this->hook ) {
			add_action( 'load-' . $this->hook, array( $this, 'enqueue' ) );
		}
	}

	/**
	 * Enqueue the built React bundle + its WP script dependencies (read from
	 * the generated asset manifest) and bootstrap data for the app.
	 */
	public function enqueue() {
		$asset_path = IP_LOCATION_BLOCK_PATH . 'admin/build/index.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return; // assets not built (npm run build)
		}
		$asset = require $asset_path;

		wp_enqueue_script(
			self::SLUG,
			plugins_url( 'admin/build/index.js', IP_LOCATION_BLOCK_BASE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style(
			self::SLUG,
			plugins_url( 'admin/build/style-index.css', IP_LOCATION_BLOCK_BASE ),
			array( 'wp-components' ),
			$asset['version']
		);

		wp_localize_script( self::SLUG, 'ipLocationBlockBeta', array(
			'restNamespace' => 'ip-location-block/v1',
			'restRoot'      => esc_url_raw( rest_url() ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'isNetwork'     => is_network_admin(),
			'version'       => IP_LOCATION_BLOCK_VERSION,
		) );

		wp_set_script_translations( self::SLUG, 'ip-location-block' );
	}

	public function render() {
		echo '<div class="wrap"><div id="ip-location-block-app"></div></div>';
	}
}
