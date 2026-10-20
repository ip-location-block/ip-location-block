<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Support;

use Brain\Monkey\Functions;
use IPLocationBlock\Support\FileSystem;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * Hosts without direct file access and without FTP constants make WordPress
 * print its FTP credentials form. FileSystem::init() runs on every request, so
 * that form must never reach the output (it broke every REST response).
 *
 * ABSPATH points at empty stand-ins for the two wp-admin includes; nothing
 * else in the suite depends on it being undefined.
 */
final class FileSystemTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		defined( 'ABSPATH' ) || define( 'ABSPATH', IP_LOCATION_BLOCK_PATH . 'tests/fixtures/wordpress/' );
		Functions\when( 'admin_url' )->justReturn( 'https://example.test/wp-admin/' );
		$this->reset_state();
	}

	protected function tearDown(): void {
		$this->reset_state();
		parent::tearDown();
	}

	private function reset_state(): void {
		unset( $GLOBALS['wp_filesystem'] );
		foreach ( array( 'instance' => null, 'method' => 'direct', 'unavailable' => false ) as $name => $value ) {
			( new \ReflectionProperty( FileSystem::class, $name ) )->setValue( null, $value );
		}
	}

	public function test_init_never_prints_the_credentials_form_and_asks_once(): void {
		Functions\when( 'get_filesystem_method' )->justReturn( 'ftpext' );
		Functions\expect( 'request_filesystem_credentials' )->once()->andReturnUsing(
			static function () {
				echo '<form id="request-filesystem-credentials-form"></form>';

				return false;
			}
		);
		Functions\expect( 'WP_Filesystem' )->never();

		$this->expectOutputString( '' );

		FileSystem::init();
		$this->assertFalse( FileSystem::init()->exists( __FILE__ ) );
	}

	public function test_init_sets_up_the_filesystem_when_credentials_exist(): void {
		Functions\when( 'get_filesystem_method' )->justReturn( 'direct' );
		Functions\expect( 'request_filesystem_credentials' )->once()->andReturn( true );
		Functions\expect( 'WP_Filesystem' )->once()->with( true );

		$this->expectOutputString( '' );

		FileSystem::init();
	}
}
