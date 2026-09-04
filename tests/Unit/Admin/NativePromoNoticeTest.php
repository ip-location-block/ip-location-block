<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use IPLocationBlock\Admin\NativePromoNotice;
use IPLocationBlock\Admin\WelcomeNotice;
use IPLocationBlock\Core\Validator;
use IPLocationBlock\Rest\RestApi;
use IPLocationBlock\Tests\Unit\TestCase;

final class NativePromoNoticeTest extends TestCase {

	private array $options = array();
	private array $writes = array();
	private int $blog = 1;

	protected function setUp(): void {
		parent::setUp();
		$this->options = array( 1 => array(), 2 => array() );
		$this->writes = array();
		$this->blog = 1;
		Functions\when( 'get_option' )->alias(
			fn( $key, $default = false ) => $this->options[ $this->blog ][ $key ] ?? $default
		);
		Functions\when( 'update_option' )->alias(
			function ( $key, $value, $autoload = null ) {
				$this->writes[] = array( $key, $value, $autoload );
				$this->options[ $this->blog ][ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'rest_ensure_response' )->returnArg();
	}

	private function dismiss( string $id = 'native-mode-promo' ) {
		$request = new \WP_REST_Request();
		$request->set_param( 'id', $id );
		return RestApi::dismiss_notice( $request );
	}

	public function test_dismissal_persists_without_touching_settings_or_welcome(): void {
		$original = array(
			Validator::OPTION_NAME => array( 'version' => '1.4.0', 'welcome' => false ),
			WelcomeNotice::OPTION => array( 'campaign' => WelcomeNotice::CAMPAIGN ),
		);
		$this->options[1] = $original;

		$this->assertFalse( NativePromoNotice::is_dismissed() );
		$this->assertSame( array( 'dismissed' => true ), $this->dismiss() );
		$this->assertTrue( NativePromoNotice::is_dismissed() );
		$this->assertSame( array( array( NativePromoNotice::OPTION, true, false ) ), $this->writes );
		$this->assertSame( $original + array( NativePromoNotice::OPTION => true ), $this->options[1] );

		// Version/campaign changes and settings replacement cannot reopen it.
		$this->options[1][ Validator::OPTION_NAME ] = array( 'version' => '2.0.0' );
		$this->options[1][ WelcomeNotice::OPTION ] = array( 'campaign' => 'future' );
		$this->assertTrue( NativePromoNotice::is_dismissed() );
	}

	public function test_dismissal_is_shared_by_site_and_idempotent(): void {
		$this->dismiss();
		$this->assertSame( array( 'dismissed' => true ), $this->dismiss() );
		$this->assertCount( 1, $this->writes );

		$this->blog = 2;
		$this->assertFalse( NativePromoNotice::is_dismissed() );
		$this->blog = 1;
		$this->assertTrue( NativePromoNotice::is_dismissed() );
	}

	public function test_storage_failure_does_not_claim_success(): void {
		Functions\when( 'update_option' )->justReturn( false );
		$result = $this->dismiss();
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ilb_notice_dismiss_failed', $result->get_error_code() );
		$this->assertSame( 500, $result->data['status'] );
		$this->assertFalse( NativePromoNotice::is_dismissed() );
	}

	public function test_unknown_notice_does_not_write_any_option(): void {
		$result = $this->dismiss( 'other-notice' );
		$this->assertSame( 'ilb_unknown_notice', $result->get_error_code() );
		$this->assertSame( 400, $result->data['status'] );
		$this->assertSame( array(), $this->writes );
	}

	public function test_notice_route_requires_administrative_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->assertFalse( RestApi::permission() );
		$this->assertSame( array(), $this->writes );
	}
}
