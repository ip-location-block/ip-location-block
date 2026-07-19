<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Providers;

use Brain\Monkey\Functions;
use IPLocationBlock\Providers\NativeProvider;
use IPLocationBlock\Providers\NativeQuotaService;
use IPLocationBlock\Providers\PrecisionLocationSource;
use IPLocationBlock\Providers\ProviderInterface;
use IPLocationBlock\Providers\ProviderRegistry;
use IPLocationBlock\Tests\Unit\TestCase;

final class ProviderRegistryTest extends TestCase {

	/** @return string[] */
	private function ids( array $providers ): array {
		return array_map( static fn( ProviderInterface $p ): string => $p->id(), $providers );
	}

	/** ===== SEALED SHAPE ===== */

	public function test_registry_is_final(): void {
		$this->assertTrue( ( new \ReflectionClass( ProviderRegistry::class ) )->isFinal() );
	}

	public function test_constructor_and_clone_are_private(): void {
		$class = new \ReflectionClass( ProviderRegistry::class );
		$this->assertTrue( $class->getConstructor()->isPrivate() );
		$this->assertTrue( $class->getMethod( '__clone' )->isPrivate() );
	}

	public function test_registry_exposes_no_mutators(): void {
		$class = new \ReflectionClass( ProviderRegistry::class );
		foreach ( $class->getMethods( \ReflectionMethod::IS_PUBLIC ) as $method ) {
			$this->assertDoesNotMatchRegularExpression(
				'/^(register|add|set|remove|unset|push|append)/i',
				$method->getName(),
				'Sealed registry must expose no mutators; found ' . $method->getName()
			);
		}
	}

	public function test_provider_list_is_a_private_const(): void {
		$class = new \ReflectionClass( ProviderRegistry::class );
		$this->assertTrue( $class->getReflectionConstant( 'PROVIDERS' )->isPrivate() );
	}

	public function test_instance_is_a_singleton(): void {
		$this->assertSame( ProviderRegistry::instance(), ProviderRegistry::instance() );
	}

	/** ===== THE MARKER INVARIANT ===== */

	public function test_native_is_the_only_precision_source(): void {
		$precision = array();
		foreach ( ProviderRegistry::instance()->catalog() as $id => $provider ) {
			if ( $provider instanceof PrecisionLocationSource ) {
				$precision[] = $id;
			}
		}

		$this->assertSame( array( NativeProvider::ID ), $precision );
	}

	/** ===== ORDERING (REST byte-order contract) ===== */

	public function test_catalog_order(): void {
		$this->assertSame(
			array( 'IP Location Block', 'IPInfoDB', 'ipinfo.io', 'ipapi', 'ipstack', 'IP2Location', 'GeoLite2' ),
			array_keys( ProviderRegistry::instance()->catalog() )
		);
	}

	public function test_selection_list_is_locals_first(): void {
		$this->assertSame(
			array( 'IP2Location', 'GeoLite2', 'IP Location Block', 'IPInfoDB', 'ipinfo.io', 'ipapi', 'ipstack' ),
			$this->ids( ProviderRegistry::instance()->selectionList() )
		);
	}

	public function test_local_and_remote_partitions(): void {
		$registry = ProviderRegistry::instance();
		$this->assertSame( array( 'IP2Location', 'GeoLite2' ), $this->ids( $registry->localProviders() ) );
		$this->assertSame(
			array( 'IP Location Block', 'IPInfoDB', 'ipinfo.io', 'ipapi', 'ipstack' ),
			$this->ids( $registry->remoteProviders() )
		);
	}

	/** ===== get() semantics ===== */

	public function test_get_is_exact_and_returns_null_for_unknown(): void {
		$registry = ProviderRegistry::instance();
		$this->assertInstanceOf( NativeProvider::class, $registry->get( 'IP Location Block' ) );
		$this->assertNull( $registry->get( 'Maxmind' ) ); // alias handled by the facade, not get()
		$this->assertNull( $registry->get( 'GeoIPLookup' ) );
		$this->assertFalse( $registry->has( 'Nope' ) );
	}

	/** ===== SELECTION MATRIX ===== */

