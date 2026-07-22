<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Support;

use Brain\Monkey;
use Brain\Monkey\Functions;
use IPLocationBlock\Support\Util;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the pure / stubbable helpers on Support\Util.
 *
 * Only genuinely side-effect-free (or trivially WP-stubbable) methods are
 * covered here; the crypto / cookie / filesystem surface is exercised by the
 * real-WP smoke checks rather than mocked to death.
 */
final class UtilTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider privateIpProvider
	 */
	public function test_is_private_ip( string $ip, bool $expected ): void {
		$this->assertSame( $expected, Util::is_private_ip( $ip ) );
	}

	public function privateIpProvider(): array {
		return array(
			'loopback v4'   => array( '127.0.0.1', true ),
			'rfc1918 10/8'  => array( '10.0.0.1', true ),
			'rfc1918 172'   => array( '172.16.5.4', true ),
			'rfc1918 192'   => array( '192.168.1.1', true ),
			'public v4'     => array( '8.8.8.8', false ),
			'public v4 (2)' => array( '1.1.1.1', false ),
			'loopback v6'   => array( '::1', true ),
			'public v6'     => array( '2404:6800:4004::1', false ),
			'not an ip'     => array( 'not-an-ip', true ), // filter_var fails -> treated private
		);
	}

	public function test_multiexplode_splits_on_multiple_delimiters(): void {
		$this->assertSame(
			array( 'a', 'b', 'c', 'd' ),
			array_values( Util::multiexplode( array( ',', "\n" ), "a,b\nc,d" ) )
		);
	}

	public function test_multiexplode_passes_through_arrays(): void {
		$in = array( 'x', 'y' );
		$this->assertSame( $in, Util::multiexplode( array( ',' ), $in ) );
	}

	public function test_slashit_and_unslashit(): void {
		$this->assertSame( '/a/b/', Util::slashit( '/a/b' ) );
		$this->assertSame( '/a/b/', Util::slashit( '/a/b/' ) );
		$this->assertSame( '/a/b/', Util::slashit( '/a/b\\' ) );
		$this->assertSame( '/a/b', Util::unslashit( '/a/b/' ) );
		$this->assertSame( '/a/b', Util::unslashit( '/a/b\\' ) );
	}

	/**
	 * @dataProvider asnProvider
	 */
	public function test_parse_asn( string $in, string $expected ): void {
		$this->assertSame( $expected, Util::parse_asn( $in ) );
	}

	public function asnProvider(): array {
		return array(
			'labelled'   => array( 'AS81281 Provider Name', 'AS81281' ),
			'bare number' => array( '9239', 'AS9239' ),
			'prefixed'   => array( 'AS1111', 'AS1111' ),
		);
	}

	/**
	 * @dataProvider wildcardProvider
	 */
	public function test_wildcard_match( string $needle, string $haystack, bool $expected ): void {
		$this->assertSame( $expected, Util::wildcard_match( $needle, $haystack ) );
	}

	public function wildcardProvider(): array {
		return array(
			'star suffix hit'   => array( 'health-check*', 'health-check-page', true ),
			'star suffix miss'  => array( 'health-check*', 'other-page', false ),
			'exact'             => array( 'heartbeat', 'heartbeat', true ),
			'question mark'     => array( 'gf?', 'gfx', true ),
			'question too long' => array( 'gf?', 'gfxx', false ),
		);
	}

	public function test_wildcard_in_array(): void {
		$haystack = array( 'oembed-cache', 'jetpack*', 'wp_block*' );
		$this->assertTrue( Util::wildcard_in_array( 'jetpack_modules', $haystack ) );
		$this->assertTrue( Util::wildcard_in_array( 'oembed-cache', $haystack ) );
		$this->assertFalse( Util::wildcard_in_array( 'delete-post', $haystack ) );
		$this->assertFalse( Util::wildcard_in_array( 'anything', 'not-iterable' ) );
	}

	public function test_array_except_removes_listed_values(): void {
		$this->assertSame(
			array( 'a', 'c' ),
			Util::array_except( array( 'a', 'b', 'c' ), array( 'b' ) )
		);
	}

	public function test_anonymize_ip_strict_masks_last_group(): void {
		$this->assertSame( '192.168.1.***', Util::anonymize_ip( '192.168.1.55' ) );
	}

	public function test_mask_qualification_strips_host_tokens(): void {
		// HOST and HOST=... tokens collapse to '*'/removed.
		$this->assertSame( 'Google:*,bot:*', Util::mask_qualification( 'Google:HOST,bot:HOST' ) );
	}

	/**
	 * Brain Monkey: allowed_pages_actions() runs the 'ip-location-block-bypass-admins'
	 * filter and merges the result with the built-in allow-list.
	 */
	public function test_allowed_pages_actions_merges_filter_and_defaults(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) {
				return $value; // no external additions
			}
		);

		$actions = Util::allowed_pages_actions( array() );

		$this->assertIsArray( $actions );
		$this->assertContains( 'heartbeat', $actions );
		$this->assertContains( 'jetpack*', $actions );
	}

	public function test_allowed_pages_actions_prepends_filtered_entries(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) {
				return array( 'my-custom-action' );
			}
		);

		$actions = Util::allowed_pages_actions( array() );

		$this->assertContains( 'my-custom-action', $actions );
		$this->assertContains( 'heartbeat', $actions );
	}
}
