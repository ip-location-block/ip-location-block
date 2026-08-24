<?php
/**
 * IP Location Block - Cron Class
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

namespace IPLocationBlock\Cron;

use IPLocationBlock\Core\Validator;
use IPLocationBlock\Logging\Logs;
use IPLocationBlock\Providers\DownloadableProviderInterface;
use IPLocationBlock\Providers\ProviderRegistry;
use IPLocationBlock\Settings\Options;

/**
 * Class Scheduler
 *
 * Schedules and runs the database-download cron job.
 *
 * The legacy class name IP_Location_Block_Cron is kept working via
 * class_alias in compat/legacy-aliases.php.
 */
class Scheduler {

	/**
	 * Cron scheduler.
	 *
	 * @param $update
	 * @param $db
	 * @param bool $immediate
	 */
	private static function schedule_cron_job( &$update, $db, $immediate = false ) {
		wp_clear_scheduled_hook( Validator::CRON_NAME, array( $immediate ) );

		if ( $update['auto'] || $immediate ) {
			$now  = time();
			$next = $now + ( $immediate ? 0 : DAY_IN_SECONDS );

			if ( false === $immediate ) {
				++ $update['retry'];
				$cycle = DAY_IN_SECONDS * (int) $update['cycle'];

				if ( isset( $db['ipv4_last'] ) ) {
					// in case of Maxmind Legacy or IP2Location
					if ( $now - (int) $db['ipv4_last'] < $cycle &&
					     $now - (int) $db['ipv6_last'] < $cycle ) {
						$update['retry'] = 0;
						$next            = max( (int) $db['ipv4_last'], (int) $db['ipv6_last'] ) +
						                   $cycle + rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 6 );
					}
				} elseif ( isset( $db['ip_last'] ) ) {
					// in case of Maxmind GeoLite2
					if ( $now - (int) $db['ip_last'] < $cycle ) {
						$update['retry'] = 0;
						$next            = (int) $db['ip_last'] +
						                   $cycle + rand( DAY_IN_SECONDS, DAY_IN_SECONDS * 6 );
					}
				}
			}

			wp_schedule_single_event( $next, Validator::CRON_NAME, array( $immediate ) );
		}
	}

	/**
	 * Database auto downloader.
	 *
	 * This function is called when:
	 *   1. Plugin is activated
	 *   2. WP Cron is kicked
	 * under the following condition:
	 *   A. Once per site when this plugin is activated on network wide
	 *   B. Multiple time for each blog when this plugin is individually activated
	 *
	 * @param bool $immediate
	 *
	 * @return mixed|null
	 */
	public static function exec_update_db( $immediate = false ) {

		$settings = Validator::get_option();

		// extract ip address from transient API to confirm the request source
		if ( $immediate ) {
			set_transient( Validator::CRON_NAME, Validator::get_ip_address( $settings ), MINUTE_IN_SECONDS );
			add_filter( 'ip-location-block-ip-addr', array( __CLASS__, 'extract_ip' ) );
		}

		$context = Validator::get_instance();
		$args    = Validator::get_request_headers( $settings );

		// Download database files for local, downloadable providers, in priority order.
		$registry  = ProviderRegistry::instance();
		$providers = $registry->localProviderIds( is_array( $settings['providers'] ) ? $settings['providers'] : array() );
		foreach ( $providers as $provider ) {

			$geo = $registry->get( $provider );
			if ( $geo instanceof DownloadableProviderInterface ) {

				$report           = $geo->download( $args, $settings );
				$res[ $provider ] = $report->files();

				// Persist the fresh *_path / *_last values computed during the
				// download immediately: cron rescheduling and the saved option
				// both need them, or they keep stale timestamps. DownloadReport
				// exposes them via settingsFragment().
				$fragment = $report->settingsFragment();
				if ( is_array( $fragment ) && ! empty( $fragment ) ) {
					$settings[ $provider ] = array_merge(
						isset( $settings[ $provider ] ) && is_array( $settings[ $provider ] ) ? $settings[ $provider ] : array(),
						$fragment
					);
				}

				// re-schedule cron job
				self::schedule_cron_job( $settings['update'], $settings[ $provider ], false );

				// update provider settings
				self::update_settings( $settings, array( 'update', $provider ) );

				// skip to update settings in case of InfiniteWP that could be in a different country
				if ( isset( $_SERVER['HTTP_X_REQUESTED_FROM'] ) && false !== strpos( $_SERVER['HTTP_X_REQUESTED_FROM'], 'InfiniteWP' ) ) {
					continue;
				}

				// update matching rule immediately
				if ( $immediate && 'done' !== get_transient( Validator::CRON_NAME ) ) {
					$validate = $context->validate_ip( 'admin', $settings );

					if ( 'ZZ' === $validate['code'] ) {
						continue;
					}

					// matching rule should be reset when blocking happens
					if ( 'passed' !== $validate['result'] ) {
						$settings['matching_rule'] = - 1;
					}

					// setup country code in whitelist if it needs to be initialized
					if ( - 1 === (int) $settings['matching_rule'] ) {
						$settings['matching_rule'] = 0; // white list

						// when the country code doesn't exist in whitelist, then add it
						if ( false === strpos( $settings['white_list'], $validate['code'] ) ) {
							$settings['white_list'] .= ( $settings['white_list'] ? ',' : '' ) . $validate['code'];
						}
					}

					// update option settings
					self::update_settings( $settings, array( 'matching_rule', 'white_list' ) );

					// finished to update matching rule
					set_transient( Validator::CRON_NAME, 'done', 5 * MINUTE_IN_SECONDS );

					// trigger update action
					do_action( 'ip-location-block-db-updated', $settings, $validate['code'] );
				}
			}
		}

		return isset( $res ) ? $res : null;
	}

	/**
	 * Update setting data according to the site type.
	 *
	 * @param $src
	 * @param array $keys
	 */
	private static function update_settings( $src, $keys = array() ) {
		// for multisite
		if ( is_plugin_active_for_network( IP_LOCATION_BLOCK_BASE ) ) {
			global $wpdb;
			$blog_ids = $wpdb->get_col( "SELECT `blog_id` FROM `$wpdb->blogs`" );

			foreach ( $blog_ids as $id ) {
				switch_to_blog( $id );
				$dst = Validator::get_option( false );

				foreach ( $keys as $key ) {
					$dst[ $key ] = $src[ $key ];
				}

				Validator::update_option( $dst, false );
				restore_current_blog();
			}
		} // for single site
		else {
			Validator::update_option( $src );
		}
	}

	/**
	 * Extract ip address from transient API.
	 *
	 * @param $ip
	 *
	 * @return mixed
	 */
	public static function extract_ip( $ip ) {
		return filter_var(
			$ip_self = get_transient( Validator::CRON_NAME ), FILTER_VALIDATE_IP
		) ? $ip_self : $ip;
	}

	/**
	 * Kick off a cron job to download database immediately in background on activation.
	 *
	 * @param $settings
	 * @param bool $immediate
	 */
	public static function start_update_db( $settings, $immediate = true ) {
		// updating should be done by main site when this plugin is activated for network
		if ( is_main_site() || ! is_plugin_active_for_network( IP_LOCATION_BLOCK_BASE ) ) {
			self::schedule_cron_job( $settings['update'], null, $immediate );
		}
	}

	/**
	 * Stop update db
	 */
	public static function stop_update_db() {
		wp_clear_scheduled_hook( Validator::CRON_NAME, array( false ) ); // @since  0.2.1.0

		// wait until updating has finished to avoid race condition with IP_Location_Block_Opts::install_api()
		$time = 0;
		while ( ( $stat = get_transient( Validator::CRON_NAME ) ) && 'done' !== $stat ) {
			sleep( 1 );

			if ( ++ $time > 5 * MINUTE_IN_SECONDS ) {
				break;
			}
		}
	}

	/**
	 * Kick off a cron job to garbage collection for IP address cache.
	 *
	 * @param $settings
	 */
	public static function exec_cache_gc( $settings ) {
		self::stop_cache_gc();

		Logs::delete_expired( array(
			min( 365, max( 1, (int) $settings['validation']['explogs'] ) ) * DAY_IN_SECONDS,
			(int) $settings['cache_time']
		) );

		self::start_cache_gc( $settings );
	}

	/**
	 * Start garbage collection
	 *
	 * @param false $settings
	 */
	public static function start_cache_gc( $settings = false ) {
		if ( ! wp_next_scheduled( Validator::CACHE_NAME ) ) {
			$settings or $settings = Validator::get_option();
			wp_schedule_single_event( time() + max( $settings['cache_time_gc'], MINUTE_IN_SECONDS ), Validator::CACHE_NAME );
		}
	}

	/**
	 * Stop garbage collection
	 */
	public static function stop_cache_gc() {
		wp_clear_scheduled_hook( Validator::CACHE_NAME ); // @since  0.2.1.0
	}

}