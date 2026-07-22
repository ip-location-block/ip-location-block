<?php
/**
 * IP Location Block
 *
 * A WordPress plugin that blocks undesired access based on geolocation of IP address.
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 *
 * Plugin Name:       IP Location Block
 * Plugin URI:        https://wordpress.org/plugins/ip-location-block/
 * Description:       Easily setup location block based on the visitor country, city, state or provider. Also protects your site from spam, login attempts, zero-day exploits, malicious access & more.
 * Version:           1.4.0
 * Requires PHP:      8.1
 * Author:            IP Location Block
 * Author URI:        https://iplocationblock.com/
 * Text Domain:       ip-location-block
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path:       /languages
 */

defined( 'WPINC' ) or die; // If this file is called directly, abort.

if(!defined("AUTH_KEY") || !defined("SECURE_AUTH_KEY") || !defined("LOGGED_IN_KEY") || !defined("NONCE_KEY") ||
   !defined("AUTH_SALT") || !defined("SECURE_AUTH_SALT") || !defined("LOGGED_IN_SALT") || !defined("NONCE_SALT")) {
	if(isset($GLOBALS['ip_location_block_hash_keys_notice']) && $GLOBALS['ip_location_block_hash_keys_notice']) {
		return;
	}
	add_action('admin_notices', function (){
		?>
		<div class="notice notice-error">
			<p><strong><?php _e( 'IP Location Block Error:', 'ip-location-block' ); ?></strong></p>
			<p><?php _e( 'WordPress security keys and salts are not properly configured in wp-config.php. This plugin requires all authentication constants (AUTH_KEY, SECURE_AUTH_KEY, LOGGED_IN_KEY, NONCE_KEY, AUTH_SALT, SECURE_AUTH_SALT, LOGGED_IN_SALT, NONCE_SALT) to be defined for security reasons.', 'ip-location-block' ); ?></p>
			<p><?php printf(
					__( 'Please generate new security keys at %s and add them to your wp-config.php file.', 'ip-location-block' ),
					'<a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank">https://api.wordpress.org/secret-key/1.1/salt/</a>'
				); ?></p>
		</div>
		<?php
	});
	$GLOBALS['ip_location_block_hash_keys_notice'] = true;
	return;
}


if ( ! defined( 'IP_LOCATION_BLOCK_VERSION' ) ):

	/*----------------------------------------------------------------------------*
	 * Global definition
	 *----------------------------------------------------------------------------*/
	define( 'IP_LOCATION_BLOCK_VERSION', '1.4.0' );
	define( 'IP_LOCATION_BLOCK_PATH', plugin_dir_path( __FILE__ ) ); // @since  0.2.8
	define( 'IP_LOCATION_BLOCK_BASE', plugin_basename( __FILE__ ) ); // @since 1.5

	/*----------------------------------------------------------------------------*
	 * Composer autoloaders (scoped vendor first, then the main autoloader).
	 * The scoped tree is optional (populated by the release build); the main
	 * autoloader is required — without it the PSR-4 src/ code and the compat
	 * layer cannot load, so show a developer notice and bail cleanly.
	 *----------------------------------------------------------------------------*/
	if ( file_exists( IP_LOCATION_BLOCK_PATH . 'vendor_prefixed/vendor/autoload.php' ) ) {
		require_once IP_LOCATION_BLOCK_PATH . 'vendor_prefixed/vendor/autoload.php';
	}
	if ( file_exists( IP_LOCATION_BLOCK_PATH . 'vendor/autoload.php' ) ) {
		require_once IP_LOCATION_BLOCK_PATH . 'vendor/autoload.php';
	} else {
		add_action( 'admin_notices', function () {
			?>
			<div class="notice notice-error">
				<p><strong><?php _e( 'IP Location Block Error:', 'ip-location-block' ); ?></strong></p>
				<p><?php _e( 'The Composer autoloader is missing. Run <code>composer install</code> in the plugin directory (or install the packaged release build).', 'ip-location-block' ); ?></p>
			</div>
			<?php
		} );
		return;
	}

	/*----------------------------------------------------------------------------*
	 * Backward-compatibility layer.
	 * Registers the legacy class aliases (compat/legacy-aliases.php) eagerly —
	 * BEFORE any legacy code, the deployed mu-plugin copies, or the immediate
	 * IP_Location_Block::get_option() call below reference the historic names.
	 *----------------------------------------------------------------------------*/
	require_once IP_LOCATION_BLOCK_PATH . 'compat/bootstrap.php';

	// is_plugin_active_for_network() / is_plugin_active() live here and are used
	// by ip_location_block_update() on plugins_loaded.
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	/*----------------------------------------------------------------------------*
	 * Global procedural API (names are contract).
	 *
	 * These global function names are part of the public API and — critically —
	 * the exact identities the deployed mu-plugin copies remove_action() and
	 * re-invoke. They delegate to the namespaced kernel/ports.
	 *----------------------------------------------------------------------------*/

	function ip_location_block_activate( $network_wide = false ) {
		\IPLocationBlock\Core\Activator::activate( $network_wide );
	}

	function ip_location_block_deactivate( $network_wide = false ) {
		\IPLocationBlock\Core\Activator::deactivate( $network_wide );
	}

	function ip_location_block_upgrader_process_complete( $upgrader_object, $options ) {
		// If an update has taken place and the updated type is plugins and the plugins element exists
		if ( isset( $options['action'] ) && $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
			foreach ( $options['plugins'] as $plugin ) {
				if ( $plugin == plugin_basename( __FILE__ ) ) {
					IP_Location_Block_Opts::upgrade();
				}
			}
		}
	}

	/**
	 * check version and update before instantiation
	 *
	 * @see https://make.wordpress.org/core/2010/10/27/plugin-activation-hooks/
	 * @see https://wordpress.stackexchange.com/questions/144870/wordpress-update-plugin-hook-action-since-3-9
	 */
	function ip_location_block_update() {
		$settings = IP_Location_Block::get_option();
		if ( version_compare( $settings['version'], IP_LOCATION_BLOCK_VERSION ) < 0 ) {
			ip_location_block_activate( is_plugin_active_for_network( IP_LOCATION_BLOCK_BASE ) );
		}
	}

	/*----------------------------------------------------------------------------*
	 * Boot the kernel — registers activation/deactivation, upgrade, plugins_loaded
	 * (legacy callable identities), rest_api_init and the admin hooks.
	 *----------------------------------------------------------------------------*/
	( new \IPLocationBlock\Plugin( __FILE__ ) )->register();

	/*----------------------------------------------------------------------------*
	 * Emergency Functionality
	 *----------------------------------------------------------------------------*/

	/**
	 * Invalidate blocking behavior in case yourself is locked out.
	 *
	 * How to use: Activate the following code and upload this file via FTP.
	 */
	/* -- ADD `/` TO THE TOP OR END OF THIS LINE TO ACTIVATE THE FOLLOWINGS -- *
	function ip_location_block_emergency( $validate, $settings ) {
		$validate['result'] = 'passed';
		return $validate;
	}
	add_filter( 'ip-location-block-login',  'ip_location_block_emergency', 1, 2 );
	add_filter( 'ip-location-block-admin',  'ip_location_block_emergency', 1, 2 );
	add_filter( 'ip-location-block-public', 'ip_location_block_emergency', 1, 2 );
	// */

endif; // ! defined( 'IP_LOCATION_BLOCK_VERSION' )
