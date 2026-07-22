<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Providers;

use IPLocationBlock\Providers\Capability;
use IPLocationBlock\Tests\Unit\TestCase;

final class CapabilityTest extends TestCase {

	public function test_to_legacy_list_uses_canonical_order(): void {
		$bits = Capability::IPV4 | Capability::IPV6 | Capability::ASN | Capability::CITY | Capability::STATE;

		$this->assertSame(
			array( 'ipv4', 'ipv6', 'asn', 'city', 'state' ),
			Capability::toLegacyList( $bits )
		);
	}

	public function test_to_legacy_list_geolite2_order(): void {
		$bits = Capability::IPV4 | Capability::IPV6 | Capability::ASN | Capability::ASN_DATABASE;

		$this->assertSame(
			array( 'ipv4', 'ipv6', 'asn', 'asn_database' ),
			Capability::toLegacyList( $bits )
		);
	}

	public function test_from_legacy_feature_round_trips(): void {
		foreach ( array( 'ipv4', 'ipv6', 'asn', 'asn_database', 'city', 'state' ) as $feature ) {
			$bit = Capability::fromLegacyFeature( $feature );
			$this->assertNotSame( 0, $bit );
			$this->assertContains( $feature, Capability::toLegacyList( $bit ) );
		}
		$this->assertSame( 0, Capability::fromLegacyFeature( 'nope' ) );
	}

	/**
	 * @dataProvider familyProvider
	 */
	public function test_supports_ip( int $caps, string $ip, bool $expected ): void {
		$this->assertSame( $expected, Capability::supportsIp( $caps, $ip ) );
	}

	public function familyProvider(): array {
		return array(
			'v4 provider, v4 ip'   => array( Capability::IPV4, '8.8.8.8', true ),
			'v4 provider, v6 ip'   => array( Capability::IPV4, '2404:6800:4004::1', false ),
			'v6 provider, v6 ip'   => array( Capability::IPV6, '2404:6800:4004::1', true ),
			'v6 provider, v4 ip'   => array( Capability::IPV6, '8.8.8.8', false ),
			'both, v4'             => array( Capability::IPV4 | Capability::IPV6, '8.8.8.8', true ),
			'both, v6'             => array( Capability::IPV4 | Capability::IPV6, '::1', true ),
			'both, garbage'        => array( Capability::IPV4 | Capability::IPV6, 'not-an-ip', false ),
		);
	}
}