	/**
	 * @dataProvider selectionProvider
	 */
	public function test_active_provider_ids( array $settings, array $expected ): void {
		$this->assertSame(
			$expected,
			ProviderRegistry::instance()->activeProviderIds( $settings, false )
		);
	}

	public function selectionProvider(): array {
		return array(
			'default (implicit local only)' => array(
				array(),
				array( 'IP2Location' ),
			),
			'explicit remote key adds it (locals first)' => array(
				array( 'providers' => array( 'IPInfoDB' => 'somekey' ) ),
				array( 'IP2Location', 'IPInfoDB' ),
			),
			'@ sentinel counts as selected' => array(
				array( 'providers' => array( 'ipinfo.io' => '@' ) ),
				array( 'IP2Location', 'ipinfo.io' ),
			),
			'explicit empty disables implicit local' => array(
				array( 'providers' => array( 'IP2Location' => '' ) ),
				array(),
			),
			// restrict_api is dropped: a stored value no longer filters providers.
			'restrict_api in stored settings is ignored' => array(
				array( 'restrict_api' => 1, 'providers' => array( 'IPInfoDB' => 'key' ) ),
				array( 'IP2Location', 'IPInfoDB' ),
			),
		);
	}

	public function test_shuffle_preserves_the_set(): void {
		$settings = array(
			'providers' => array( 'IPInfoDB' => 'a', 'ipapi' => 'b', 'ipstack' => 'c' ),
		);
		$expected = array( 'IP2Location', 'IPInfoDB', 'ipapi', 'ipstack' );

		$shuffled = ProviderRegistry::instance()->activeProviderIds( $settings, true );
		sort( $shuffled );
		sort( $expected );

		$this->assertSame( $expected, $shuffled );
	}

	public function test_shuffle_keeps_locals_before_remotes(): void {
		$settings = array(
			'providers' => array( 'IPInfoDB' => 'a', 'ipapi' => 'b' ),
		);

		$ids = ProviderRegistry::instance()->activeProviderIds( $settings, true );

		$this->assertSame( 'IP2Location', $ids[0], 'locals always precede shuffled remotes' );
	}

	/** ===== isNativeOnly ===== */

	/**
	 * @dataProvider nativeOnlyProvider
	 */
	public function test_is_native_only( array $settings, bool $expected ): void {
		$this->assertSame( $expected, ProviderRegistry::instance()->isNativeOnly( $settings ) );
	}

	public function nativeOnlyProvider(): array {
		return array(
			'native key + local disabled' => array(
				array( 'providers' => array( 'IP Location Block' => 'key', 'IP2Location' => '' ) ),
				true,
			),
			'native key but local still implicit' => array(
				array( 'providers' => array( 'IP Location Block' => 'key' ) ),
				false,
			),
			'default (local only)' => array(
				array(),
				false,
			),
			'no providers at all' => array(
				array( 'providers' => array( 'IP2Location' => '' ) ),
				false,
			),
		);
	}

	public function test_local_provider_ids_mirror_get_addons(): void {
		$registry = ProviderRegistry::instance();
		// unset => included
		$this->assertSame( array( 'IP2Location', 'GeoLite2' ), $registry->localProviderIds( array() ) );
		// explicit empty => excluded (unless forced)
		$this->assertSame( array( 'GeoLite2' ), $registry->localProviderIds( array( 'IP2Location' => '' ) ) );
		$this->assertSame( array( 'IP2Location', 'GeoLite2' ), $registry->localProviderIds( array( 'IP2Location' => '' ), true ) );
	}

	/** ===== isNativeEnforced() (pure: precision rule + real key) ===== */

	/**
	 * @dataProvider nativeEnforcedProvider
	 */
	public function test_is_native_enforced( array $settings, bool $expected ): void {
		$this->assertSame( $expected, ProviderRegistry::instance()->isNativeEnforced( $settings ) );
	}

