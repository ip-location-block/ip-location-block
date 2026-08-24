<?php

/**
 * IP Location Block - Attribution Class
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

class IP_Location_Block_Admin_Tab {

	public static function tab_setup( $context, $tab ) {

		register_setting(
			$option_slug = IP_Location_Block::PLUGIN_NAME,
			$option_name = IP_Location_Block::OPTION_NAME
		);

		add_settings_section(
			$section = IP_Location_Block::PLUGIN_NAME . '-attribution',
			__( 'Attribution links', 'ip-location-block' ),
			null,
			$option_slug
		);

		foreach ( IP_Location_Block_Provider::all() as $provider => $data ) {
			if ( 'Cache' === $provider ) {
				continue;
			}
			add_settings_field(
				$option_name . '_attribution_' . $provider,
				$provider,
				array( $context, 'callback_field' ),
				$option_slug,
				$section,
				array(
					'type'   => 'html',
					'option' => $option_name,
					'field'  => 'attribution',
					'value'  => sprintf( '<a href="%s" target="_blank">%s</a> (%s)', esc_url( $data['link'] ), esc_url( $data['link'] ), $data['type'] ),
				)
			);
		}
	}

}
