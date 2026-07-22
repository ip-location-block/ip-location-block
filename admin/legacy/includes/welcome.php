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
$url_native    = 'https://iplocationblock.com/codex/native-geo-location-provider/?utm_source=plugin&utm_medium=welcome&utm_campaign=codex_views';
$url_github    = 'https://github.com/gdarko/ip-location-block/';
$url_wordpress = 'https://wordpress.org/support/plugin/ip-location-block/';
$url_review    = 'https://wordpress.org/support/plugin/ip-location-block/reviews/#new-post';
$logo          = plugins_url( 'admin/images/logo.svg', IP_LOCATION_BLOCK_BASE );

$link = static function ( $url, $text ) {
	return '<a target="_blank" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
};
?>

<div class="ilb-welcome">

	<div class="ilb-welcome__hero">
		<img class="ilb-welcome__logo" src="<?php echo esc_url( $logo ); ?>" alt="" width="56" height="56" />
		<div class="ilb-welcome__intro">
			<h2 class="ilb-welcome__title">
				<?php esc_html_e( 'Thanks for installing IP Location Block', 'ip-location-block' ); ?>
				<span class="ilb-welcome__version">v<?php echo esc_html( IP_LOCATION_BLOCK_VERSION ); ?></span>
			</h2>
			<p class="ilb-welcome__desc">
				<?php esc_html_e( 'Complete geolocation blocking — keep unwanted visitors off your site. Free blacklisting and whitelisting by country, with optional precision down to state or city.', 'ip-location-block' ); ?>
			</p>
			<p class="ilb-welcome__actions">
				<a class="button button-primary" target="_blank" href="<?php echo esc_url( $url_docs ); ?>">
					<?php esc_html_e( 'Read the docs', 'ip-location-block' ); ?>
				</a>
				<a class="ilb-welcome__rate" target="_blank" title="<?php esc_attr_e( 'Give this plugin a five star rating', 'ip-location-block' ); ?>" href="<?php echo esc_url( $url_review ); ?>">
					<span class="ilb-welcome__stars" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
					<?php esc_html_e( 'Rate this plugin', 'ip-location-block' ); ?>
				</a>
			</p>
		</div>
	</div>

	<div class="ilb-welcome__cards">

		<div class="ilb-welcome__card">
			<h3 class="ilb-welcome__card-title">
				<span class="dashicons dashicons-location" aria-hidden="true"></span>
				<?php esc_html_e( 'Precision blocking by state or city', 'ip-location-block' ); ?>
				<span class="ilb-welcome__badge"><?php esc_html_e( 'New in 1.2.0+', 'ip-location-block' ); ?></span>
			</h3>
			<p>
				<?php
				printf(
					/* translators: 1: native provider link, 2: pricing page link, 3: setup guide link */
					esc_html__( 'Match visitors by state or city, not just by country, using the %1$s. Sign up for a %2$s, then %3$s.', 'ip-location-block' ),
					$link( $url_native, __( 'Native Geo-Location Provider', 'ip-location-block' ) ),
					$link( $url_purchase, __( 'premium plan', 'ip-location-block' ) ),
					$link( $url_prem_docs, __( 'learn how to set it up', 'ip-location-block' ) )
				);
				?>
			</p>
		</div>

		<div class="ilb-welcome__card">
			<h3 class="ilb-welcome__card-title">
				<span class="dashicons dashicons-sos" aria-hidden="true"></span>
				<?php esc_html_e( 'Found a problem?', 'ip-location-block' ); ?>
			</h3>
			<p>
				<?php
				printf(
					/* translators: 1: WordPress.org support link, 2: GitHub link */
					esc_html__( 'Open a support ticket %1$s, or report the issue on %2$s.', 'ip-location-block' ),
					$link( $url_wordpress, __( 'on WordPress.org', 'ip-location-block' ) ),
					$link( $url_github, __( 'GitHub', 'ip-location-block' ) )
				);
				?>
			</p>
		</div>

	</div>
</div>
