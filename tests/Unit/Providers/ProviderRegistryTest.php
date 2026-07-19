<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Providers;

use IPLocationBlock\Providers\NativeProvider;
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
			ProviderRegistry::instance()->activeProviderIds( $settings, false, false )
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
			'restrict_api limits to locals' => array(
				array( 'restrict_api' => 1, 'providers' => array( 'IPInfoDB' => 'key' ) ),
				array( 'IP2Location' ),
			),
		);
	}

	public function test_ignore_restrict_api_includes_remotes(): void {
		$settings = array( 'restrict_api' => 1, 'providers' => array( 'IPInfoDB' => 'key' ) );

		$this->assertSame(
			array( 'IP2Location', 'IPInfoDB' ),
			ProviderRegistry::instance()->activeProviderIds( $settings, false, true )
		);
	}

	public function test_shuffle_preserves_the_set(): void {
		$settings = array(
			'providers' => array( 'IPInfoDB' => 'a', 'ipapi' => 'b', 'ipstack' => 'c' ),
		);
		$expected = array( 'IP2Location', 'IPInfoDB', 'ipapi', 'ipstack' );

		$shuffled = ProviderRegistry::instance()->activeProviderIds( $settings, true, false );
		sort( $shuffled );
		sort( $expected );

		$this->assertSame( $expected, $shuffled );
	}

	public function test_shuffle_keeps_locals_before_remotes(): void {
		$settings = array(
			'providers' => array( 'IPInfoDB' => 'a', 'ipapi' => 'b' ),
		);

		$ids = ProviderRegistry::instance()->activeProviderIds( $settings, true, false );

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

	public function test_providers_with_capability(): void {
		$asn = $this->ids( ProviderRegistry::instance()->providersWithCapability( \IPLocationBlock\Providers\Capability::ASN ) );
		$this->assertContains( 'IP Location Block', $asn );
		$this->assertContains( 'GeoLite2', $asn );
		$this->assertNotContains( 'IP2Location', $asn );
	}
}
