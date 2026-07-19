<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Compat;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use IPLocationBlock\Compat;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * Byte-parity of the IP_Location_Block_Provider facade against recorded
 * fixtures of the legacy payload shape (tests/fixtures/snapshots/), plus the
 * register_addon() no-op + deprecation behaviour.
 */
final class ProviderFacadeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Compat::reset();
	}

	private function snapshot( string $name ) {
		return json_decode(
			(string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/snapshots/' . $name . '.json' ),
			true
		);
	}

	public function test_all_matches_legacy_snapshot(): void {
		$this->assertSame( $this->snapshot( 'all' ), \IP_Location_Block_Provider::all() );
	}

	public function test_get_providers_key_matches_snapshot(): void {
		$this->assertSame(
			$this->snapshot( 'get_providers_key' ),
			\IP_Location_Block_Provider::get_providers( 'key', false, false, true )
		);
	}

	public function test_get_providers_type_matches_snapshot(): void {
		$this->assertSame(
			$this->snapshot( 'get_providers_type' ),
			\IP_Location_Block_Provider::get_providers( 'type', false, true, true )
		);
	}

	public function test_get_valid_providers_default_matches_snapshot(): void {
		$settings = array( 'cache_hold' => 1, 'providers' => array() );

		$this->assertSame(
			$this->snapshot( 'valid_default' ),
			\IP_Location_Block_Provider::get_valid_providers( $settings, false, true, false )
		);
	}

	public function test_get_valid_providers_restrict_matches_snapshot(): void {
		$settings = array( 'cache_hold' => 1, 'restrict_api' => 1, 'providers' => array() );

		$this->assertSame(
			$this->snapshot( 'valid_restrict' ),
			\IP_Location_Block_Provider::get_valid_providers( $settings, false, true, false )
		);
	}

	public function test_get_addons_matches_snapshot(): void {
		$this->assertSame(
			$this->snapshot( 'get_addons' ),
			\IP_Location_Block_Provider::get_addons( array() )
		);
	}

	public function test_supports_matches_legacy_semantics(): void {
		$this->assertTrue( \IP_Location_Block_Provider::supports( 'IP Location Block', 'city' ) );
		$this->assertTrue( \IP_Location_Block_Provider::supports( 'GeoLite2', array( 'asn', 'asn_database' ) ) );
		$this->assertFalse( \IP_Location_Block_Provider::supports( 'IP2Location', 'asn' ) );
		$this->assertFalse( \IP_Location_Block_Provider::supports( 'Cache', 'ipv4' ) );
	}

	public function test_auth_constants(): void {
		$this->assertSame( 1, \IP_Location_Block_Provider::API_AUTH_OPTIONAL );
		$this->assertSame( 2, \IP_Location_Block_Provider::API_AUTH_REQUIRED );
		$this->assertSame( 3, \IP_Location_Block_Provider::API_AUTH_NOT_REQUIRED );
	}

	/** ===== register_addon() no-op + deprecation ===== */

	public function test_register_addon_is_a_noop_and_fires_deprecation_once(): void {
		Actions\expectDone( 'ip-location-block-deprecated' )->once();

		$before = \IP_Location_Block_Provider::all();
		\IP_Location_Block_Provider::register_addon( array( 'MyGeo' => array( 'key' => null ), 'Other' => array() ) );
		$after = \IP_Location_Block_Provider::all();

		$this->assertSame( $before, $after, 'registry must be unchanged' );
		$this->assertArrayNotHasKey( 'MyGeo', $after );
		$this->assertArrayNotHasKey( 'Other', $after );
	}

	public function test_register_addon_queues_notice_when_option_api_available(): void {
		$stored = null;
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value ) use ( &$stored ) {
				$stored = $value;
				return true;
			}
		);
		Functions\when( 'has_action' )->justReturn( false );
		Functions\when( 'add_action' )->justReturn( true );

		\IP_Location_Block_Provider::register_addon( array( 'MyGeo' => array() ) );

		$this->assertContains( 'MyGeo', (array) $stored );
	}
}
