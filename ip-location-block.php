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
 * Version:           1.3.7
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


if ( ! class_exists( 'IP_Location_Block', false ) ):

	/*----------------------------------------------------------------------------*
	 * Global definition
	 *----------------------------------------------------------------------------*/
	define( 'IP_LOCATION_BLOCK_VERSION', '1.3.7' );
	define( 'IP_LOCATION_BLOCK_PATH', plugin_dir_path( __FILE__ ) ); // @since  0.2.8
	define( 'IP_LOCATION_BLOCK_BASE', plugin_basename( __FILE__ ) ); // @since 1.5

	/*----------------------------------------------------------------------------*
	 * Public-Facing Functionality
	 *----------------------------------------------------------------------------*/

	/**
	 * Load class
	 *
	 */
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block.php';
	require IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-util.php';
	require IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-load.php';
	require IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-logs.php';
	require IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-apis.php';
	require IP_LOCATION_BLOCK_PATH . 'classes/db-providers/ip2location/class-ip2location.php';
	require IP_LOCATION_BLOCK_PATH . 'classes/db-providers/maxmind/class-maxmind-geolite2.php';


	function ip_location_block_activate( $network_wide = false ) {
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-actv.php';
		IP_Location_Block_Activate::activate( $network_wide );
	}

	function ip_location_block_deactivate( $network_wide = false ) {
		require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-actv.php';
		IP_Location_Block_Activate::deactivate( $network_wide );
	}

	register_activation_hook( __FILE__, 'ip_location_block_activate' );
	register_deactivation_hook( __FILE__, 'ip_location_block_deactivate' );

	function ip_location_block_upgrader_process_complete( $upgrader_object, $options ) {
		// If an update has taken place and the updated type is plugins and the plugins element exists
		if ( isset( $options['action']) && $options['action'] == 'update' && $options['type'] == 'plugin' && isset( $options['plugins'] ) ) {
			foreach( $options['plugins'] as $plugin ) {
				if( $plugin == plugin_basename( __FILE__ ) ) {
					require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-util.php';
					require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-opts.php';
					require_once IP_LOCATION_BLOCK_PATH . 'classes/class-ip-location-block-logs.php';
					IP_Location_Block_Opts::upgrade();
				}
			}
		}
	}
	add_action( 'upgrader_process_complete', 'ip_location_block_upgrader_process_complete', 10, 2 );

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

	add_action( 'plugins_loaded', 'ip_location_block_update' );

	/**
	 * Instantiate class
	 *
	 */
	add_action( 'plugins_loaded', array( 'IP_Location_Block', 'get_instance' ) );

	/*----------------------------------------------------------------------------*
	 * Dashboard and Administrative Functionality
	 *----------------------------------------------------------------------------*/

	/**
	 * Load class in case of wp-admin/*.php
	 *
	 */
	if ( is_admin() ) {
		require IP_LOCATION_BLOCK_PATH . 'admin/class-ip-location-block-admin.php';
		add_action( 'plugins_loaded', array( 'IP_Location_Block_Admin', 'get_instance' ) );
	}

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

endif; // ! class_exists( 'IP_Location_Block', FALSE )
