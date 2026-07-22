<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Support;

use IPLocationBlock\Support\Ip;
use PHPUnit\Framework\TestCase;

/**
 * PEAR-parity suite for the native CIDR matcher.
 *
 * Every expected value below matches the PEAR Net_IPv4::ipInNetwork() /
 * Net_IPv6::isInNetmask() semantics (the exact call shapes used by
 * IP_Location_Block::check_ips()), so this file is the frozen behavioural
 * contract for Support\Ip.
 */
final class IpTest extends TestCase {

	/**
	 * @dataProvider ipv4Provider
	 */
	public function test_ipv4_cidr( string $ip, string $cidr, bool $expected ): void {
		$this->assertSame( $expected, Ip::matches( $ip, $cidr ) );
	}

	public function ipv4Provider(): array {
		return array(
			'inside /24'          => array( '192.168.1.5', '192.168.1.0/24', true ),
			'outside /24'         => array( '192.168.2.5', '192.168.1.0/24', false ),
			'/0 matches all'      => array( '10.0.0.1', '0.0.0.0/0', true ),
			'/0 matches all (2)'  => array( '0.0.0.0', '255.255.255.255/0', true ),
			'/32 exact match'     => array( '1.2.3.4', '1.2.3.4/32', true ),
			'/32 no match'        => array( '1.2.3.5', '1.2.3.4/32', false ),
			'/25 upper half in'   => array( '192.168.1.130', '192.168.1.128/25', true ),
			'/25 lower half out'  => array( '192.168.1.126', '192.168.1.128/25', false ),
			'/12 private in'      => array( '172.16.5.4', '172.16.0.0/12', true ),
			'/12 private out'     => array( '172.32.5.4', '172.16.0.0/12', false ),
			'/16 in'              => array( '8.8.8.8', '8.8.0.0/16', true ),
			'broadcast /32'       => array( '255.255.255.255', '255.255.255.255/32', true ),
			'no slash -> host eq' => array( '10.0.0.0', '10.0.0.0', true ),
			'no slash -> host ne' => array( '10.0.0.5', '10.0.0.0', false ),
		);
	}

	/**
	 * @dataProvider ipv6Provider
	 */
	public function test_ipv6_cidr( string $ip, string $cidr, bool $expected ): void {
		$this->assertSame( $expected, Ip::matches( $ip, $cidr ) );
	}

	public function ipv6Provider(): array {
		return array(
			'/32 in'                    => array( '2001:db8::1', '2001:db8::/32', true ),
			'/32 out'                   => array( '2001:db9::1', '2001:db8::/32', false ),
			'/32 deeper still in'       => array( '2001:db8:1::1', '2001:db8::/32', true ),
			'/0 matches all'            => array( '::1', '::/0', true ),
			'/128 loopback exact'       => array( '::1', '::1/128', true ),
			'/128 loopback no match'    => array( '::2', '::1/128', false ),
			'fully-expanded == compact' => array( '2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1/128', true ),
			'uppercase == lowercase'    => array( '2001:DB8::1', '2001:db8::1/128', true ),
			'zero-run == compact'       => array( '2001:db8:0:0:0:0:0:1', '2001:db8::1/128', true ),
			'link-local /10'            => array( 'fe80::1', 'fe80::/10', true ),
			'/64 in'                    => array( '2001:db8:abcd:12::1', '2001:db8:abcd:12::/64', true ),
			'/64 out'                   => array( '2001:db8:abcd:13::1', '2001:db8:abcd:12::/64', false ),
			'unique-local /8'           => array( 'fd00::1', 'fd00::/8', true ),
			'ipv4-mapped /120'          => array( '::ffff:192.168.1.1', '::ffff:192.168.1.0/120', true ),
		);
	}

	/**
	 * @dataProvider edgeProvider
	 */
	public function test_edge_cases( string $ip, string $cidr, bool $expected ): void {
		$this->assertSame( $expected, Ip::matches( $ip, $cidr ) );
	}

	public function edgeProvider(): array {
		return array(
			// Malformed operands are rejected (the call sites also guard with
			// filter_var(), so this is the defensive path).
			'invalid ip'            => array( 'not-an-ip', '10.0.0.0/8', false ),
			'invalid subnet'        => array( '10.0.0.5', 'garbage/8', false ),
			// Family mismatch never matches, regardless of prefix.
			'v4 ip vs v6 net'       => array( '10.0.0.5', '2001:db8::/32', false ),
			'v6 ip vs v4 net'       => array( '2001:db8::1', '10.0.0.0/8', false ),
			'v4 ip vs v6 net /0'    => array( '10.0.0.5', '::/0', false ),
		);
	}
}
