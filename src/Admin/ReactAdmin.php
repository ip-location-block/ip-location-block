<?php
/**
 * IP Location Block - React (Beta) admin.
 *
 * Registers a separate "IP Location Block (Beta)" menu that mounts the React
 * app (admin/app/build/). The classic admin is untouched and remains the
 * default; this Beta UI is opt-in until it reaches parity and is promoted.
 *
 * Requires WordPress 5.0+ (wp-element / React). On older WP the menu is hidden.
 *
 * The legacy class name IP_Location_Block_Beta is kept working via class_alias
 * in compat/legacy-aliases.php. Unlike the frozen classic admin, this class is
 * new code (introduced alongside the React admin) and is therefore namespaced
 * + written to modern PHP 8.1 idioms rather than kept under its legacy
 * identity.
 *
 * @package IP_Location_Block
 */

declare(strict_types=1);

namespace IPLocationBlock\Admin;

/**
 * Class ReactAdmin
 */
final class ReactAdmin {

	public const SLUG = 'ip-location-block-beta';

	private static ?self $instance = null;

	private string $hook = '';

	public static function get_instance(): self {
		return self::$instance ??= new self();
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
	public static function is_supported(): bool {
		return version_compare( get_bloginfo( 'version' ), '5.0', '>=' );
	}

	public function add_menu(): void {
		if ( ! self::is_supported() ) {
			return;
		}
		$network = is_network_admin();

		$this->hook = (string) add_submenu_page(
			$network ? 'settings.php' : 'options-general.php',
			__( 'IP Location Block (Beta)', 'ip-location-block' ),
			__( 'IP Location Block (Beta)', 'ip-location-block' ),
			$network ? 'manage_network_options' : 'manage_options',
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
	public function enqueue(): void {
		$asset_path = IP_LOCATION_BLOCK_PATH . 'admin/app/build/index.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return; // assets not built (npm run build)
		}
		$asset = require $asset_path;

		// Bundled Leaflet for the Search tab map (exposes window.L).
		wp_enqueue_style(
			'ip-location-block-leaflet',
			plugins_url( 'admin/app/vendor/leaflet/leaflet.css', IP_LOCATION_BLOCK_BASE ),
			array(),
			IP_LOCATION_BLOCK_VERSION
		);
		wp_enqueue_script(
			'ip-location-block-leaflet',
			plugins_url( 'admin/app/vendor/leaflet/leaflet.js', IP_LOCATION_BLOCK_BASE ),
			array(),
			IP_LOCATION_BLOCK_VERSION,
			true
		);

		wp_enqueue_script(
			self::SLUG,
			plugins_url( 'admin/app/build/index.js', IP_LOCATION_BLOCK_BASE ),
			array_merge( $asset['dependencies'], array( 'ip-location-block-leaflet' ) ),
			$asset['version'],
			true
		);

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'dashicons' );

		// @wordpress/scripts splits extracted CSS into two files: shared styles
		// from any `style.scss` land in style-index.css, while styles imported
		// from other-named modules (e.g. charts.scss) land in index.css. Enqueue
		// both so every component's styles load.
		wp_enqueue_style(
			self::SLUG,
			plugins_url( 'admin/app/build/style-index.css', IP_LOCATION_BLOCK_BASE ),
			array( 'wp-components' ),
			$asset['version']
		);
		if ( file_exists( IP_LOCATION_BLOCK_PATH . 'admin/app/build/index.css' ) ) {
			wp_enqueue_style(
				self::SLUG . '-components',
				plugins_url( 'admin/app/build/index.css', IP_LOCATION_BLOCK_BASE ),
				array( self::SLUG ),
				$asset['version']
			);
		}

		wp_localize_script( self::SLUG, 'ipLocationBlockBeta', array(
			'restNamespace' => 'ip-location-block/v1',
			'restRoot'      => esc_url_raw( rest_url() ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'isNetwork'     => is_network_admin(),
			'version'       => IP_LOCATION_BLOCK_VERSION,
			'logoUrl'       => plugins_url( 'admin/images/logo.svg', IP_LOCATION_BLOCK_BASE ),
			'docsUrl'       => 'https://iplocationblock.com/codex/?utm_source=plugin&utm_medium=beta&utm_campaign=admin_topbar',
		) );

		wp_set_script_translations( self::SLUG, 'ip-location-block' );
	}

	public function render(): void {
		// Give WordPress a definite notice anchor before the React heading. This
		// screen's stylesheet suppresses those notices, but the marker prevents
		// core or third-party code from relocating them inside the product bar.
		echo '<div class="wrap ilb-beta-wrap"><hr class="wp-header-end" /><div id="ip-location-block-app"></div></div>';
	}
}
