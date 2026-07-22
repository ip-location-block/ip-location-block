<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Geolocation;

use IPLocationBlock\Geolocation\IpCacheRepository;
use IPLocationBlock\Tests\Unit\TestCase;

final class IpCacheRepositoryTest extends TestCase {

	protected function tearDown(): void {
		IpCacheRepository::flushMemory();
		parent::tearDown();
	}

	private function settings(): array {
		return array(
			'cache_hold'      => 0, // keep the DB (Logs) layer out of this unit test
			'save_statistics' => 1,
			'behavior'        => array( 'time' => 100 ),
		);
	}

	public function test_update_then_find_round_trips_through_memcache(): void {
		$_SERVER['REQUEST_TIME'] = 1_700_000_000;

		$repo     = new IpCacheRepository();
		$validate = array(
			'ip'    => '203.0.113.10',
			'asn'   => 'AS64500',
			'code'  => 'US',
			'auth'  => 0,
			'city'  => 'Seattle',
			'state' => 'Washington',
		);

		$written = $repo->update( 'admin', $validate, $this->settings(), true );

		$this->assertSame( 'US', $written['code'] );

		$read = $repo->find( '203.0.113.10' );
		$this->assertIsArray( $read );
		$this->assertSame( 'US', $read['code'] );
		$this->assertSame( 'Seattle', $read['city'] );
		$this->assertSame( 'Washington', $read['state'] );
		$this->assertSame( 'AS64500', $read['asn'] );
	}

	public function test_find_returns_null_for_unknown_ip_without_cache(): void {
		$this->assertNull( ( new IpCacheRepository() )->find( '198.51.100.5', false ) );
	}
}
