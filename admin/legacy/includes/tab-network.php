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

	// UI control parameters
	static $controls = array(
		'time' => 0, // Duration to retrieve
		'rows' => 2, // Rows
		'cols' => 1, // Columns
		'warn' => false,
	);

	public static function tab_setup( $context, $tab ) {
		/*----------------------------------------*
		 * Control parameters in cookie
		 *----------------------------------------*/
		$options                = IP_Location_Block::get_option();
		$cookie                 = $context->get_cookie(); // [0]:Section, [1]:Open a new window, [2]:Duration to retrieve, [3]:Row, [4]:Column
		self::$controls['time'] = empty( $cookie[ $tab ][2] ) ? self::$controls['time'] : min( 3, max( 0, (int) $cookie[ $tab ][2] ) );
		self::$controls['rows'] = empty( $cookie[ $tab ][3] ) ? self::$controls['rows'] : min( 4, max( 1, (int) $cookie[ $tab ][3] ) );
		self::$controls['cols'] = empty( $cookie[ $tab ][4] ) ? self::$controls['cols'] : min( 5, max( 1, (int) $cookie[ $tab ][4] ) );
		self::$controls['warn'] = ! $options['validation']['reclogs'];

		/*----------------------------------------*
		 * Blocked by target in logs section
		 *----------------------------------------*/
		register_setting(
			$option_slug = IP_Location_Block::PLUGIN_NAME,
			$option_name = IP_Location_Block::OPTION_NAME
		);

		add_settings_section(
			$section = IP_Location_Block::PLUGIN_NAME . '-network',
			__( 'Blocked by target in logs', 'ip-location-block' ),
			array( __CLASS__, 'render_network' ),
			$option_slug
		);

		/*----------------------------------------*
		 * Chart display layout
		 *----------------------------------------*/
		$html = '<ul id="ip-location-block-select-layout">';
		$html .= '<li>' . __( 'Rows', 'ip-location-block' ) . ' : <select name="rows">';
		$html .= '<option value="1"' . selected( 1, self::$controls['rows'], false ) . '> 5</option>';
		$html .= '<option value="2"' . selected( 2, self::$controls['rows'], false ) . '>10</option>';
		$html .= '<option value="4"' . selected( 4, self::$controls['rows'], false ) . '>20</option>';
		$html .= '</select></li>';
		$html .= '<li>' . __( 'Columns', 'ip-location-block' ) . ' : <select name="cols">';
		$html .= '<option value="1"' . selected( 1, self::$controls['cols'], false ) . '>1</option>';
		$html .= '<option value="2"' . selected( 2, self::$controls['cols'], false ) . '>2</option>';
		$html .= '<option value="3"' . selected( 3, self::$controls['cols'], false ) . '>3</option>';
		$html .= '<option value="4"' . selected( 4, self::$controls['cols'], false ) . '>4</option>';
		$html .= '<option value="5"' . selected( 5, self::$controls['cols'], false ) . '>5</option>';
		$html .= '</select></li>';
		$html .= '<li><a id="ip-location-block-apply-layout" class="button button-secondary" href="';
		$html .= esc_url( add_query_arg( array(
			'page' => IP_Location_Block::PLUGIN_NAME,
			'tab'  => 5
		), network_admin_url( 'admin.php' ) ) );
		$html .= '">' . __( 'Apply', 'ip-location-block' ) . '</a></li>';
		$html .= '</ul>';

		add_settings_field(
			$option_name . '_chart-size',
			__( 'Chart display layout', 'ip-location-block' ),
			array( $context, 'callback_field' ),
			$option_slug,
			$section,
			array(
				'type'  => 'html',
				'value' => $html,
			)
		);

		/*----------------------------------------*
		 * Duration to retrieve
		 *----------------------------------------*/
		$time = array(
			__( 'All', 'ip-location-block' ),
			__( 'Latest 1 hour', 'ip-location-block' ),
			__( 'Latest 24 hours', 'ip-location-block' ),
			__( 'Latest 1 week', 'ip-location-block' ),
		);

		// make a list of duration
		$html = "\n";
		foreach ( $time as $key => $val ) {
			$html .= '<li><label><input type="radio" name="' . $option_slug . '-duration" value="' . $key . '"'
			         . ( $key == self::$controls['time'] ? ' checked="checked"' : '' ) . ' />' . $val . '</label></li>' . "\n";
		}

		add_settings_field(
			$option_name . '_select_duration',
			__( 'Duration to retrieve', 'ip-location-block' ),
			array( $context, 'callback_field' ),
			$option_slug,
			$section,
			array(
				'type'  => 'html',
				'value' => '<ul id="' . $option_slug . '-select-duration">' . $html . '</ul>',
			)
		);
	}

	/**
	 * Render log data
	 *
	 * @param array $args associative array of `id`, `title`, `callback`.
	 */
	public static function render_network( $args ) {
		require_once IP_LOCATION_BLOCK_PATH . 'admin/legacy/includes/class-admin-ajax.php';

		if ( self::$controls['warn'] ) {
			$context = IP_Location_Block_Admin::get_instance();
			$url     = esc_url( add_query_arg( array(
					'page' => IP_Location_Block::PLUGIN_NAME,
					'tab'  => '0',
					'sec'  => 5
				), $context->dashboard_url() ) . '#' . IP_Location_Block::PLUGIN_NAME . '-section-5' );
			echo '<p style="padding:0 1em">', sprintf( __( '[ %sRecord &#8220;Validation logs&#8221;%s ] is disabled.', 'ip-location-block' ), '<a href="' . $url . '"><strong>', '</strong></a>' ), '</p>', "\n";
			echo '<p style="padding:0 1em">', __( 'Please set the proper condition to record and analyze the validation logs.', 'ip-location-block' ), '</p>', "\n";
		}

		$row   = self::$controls['rows'] * 5;
		$col   = self::$controls['cols'];
		$page  = empty( $_REQUEST['p'] ) ? 0 : (int) $_REQUEST['p'];
		$start = $page * ( $row * $col );
		$count = min( $total = IP_Location_Block_Admin_Ajax::get_network_count(), $row * $col );

		// [0]:site, [1]:comment, [2]:xmlrpc, [3]:login, [4]:admin, [5]:public, [6]:link
		$json = IP_Location_Block_Admin_Ajax::restore_network( self::$controls['time'], $start, $count, false );

		// Max value on hAxis
		$max = 0;
		$num = count( $json );
		for ( $i = 0; $i < $num; ++ $i ) {
			$max = max( $max, array_sum( array_slice( $json[ $i ], 1, 5 ) ) );
		}

		// Split the array into chunks
		$arr = array_chunk( $json, $row );
		$num = (int) floor( count( $arr ) / $col );

		// Embed array into data attribute as json
		echo '<div class="ip-location-block-row ip-location-block-range" data-ip-location-block-range="[0,', $max, ']">', "\n";
		for ( $i = 0; $i < $col; ++ $i ) {
			if ( isset( $arr[ $i ] ) ) {
				echo '<div class="ip-location-block-network ip-location-block-column" ',
				'id="', $args['id'], '-', $i, '" ',
				'data-', $args['id'], '-', $i, '=\'', json_encode( $arr[ $i ] ), '\'>',
				'</div>', "\n";
			} else {
				echo '<div class="ip-location-block-column"></div>';
			}
		}
		echo '</div>', "\n";

		// pagination
		$url = esc_url( add_query_arg( array(
			'page' => IP_Location_Block::PLUGIN_NAME,
			'tab'  => 5
		), network_admin_url( 'admin.php' ) ) );
		echo '<div class="dataTables_wrapper"><div class="dataTables_paginate">', "\n",
		'<a class="paginate_button first', ( $page === 0 ? ' disabled' : '' ), '" href="', $url, '&p=', ( 0 ), '">&laquo;</a>',
		'<a class="paginate_button previous', ( $page === 0 ? ' disabled' : '' ), '" href="', $url, '&p=', ( 0 < $page ? $page - 1 : 0 ), '">&lsaquo;</a><span>';

		$num = (int) ceil( $total / ( $row * $col ) );
		for ( $i = 0; $i < $num; ++ $i ) {
			echo '<a class="paginate_button', ( $i === $page ? ' current' : '' ), '" href="', $url, '&p=', $i, '">', $i + 1, '</a>';
		}
		$num -= 1;

		echo '</span>',
		'<a class="paginate_button next', ( $page === $num ? ' disabled' : '' ), '" href="', $url, '&p=', ( $num > $page ? $page + 1 : $page ), '">&rsaquo;</a>',
		'<a class="paginate_button last', ( $page === $num ? ' disabled' : '' ), '" href="', $url, '&p=', ( $num ), '">&raquo;</a>',
		'</div></div>', "\n"; // paginate wrapper
	}

}