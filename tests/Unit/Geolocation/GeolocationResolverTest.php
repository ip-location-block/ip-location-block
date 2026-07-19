<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Geolocation;

use IPLocationBlock\Geolocation\GeolocationResolver;
use IPLocationBlock\Geolocation\IpCacheRepository;
use IPLocationBlock\Geolocation\LocationResult;
use IPLocationBlock\Providers\Capability;
use IPLocationBlock\Providers\LookupContext;
use IPLocationBlock\Tests\Fakes\FakePrecisionProvider;
use IPLocationBlock\Tests\Fakes\FakeProvider;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The monetization core: GeolocationResolver's precision gate + ASN enrichment
 * + cache-first replay + first-non-empty-country ordering.
 */
final class GeolocationResolverTest extends TestCase {

	private const PUBLIC_IP = '8.8.8.8';

	protected function tearDown(): void {
		IpCacheRepository::flushMemory();
		parent::tearDown();
	}

	private function resolver(): GeolocationResolver {
		return new GeolocationResolver( new IpCacheRepository() );
	}

	private function context( array $settings ): LookupContext {
		return new LookupContext( $settings, array() );
	}

	private function cityStateResult(): LocationResult {
		return LocationResult::fromArray(
			array(
				'countryCode' => 'US',
				'cityName'    => 'Seattle',
				'stateName'   => 'Washington',
			)
		);
	}

	/** ===== THE PRECISION GATE ===== */

