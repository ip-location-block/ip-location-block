<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Providers;

use Brain\Monkey\Functions;
use IPLocationBlock\Providers\LookupContext;
use IPLocationBlock\Providers\NativeProvider;
use IPLocationBlock\Providers\Remote\IpApiProvider;
use IPLocationBlock\Providers\Remote\IpInfoDbProvider;
use IPLocationBlock\Providers\Remote\IpInfoIoProvider;
use IPLocationBlock\Providers\Remote\IpStackProvider;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * Remote providers fed canned fixtures via a stubbed wp_remote_get() — asserts
 * each transform map, the provider-specific reshaping (ipinfo.io loc/ASN, ipapi
 * error envelope), country-code normalization and IP-family rejection.
 */
final class ProviderTransformTest extends TestCase {

	private function fixture( string $name ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/providers/' . $name );
	}

	private function stubResponse( string $contentType, string $body ): void {
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_remote_get' )->justReturn( array( '__ct' => $contentType, '__body' => $body ) );
		Functions\when( 'wp_remote_retrieve_header' )->alias(
			static fn( $res, $h ) => is_array( $res ) ? ( $res['__ct'] ?? '' ) : ''
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( $res ) => is_array( $res ) ? ( $res['__body'] ?? '' ) : ''
		);
	}

	private function context( string $id ): LookupContext {
		return new LookupContext( array( 'providers' => array( $id => 'test-key' ) ), array(), true );
	}

	public function test_native_transform_maps_region_to_state(): void {
		$this->stubResponse( 'application/json; charset=utf-8', $this->fixture( 'native.json' ) );

		$result = ( new NativeProvider() )->lookup( '8.8.8.8', $this->context( 'IP Location Block' ) );

		$this->assertSame( 'US', $result->countryCode );
		$this->assertSame( 'Mountain View', $result->cityName );
		$this->assertSame( 'California', $result->stateName );
		$this->assertSame( 'California', $result->regionName );
		$this->assertSame( 'AS15169', $result->asn );
		$this->assertTrue( $result->isUsable() );
	}

	public function test_ipinfoio_splits_loc_and_parses_asn(): void {
		$this->stubResponse( 'application/json', $this->fixture( 'ipinfoio.json' ) );

		$result = ( new IpInfoIoProvider() )->lookup( '8.8.8.8', $this->context( 'ipinfo.io' ) );

		$this->assertSame( 'US', $result->countryCode );
		$this->assertSame( 'Mountain View', $result->cityName );
		$this->assertSame( '37.4056', $result->latitude );
		$this->assertSame( '-122.0775', $result->longitude );
		$this->assertSame( 'AS15169', $result->asn );
	}

	public function test_ipapi_success(): void {
		$this->stubResponse( 'application/json', $this->fixture( 'ipapi-success.json' ) );

		$result = ( new IpApiProvider() )->lookup( '8.8.8.8', $this->context( 'ipapi' ) );

		$this->assertSame( 'US', $result->countryCode );
		$this->assertSame( 'United States', $result->countryName );
		$this->assertSame( 'Seattle', $result->cityName );
		$this->assertTrue( $result->isUsable() );
	}

	public function test_ipapi_error_envelope(): void {
		$this->stubResponse( 'application/json', $this->fixture( 'ipapi-error.json' ) );

		$result = ( new IpApiProvider() )->lookup( '8.8.8.8', $this->context( 'ipapi' ) );

		$this->assertFalse( $result->isUsable() );
		$this->assertSame( 'You have not supplied a valid API Access Key.', $result->errorMessage );
	}

	public function test_ipstack_transform(): void {
		$this->stubResponse( 'application/json', $this->fixture( 'ipstack.json' ) );

		$result = ( new IpStackProvider() )->lookup( '8.8.8.8', $this->context( 'ipstack' ) );

		$this->assertSame( 'US', $result->countryCode );
		$this->assertSame( 'Washington', $result->regionName );
		$this->assertSame( 'Seattle', $result->cityName );
	}

	public function test_ipinfodb_xml_transform(): void {
		$this->stubResponse( 'text/xml; charset=utf-8', $this->fixture( 'ipinfodb.xml' ) );

		$result = ( new IpInfoDbProvider() )->lookup( '8.8.8.8', $this->context( 'IPInfoDB' ) );

		$this->assertSame( 'US', $result->countryCode );
		$this->assertSame( 'WASHINGTON', $result->regionName );
		$this->assertSame( 'SEATTLE', $result->cityName );
	}

	public function test_country_code_dash_is_normalized_to_null(): void {
		$this->stubResponse( 'application/json', '{"country_code":"-","country_name":"n/a"}' );

		$result = ( new NativeProvider() )->lookup( '8.8.8.8', $this->context( 'IP Location Block' ) );

		$this->assertNull( $result->countryCode );
		$this->assertFalse( $result->isUsable() );
	}

	public function test_unsupported_content_type_is_an_error(): void {
		$this->stubResponse( 'text/csv', 'a,b,c' );

		$result = ( new NativeProvider() )->lookup( '8.8.8.8', $this->context( 'IP Location Block' ) );

		$this->assertFalse( $result->isUsable() );
		$this->assertStringContainsString( 'unsupported content type', (string) $result->errorMessage );
	}

	public function test_ip_family_rejection_returns_rejected(): void {
		// Cache is consulted first; return a miss so we reach the family check.
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		// wp_remote_get must NOT be called for a rejected family.
		Functions\when( 'wp_remote_get' )->alias(
			static function () {
				throw new \RuntimeException( 'wp_remote_get should not run on family rejection' );
			}
		);

		$result = ( new IpStackProvider() )->lookup( 'not-an-ip', $this->context( 'ipstack' ) );

		$this->assertTrue( $result->isRejected() );
		$this->assertFalse( $result->isUsable() );
	}

	public function test_wp_error_response_becomes_error_result(): void {
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error( 'http', 'Connection timed out' ) );

		$result = ( new IpStackProvider() )->lookup( '8.8.8.8', $this->context( 'ipstack' ) );

		$this->assertFalse( $result->isUsable() );
		$this->assertSame( 'Connection timed out', $result->errorMessage );
	}
}
