<?php
/**
 * Drop-in for IP Location Block custom filters for admin
 *
 * This file should be named as `drop-in-admin.php` and placed in
 * `wp-content/uploads/ip-location-block/dropins/`.
 *
 * Since 1.4.0 the plugin internals are namespaced (IPLocationBlock\), but the
 * `IP_Location_Block` class used below IS the supported public API and is
 * guaranteed through all 1.x releases.
 *
 * @package   IP_Location_Block
 * @author    darkog <dg@darkog.com>
 * @license   GPL-3.0
 * @see      http://iplocationblock.com/codex/my-custom-functions-in-functions-php-doesnt-work/
 * @see       https://iplocationblock.com/?codex-category=actions-and-filters
 * @example   Use `IP_Location_Block::add_filter()` instead of `add_filter()`
 */
class_exists( 'IP_Location_Block', false ) or die;

/**
 * Analyze entries in "Validation logs"
 *
 * @param array $logs An array including each entry where:
 * Array (
 *     [0 DB row number] => 154
 *     [1 Target       ] => comment
 *     [2 Time         ] => 1534580897
 *     [3 IP address   ] => 102.177.147.***
 *     [4 Country code ] => ZA
 *     [5 City         ] = SomeCity
 *     [6 State        ] = SomeState
 *     [7 Result       ] => blocked
 *     [8 AS number    ] => AS328239
 *     [9 Request      ] => POST[80]:/wp-comments-post.php
 *     [10 User agent   ] => Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) ...
 *     [11 HTTP headers ] => HTTP_ORIGIN=http://localhost,HTTP_X_FORWARDED_FOR=102.177.147.***
 *    [12 $_POST data  ] => comment=Hello.,author,email,url,comment_post_ID,comment_parent
 * )
 *
 * And put a mark at "Target"
 *    ¹¹: Passed  in Whitelist
 *    ¹²: Passed  in Blacklist
 *    ¹³: Passed  not in Lists
 *    ²¹: Blocked in Whitelist
 *    ²²: Blocked in Blacklist
 *    ²³: Blocked not in Lists
 *
 * @return array
 */
function ip_location_block_logs( array $logs ) {
	// Get settings of IP Geo Block
	$settings = IP_Location_Block::get_option();

	// White/Black list for back-end
	$white_backend = $settings['white_list'];
	$black_backend = $settings['black_list'];

	// White/Black list for front-end
	if ( $settings['public']['matching_rule'] < 0 ) {
		// Follow "Validation rule settings"
		$white_frontend = $white_backend;
		$black_frontend = $black_backend;
	} else {
		// Whitelist or Blacklist for "Public facing pages"
		$white_frontend = $settings['public']['white_list'];
		$black_frontend = $settings['public']['black_list'];
	}
	foreach ( $logs as $key => $log ) {
		// Passed or Blocked
		$mark = IP_Location_Block::is_passed( $log[7] ) ? '&sup1;' : '&sup2;';
		// Whitelisted, Blacklisted or N/A
		if ( 'public' === $log[1] ) {
			$mark .= IP_Location_Block::is_listed( $log[4], $white_frontend ) ? '&sup1;' : (
			IP_Location_Block::is_listed( $log[4], $black_frontend ) ? '&sup2;' : '&sup3;' );
		} else {
			$mark .= IP_Location_Block::is_listed( $log[4], $white_backend ) ? '&sup1;' : (
			IP_Location_Block::is_listed( $log[4], $black_backend ) ? '&sup2;' : '&sup3;' );
		}
		// Put a mark at "Target"
		$logs[ $key ][1] .= $mark;
	}

	return $logs;
}

IP_Location_Block::add_filter( 'ip-location-block-logs', 'ip_location_block_logs' );

/**
 * Register UI "Preset filters" at "Search in logs"
 *
 * @param array $filters An empty array by default.
 *
 * @return array $filters The array of paired with 'title' and 'value'.
 */
function ip_location_block_logs_preset( $filters ) {
	return array(
		array(
			'title' => '<span class="ip-geo-block-icon ip-geo-block-icon-happy"    >&nbsp;</span>' . __( '<span title="Show only passed entries whose country codes are in Whitelist.">Passed in Whitelist</span>', 'ip-geo-block' ),
			'value' => '&sup1;&sup1;'
		),
		array(
			'title' => '<span class="ip-geo-block-icon ip-geo-block-icon-grin2"    >&nbsp;</span>' . __( '<span title="Show only passed entries whose country codes are in Blacklist.">Passed in Blacklist</span>', 'ip-geo-block' ),
			'value' => '&sup1;&sup2;'
		),
		array(
			'title' => '<span class="ip-geo-block-icon ip-geo-block-icon-cool"     >&nbsp;</span>' . __( '<span title="Show only passed entries whose country codes are not in either list.">Passed not in List</span>', 'ip-geo-block' ),
			'value' => '&sup1;&sup3;'
		),
		array(
			'title' => '<span class="ip-geo-block-icon ip-geo-block-icon-confused" >&nbsp;</span>' . __( '<span title="Show only blocked entries whose country codes are in Whitelist.">Blocked in Whitelist</span>', 'ip-geo-block' ),
			'value' => '&sup2;&sup1;'
		),
		array(
			'title' => '<span class="ip-geo-block-icon ip-geo-block-icon-confused2">&nbsp;</span>' . __( '<span title="Show only blocked entries whose country codes are in Blacklist.">Blocked in Blacklist</span>', 'ip-geo-block' ),
			'value' => '&sup2;&sup2;'
		),
		array(
			'title' => '<span class="ip-geo-block-icon ip-geo-block-icon-crying"   >&nbsp;</span>' . __( '<span title="Show only blocked entries whose country codes are not in either list.">Blocked not in List</span>', 'ip-geo-block' ),
			'value' => '&sup2;&sup3;'
		),
	);
}

IP_Location_Block::add_filter( 'ip-location-block-logs-preset', 'ip_location_block_logs_preset' );
