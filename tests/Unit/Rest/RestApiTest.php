<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Rest;

use IPLocationBlock\Rest\RestApi;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * Pure/near-pure payload-shaping helpers carried over 1:1 from the legacy
 * IP_Location_Block_Rest class. These are the REST byte-compat guard at the
 * unit level; the exhaustive route/payload oracle is the pre5/post5 snapshot
 * diff captured for the migration (see the phase 5 plan), not this suite.
 */
final class RestApiTest extends TestCase {

	private function call( string $method, array $args ) {
		$ref = new \ReflectionMethod( RestApi::class, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, $args );
	}

	/** ===== provider_is_selected() ===== */

	public function test_provider_is_selected_uses_explicit_stored_value(): void {
		$this->assertTrue( RestApi::provider_is_selected( 'ipapi', array(), array( 'ipapi' => 'somekey' ) ) );
		$this->assertFalse( RestApi::provider_is_selected( 'ipapi', array(), array( 'ipapi' => '' ) ) );
	}

	public function test_provider_is_selected_implicit_when_key_is_null_and_unstored(): void {
		// IP2Location's registry entry has 'key' => null (no credential concept).
		$this->assertTrue(
			RestApi::provider_is_selected( 'IP2Location', array( 'key' => null ), array() )
		);
	}

	public function test_provider_is_selected_false_when_unstored_and_key_not_null(): void {
		$this->assertFalse(
			RestApi::provider_is_selected( 'ipapi', array( 'key' => 'ipapi' ), array() )
		);
	}

	public function test_provider_is_selected_false_when_meta_has_no_key_entry(): void {
		$this->assertFalse( RestApi::provider_is_selected( 'ipapi', array(), array() ) );
	}

	/** ===== local_provider_ready() ===== */

	public function test_local_provider_ready_ip2location_true_when_file_exists(): void {
		$file = tempnam( sys_get_temp_dir(), 'ilb-ip2loc-' );
		try {
			$this->assertTrue(
				RestApi::local_provider_ready( 'IP2Location', array( 'IP2Location' => array( 'ipv4_path' => $file ) ) )
			);
		} finally {
			@unlink( $file );
		}
	}

	public function test_local_provider_ready_ip2location_false_when_file_missing(): void {
		$this->assertFalse(
			RestApi::local_provider_ready( 'IP2Location', array( 'IP2Location' => array( 'ipv4_path' => '/nonexistent/ip2location.bin' ) ) )
		);
	}

	public function test_local_provider_ready_geolite2_true_when_file_exists(): void {
		$file = tempnam( sys_get_temp_dir(), 'ilb-geolite2-' );
		try {
			$this->assertTrue(
				RestApi::local_provider_ready( 'GeoLite2', array( 'GeoLite2' => array( 'ip_path' => $file ) ) )
			);
		} finally {
			@unlink( $file );
		}
	}

	public function test_local_provider_ready_geolite2_false_when_file_missing(): void {
		$this->assertFalse(
			RestApi::local_provider_ready( 'GeoLite2', array( 'GeoLite2' => array( 'ip_path' => '/nonexistent/GeoLite2-Country.mmdb' ) ) )
		);
	}

	public function test_local_provider_ready_defaults_true_for_non_local_providers(): void {
		$this->assertTrue( RestApi::local_provider_ready( 'ipapi', array() ) );
	}

	/** ===== normalize_list_value() (private, reflected) ===== */

	public function test_normalize_list_value_plain_ipv4(): void {
		$this->assertSame( '203.0.113.5', $this->call( 'normalize_list_value', array( '203.0.113.5', 'ip' ) ) );
	}

	public function test_normalize_list_value_ipv4_cidr(): void {
		$this->assertSame( '203.0.113.0/24', $this->call( 'normalize_list_value', array( '203.0.113.0/24', 'ip' ) ) );
	}

	public function test_normalize_list_value_ipv4_wildcard_expands_to_slash_24(): void {
		$this->assertSame( '203.0.113.0/24', $this->call( 'normalize_list_value', array( '203.0.113.*', 'ip' ) ) );
	}

	public function test_normalize_list_value_ipv6_wildcard_expands_to_slash_116(): void {
		$this->assertSame(
			'2001:db8::0000/116',
			$this->call( 'normalize_list_value', array( '2001:db8::****', 'ip' ) )
		);
	}

