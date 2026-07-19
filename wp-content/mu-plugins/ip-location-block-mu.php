<?php
/**
 * IP Location Block
 *
 * A WordPress plugin that blocks undesired access based on geolocation of IP address.
 *
 * This template is copied to WPMU_PLUGIN_DIR by the "validation timing"
 * setting (IPLocationBlock\Settings\Options::setup_validation_timing()) so the
 * plugin can validate requests before regular plugins load. Deployed copies
 * are refreshed by a version_compare gate in Options::upgrade() — when this
 * file changes, add a new gate for the release that ships the change, or
 * existing sites will keep running the previous copy.
 *
 * CONTRACT: the require of the main plugin file, the class_exists guard, and
 * the two remove_action() callable identities below must stay in sync with
 * the hook registrations in src/Plugin.php. Deployed copies of this file
 * cannot be assumed to update, so the main plugin must keep honoring the
 * identities used here.
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021-2026 darkog
 * @copyright 2013-2019 tokkonopapa
 *
 * Plugin Name:       IP Location Block (mu)
 * Plugin URI:        https://wordpress.org/plugins/ip-location-block/
 * Description:       Early-loading helper for IP Location Block: validates requests at mu-plugin time, before regular plugins load.
 * Version:           1.4.0
 * Author:            darkog
 * Author URI:        https://iplocationblock.com/
 * Text Domain:       ip-location-block
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

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


if ( ! class_exists( 'IP_Location_Block', false ) ) {

	// Avoid redirection loop
	if ( 'wp-login.php' === basename( $_SERVER['SCRIPT_NAME'] ) && site_url() !== home_url() ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$plugin_basename = 'ip-location-block/ip-location-block.php';
	if ( is_plugin_active( $plugin_basename ) || is_plugin_active_for_network( $plugin_basename ) ) {
		// Load plugin class
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_basename ) ) {
			require WP_PLUGIN_DIR . '/' . $plugin_basename;
			$options = IP_Location_Block::get_option();
			// check setup had already done
			if ( version_compare( $options['version'], IP_LOCATION_BLOCK_VERSION ) >= 0 && $options['matching_rule'] >= 0 ) {
				// Remove the deferred instantiation registered by src/Plugin.php.
				// These literal callable identities are the contract — do not change.
				remove_action( 'plugins_loaded', 'ip_location_block_update' );
				remove_action( 'plugins_loaded', array( 'IP_Location_Block', 'get_instance' ) );
				// Instantiate immediately so validation runs at mu-plugin time.
				IP_Location_Block::get_instance();
			}
		} else {
			add_action( 'admin_notices', 'ip_location_block_mu_notice' );
		}
	}
	/**
	 * Show global notice.
	 */
	function ip_location_block_mu_notice() {
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo sprintf(
			__( 'Can\'t find IP Location Block in your plugins directory. Please remove <code>%s</code> or re-install %s.', 'ip-location-block' ),
			__FILE__,
			'<a href="https://wordpress.org/plugins/ip-location-block/" title="IP Location Block &mdash; WordPress Plugins">IP Location Block</a>'
		);
		echo '</p></div>' . "\n";
	}
}
