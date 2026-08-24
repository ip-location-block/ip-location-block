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
			Validator::OPTION_NAME => array( 'welcome' => false ),
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

	public function test_core_and_own_screens_are_eligible(): void {
		$this->assertTrue( WelcomeNotice::is_eligible_screen( 'plugins.php', '' ) );
		$this->assertTrue( WelcomeNotice::is_eligible_screen( 'edit.php', '' ) );
		$this->assertTrue( WelcomeNotice::is_eligible_screen( 'settings_page_ip-location-block', 'ip-location-block' ) );
	}

	public function test_other_plugin_screens_are_not_eligible(): void {
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

	public function test_legacy_boolean_dismissal_migrates_to_current_campaign(): void {
		$this->options[ Validator::OPTION_NAME ]['welcome'] = true;

		$this->assertTrue( WelcomeNotice::is_dismissed() );
		$this->assertSame(
			WelcomeNotice::CAMPAIGN,
			$this->options[ WelcomeNotice::OPTION ]['campaign']
		);
		$this->assertFalse( $this->writes[0][2], 'campaign state must not autoload' );
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
