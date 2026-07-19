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

class IP_Location_Block_Admin_Tab {

	public static function tab_setup( $context, $tab ) {
		$options = IP_Location_Block::get_option();

		register_setting(
			$option_slug = IP_Location_Block::PLUGIN_NAME,
			$option_name = IP_Location_Block::OPTION_NAME
		);

		/*----------------------------------------*
		 * Geolocation
		 *----------------------------------------*/
		add_settings_section(
			$section = IP_Location_Block::PLUGIN_NAME . '-search',
			__( 'Search IP address geolocation', 'ip-location-block' ),
			null,
			$option_slug
		);

		// make providers list
		$list      = array();
		$providers = IP_Location_Block_Provider::get_providers( 'key' );
		foreach ( $providers as $provider => $key ) {
			if ( ! is_string( $key ) || // provider that does not need api key
			     ! empty( $options['providers'][ $provider ] ) ) { // provider that has api key
				$list += array( $provider => $provider );
			}
		}

		// get selected item
		$provider  = array();
		$providers = array_keys( $providers );
		$cookie    = $context->get_cookie();
		if ( isset( $cookie[ $tab ] ) ) {
			foreach ( array_slice( (array) $cookie[ $tab ], 3 ) as $key => $val ) {
				if ( 'o' === $val && isset( $providers[ $key ] ) ) {
					$provider[] = $providers[ $key ];
				}
			}
		}

		add_settings_field(
			$option_name . '_service',
			__( 'Geolocation API', 'ip-location-block' ),
			array( $context, 'callback_field' ),
			$option_slug,
			$section,
			array(
				'type'   => 'select',
				'attr'   => 'multiple="multiple"',
				'option' => $option_name,
				'field'  => 'service',
				'value'  => ! empty( $provider ) ? $provider : $providers[0],
				'list'   => $list,
			)
		);

		// preset IP address
		if ( isset( $_GET['s'] ) ) {
			$list = preg_replace(
				array( '/\.\*+$/', '/:\w*\*+$/', '/(::.*)::$/' ),
				array( '.0', '::', '$1' ),
				trim( sanitize_text_field( trim( $_GET['s'] ) ) )
			); // de-anonymize if `***` exists
			$list = filter_var( $list, FILTER_VALIDATE_IP ) ? $list : '';
		} else {
			$list = '';
		}

		add_settings_field(
			$option_name . '_ip_address',
			__( 'IP address', 'ip-location-block' ),
			array( $context, 'callback_field' ),
			$option_slug,
			$section,
			array(
				'type'   => 'text',
				'option' => $option_name,
				'field'  => 'ip_address',
				'value'  => $list,
			)
		);

		// Anonymize IP address
		add_settings_field(
			$option_name . '_anonymize',
			__( '<dfn title="IP address is always encrypted on recording in Cache and Logs. Moreover, this option replaces the end of IP address with &#8220;***&#8221; to make it anonymous.">Anonymize IP address</dfn>', 'ip-location-block' ),
			array( $context, 'callback_field' ),
			$option_slug,
			$section,
			array(
				'type'   => 'checkbox',
				'option' => $option_name,
				'field'  => 'anonymize',
				'value'  => ! empty( $options['anonymize'] ) || ! empty( $options['restrict_api'] ),
			)
		);

		// Search geolocation
		add_settings_field(
			$option_name . '_get_location',
			__( 'Search geolocation', 'ip-location-block' ),
			array( $context, 'callback_field' ),
			$option_slug,
			$section,
			array(
				'type'   => 'button',
				'option' => $option_name,
				'field'  => 'get_location',
				'value'  => __( 'Search now', 'ip-location-block' ),
				'after'  => '<div id="ip-location-block-loading"></div>',
			)
		);
	}

}