<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Diagnostics;

use Brain\Monkey\Functions;
use IPLocationBlock\Diagnostics\Diagnostics;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The `ignored-addon-providers` diagnostics check and its acknowledgement,
 * which replaces the classic one-shot admin notice on the React screen.
 */
final class DiagnosticsTest extends TestCase {

	/**
	 * Invoke the private ignored-addon check builder against a by-ref $checks
	 * array and return the single check it appends.
	 *
	 * @return array<string,mixed>
	 */
	private function run_ignored_addon_check(): array {
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_strip_all_tags' )->returnArg();

		$checks = array();
		$ref    = new \ReflectionMethod( Diagnostics::class, 'add_ignored_addon_check' );
		$ref->setAccessible( true );
		$args = array( &$checks );
		$ref->invokeArgs( null, $args );

		$this->assertCount( 1, $checks );

		return $checks[0];
	}

	public function test_check_warns_and_names_keys_when_option_present(): void {
		Functions\when( 'get_option' )->justReturn( array( 'MyGeo', 'Other' ) );

		$check = $this->run_ignored_addon_check();

		$this->assertSame( 'ignored-addon-providers', $check['id'] );
		$this->assertSame( 'warning', $check['status'] );
		$this->assertSame( 'providers', $check['category'] );
		$this->assertSame( array( 'MyGeo', 'Other' ), $check['details'] );
		$this->assertTrue( $check['acknowledgeable'] );
	}

	public function test_check_passes_when_option_empty(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$check = $this->run_ignored_addon_check();

		$this->assertSame( 'ignored-addon-providers', $check['id'] );
		$this->assertSame( 'pass', $check['status'] );
		$this->assertFalse( $check['acknowledgeable'] );
	}

	public function test_acknowledging_deletes_the_ignored_addons_option(): void {
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\expect( 'delete_option' )
			->once()
			->with( \IP_Location_Block_Provider::IGNORED_ADDONS_OPTION );

		$this->assertTrue(
			Diagnostics::set_acknowledgement( 'ignored-addon-providers', true )
		);
	}

	public function test_restoring_does_not_delete_the_option(): void {
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'get_option' )->justReturn(
			array( 'ignored-addon-providers' => 123 )
		);
		Functions\when( 'update_option' )->justReturn( true );
		Functions\expect( 'delete_option' )->never();

		$this->assertTrue(
			Diagnostics::set_acknowledgement( 'ignored-addon-providers', false )
		);
	}

	public function test_ignored_addon_id_is_acknowledgeable(): void {
		$ref = new \ReflectionMethod( Diagnostics::class, 'acknowledgeable_ids' );
		$ref->setAccessible( true );

		$this->assertContains( 'ignored-addon-providers', $ref->invoke( null ) );
	}
}
