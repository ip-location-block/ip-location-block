<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Compat;

use IPLocationBlock\Compat\LegacyLocalProviderAdapter;
use IPLocationBlock\Compat\LegacyProviderAdapter;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * IP_Location_Block_API facade dispatch: get_instance() adapter selection,
 * get_class_name() aliasing, get_api_key() semantics and the method_exists()
 * contract (local adapters expose download/add_settings_field, remotes do not).
 */
final class ApiFacadeTest extends TestCase {

	public function test_get_instance_local_returns_local_adapter(): void {
		$geo = \IP_Location_Block_API::get_instance( 'IP2Location', array() );

		$this->assertInstanceOf( LegacyLocalProviderAdapter::class, $geo );
		$this->assertTrue( method_exists( $geo, 'download' ) );
		$this->assertTrue( method_exists( $geo, 'add_settings_field' ) );
	}

	public function test_get_instance_remote_returns_remote_adapter_without_download(): void {
		$geo = \IP_Location_Block_API::get_instance( 'ipstack', array() );

		$this->assertInstanceOf( LegacyProviderAdapter::class, $geo );
		$this->assertNotInstanceOf( LegacyLocalProviderAdapter::class, $geo );
		$this->assertFalse( method_exists( $geo, 'download' ), 'remote adapters must not expose download()' );
		$this->assertFalse( method_exists( $geo, 'add_settings_field' ) );
	}

	public function test_get_instance_maxmind_alias(): void {
		$geo = \IP_Location_Block_API::get_instance( 'Maxmind', array() );

		$this->assertInstanceOf( LegacyLocalProviderAdapter::class, $geo );
	}

	public function test_get_instance_cache_returns_cache_object(): void {
		$geo = \IP_Location_Block_API::get_instance( 'Cache', array() );

		$this->assertInstanceOf( \IP_Location_Block_API_Cache::class, $geo );
		$this->assertTrue( method_exists( $geo, 'get_location' ) );
	}

	/**
	 * @dataProvider unknownProvider
	 */
	public function test_get_instance_unknown_returns_null( string $name ): void {
		$this->assertNull( \IP_Location_Block_API::get_instance( $name, array() ) );
	}

	public function unknownProvider(): array {
		return array(
			'GeoIPLookup (dead)' => array( 'GeoIPLookup' ),
			'Ipdataco (dead)'    => array( 'Ipdataco' ),
			'unknown'            => array( 'TotallyBogus' ),
		);
	}

	public function test_get_class_name_maps_maxmind_to_geolite2(): void {
		$this->assertSame( 'GeoLite2', \IP_Location_Block_API::get_class_name( 'Maxmind' ) );
		$this->assertSame( 'GeoLite2', \IP_Location_Block_API::get_class_name( 'GeoLite2' ) );
		$this->assertSame( 'IP2Location', \IP_Location_Block_API::get_class_name( 'IP2Location' ) );
		$this->assertSame( 'Cache', \IP_Location_Block_API::get_class_name( 'Cache' ) );
		$this->assertNull( \IP_Location_Block_API::get_class_name( 'GeoIPLookup' ) );
	}

	/**
	 * @dataProvider apiKeyProvider
	 */
	public function test_get_api_key( string $provider, array $options, $expected ): void {
		$this->assertSame( $expected, \IP_Location_Block_API::get_api_key( $provider, $options ) );
	}

	public function apiKeyProvider(): array {
		return array(
			'present'          => array( 'ipstack', array( 'providers' => array( 'ipstack' => 'abc' ) ), 'abc' ),
			'case insensitive' => array( 'IPStack', array( 'providers' => array( 'ipstack' => 'abc' ) ), 'abc' ),
			'empty value'      => array( 'ipstack', array( 'providers' => array( 'ipstack' => '' ) ), null ),
			'missing provider' => array( 'ipstack', array( 'providers' => array() ), null ),
			'no providers key' => array( 'ipstack', array(), null ),
		);
	}

	public function test_local_adapter_get_location_round_trips_through_provider(): void {
		// IP2Location lookup of an invalid IP yields a legacy errorMessage array
		// (no DB/vendor needed): proves the adapter delegates to the new provider.
		$geo    = \IP_Location_Block_API::get_instance( 'IP2Location', array() );
		$result = $geo->get_location( 'not-an-ip', array() );

		$this->assertSame( array( 'errorMessage' => 'illegal format' ), $result );
	}

	public function test_local_adapter_supports_reflects_capabilities(): void {
		$geo = \IP_Location_Block_API::get_instance( 'GeoLite2', array() );

		$this->assertTrue( $geo->supports( 'asn' ) );
		$this->assertTrue( $geo->supports( 'asn_database' ) );
		$this->assertFalse( $geo->supports( 'city' ) );
	}
}
