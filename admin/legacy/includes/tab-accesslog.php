<?php

/**
 * IP Location Block - Access Log
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
		$cookie      = $context->get_cookie();
		$options     = IP_Location_Block::get_option();
		$plugin_slug = IP_Location_Block::PLUGIN_NAME;

		register_setting(
			$option_slug = IP_Location_Block::PLUGIN_NAME,
			$option_name = IP_Location_Block::OPTION_NAME
		);

		/*----------------------------------------*
		 * Validation logs
		 *----------------------------------------*/
		add_settings_section(
			$section = $plugin_slug . '-logs',
			array(
				__( 'Validation logs', 'ip-location-block' ),
				'<a href="https://iplocationblock.com/codex/validation-logs/" title="Validation logs | IP Location Block">' . __( 'Help', 'ip-location-block' ) . '</a>'
			),
			( $options['validation']['reclogs'] ?
				array( __CLASS__, 'validation_logs' ) :
				array( __CLASS__, 'warn_accesslog' )
			),
			$option_slug
		);

		if ( $options['validation']['reclogs'] ):

			if ( extension_loaded( 'pdo_sqlite' ) ):
				$html = '<ul id="ip-location-block-live-log">';
				$html .= '<li><input type="radio" name="ip-location-block-live-log" id="ip-location-block-live-log-start" value="start"><label for="ip-location-block-live-log-start" title="Start"><span class="ip-location-block-icon-play"></span></label></li>';
				$html .= '<li><input type="radio" name="ip-location-block-live-log" id="ip-location-block-live-log-pause" value="pause"><label for="ip-location-block-live-log-pause" title="Pause"><span class="ip-location-block-icon-pause"></span></label></li>';
				$html .= '<li><input type="radio" name="ip-location-block-live-log" id="ip-location-block-live-log-stop"  value="stop" checked><label for="ip-location-block-live-log-stop" title="Stop"><span class="ip-location-block-icon-stop"></span></label></li>';
				$html .= '</ul>';

				// Live update
				add_settings_field(
					$option_name . '_live-log',
					__( 'Live update', 'ip-location-block' ) . '<div id="ip-location-block-live-loading"><div></div><div></div></div>',
					array( $context, 'callback_field' ),
					$option_slug,
					$section,
					array(
						'type'   => 'html',
						'option' => $option_name,
						'field'  => 'live-log',
						'value'  => $html,
						'class'  => isset( $cookie[ $tab ][1] ) && $cookie[ $tab ][1] === 'o' ? '' : 'ip-location-block-hide',
					)
				);
			endif; // extension_loaded( 'pdo_sqlite' )

			// make a list of target (same as in tab-accesslog.php)
			$target = array(
				'comment' => __( 'Comment post', 'ip-location-block' ),
				'xmlrpc'  => __( 'XML-RPC', 'ip-location-block' ),
				'login'   => __( 'Login form', 'ip-location-block' ),
				'admin'   => __( 'Admin area', 'ip-location-block' ),
				'public'  => __( 'Public facing pages', 'ip-location-block' ),
			);

			$html = "\n" . '<li><label><input type="radio" name="' . $plugin_slug . '-target" value="all" checked="checked" />' . __( 'All', 'ip-location-block' ) . '</label></li>' . "\n";
			foreach ( $target as $key => $val ) {
				$html .= '<li><label><input type="radio" name="' . $plugin_slug . '-target" value="' . $key . '" />';
				$html .= '<dfn title="' . $val . '">' . $key . '</dfn>' . '</label></li>' . "\n";
			}

			// Select target
			add_settings_field(
				$option_name . '_select_target',
				__( 'Select target', 'ip-location-block' ),
				array( $context, 'callback_field' ),
				$option_slug,
				$section,
				array(
					'type'   => 'html',
					'option' => $option_name,
					'field'  => 'select_target',
					'value'  => '<ul id="' . $plugin_slug . '-select-target">' . $html . '</ul>',
				)
			);

			// Search in logs
			add_settings_field(
				$option_name . '_search_filter',
				__( 'Search in logs', 'ip-location-block' ),
				array( $context, 'callback_field' ),
				$option_slug,
				$section,
				array(
					'type'   => 'text',
					'option' => $option_name,
					'field'  => 'search_filter',
					'value'  => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '', // preset filter
					'after'  => '<a class="button button-secondary" id="ip-location-block-reset-filter" title="' . __( 'Reset', 'ip-location-block' ) . '" href="#!">' . __( 'Reset', 'ip-location-block' ) . '</a>',
				)
			);

			// Preset filters
			$filters = has_filter( 'ip-location-block-logs-preset' ) ? apply_filters( 'ip-location-block-logs-preset', array() ) : $context->preset_filters();
			if ( ! empty( $filters ) ) {
				// allowed tags and attributes
				$allow_tags = array(
					'span' => array(
						'class' => 1,
						'title' => 1,
					)
				);

				$html = '<ul id="ip-location-block-logs-preset">';
				foreach ( $filters as $filter ) {
					$html .= '<li><a href="#!" data-value="' . esc_attr( $filter['value'] ) . '">' . IP_Location_Block_Util::kses( $filter['title'], $allow_tags ) . '</a></li>';
				}

				add_settings_field(
					$option_name . '_logs_preset',
					'<div class="ip-location-block-subitem">' . __( 'Preset filters', 'ip-location-block' ) . '</div>',
					array( $context, 'callback_field' ),
					$option_slug,
					$section,
					array(
						'type'   => 'html',
						'option' => $option_name,
						'field'  => 'logs_preset',
						'value'  => $html,
					)
				);
			}

			// Bulk action
			$blk_action_options = array(
				0                      => null,
				'bulk-action-ip-erase' => __( 'Remove entries by IP address', 'ip-location-block' ),
				'bulk-action-ip-white' => __( 'Add IP address to &#8220;Whitelist&#8221;', 'ip-location-block' ),
				'bulk-action-ip-black' => __( 'Add IP address to &#8220;Blacklist&#8221;', 'ip-location-block' ),
			);
			if ( isset( $options['use_asn'] ) && (int) $options['use_asn']  ) {
				$blk_action_options = array_merge( $blk_action_options, array(
					'bulk-action-as-white' => __( 'Add AS number to &#8220;Whitelist&#8221;', 'ip-location-block' ),
					'bulk-action-as-black' => __( 'Add AS number to &#8220;Blacklist&#8221;', 'ip-location-block' ),
				) );
			}

			add_settings_field(
				$option_name . '_bulk_action',
				__( 'Bulk action', 'ip-location-block' ),
				array( $context, 'callback_field' ),
				$option_slug,
				$section,
				array(
					'type'   => 'select',
					'option' => $option_name,
					'field'  => 'bulk_action',
					'value'  => 0,
					'list'   => $blk_action_options,
					'after'  => '<a class="button button-secondary" id="ip-location-block-bulk-action" title="' . __( 'Apply', 'ip-location-block' ) . '" href="#!">' . __( 'Apply', 'ip-location-block' ) . '</a>' . '<div id="' . $plugin_slug . '-loading"></div>',
				)
			);

			// Clear logs
			add_settings_field(
				$option_name . '_clear_all',
				__( 'Clear logs', 'ip-location-block' ),
				array( $context, 'callback_field' ),
				$option_slug,
				$section,
				array(
					'type'   => 'button',
					'option' => $option_name,
					'field'  => 'clear_all',
					'value'  => __( 'Clear all', 'ip-location-block' ),
					'after'  => '<div id="' . $plugin_slug . '-logs"></div>',
					'class'  => empty( $cookie[ $tab ][1] ) || $cookie[ $tab ][1] !== 'o' ? '' : 'ip-location-block-hide',
				)
			);

			// Export logs
			add_settings_field(
				$option_name . '_export_logs',
				__( 'Export logs', 'ip-location-block' ),
				array( $context, 'callback_field' ),
				$option_slug,
				$section,
				array(
					'type'   => 'none',
					'before' => '<a class="button button-secondary" id="ip-location-block-export-logs" title="' . __( 'Export to the local file', 'ip-location-block' ) . '" href="#!">' . __( 'Export csv', 'ip-location-block' ) . '</a>',
					'after'  => '<div id="' . $plugin_slug . '-export"></div>',
					'class'  => empty( $cookie[ $tab ][1] ) || $cookie[ $tab ][1] !== 'o' ? '' : 'ip-location-block-hide',
				)
			);

		endif; // $options['validation']['reclogs']

	}

	/**
	 * Function that fills the section with the desired content.
	 *
	 */
	private static function dashboard_url() {
		$options = IP_Location_Block::get_option();
		$context = IP_Location_Block_Admin::get_instance();

		return $context->dashboard_url( $options['network_wide'] );
	}

	public static function validation_logs() {
		echo '<table id="', IP_Location_Block::PLUGIN_NAME, '-validation-logs" class="', IP_Location_Block::PLUGIN_NAME, '-dataTable display" cellspacing="0" width="100%">', "\n", '<thead></thead><tbody></tbody></table>', "\n";
	}

	public static function warn_accesslog() {
		$url = esc_url( add_query_arg( array(
				'page' => IP_Location_Block::PLUGIN_NAME,
				'tab'  => '0',
				'sec'  => 3
			), self::dashboard_url() ) . '#' . IP_Location_Block::PLUGIN_NAME . '-section-3' );
		echo '<p style="padding:0 1em">', sprintf( __( '[ %sRecord &#8220;Validation logs&#8221;%s ] is disabled.', 'ip-location-block' ), '<a href="' . $url . '">', '</a>' ), '</p>', "\n";
		echo '<p style="padding:0 1em">', __( 'Please set the proper condition to record and analyze the validation logs.', 'ip-location-block' ), '</p>', "\n";
	}

}
