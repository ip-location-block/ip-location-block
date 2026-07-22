<?php
/**
 * IP Location Block - DNS lookup
 *
 * @package   IP_Location_Block
 * @author    Darko Gjorgjijoski <dg@darkog.com>
 * @license   GPL-3.0
 * @link      https://iplocationblock.com/
 * @copyright 2021 darkog
 * @copyright 2013-2019 tokkonopapa
 */

namespace IPLocationBlock\Support;

/**
 * Class Dns
 *
 * The legacy class name IP_Location_Block_Lkup is kept working via
 * class_alias in compat/legacy-aliases.php.
 *
 * Two deliberate changes vs. the legacy class:
 *   - the lazy `require_once includes/Net/DNS2.php` / set_include_path() pair is
 *     dropped; pear/net_dns2 is now provided by the composer autoloader, so
 *     \Net_DNS2_Resolver resolves on demand.
 *   - the private inet_pton() polyfill is removed (native since PHP 7.0; the
 *     plugin now targets PHP 8.1+), calling the global inet_pton() directly.
 */
class Dns {

	/**
	 * DNS lookup by ip
	 * @param $ip
	 *
	 * @return mixed|string
	 */
	public static function gethostbyaddr( $ip ) {
		// array( 'nameservers' => array( '8.8.8.8', '8.8.4.4' ) ) // Google public DNS
		// array( 'nameservers' => array( '1.1.1.1', '1.0.0.1' ) ) // APNIC public DNS
		$servers = array( 'nameservers' => apply_filters( 'ip-location-block-dns', array() ) );
		if ( ! empty( $servers['nameservers'] ) ) {
			$r = new \Net_DNS2_Resolver( $servers );

			try {
				$result = $r->query( $ip, 'PTR' );
			}
			catch ( \Net_DNS2_Exception $e ) {
				$result = $e->getMessage();
			}

			if ( isset( $result->answer ) ) {
				foreach ( $result->answer as $obj ) {
					if ( 'PTR' === $obj->type ) {
						return $obj->ptrdname;
					}
				}
			}
		}

		// available on Windows platforms after PHP 5.3.0
		if ( function_exists( 'gethostbyaddr' ) )
			$host = @gethostbyaddr( $ip );

		// if not available
		if ( empty( $host ) && function_exists( 'dns_get_record' ) ) {
			// generate in-addr.arpa notation
			if ( FALSE !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				$ptr = implode( ".", array_reverse( explode( ".", $ip ) ) ) . ".in-addr.arpa";
			}

			elseif ( FALSE !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				$ptr = inet_pton( $ip );
				$ptr = implode(".", array_reverse( str_split( bin2hex( $ptr ) ) ) ) . ".ip6.arpa";
			}

			if ( isset( $ptr ) and $ptr = @dns_get_record( $ptr, DNS_PTR ) ) {
				return $ptr[0]['target'];
			}
		}

		return empty( $host ) ? $ip : $host;
	}

}