	public function nativeEnforcedProvider(): array {
		return array(
			'real key + precision rule' => array(
				array( 'providers' => array( 'IP Location Block' => 'realkey' ), 'white_list' => 'US:State:Washington' ),
				true,
			),
			'real key + precision rule in public list' => array(
				array(
					'providers' => array( 'IP Location Block' => 'realkey' ),
					'public'    => array( 'white_list' => 'US:City:Seattle' ),
				),
				true,
			),
			'real key but only plain-country rule' => array(
				array( 'providers' => array( 'IP Location Block' => 'realkey' ), 'white_list' => 'US,MK' ),
				false,
			),
			'real key but no rules at all' => array(
				array( 'providers' => array( 'IP Location Block' => 'realkey' ) ),
				false,
			),
			'@ sentinel key is not a real key' => array(
				array( 'providers' => array( 'IP Location Block' => '@' ), 'white_list' => 'US:State:Washington' ),
				false,
			),
			'empty key is not selected' => array(
				array( 'providers' => array( 'IP Location Block' => '' ), 'white_list' => 'US:State:Washington' ),
				false,
			),
			'native not selected at all' => array(
				array( 'providers' => array( 'IPInfoDB' => 'k' ), 'white_list' => 'US:State:Washington' ),
				false,
			),
		);
	}

	public function test_native_enforced_tracks_the_shared_precision_fingerprint(): void {
		// isNativeEnforced must key off the SAME precision fingerprint definition
		// that PrecisionCacheGuard uses (one definition, reused).
		$registry = ProviderRegistry::instance();

		$with_rule = array( 'providers' => array( 'IP Location Block' => 'k' ), 'white_list' => 'US:State:Washington' );
		$no_rule   = array( 'providers' => array( 'IP Location Block' => 'k' ), 'white_list' => 'US' );

		$this->assertNotSame( array(), \IPLocationBlock\Settings\PrecisionCacheGuard::fingerprint( $with_rule ) );
		$this->assertTrue( $registry->isNativeEnforced( $with_rule ) );

		$this->assertSame( array(), \IPLocationBlock\Settings\PrecisionCacheGuard::fingerprint( $no_rule ) );
		$this->assertFalse( $registry->isNativeEnforced( $no_rule ) );
	}

	/** ===== enforcement ordering (native promoted to index 0) ===== */

	/** Mixed selection: native (real key) + an implicit local, with a precision rule. */
	private const MIXED_ENFORCED = array(
		'providers'  => array( 'IP Location Block' => 'realkey' ), // IP2Location stays implicit
		'white_list' => 'US:State:Washington',
	);

	public function test_native_promoted_to_front_when_enforced(): void {
		Functions\when( 'wp_salt' )->justReturn( 'salt' );
		Functions\when( 'get_transient' )->justReturn( false ); // no cached quota => not blocking
		NativeQuotaService::flushMemo();

		$this->assertSame(
			array( 'IP Location Block', 'IP2Location' ),
			ProviderRegistry::instance()->activeProviderIds( self::MIXED_ENFORCED, false ),
			'native must be prioritized ahead of the local fallback'
		);
	}

	public function test_native_not_promoted_when_cached_quota_is_blocking(): void {
		Functions\when( 'wp_salt' )->justReturn( 'salt' );
		Functions\when( 'get_transient' )->justReturn( array( 'status' => 'rate_limited' ) );
		NativeQuotaService::flushMemo();

		$this->assertSame(
			array( 'IP2Location', 'IP Location Block' ),
			ProviderRegistry::instance()->activeProviderIds( self::MIXED_ENFORCED, false ),
			'an exhausted/blocked key keeps the normal order (no failed live API call)'
		);
	}

	public function test_native_not_promoted_without_a_precision_rule(): void {
		Functions\when( 'wp_salt' )->justReturn( 'salt' );
		Functions\when( 'get_transient' )->justReturn( false );
		NativeQuotaService::flushMemo();

		$settings = array(
			'providers'  => array( 'IP Location Block' => 'realkey' ),
			'white_list' => 'US,MK', // plain country only => not enforced
		);

		$this->assertSame(
			array( 'IP2Location', 'IP Location Block' ),
			ProviderRegistry::instance()->activeProviderIds( $settings, false )
		);
	}

	public function test_providers_with_capability(): void {
		$asn = $this->ids( ProviderRegistry::instance()->providersWithCapability( \IPLocationBlock\Providers\Capability::ASN ) );
		$this->assertContains( 'IP Location Block', $asn );
		$this->assertContains( 'GeoLite2', $asn );
		$this->assertNotContains( 'IP2Location', $asn );
	}
}
