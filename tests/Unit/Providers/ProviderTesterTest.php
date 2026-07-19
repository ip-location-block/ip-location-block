<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Providers;

use Brain\Monkey\Functions;
use IPLocationBlock\Providers\NativeQuotaService;
use IPLocationBlock\Providers\ProviderTester;
use IPLocationBlock\Tests\Unit\TestCase;

final class ProviderTesterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_salt' )->justReturn( 'salt' );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		NativeQuotaService::flushMemo();
	}

	private function tester(): ProviderTester {
		return new ProviderTester();
	}

	/** Point wp_remote_get at a canned geo response and (optionally) quota. */
	private function stubHttp( string $geoBody, ?string $quotaBody = null ): void {
		Functions\when( 'wp_remote_get' )->alias(
			static function ( $url ) use ( $geoBody, $quotaBody ) {
				if ( null !== $quotaBody && false !== strpos( (string) $url, '/quota/' ) ) {
					return array( 'body' => $quotaBody, '__ct' => 'application/json' );
				}
				return array( 'body' => $geoBody, '__ct' => 'application/json' );
			}
		);
		Functions\when( 'wp_remote_retrieve_header' )->alias(
			static fn( $r, $h ) => is_array( $r ) ? ( $r['__ct'] ?? '' ) : ''
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( $r ) => is_array( $r ) ? ( $r['body'] ?? '' ) : ''
		);
	}

	public function test_cache_provider_is_rejected(): void {
		$this->assertInstanceOf( \WP_Error::class, $this->tester()->test( 'Cache', '', array() ) );
	}

	public function test_unknown_provider_is_rejected(): void {
		$this->assertInstanceOf( \WP_Error::class, $this->tester()->test( 'Bogus', 'x', array() ) );
	}

	public function test_missing_key_for_required_provider(): void {
		$result = $this->tester()->test( 'IPInfoDB', '', array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ip_location_block_missing_provider_key', $result->get_error_code() );
	}

	public function test_credential_injection_does_not_leak_into_settings(): void {
		$this->stubHttp( '{"country_code":"US","region_name":"WA","city":"Seattle"}' );

		$settings = array( 'providers' => array() );
		$this->tester()->test( 'ipstack', 'my-secret-key', $settings );

		$this->assertSame( array( 'providers' => array() ), $settings, 'caller settings must be untouched' );
	}

	public function test_successful_test_returns_ok_envelope(): void {
		$this->stubHttp( '{"country_code":"US","region_name":"WA","city":"Seattle"}' );

		$result = $this->tester()->test( 'ipstack', 'my-secret-key', array( 'providers' => array() ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'US', $result['countryCode'] );
		$this->assertArrayHasKey( 'verifiedAt', $result );
	}

	public function test_non_two_letter_country_is_a_failure_envelope(): void {
		$this->stubHttp( '{"country_code":"-"}' );

		$result = $this->tester()->test( 'ipstack', 'k', array( 'providers' => array() ) );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['ok'] );
		$this->assertArrayHasKey( 'message', $result );
	}

	public function test_native_blocking_quota_fails_even_with_valid_country(): void {
		$this->stubHttp(
			'{"country_code":"US","region":"California","city":"Mountain View"}',
			'{"status":"rate_limited"}'
		);

		$result = $this->tester()->test( 'IP Location Block', 'native-key', array( 'providers' => array() ) );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'US', $result['countryCode'] );
		$this->assertSame( 'rate_limited', $result['quota']['status'] );
	}
}
