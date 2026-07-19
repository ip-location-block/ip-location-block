<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Providers;

use Brain\Monkey\Functions;
use IPLocationBlock\Providers\NativeProvider;
use IPLocationBlock\Providers\NativeQuotaService;
use IPLocationBlock\Tests\Unit\TestCase;

final class NativeQuotaServiceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_salt' )->justReturn( 'salt' );
		NativeQuotaService::flushMemo();
	}

	protected function tearDown(): void {
		NativeQuotaService::flushMemo();
		parent::tearDown();
	}

	private function service(): NativeQuotaService {
		return new NativeQuotaService();
	}

	/** ===== normalize() status matrix ===== */

	public function test_normalize_unlimited(): void {
		$status = $this->service()->normalize( array( 'balance' => array( 'recurring' => -1, 'total' => 999 ) ) );

		$this->assertSame( 'unlimited', $status['status'] );
		$this->assertTrue( $status['unlimited'] );
	}

	public function test_normalize_ok(): void {
		$status = $this->service()->normalize(
			array( 'balance' => array( 'recurring' => 100, 'total' => 100, 'onetime' => 0 ) )
		);

		$this->assertSame( 'ok', $status['status'] );
		$this->assertFalse( $status['unlimited'] );
		$this->assertSame( 100, $status['total'] );
	}

	public function test_normalize_exhausted(): void {
		$status = $this->service()->normalize( array( 'balance' => array( 'recurring' => 0, 'total' => 0 ) ) );

		$this->assertSame( 'exhausted', $status['status'] );
	}

	public function test_normalize_rate_limited(): void {
		$status = $this->service()->normalize( array( 'status' => 'rate_limited' ) );

		$this->assertSame( 'rate_limited', $status['status'] );
	}

	public function test_normalize_key_upgrade_required(): void {
		$status = $this->service()->normalize( array( 'name' => 'requires-api-key-upgrade' ) );

		$this->assertSame( 'key_upgrade_required', $status['status'] );
	}

	public function test_normalize_error_is_unavailable(): void {
		$status = $this->service()->normalize( array( 'error' => 'boom' ) );

		$this->assertSame( 'unavailable', $status['status'] );
		$this->assertSame( 'boom', $status['message'] );
	}

	public function test_normalize_empty_is_unavailable(): void {
		$status = $this->service()->normalize( array() );

		$this->assertSame( 'unavailable', $status['status'] );
	}

	public function test_normalize_wp_error_is_unavailable_with_message(): void {
		$status = $this->service()->normalize( new \WP_Error( 'x', 'network down' ) );

		$this->assertSame( 'unavailable', $status['status'] );
		$this->assertSame( 'network down', $status['message'] );
	}

	public function test_normalize_carries_monetization_urls(): void {
		$status = $this->service()->normalize( array() );

		$this->assertSame( NativeProvider::ACCOUNT_URL, $status['accountUrl'] );
		$this->assertSame( NativeProvider::UPGRADE_URL, $status['upgradeUrl'] );
	}

	/** ===== blocking set ===== */

	public function test_blocking_status_set(): void {
		$service = $this->service();
		$this->assertTrue( $service->isBlocking( array( 'status' => 'exhausted' ) ) );
		$this->assertTrue( $service->isBlocking( array( 'status' => 'rate_limited' ) ) );
		$this->assertTrue( $service->isBlocking( array( 'status' => 'key_upgrade_required' ) ) );
		$this->assertFalse( $service->isBlocking( array( 'status' => 'ok' ) ) );
		$this->assertFalse( $service->isBlocking( array( 'status' => 'unlimited' ) ) );
	}

	/** ===== sentinel key rejection ===== */

	public function test_empty_key_is_rejected(): void {
		$this->assertInstanceOf( \WP_Error::class, $this->service()->fetch( '' ) );
	}

	public function test_at_sentinel_key_is_rejected(): void {
		$this->assertInstanceOf( \WP_Error::class, $this->service()->fetch( '@' ) );
	}

	/** ===== transient caching ===== */

	public function test_fetch_returns_cached_transient_without_network(): void {
		$cached = array( 'balance' => array( 'recurring' => -1 ), '_ilb_checked_at' => 123 );
		Functions\when( 'get_transient' )->justReturn( $cached );
		Functions\when( 'wp_remote_get' )->alias(
			static function () {
				throw new \RuntimeException( 'network must not be hit on a transient hit' );
			}
		);

		$result = $this->service()->fetch( 'valid-key' );

		$this->assertSame( $cached, $result );
	}

	public function test_fetch_stores_fresh_response_in_transient(): void {
		$body = '{"balance":{"recurring":100,"total":100}}';
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_remote_get' )->justReturn( array( 'body' => $body ) );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $body );

		$stored = null;
		Functions\when( 'set_transient' )->alias(
			static function ( $k, $v, $ttl ) use ( &$stored ) {
				$stored = $v;
				return true;
			}
		);

		$result = $this->service()->fetch( 'valid-key' );

		$this->assertIsArray( $result );
		$this->assertSame( 100, $result['balance']['total'] );
		$this->assertArrayHasKey( '_ilb_checked_at', $result );
		$this->assertIsArray( $stored );
		$this->assertArrayHasKey( '_ilb_checked_at', $stored );
	}
}
