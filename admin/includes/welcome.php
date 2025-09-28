<?php
/**
 * This file comes from the "IP Location Block" WordPress plugin.
 * https://darkog.com/p/ip-location-block/
 *
 * Copyright (C) 2020-2023  Darko Gjorgjijoski. All Rights Reserved.
 *
 * IP Location Block is free software; you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * IP Location Block program is distributed in the hope that it
 * will be useful,but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License v3
 * along with this program;
 *
 * If not, see: https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * Code written, maintained by Darko Gjorgjijoski (https://darkog.com)
 */

// Urls
$url_docs      = 'https://iplocationblock.com/codex/?utm_source=plugin&utm_medium=welcome&utm_campaign=codex_views';
$url_purchase  = 'https://iplocationblock.com/pricing/?utm_source=plugin&utm_medium=welcome&utm_campaign=api_signups';
$url_prem_docs = 'https://iplocationblock.com/codex/city-state-level-matching/?utm_source=plugin&utm_medium=welcome&utm_campaign=city_state_matching';
$url_github    = 'https://github.com/gdarko/ip-location-block/';
$url_wordpress = 'https://wordpress.org/support/plugin/ip-location-block/';
?>

<div class="instructions ilb-instructions">
    <div class="ilb-instructions-card ilb-instructions-card-shadow">
        <div class="ilb-instructions-row ilb-instructions-header">
            <div class="ilb-instructions-colf">
                <p class="lead"><?php _e( 'Thanks for installing <strong class="green">IP Location Block</strong>', 'ip-location-block' ); ?> v<?php echo esc_attr(IP_LOCATION_BLOCK_VERSION); ?></p>
                <p class="desc"><?php _e( 'IP Location Block provides complete <strong class="underline">geolocation blocking</strong> solution to block unwanted visitors on your website.', 'ip-location-block' ); ?></p>
                <p class="desc"><?php _e( 'The plugin is <strong>FREE</strong> and supports geolocation based blacklisting/whitelisting by country. If you want precision blocking by city/state, check <a href="https://iplocationblock.com/codex/native-geo-location-provider/?utm_source=plugin&utm_medium=welcome&utm_campaign=codex_views" target="_blank">Native Geo-Location Provider</a>.', 'ip-location-block' ); ?></p>
                <p class="desc"><?php _e( 'If you found this plugin <strong>useful</strong> useful, we will greatly appreciate if you take a minute to <a target="_blank" title="Give this plugin a good five star rating :)" href="https://wordpress.org/support/plugin/ip-location-block/reviews/#new-post">rate it. &#9733;&#9733;&#9733;&#9733;&#9733;</a>', 'ip-location-block' ); ?></p>
                <p class="desc"><?php echo sprintf( __( '<a target="_blank" class="button button-primary" title="Plugin Documentation" href="%s">Read Docs</a>', 'ip-location-block' ), esc_url( $url_docs ) ); ?></p>
            </div>
        </div>
        <div class="ilb-instructions-row ilb-instructions-mb-10">
            <div class="ilb-instructions-colf">
                <div class="ilb-instructions-extra">
                    <h4 style="margin-top:0;"
                        class="navy"><?php _e( 'Precision blocking by state or city <span class="ilb-notice-badge">New from v1.2.0+</span>', 'ip-location-block' ); ?></h4>
                    <p>
						<?php printf( __( 'If you need a better and more precise IP Geo-Location matching by <strong>CITY</strong> and <strong>STATE</strong>, sign up for a <a target="_blank" href="%s">premium plan</a> and <a target="_blank" href="%s">learn how to set up</a>.', 'ip-location-block' ), esc_url( $url_purchase ), esc_url( $url_prem_docs ) ); ?>
                    </p>
                </div>
            </div>
            <div class="ilb-instructions-colf" style="padding-top:0;">
                <div class="ilb-instructions-extra">
                    <h4 class="navy"><?php _e( 'Found problem? Report it!', 'ip-location-block' ); ?></h4>
                    <p style="margin-bottom: 0;">
						<?php printf( __( 'If you found a bug, or you want to report a problem please open a support ticket <a target="_blank" href="%s">here</a> or on <a target="_blank" href="%s">Github!</a>', 'ip-location-block' ), esc_url( $url_wordpress ), esc_url( $url_github ) ); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

