<?php
/**
 * Provider capability bitmask.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

/**
 * Single bitmask type system describing what a provider can answer.
 *
 * Plain int constants (not a backed enum): bitwise masks compose poorly with
 * enums. CITY/STATE here are pure UI/metadata — the precision gate keys on the
 * PrecisionLocationSource marker interface, never on these bits.
 */
final class Capability {

	public const IPV4         = 1;
	public const IPV6         = 2;
	public const ASN          = 4;
	public const ASN_DATABASE = 8;
	public const CITY         = 16;
	public const STATE        = 32;

	/**
	 * Bit => legacy `supports` string, in the canonical order used by every
	 * provider registry entry: Native ipv4,ipv6,asn,city,state / GeoLite2
	 * ipv4,ipv6,asn,asn_database / locals+remotes ipv4,ipv6.
	 */
	private const LEGACY_MAP = array(
		self::IPV4         => 'ipv4',
		self::IPV6         => 'ipv6',
		self::ASN          => 'asn',
		self::ASN_DATABASE => 'asn_database',
		self::CITY         => 'city',
		self::STATE        => 'state',
	);

	private function __construct() {}

	/**
	 * Whether the capability set can answer for the given IP family.
	 */
	public static function supportsIp( int $capabilities, string $ip ): bool {
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return ( $capabilities & self::IPV4 ) !== 0;
		}
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return ( $capabilities & self::IPV6 ) !== 0;
		}

		return false;
	}

	/**
	 * Convert a bitmask into the exact legacy `supports` array (canonical order).
	 *
	 * @return string[]
	 */
	public static function toLegacyList( int $capabilities ): array {
		$out = array();
		foreach ( self::LEGACY_MAP as $bit => $label ) {
			if ( ( $capabilities & $bit ) !== 0 ) {
				$out[] = $label;
			}
		}

		return $out;
	}

	/**
	 * Convert one legacy `supports` string back into its bit (0 if unknown).
	 */
	public static function fromLegacyFeature( string $feature ): int {
		$flip = array_flip( self::LEGACY_MAP );

		return $flip[ $feature ] ?? 0;
	}
}
