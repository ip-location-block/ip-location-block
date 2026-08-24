<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   IP_Location_Block
 * @author    tokkonopapa <tokkonopapa@yahoo.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2013-2019 tokkonopapa
 */

// If uninstall not called from WordPress, then exit
defined( 'WP_UNINSTALL_PLUGIN' ) or die;

// Boot the backward-compatibility layer: defines the three plugin constants
// (notably IP_LOCATION_BLOCK_VERSION — the main file never runs during
// uninstall, and Opts::$option_table references it: this fixes the historic
// fatal when the settings row was already deleted), loads the autoloaders and
// registers the legacy class aliases.
require __DIR__ . '/compat/bootstrap.php';

class IP_Location_Block_Uninstall {

	/**
	 * Delete settings options, IP address cache, log.
	 *
	 */
	private static function delete_blog_options() {
		delete_option( IP_Location_Block::OPTION_NAME ); // @since 1.2.0
		delete_option( IP_Location_Block::OPTION_META ); // @since 3.0.17
		IP_Location_Block_Logs::delete_tables();
	}

	/**
	 * Delete options from database when the plugin is uninstalled.
	 *
	 */
	public static function uninstall() {
		$settings = IP_Location_Block::get_option();

		if ( $settings['clean_uninstall'] ) {
			if ( ! is_multisite() ) {
				self::delete_blog_options();
			}

			else {
				global $wpdb;
				$blog_ids = $wpdb->get_col( "SELECT `blog_id` FROM `$wpdb->blogs`" );

				foreach ( $blog_ids as $id ) {
					switch_to_blog( $id );
					self::delete_blog_options();
					restore_current_blog();
				}
			}
		}

		IP_Location_Block_Opts::setup_validation_timing( FALSE );
	}

}

IP_Location_Block_Uninstall::uninstall();