	public function test_non_native_provider_has_city_and_state_stripped(): void {
		$provider = new FakeProvider( 'Standard', $this->cityStateResult() );
		$settings = array();

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $provider ), $this->context( $settings ), false );

		$this->assertSame( 'Standard', $out['provider'] );
		$this->assertSame( 'US', $out['code'] );
		$this->assertNull( $out['city'], 'city must be stripped for a non-precision provider' );
		$this->assertNull( $out['state'], 'state must be stripped for a non-precision provider' );
	}

	public function test_precision_source_passes_city_and_state_through(): void {
		$provider = new FakePrecisionProvider( 'IP Location Block', $this->cityStateResult() );
		$settings = array();

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $provider ), $this->context( $settings ), false );

		$this->assertSame( 'IP Location Block', $out['provider'] );
		$this->assertSame( 'US', $out['code'] );
		$this->assertSame( 'Seattle', $out['city'] );
		$this->assertSame( 'Washington', $out['state'] );
	}

	/** ===== CACHE REPLAY ===== */

	public function test_cache_replay_returns_stored_city_and_state(): void {
		$this->seedCache(
			self::PUBLIC_IP,
			array( 'code' => 'US', 'city' => 'Seattle', 'state' => 'Washington', 'asn' => 'AS15169' )
		);

		// A live provider is present but must NOT be consulted on a cache hit.
		$provider = new FakeProvider( 'Standard', LocationResult::error( 'should not run' ) );
		$settings = array( 'cache_hold' => 1 );

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $provider ), $this->context( $settings ), true );

		$this->assertSame( 'Cache', $out['provider'] );
		$this->assertSame( 'US', $out['code'] );
		$this->assertSame( 'Seattle', $out['city'] );
		$this->assertSame( 'Washington', $out['state'] );
		$this->assertSame( 'AS15169', $out['asn'] );
		$this->assertSame( 0, $provider->lookupCalls );
	}

	/** ===== ORDERING ===== */

	public function test_first_usable_country_wins(): void {
		$empty   = new FakeProvider( 'Empty', LocationResult::fromArray( array() ) );
		$errored = new FakeProvider( 'Errored', LocationResult::error( 'nope' ) );
		$hit     = new FakeProvider( 'Hit', LocationResult::fromArray( array( 'countryCode' => 'DE' ) ) );
		$later   = new FakeProvider( 'Later', LocationResult::fromArray( array( 'countryCode' => 'FR' ) ) );
		$settings = array();

		$out = $this->resolver()->resolve(
			self::PUBLIC_IP,
			$settings,
			array( $empty, $errored, $hit, $later ),
			$this->context( $settings ),
			false
		);

		$this->assertSame( 'Hit', $out['provider'] );
		$this->assertSame( 'DE', $out['code'] );
		$this->assertSame( 0, $later->lookupCalls, 'providers after the first hit must not run' );
	}

	/** ===== ASN ENRICHMENT ===== */

	public function test_asn_used_from_first_lookup_when_capable(): void {
		$provider = new FakeProvider(
			'Native',
			LocationResult::fromArray( array( 'countryCode' => 'US', 'asn' => 'AS15169' ) ),
			Capability::IPV4 | Capability::IPV6 | Capability::ASN
		);
		$settings = array( 'use_asn' => 1 );

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $provider ), $this->context( $settings ), false );

		$this->assertSame( 'AS15169', $out['asn'] );
		$this->assertSame( 1, $provider->lookupCalls, 'no second pass when the first lookup already has an ASN' );
	}

	public function test_asn_second_pass_when_first_lookup_has_no_asn(): void {
		$provider = new FakeProvider(
			'GeoLite2',
			LocationResult::fromArray( array( 'countryCode' => 'US' ) ),
			Capability::IPV4 | Capability::IPV6 | Capability::ASN | Capability::ASN_DATABASE,
			LocationResult::fromArray( array( 'asn' => 'AS64500' ) )
		);
		$settings = array( 'use_asn' => 1 );

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $provider ), $this->context( $settings ), false );

		$this->assertSame( 'AS64500', $out['asn'] );
		$this->assertSame( 2, $provider->lookupCalls, 'a second ASN-pass lookup is expected' );
	}

	public function test_no_asn_when_use_asn_disabled(): void {
		$provider = new FakeProvider(
			'Native',
			LocationResult::fromArray( array( 'countryCode' => 'US', 'asn' => 'AS15169' ) ),
			Capability::IPV4 | Capability::IPV6 | Capability::ASN
		);
		$settings = array(); // use_asn off

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $provider ), $this->context( $settings ), false );

		$this->assertNull( $out['asn'] );
	}

	public function test_no_second_pass_without_plain_asn_capability(): void {
		// asn_database only (no 'asn') => first branch can't fill it, no 2nd pass.
		$provider = new FakeProvider(
			'DbOnly',
			LocationResult::fromArray( array( 'countryCode' => 'US' ) ),
			Capability::IPV4 | Capability::IPV6 | Capability::ASN_DATABASE,
			LocationResult::fromArray( array( 'asn' => 'AS999' ) )
		);
		$settings = array( 'use_asn' => 1 );

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $provider ), $this->context( $settings ), false );

		$this->assertNull( $out['asn'] );
		$this->assertSame( 1, $provider->lookupCalls );
	}

	/** ===== SHORT-CIRCUITS ===== */

	public function test_private_ip_short_circuits(): void {
		$provider = new FakeProvider( 'Standard', LocationResult::fromArray( array( 'countryCode' => 'US' ) ) );
		$settings = array();

		$out = $this->resolver()->resolve( '10.0.0.1', $settings, array( $provider ), $this->context( $settings ), false );

		$this->assertSame( 'Private', $out['provider'] );
		$this->assertSame( 'XX', $out['code'] );
		$this->assertSame( 0, $provider->lookupCalls );
	}

	public function test_empty_provider_list_short_circuits(): void {
		$settings = array();

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array(), $this->context( $settings ), false );

		$this->assertSame( 'Private', $out['provider'] );
		$this->assertSame( 'XX', $out['code'] );
	}

	/**
	 * A cache hit must replay even when every real provider is disabled. The
	 * cache read must therefore run BEFORE the empty-provider short-circuit.
	 */
	public function test_cache_replays_even_with_empty_provider_list(): void {
		$this->seedCache( self::PUBLIC_IP, array( 'code' => 'DE', 'city' => 'Berlin', 'state' => '', 'asn' => '' ) );

		$settings = array( 'cache_hold' => 1 );

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array(), $this->context( $settings ), true );

		$this->assertSame( 'Cache', $out['provider'] );
		$this->assertSame( 'DE', $out['code'] );
		$this->assertSame( 'Berlin', $out['city'] );
		$this->assertNull( $out['state'] );
		$this->assertNull( $out['asn'] );
	}

	public function test_no_usable_result_returns_unknown_envelope(): void {
		$provider = new FakeProvider( 'Empty', LocationResult::error( 'nope' ) );
		$settings = array();

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $provider ), $this->context( $settings ), false );

		$this->assertSame( array( 'errorMessage' => 'unknown' ), $out );
	}

	/** ===== SELF-HEAL: stale pre-precision cache rows ===== */

	/** Native-only provider config that isNativeOnly() reports as native-only. */
	private const NATIVE_ONLY = array( 'IP Location Block' => 'key', 'IP2Location' => '' );

	public function test_stale_precision_row_refreshed_live_when_native_only_and_rule_present(): void {
		$this->seedCache( self::PUBLIC_IP, array( 'code' => 'US', 'city' => '', 'state' => '', 'asn' => '' ) );

		$native   = new FakePrecisionProvider( 'IP Location Block', $this->cityStateResult() );
		$settings = array(
			'cache_hold' => 1,
			'white_list' => 'US:State:Washington',
			'providers'  => self::NATIVE_ONLY,
		);

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $native ), $this->context( $settings ), true );

		$this->assertSame( 'IP Location Block', $out['provider'], 'stale row must be refreshed by a live lookup' );
		$this->assertSame( 'Seattle', $out['city'] );
		$this->assertSame( 'Washington', $out['state'] );
		$this->assertSame( 1, $native->lookupCalls );
	}

	public function test_stale_precision_row_replayed_without_a_precision_rule(): void {
		$this->seedCache( self::PUBLIC_IP, array( 'code' => 'US', 'city' => '', 'state' => '', 'asn' => '' ) );

		$native   = new FakePrecisionProvider( 'IP Location Block', $this->cityStateResult() );
		$settings = array(
			'cache_hold' => 1,
			'white_list' => 'US', // plain country only — no precision entry
			'providers'  => self::NATIVE_ONLY,
		);

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $native ), $this->context( $settings ), true );

		$this->assertSame( 'Cache', $out['provider'] );
		$this->assertNull( $out['city'] );
		$this->assertSame( 0, $native->lookupCalls, 'no live lookup without a precision rule' );
	}

	public function test_stale_precision_row_refreshed_when_native_enforced_with_mixed_providers(): void {
		$this->seedCache( self::PUBLIC_IP, array( 'code' => 'US', 'city' => '', 'state' => '', 'asn' => '' ) );

		$native = new FakePrecisionProvider( 'IP Location Block', $this->cityStateResult() );
		// Native selected with a REAL key while IP2Location stays implicitly enabled
		// (mixed) + a precision rule => native-enforced. The self-heal broadens from
		// native-only to also cover the enforced case, so the stale row refreshes.
		$settings = array(
			'cache_hold' => 1,
			'white_list' => 'US:State:Washington',
			'providers'  => array( 'IP Location Block' => 'key' ),
		);

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $native ), $this->context( $settings ), true );

		$this->assertSame( 'IP Location Block', $out['provider'], 'enforced native refreshes the stale row' );
		$this->assertSame( 'Seattle', $out['city'] );
		$this->assertSame( 'Washington', $out['state'] );
		$this->assertSame( 1, $native->lookupCalls );
	}

	public function test_stale_precision_row_replayed_when_native_selected_without_a_real_key(): void {
		$this->seedCache( self::PUBLIC_IP, array( 'code' => 'US', 'city' => '', 'state' => '', 'asn' => '' ) );

		$native = new FakePrecisionProvider( 'IP Location Block', $this->cityStateResult() );
		// '@' selects native but is NOT a real key => neither native-only nor
		// enforced, so the stale row is replayed (self-heal stays bounded).
		$settings = array(
			'cache_hold' => 1,
			'white_list' => 'US:State:Washington',
			'providers'  => array( 'IP Location Block' => '@' ),
		);

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $native ), $this->context( $settings ), true );

		$this->assertSame( 'Cache', $out['provider'] );
		$this->assertSame( 0, $native->lookupCalls, 'no self-heal without native-only or a real enforced key' );
	}

	public function test_partial_precision_row_is_not_treated_as_stale(): void {
		// A row that already carries a state (city empty) is NOT stale.
		$this->seedCache( self::PUBLIC_IP, array( 'code' => 'US', 'city' => '', 'state' => 'Washington', 'asn' => '' ) );

		$native   = new FakePrecisionProvider( 'IP Location Block', $this->cityStateResult() );
		$settings = array(
			'cache_hold' => 1,
			'white_list' => 'US:State:Washington',
			'providers'  => self::NATIVE_ONLY,
		);

		$out = $this->resolver()->resolve( self::PUBLIC_IP, $settings, array( $native ), $this->context( $settings ), true );

		$this->assertSame( 'Cache', $out['provider'] );
		$this->assertSame( 'Washington', $out['state'] );
		$this->assertSame( 0, $native->lookupCalls );
	}

	/**
	 * Seed the IpCacheRepository in-memory cache without touching the DB layer.
	 */
	private function seedCache( string $ip, array $row ): void {
		$prop = new \ReflectionProperty( IpCacheRepository::class, 'memcache' );
		$prop->setAccessible( true );
		$current         = $prop->getValue();
		$current[ $ip ]  = $row;
		$prop->setValue( null, $current );
	}
}
