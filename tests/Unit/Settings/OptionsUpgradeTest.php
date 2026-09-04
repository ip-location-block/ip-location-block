<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use IPLocationBlock\Admin\WelcomeNotice;
use IPLocationBlock\Core\Validator;
use IPLocationBlock\Settings\Options;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The 1.4.1 install-provenance rules in Options::upgrade().
 *
 * A site that already had a settings row is an upgrade: it gets the current
 * welcome campaign closed for it, so updating the plugin never re-opens the
 * release panel. A site without one is a first install and keeps the panel.
 *
 * The stored version is pinned at 1.4.0 so none of the historical migration
 * branches (which need $wpdb, SQLite and the mu-plugin filesystem) run.
 */
final class OptionsUpgradeTest extends TestCase {

	/** @var array<string,mixed> */
	private array $options = array();

	/** @var list<string> option names written, in order */
	private array $writes = array();

	protected function setUp(): void {
		parent::setUp();

		$this->options = array();
		$this->writes  = array();
		$this->resetValidatorSettings();

		Functions\when( 'get_option' )->alias(
			function ( $name, $default = false ) {
				return array_key_exists( (string) $name, $this->options )
					? $this->options[ (string) $name ]
					: $default;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( $name, $value, $autoload = null ) {
				$this->options[ (string) $name ] = $value;
				$this->writes[]                  = (string) $name;
				return true;
			}
		);
	}

	protected function tearDown(): void {
		$this->resetValidatorSettings();
		parent::tearDown();
	}

	private function resetValidatorSettings( $settings = null ): void {
		$property = new \ReflectionProperty( Validator::class, 'settings' );
		$property->setValue( null, $settings );
	}

	/**
	 * Seed a site that already runs the plugin: a stored settings row, and the
	 * same array primed in the Validator cache (Validator::get_default() cannot
	 * run here, it requires the WordPress-only compat bootstrap).
	 *
	 * @param array<string,mixed> $extra
	 */
	private function seed_existing_install( array $extra = array() ): void {
		$settings = array_merge(
			array(
				'version'    => '1.4.0',
				'welcome'    => false,
				'request_ua' => null,
			),
			$extra
		);

		$this->options[ Validator::OPTION_NAME ] = $settings;
		$this->resetValidatorSettings( $settings );
	}

	/**
	 * Seed a first install: no stored settings row, defaults in the cache.
	 */
	private function seed_first_install(): void {
		unset( $this->options[ Validator::OPTION_NAME ] );
		$this->resetValidatorSettings(
			array(
				'version'                        => IP_LOCATION_BLOCK_VERSION,
				'welcome'                        => false,
				'request_ua'                     => null,
				WelcomeNotice::FIRST_INSTALL_KEY => '',
			)
		);
	}

	public function test_upgrade_of_an_existing_install_closes_the_welcome_campaign(): void {
		$this->seed_existing_install();

		Options::upgrade();

		$this->assertSame(
			WelcomeNotice::CAMPAIGN,
			$this->options[ WelcomeNotice::OPTION ]['campaign'],
			'an upgraded site must not be shown the release panel'
		);
		$this->assertTrue( WelcomeNotice::is_dismissed() );
		$this->assertTrue( $this->options[ Validator::OPTION_NAME ]['welcome'] );
	}

	public function test_upgrade_records_the_version_the_site_came_from(): void {
		$this->seed_existing_install();

		Options::upgrade();

		$saved = $this->options[ Validator::OPTION_NAME ];
		$this->assertSame( '1.4.0', $saved[ WelcomeNotice::FIRST_INSTALL_KEY ] );
		$this->assertSame( IP_LOCATION_BLOCK_VERSION, $saved['version'] );
		$this->assertFalse( WelcomeNotice::is_fresh_install() );
	}

	public function test_upgrade_keeps_an_already_recorded_first_version(): void {
		$this->seed_existing_install( array( WelcomeNotice::FIRST_INSTALL_KEY => '1.2.0' ) );

		Options::upgrade();

		$this->assertSame(
			'1.2.0',
			$this->options[ Validator::OPTION_NAME ][ WelcomeNotice::FIRST_INSTALL_KEY ]
		);
	}

	public function test_first_install_keeps_the_welcome_panel(): void {
		$this->seed_first_install();

		Options::upgrade();

		$this->assertArrayNotHasKey(
			WelcomeNotice::OPTION,
			$this->options,
			'a first install must not have its campaign auto-dismissed'
		);
		$this->assertSame( array( Validator::OPTION_NAME ), $this->writes );

		$saved = $this->options[ Validator::OPTION_NAME ];
		$this->assertSame( IP_LOCATION_BLOCK_VERSION, $saved[ WelcomeNotice::FIRST_INSTALL_KEY ] );
		$this->assertFalse( $saved['welcome'] );

		$this->assertTrue( WelcomeNotice::is_fresh_install() );
		$this->assertFalse( WelcomeNotice::is_dismissed() );
	}

	public function test_an_explicit_dismissal_survives_a_later_upgrade(): void {
		$this->options[ WelcomeNotice::OPTION ] = array(
			'campaign'     => WelcomeNotice::CAMPAIGN,
			'dismissed_at' => 1234,
		);
		$this->seed_existing_install( array( 'welcome' => true ) );

		Options::upgrade();

		$this->assertSame(
			WelcomeNotice::CAMPAIGN,
			$this->options[ WelcomeNotice::OPTION ]['campaign']
		);
		$this->assertTrue( WelcomeNotice::is_dismissed() );
	}
}
