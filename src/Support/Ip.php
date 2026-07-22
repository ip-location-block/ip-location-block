<?php

declare(strict_types=1);

namespace IPLocationBlock\Support;

/**
 * Native CIDR / range matcher.
 *
 * Replaces the bundled PEAR Net_IPv4 / Net_IPv6 (includes/IP/). Uses
 * inet_pton() to normalise both operands to their packed binary form and
 * compares the leading prefix bits — the standard, family-agnostic longest-
 * prefix test. Behaviour is a superset of the two former call sites:
 *
 *   - IPv4: Net_IPv4::ipInNetwork( $ip, "$subnet/$bits" )
 *   - IPv6: Net_IPv6::isInNetmask( $ip, "$subnet/$bits" )
 *
 * both collapse to Ip::matches( $ip, "$subnet/$bits" ). A missing "/bits"
 * means a full-length (host) match; a family mismatch or unparseable operand
 * yields false (the former call sites guard their inputs with filter_var(),
 * so the false-on-garbage path is defensive only).
 *
 * @since 1.4.0
 */
final class Ip {

	/**
	 * Whether $ip falls inside the CIDR block $cidr.
	 *
	 * @param string $ip   an IPv4 or IPv6 address (no prefix).
	 * @param string $cidr "subnet" or "subnet/bits"; family must match $ip.
	 *
	 * @return bool
	 */
	public static function matches( string $ip, string $cidr ): bool {
		$slash = strrpos( $cidr, '/' );
		if ( false === $slash ) {
			$subnet = $cidr;
			$prefix = null;
		} else {
			$subnet = substr( $cidr, 0, $slash );
			$prefix = substr( $cidr, $slash + 1 );
		}

		$ip_bin  = @inet_pton( $ip );
		$sub_bin = @inet_pton( $subnet );

		if ( false === $ip_bin || false === $sub_bin ) {
			return false;
		}

		$bytes = strlen( $ip_bin );
		if ( $bytes !== strlen( $sub_bin ) ) {
			return false; // IPv4 vs IPv6 mismatch.
		}

		$max_bits = $bytes * 8; // 32 for IPv4, 128 for IPv6.

		if ( null === $prefix || '' === $prefix ) {
			$prefix = $max_bits;
		}
		$prefix = (int) $prefix;

		if ( $prefix <= 0 ) {
			return true; // /0 matches any address of the same family.
		}
		if ( $prefix > $max_bits ) {
			$prefix = $max_bits;
		}

		// Compare the whole leading bytes.
		$whole = intdiv( $prefix, 8 );
		if ( $whole > 0 && substr( $ip_bin, 0, $whole ) !== substr( $sub_bin, 0, $whole ) ) {
			return false;
		}

		// Compare the remaining bits within the next byte.
		$rem = $prefix % 8;
		if ( 0 === $rem ) {
			return true;
		}

		$mask = chr( ( 0xFF << ( 8 - $rem ) ) & 0xFF );

		return ( $ip_bin[ $whole ] & $mask ) === ( $sub_bin[ $whole ] & $mask );
	}
}
