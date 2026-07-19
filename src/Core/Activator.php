<?php
/**
 * IP Location Block
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

namespace IPLocationBlock\Core;

use IPLocationBlock\Cron\Scheduler;
use IPLocationBlock\Logging\Logs;
use IPLocationBlock\Settings\Options;

/**
 * Plugin (de)activation — 1:1 port of IP_Location_Block_Activate.
 *
 * The historic eager require_once of util/opts/logs/cron at the top of the
 * legacy file is dropped: those classes are autoloaded (and aliased) now.
 */
class Activator {

	// Activate and deactivate main blog
	private static function activate_main_blog( $settings ) {
		Scheduler::start_update_db( $settings );
		Options::setup_validation_timing( $settings );
	}

	private static function deactivate_main_blog() {
		Scheduler::stop_update_db();
		Options::setup_validation_timing();
	}

	// Activate and deactivate each blog
	public static function activate_blog() {
		Options::upgrade();
		Logs::create_tables();
		Logs::delete_cache_entry();
		Scheduler::start_cache_gc();
	}

	private static function deactivate_blog() {
		Scheduler::stop_cache_gc();
		Logs::delete_cache_entry();
	}

	/**
	 * Register options into database table when the plugin is activated.
	 *
	 * @link https://wordpress.stackexchange.com/questions/181141/how-to-run-an-activation-function-when-plugin-is-network-activated-on-multisite
	 *
	 * @param bool $network_wide
	 */
	public static function activate( $network_wide = false ) {
		// Update main blog first.
		self::activate_blog();

		// Get option of main blog.
		$settings = Validator::get_option();

		if ( \is_multisite() && $network_wide ) {
			global $wpdb;
			$blog_ids = $wpdb->get_col( "SELECT `blog_id` FROM `$wpdb->blogs` ORDER BY `blog_id` ASC" );

			// Skip the main blog.
			\array_shift( $blog_ids );

			foreach ( $blog_ids as $id ) {
				\switch_to_blog( $id );
				self::activate_blog();
				\restore_current_blog();
			}
		}

		if ( \did_action( 'init' ) && \current_user_can( 'manage_options' ) ) {
			self::activate_main_blog( $settings );
		}
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @param bool $network_wide
	 */
	public static function deactivate( $network_wide = false ) {
		\add_action( 'shutdown', array( __CLASS__, 'deactivate_plugin' ) );
	}

	public static function deactivate_plugin() {
		self::deactivate_blog();

		if ( \is_multisite() && \is_main_site() ) {
			global $wpdb;
			$blog_ids = $wpdb->get_col( "SELECT `blog_id` FROM `$wpdb->blogs` ORDER BY `blog_id` ASC" );

			// Skip the main blog.
			\array_shift( $blog_ids );

			foreach ( $blog_ids as $id ) {
				\switch_to_blog( $id );

				// Skip when this plugin is still active
				if ( ! \is_plugin_active( IP_LOCATION_BLOCK_BASE ) ) {
					self::deactivate_blog();
				}

				\restore_current_blog();
			}
		}

		self::deactivate_main_blog();
	}

}