	public function test_normalize_list_value_invalid_ip_returns_empty(): void {
		$this->assertSame( '', $this->call( 'normalize_list_value', array( 'not-an-ip', 'ip' ) ) );
	}

	public function test_normalize_list_value_cidr_prefix_over_max_rejected(): void {
		$this->assertSame( '', $this->call( 'normalize_list_value', array( '203.0.113.0/33', 'ip' ) ) );
	}

	public function test_normalize_list_value_asn_uppercased_and_validated(): void {
		$this->assertSame( 'AS15169', $this->call( 'normalize_list_value', array( 'as15169', 'asn' ) ) );
		$this->assertSame( '', $this->call( 'normalize_list_value', array( 'not-an-asn', 'asn' ) ) );
	}

	/** ===== log_slug() (private, reflected) ===== */

	public function test_log_slug_xmlrpc_method_name(): void {
		$this->assertSame(
			'/xmlrpc.php pingback.ping',
			$this->call( 'log_slug', array( '<methodName>pingback.ping</methodName>' ) )
		);
	}

	public function test_log_slug_plugin_path(): void {
		$this->assertSame(
			'/wp-content/plugins/akismet/',
			$this->call( 'log_slug', array( '/wp-content/plugins/akismet/akismet.php' ) )
		);
	}

	public function test_log_slug_admin_php_with_page_or_action(): void {
		$this->assertSame(
			'/wp-admin/admin-ajax.php action=some_action',
			$this->call( 'log_slug', array( '/wp-admin/admin-ajax.php?action=some_action' ) )
		);
	}

	public function test_log_slug_non_admin_wp_admin_php_file(): void {
		$this->assertSame(
			'/wp-admin/options-general.php',
			$this->call( 'log_slug', array( '/wp-admin/options-general.php?page=foo' ) )
		);
	}

	public function test_log_slug_returns_empty_when_no_pattern_matches(): void {
		$this->assertSame( '', $this->call( 'log_slug', array( 'nothing recognizable here' ) ) );
	}

	/** ===== rank_counts() (private, reflected) ===== */

	public function test_rank_counts_sorts_descending_by_count(): void {
		$rows = $this->call( 'rank_counts', array( array( 'US' => 3, 'FR' => 10, 'MK' => 1 ), null ) );

		$this->assertSame(
			array(
				array( 'value' => 'FR', 'count' => 10 ),
				array( 'value' => 'US', 'count' => 3 ),
				array( 'value' => 'MK', 'count' => 1 ),
			),
			$rows
		);
	}

	public function test_rank_counts_respects_limit(): void {
		$rows = $this->call( 'rank_counts', array( array( 'A' => 1, 'B' => 5, 'C' => 3 ), 2 ) );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'B', $rows[0]['value'] );
		$this->assertSame( 'C', $rows[1]['value'] );
	}

	/** ===== log_lists_for_target() (private, reflected) ===== */

	public function test_log_lists_for_target_uses_public_scoped_lists_when_public_rule_active(): void {
		$settings = array(
			'white_list' => 'top-level-white',
			'black_list' => 'top-level-black',
			'public'     => array(
				'matching_rule' => 0,
				'white_list'    => 'public-white',
				'black_list'    => 'public-black',
			),
		);

		$this->assertSame(
			array( 'white' => 'public-white', 'black' => 'public-black' ),
			$this->call( 'log_lists_for_target', array( 'public', $settings ) )
		);
	}

	public function test_log_lists_for_target_falls_back_to_top_level_lists_for_non_public_target(): void {
		$settings = array(
			'white_list' => 'top-level-white',
			'black_list' => 'top-level-black',
		);

		$this->assertSame(
			array( 'white' => 'top-level-white', 'black' => 'top-level-black' ),
			$this->call( 'log_lists_for_target', array( 'login', $settings ) )
		);
	}

	public function test_log_lists_for_target_falls_back_when_public_matching_rule_disabled(): void {
		$settings = array(
			'white_list' => 'top-level-white',
			'black_list' => 'top-level-black',
			'public'     => array(
				'matching_rule' => -1,
				'white_list'    => 'public-white',
				'black_list'    => 'public-black',
			),
		);

		$this->assertSame(
			array( 'white' => 'top-level-white', 'black' => 'top-level-black' ),
			$this->call( 'log_lists_for_target', array( 'public', $settings ) )
		);
	}
}
