<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use IPLocationBlock\Geolocation\IpCacheRepository;
use IPLocationBlock\Settings\PrecisionCacheGuard;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The settings-updated listener that heals stale IP-cache rows: it clears the
 * cache iff the precision ("`:`"-bearing) rule set changed. The fingerprint is
 * the decision surface, so it is pinned here.
 */
final class PrecisionCacheGuardTest extends TestCase {

	/** @var object minimal $wpdb double recording TRUNCATE queries */
	private object $wpdb;

	protected function setUp(): void {
		parent::setUp();

		$this->wpdb = new class {
			public string $prefix = 'wp_';
			/** @var list<string> */
			public array $queries = array();

			/** @param string $query */
			public function get_var( $query ) {
				return 'wp_ip_location_block_cache'; // table exists
			}

			/** @param string $query */
			public function query( $query ) {
				$this->queries[] = (string) $query;
				return true;
			}
		};
		$GLOBALS['wpdb'] = $this->wpdb;
		IpCacheRepository::flushMemory();
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		IpCacheRepository::flushMemory();
		parent::tearDown();
	}

	/** ===== fingerprint() ===== */

	public function test_fingerprint_ignores_plain_country_entries(): void {
		$this->assertSame(
			array(),
			PrecisionCacheGuard::fingerprint(
				array( 'white_list' => 'US,FR,CN', 'black_list' => 'ZZ' )
			)
		);
	}

	public function test_fingerprint_is_case_and_order_insensitive(): void {
		$a = PrecisionCacheGuard::fingerprint(
			array( 'black_list' => 'US:State:Washington,US:City:Seattle' )
		);
		$b = PrecisionCacheGuard::fingerprint(
			array( 'black_list' => 'us:city:seattle, US:STATE:WASHINGTON' )
		);
		$this->assertSame( $a, $b );
		$this->assertContains( 'us:state:washington', $a );
		$this->assertContains( 'us:city:seattle', $a );
	}

	public function test_fingerprint_spans_public_lists(): void {
		$this->assertSame(
			array( 'us:city:seattle' ),
			PrecisionCacheGuard::fingerprint(
				array( 'public' => array( 'black_list' => 'US:City:Seattle' ) )
			)
		);
	}

	/** ===== maybe_clear() ===== */

	public function test_clears_cache_when_a_precision_rule_is_added(): void {
		PrecisionCacheGuard::maybe_clear(
			array( 'black_list' => 'US' ),
			array( 'black_list' => 'US,US:State:Washington' )
		);

		$this->assertCount( 1, $this->wpdb->queries );
		$this->assertStringContainsString( 'TRUNCATE', $this->wpdb->queries[0] );
	}

	public function test_clears_cache_when_a_precision_rule_is_altered(): void {
		PrecisionCacheGuard::maybe_clear(
			array( 'black_list' => 'US:State:Washington' ),
			array( 'black_list' => 'US:State:Oregon' )
		);

		$this->assertCount( 1, $this->wpdb->queries );
	}

	public function test_clears_cache_on_public_list_precision_change(): void {
		PrecisionCacheGuard::maybe_clear(
			array( 'public' => array( 'black_list' => 'US' ) ),
			array( 'public' => array( 'black_list' => 'US:City:Seattle' ) )
		);

		$this->assertCount( 1, $this->wpdb->queries );
	}

	public function test_does_not_clear_when_only_plain_countries_change(): void {
		PrecisionCacheGuard::maybe_clear(
			array( 'black_list' => 'US,US:State:Washington' ),
			array( 'black_list' => 'US,FR,CN,US:State:Washington' )
		);

		$this->assertSame( array(), $this->wpdb->queries );
	}

	public function test_does_not_clear_when_nothing_relevant_changes(): void {
		$settings = array(
			'black_list' => 'US:State:Washington',
			'timeout'    => 5,
		);
		PrecisionCacheGuard::maybe_clear(
			$settings,
			array( 'black_list' => 'US:State:Washington', 'timeout' => 8 )
		);

		$this->assertSame( array(), $this->wpdb->queries );
	}

	public function test_tolerates_non_array_values(): void {
		PrecisionCacheGuard::maybe_clear( false, array( 'black_list' => 'US:State:Washington' ) );
		$this->assertCount( 1, $this->wpdb->queries );
	}

	/** ===== register() ===== */

	public function test_register_adds_the_update_option_listener(): void {
		$added = array();
		Functions\when( 'add_action' )->alias(
			static function ( $hook ) use ( &$added ) {
				$added[] = $hook;
			}
		);

		PrecisionCacheGuard::register();

		$this->assertContains( 'update_option_ip_location_block_settings', $added );
	}
}
