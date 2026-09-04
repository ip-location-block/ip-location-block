<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use IPLocationBlock\Admin\WelcomeNotice;
use IPLocationBlock\Core\Validator;
use IPLocationBlock\Tests\Unit\TestCase;

final class WelcomeNoticeTest extends TestCase {

	/** @var array<string,mixed> */
	private array $options = array();

	/** @var array<int,array{0:string,1:mixed,2:mixed}> */
	private array $writes = array();

	protected function setUp(): void {
		parent::setUp();

		$this->options = array(
			Validator::OPTION_NAME => array(
				'welcome' => false,
				// Default state for the campaign tests: a first install of the
				// running version, which is the only install that may see the panel.
				WelcomeNotice::FIRST_INSTALL_KEY => IP_LOCATION_BLOCK_VERSION,
			),
		);
		$this->writes = array();
		$this->resetValidatorSettings();

		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
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
				$this->writes[] = array( (string) $name, $value, $autoload );
				return true;
			}
		);
	}

	protected function tearDown(): void {
		$_GET = array();
		$this->resetValidatorSettings();
		parent::tearDown();
	}

	private function resetValidatorSettings(): void {
		$property = new \ReflectionProperty( Validator::class, 'settings' );
		$property->setValue( null, null );
	}

	public function test_core_screens_are_eligible(): void {
		$this->assertTrue( WelcomeNotice::is_eligible_screen( 'plugins.php', '' ) );
		$this->assertTrue( WelcomeNotice::is_eligible_screen( 'edit.php', '' ) );
	}

	public function test_plugin_screens_are_not_eligible(): void {
		$this->assertFalse( WelcomeNotice::is_eligible_screen( 'settings_page_ip-location-block', 'ip-location-block' ) );
		$this->assertFalse( WelcomeNotice::is_eligible_screen( 'toplevel_page_woocommerce', 'woocommerce' ) );
		$this->assertFalse( WelcomeNotice::is_eligible_screen( 'settings_page_other-plugin', 'other-plugin' ) );
		$this->assertFalse( WelcomeNotice::is_eligible_screen( 'vendor_page_reports', '' ) );
	}

	public function test_new_install_has_not_dismissed_campaign(): void {
		$this->assertFalse( WelcomeNotice::is_dismissed() );
		$this->assertSame( array(), $this->writes );
	}

	public function test_current_campaign_is_dismissed(): void {
		$this->options[ WelcomeNotice::OPTION ] = array(
			'campaign'     => WelcomeNotice::CAMPAIGN,
			'dismissed_at' => 123,
		);

		$this->assertTrue( WelcomeNotice::is_dismissed() );
	}

	public function test_older_campaign_is_shown_again(): void {
		$this->options[ WelcomeNotice::OPTION ] = array(
			'campaign'     => 'welcome-older-campaign',
			'dismissed_at' => 123,
		);
		$this->options[ Validator::OPTION_NAME ]['welcome'] = true;

		$this->assertFalse( WelcomeNotice::is_dismissed() );
	}

	public function test_legacy_boolean_dismissal_migrates_as_a_previous_campaign(): void {
		$this->options[ Validator::OPTION_NAME ]['welcome'] = true;

		$this->assertFalse( WelcomeNotice::is_dismissed() );
		$this->assertSame(
			WelcomeNotice::LEGACY_CAMPAIGN,
			$this->options[ WelcomeNotice::OPTION ]['campaign']
		);
		$this->assertFalse( $this->writes[0][2], 'campaign state must not autoload' );
	}

	/** ===== fresh install gate (1.4.1) ===== */

	public function test_first_install_of_the_running_version_is_fresh(): void {
		$this->assertTrue( WelcomeNotice::is_fresh_install() );
	}

	public function test_install_upgraded_from_an_older_version_is_not_fresh(): void {
		$this->options[ Validator::OPTION_NAME ][ WelcomeNotice::FIRST_INSTALL_KEY ] = '1.3.9';

		$this->assertFalse( WelcomeNotice::is_fresh_install() );
	}

	public function test_unstamped_install_is_not_treated_as_fresh(): void {
		unset( $this->options[ Validator::OPTION_NAME ][ WelcomeNotice::FIRST_INSTALL_KEY ] );
		$this->assertFalse( WelcomeNotice::is_fresh_install() );

		$this->resetValidatorSettings();
		$this->options[ Validator::OPTION_NAME ][ WelcomeNotice::FIRST_INSTALL_KEY ] = '';
		$this->assertFalse( WelcomeNotice::is_fresh_install() );
	}

	public function test_mark_dismissed_closes_the_campaign_without_writing_settings(): void {
		$this->assertTrue( WelcomeNotice::mark_dismissed() );

		$this->assertSame(
			WelcomeNotice::CAMPAIGN,
			$this->options[ WelcomeNotice::OPTION ]['campaign']
		);
		$this->assertTrue( WelcomeNotice::is_dismissed() );
		$this->assertSame(
			array( WelcomeNotice::OPTION ),
			array_column( $this->writes, 0 ),
			'only the campaign option may be written'
		);
	}

	public function test_dismiss_stores_campaign_and_keeps_legacy_flag_in_sync(): void {
		$this->assertTrue( WelcomeNotice::dismiss() );

		$this->assertSame(
			WelcomeNotice::CAMPAIGN,
			$this->options[ WelcomeNotice::OPTION ]['campaign']
		);
		$this->assertIsInt( $this->options[ WelcomeNotice::OPTION ]['dismissed_at'] );
		$this->assertTrue( $this->options[ Validator::OPTION_NAME ]['welcome'] );
		$this->assertFalse( $this->writes[0][2], 'campaign state must not autoload' );
	}
}
