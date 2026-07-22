<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Geolocation;

use IPLocationBlock\Geolocation\LocationResult;
use IPLocationBlock\Tests\Unit\TestCase;

final class LocationResultTest extends TestCase {

	public function test_from_array_and_to_legacy_array_round_trip(): void {
		$data = array(
			'countryCode' => 'US',
			'countryName' => 'United States',
			'regionName'  => 'California',
			'cityName'    => 'Mountain View',
			'stateName'   => 'California',
			'latitude'    => '37.4',
			'longitude'   => '-122.1',
			'asn'         => 'AS15169',
		);

		$result = LocationResult::fromArray( $data );

		$this->assertSame( $data, $result->toLegacyArray() );
	}

	public function test_from_array_collapses_empty_strings_to_null(): void {
		$result = LocationResult::fromArray(
			array(
				'countryCode' => 'US',
				'cityName'    => '',
				'stateName'   => '',
			)
		);

		$this->assertSame( 'US', $result->countryCode );
		$this->assertNull( $result->cityName );
		$this->assertNull( $result->stateName );
		$this->assertSame( array( 'countryCode' => 'US' ), $result->toLegacyArray() );
	}

	public function test_error_factory_produces_error_array(): void {
		$result = LocationResult::error( 'boom' );

		$this->assertFalse( $result->isUsable() );
		$this->assertSame( array( 'errorMessage' => 'boom' ), $result->toLegacyArray() );
	}

	public function test_rejected_is_not_usable(): void {
		$result = LocationResult::rejected();

		$this->assertTrue( $result->isRejected() );
		$this->assertFalse( $result->isUsable() );
	}

	public function test_without_precision_strips_region_city_state_only(): void {
		$result = LocationResult::fromArray(
			array(
				'countryCode' => 'US',
				'countryName' => 'United States',
				'regionName'  => 'California',
				'cityName'    => 'Seattle',
				'stateName'   => 'Washington',
				'asn'         => 'AS15169',
			)
		)->withoutPrecision();

		$this->assertSame( 'US', $result->countryCode );
		$this->assertSame( 'United States', $result->countryName );
		$this->assertSame( 'AS15169', $result->asn );
		$this->assertNull( $result->regionName );
		$this->assertNull( $result->cityName );
		$this->assertNull( $result->stateName );
	}

	public function test_with_asn_returns_copy_with_asn(): void {
		$base = LocationResult::fromArray( array( 'countryCode' => 'US' ) );
		$with = $base->withAsn( 'AS64500' );

		$this->assertNull( $base->asn );
		$this->assertSame( 'AS64500', $with->asn );
	}

	/**
	 * @dataProvider usableProvider
	 */
	public function test_is_usable_edge_cases( array $data, bool $expected ): void {
		$this->assertSame( $expected, LocationResult::fromArray( $data )->isUsable() );
	}

	public function usableProvider(): array {
		return array(
			'valid US'        => array( array( 'countryCode' => 'US' ), true ),
			'empty code'      => array( array( 'countryCode' => '' ), false ),
			'missing code'    => array( array( 'countryName' => 'X' ), false ),
			'lowercase code'  => array( array( 'countryCode' => 'us' ), false ),
			'one letter'      => array( array( 'countryCode' => 'U' ), false ),
			'error set'       => array( array( 'countryCode' => 'US', 'errorMessage' => 'x' ), false ),
		);
	}

	/**
	 * @dataProvider normalizeProvider
	 */
	public function test_normalize_country_code( $in, ?string $expected ): void {
		$this->assertSame( $expected, LocationResult::normalizeCountryCode( $in ) );
	}

	public function normalizeProvider(): array {
		return array(
			'plain'      => array( 'US', 'US' ),
			'longer'     => array( 'USA', 'US' ),
			'dash'       => array( '-', null ),
			'undefined'  => array( 'UNDEFINED', 'UN' ),
			'lowercase'  => array( 'us', null ),
			'non-string' => array( 123, null ),
		);
	}
}
