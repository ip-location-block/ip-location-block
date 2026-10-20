<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Cron;

use Brain\Monkey\Functions;
use IPLocationBlock\Core\Validator;
use IPLocationBlock\Cron\Scheduler;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The database update event is only expected when a local provider downloads
 * a database, and ensure_events() recreates events whose self-rescheduling
 * chain broke.
 */
final class SchedulerTest extends TestCase {

	protected function tearDown(): void {
		( new \ReflectionProperty( Validator::class, 'settings' ) )->setValue( null, null );
		parent::tearDown();
	}

	private function settings( array $providers, bool $auto = true ): array {
		return array(
			'providers'     => $providers,
			'update'        => array( 'auto' => $auto, 'retry' => 0, 'cycle' => 30 ),
			'cache_time_gc' => 900,
		);
	}

	/** ===== needs_db_updates() ===== */

	public function test_api_only_sites_need_no_database_updates(): void {
		$this->assertFalse( Scheduler::needs_db_updates(
			$this->settings( array( 'IP2Location' => '', 'GeoLite2' => '', 'IP Location Block' => 'key' ) )
		) );
	}

	public function test_enabled_or_implicit_local_providers_need_database_updates(): void {
		$this->assertTrue( Scheduler::needs_db_updates( $this->settings( array() ) ) );
		$this->assertTrue( Scheduler::needs_db_updates( $this->settings( array( 'IP2Location' => '', 'GeoLite2' => 'on' ) ) ) );
	}

	public function test_disabled_auto_updates_need_no_database_updates(): void {
		$this->assertFalse( Scheduler::needs_db_updates( $this->settings( array(), false ) ) );
	}

	/** ===== next_db_update() ===== */

	public function test_next_db_update_counts_the_one_off_activation_run(): void {
		Functions\when( 'wp_next_scheduled' )->alias(
			static fn( $hook, $args = array() ) => array( true ) === $args ? 123 : false
		);

		$this->assertSame( 123, Scheduler::next_db_update() );
	}

	/** ===== ensure_events() ===== */

	public function test_ensure_events_recreates_both_missing_events(): void {
		Functions\when( 'get_option' )->justReturn( $this->settings( array() ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'is_main_site' )->justReturn( true );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );
		Functions\when( 'wp_clear_scheduled_hook' )->justReturn( 0 );

		$scheduled = array();
		Functions\when( 'wp_schedule_single_event' )->alias(
			static function ( $time, $hook, $args = array() ) use ( &$scheduled ) {
				$scheduled[] = array( $hook, $args );

				return true;
			}
		);

		Scheduler::ensure_events();

		$this->assertSame(
			array( array( Validator::CRON_NAME, array( false ) ), array( Validator::CACHE_NAME, array() ) ),
			$scheduled
		);
	}

	public function test_ensure_events_leaves_api_only_sites_and_queued_events_alone(): void {
		Functions\when( 'get_option' )->justReturn( $this->settings( array( 'IP2Location' => '', 'GeoLite2' => '' ) ) );
		Functions\when( 'wp_next_scheduled' )->alias(
			static fn( $hook ) => Validator::CACHE_NAME === $hook ? 456 : false
		);
		Functions\expect( 'wp_schedule_single_event' )->never();
		Functions\expect( 'wp_clear_scheduled_hook' )->never();

		Scheduler::ensure_events();
		$this->addToAssertionCount( 1 );
	}
}
