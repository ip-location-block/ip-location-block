<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Core;

use Brain\Monkey\Actions;
use IPLocationBlock\Core\Validator;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The country/city/state rule matcher and the result predicates. These are
 * pure, WP-free static methods; their behavior is the country-blocking
 * decision surface, so they are pinned here.
 */
final class ValidatorTest extends TestCase {

	/** ===== validate_list_match(): plain country ===== */

	public function test_plain_country_matches(): void {
		$this->assertTrue( Validator::validate_list_match( 'US', array( 'code' => 'US' ) ) );
		$this->assertTrue( Validator::validate_list_match( 'MK,US,FR', array( 'code' => 'FR' ) ) );
	}

	public function test_plain_country_no_match(): void {
		$this->assertFalse( Validator::validate_list_match( 'MK,US,FR', array( 'code' => 'JP' ) ) );
	}

	public function test_plain_country_is_case_insensitive(): void {
		$this->assertTrue( Validator::validate_list_match( 'us', array( 'code' => 'US' ) ) );
		$this->assertTrue( Validator::validate_list_match( 'US', array( 'code' => 'us' ) ) );
	}

	public function test_whitespace_around_parts_is_trimmed(): void {
		$this->assertTrue( Validator::validate_list_match( 'MK , US , FR', array( 'code' => 'US' ) ) );
	}

	/** ===== validate_list_match(): CC:City:Name ===== */

	public function test_city_rule_matches(): void {
		$this->assertTrue(
			Validator::validate_list_match( 'US:City:Seattle', array( 'code' => 'US', 'city' => 'Seattle' ) )
		);
	}

	public function test_city_rule_is_case_insensitive(): void {
		$this->assertTrue(
			Validator::validate_list_match( 'us:city:seattle', array( 'code' => 'US', 'city' => 'Seattle' ) )
		);
	}

	public function test_city_rule_wrong_country_no_match(): void {
		$this->assertFalse(
			Validator::validate_list_match( 'US:City:Seattle', array( 'code' => 'CA', 'city' => 'Seattle' ) )
		);
	}

	public function test_city_rule_wrong_city_no_match(): void {
		$this->assertFalse(
			Validator::validate_list_match( 'US:City:Seattle', array( 'code' => 'US', 'city' => 'Portland' ) )
		);
	}

	/** ===== validate_list_match(): CC:City (two-part shorthand) ===== */

	public function test_two_part_rule_defaults_to_city(): void {
		$this->assertTrue(
			Validator::validate_list_match( 'US:Seattle', array( 'code' => 'US', 'city' => 'Seattle' ) )
		);
		$this->assertFalse(
			Validator::validate_list_match( 'US:Seattle', array( 'code' => 'US', 'city' => 'Portland' ) )
		);
	}

	/** ===== validate_list_match(): CC:City:A~B ('~' is an OR) ===== */

	public function test_city_tilde_matches_any_alternative(): void {
		// '~' is an OR: the entry matches when the city equals ANY alternative.
		$this->assertTrue(
			Validator::validate_list_match( 'US:City:Seattle~Tacoma', array( 'code' => 'US', 'city' => 'Seattle' ) )
		);
		$this->assertTrue(
			Validator::validate_list_match( 'US:City:Seattle~Tacoma', array( 'code' => 'US', 'city' => 'Tacoma' ) )
		);
		$this->assertFalse(
			Validator::validate_list_match( 'US:City:Seattle~Tacoma', array( 'code' => 'US', 'city' => 'Portland' ) )
		);
		// The literal "A~B" is no longer treated as one verbatim value.
		$this->assertFalse(
			Validator::validate_list_match( 'US:City:Seattle~Tacoma', array( 'code' => 'US', 'city' => 'Seattle~Tacoma' ) )
		);
	}

	public function test_two_part_tilde_matches_any_alternative(): void {
		$this->assertTrue(
			Validator::validate_list_match( 'US:Seattle~Tacoma', array( 'code' => 'US', 'city' => 'Tacoma' ) )
		);
		$this->assertFalse(
			Validator::validate_list_match( 'US:Seattle~Tacoma', array( 'code' => 'US', 'city' => 'Portland' ) )
		);
	}

	public function test_state_tilde_matches_any_alternative(): void {
		$this->assertTrue(
			Validator::validate_list_match(
				'US:State:Oregon~Washington',
				array( 'code' => 'US', 'state' => 'Washington' )
			)
		);
	}

	/** ===== validate_list_match(): Region is an alias of State ===== */

	public function test_region_keyword_matches_state_field(): void {
		// "Region" reads the same $result['state'] field as "State".
		$this->assertTrue(
			Validator::validate_list_match( 'US:Region:Washington', array( 'code' => 'US', 'state' => 'Washington' ) )
		);
	}

	public function test_region_keyword_is_case_insensitive(): void {
		$this->assertTrue(
			Validator::validate_list_match( 'us:region:washington', array( 'code' => 'US', 'state' => 'Washington' ) )
		);
	}

	public function test_uppercase_keyword_matches(): void {
		$this->assertTrue(
			Validator::validate_list_match( 'US:STATE:Washington', array( 'code' => 'US', 'state' => 'Washington' ) )
		);
		$this->assertTrue(
			Validator::validate_list_match( 'US:CITY:Seattle', array( 'code' => 'US', 'city' => 'Seattle' ) )
		);
	}

	public function test_unsupported_keyword_never_matches(): void {
		// Only State/Region/City are meaningful 3-part keywords.
		$this->assertFalse(
			Validator::validate_list_match(
				'US:County:King',
				array( 'code' => 'US', 'city' => 'Seattle', 'state' => 'Washington' )
			)
		);
	}

	/** ===== validate_list_match(): multi-word place names ===== */

	public function test_multi_word_state_matches(): void {
		$this->assertTrue(
			Validator::validate_list_match( 'US:State:New York', array( 'code' => 'US', 'state' => 'New York' ) )
		);
		$this->assertTrue(
			Validator::validate_list_match(
				'AU:State:Western Australia',
				array( 'code' => 'AU', 'state' => 'Western Australia' )
			)
		);
	}

	public function test_multi_word_city_matches(): void {
		$this->assertTrue(
			Validator::validate_list_match(
				'US:City:San Francisco',
				array( 'code' => 'US', 'city' => 'San Francisco' )
			)
		);
	}

	public function test_precision_rule_after_spaced_comma_list_matches(): void {
		// Precision entry following a ", "-separated country list (spaces trimmed).
		$this->assertTrue(
			Validator::validate_list_match(
				'CN, RU, US:State:Washington',
				array( 'code' => 'US', 'state' => 'Washington' )
			)
		);
	}

	/** ===== validate_list_match(): CC:State:Name ===== */

	public function test_state_rule_matches(): void {
		$this->assertTrue(
			Validator::validate_list_match( 'US:State:Washington', array( 'code' => 'US', 'state' => 'Washington' ) )
		);
	}

	public function test_state_rule_is_case_insensitive(): void {
		$this->assertTrue(
			Validator::validate_list_match( 'US:state:washington', array( 'code' => 'US', 'state' => 'Washington' ) )
		);
	}

	/** ===== validate_list_match(): null precision fields never match ===== */

	public function test_null_city_never_matches_city_rule(): void {
		$this->assertFalse(
			Validator::validate_list_match( 'US:City:Seattle', array( 'code' => 'US', 'city' => null, 'state' => null ) )
		);
	}

	public function test_null_state_never_matches_state_rule(): void {
		$this->assertFalse(
			Validator::validate_list_match( 'US:State:Washington', array( 'code' => 'US', 'city' => null, 'state' => null ) )
		);
	}

	public function test_precision_rule_falls_back_to_plain_country_in_same_list(): void {
		// A precision rule that cannot match (null city) does not veto a later
		// plain-country rule for the same code.
		$this->assertTrue(
			Validator::validate_list_match( 'US:City:Seattle,US', array( 'code' => 'US', 'city' => null ) )
		);
	}

	/** ===== validate_lookup_result(): whitelist precision degradation ===== */

	/**
	 * Availability-first: a whitelisted precision rule (e.g. "US:State:Washington")
	 * must NOT lock out the visitor on their country when the provider returned no
	 * precision data (empty city AND state — a native outage/fallback). The result
	 * degrades to the country prefix and a disclosure action fires.
	 */
	public function test_whitelist_precision_degrades_to_country_when_no_precision_data(): void {
		Actions\expectDone( 'ip-location-block-precision-degraded' )->once();

		$settings = array( 'matching_rule' => 0, 'white_list' => 'US:State:Washington', 'black_list' => '' );
		$validate = array( 'code' => 'US', 'city' => '', 'state' => '' );

		$this->assertSame(
			'passed',
			Validator::validate_lookup_result( false, $validate, $settings, true )
		);
	}

	public function test_whitelist_degradation_keeps_visitor_but_not_a_foreign_country(): void {
		// Degrading precision entries to their country prefix must not whitelist a
		// DIFFERENT country: "US:State:Washington" => "US" still does not match FR.
		Actions\expectDone( 'ip-location-block-precision-degraded' )->never();

		$settings = array( 'matching_rule' => 0, 'white_list' => 'US:State:Washington', 'black_list' => '' );
		$validate = array( 'code' => 'FR', 'city' => '', 'state' => '' );

		$this->assertSame(
			'blocked',
			Validator::validate_lookup_result( false, $validate, $settings, true )
		);
	}

	public function test_whitelist_no_degradation_when_precision_present_but_mismatched(): void {
		// City/state ARE present but do not match: this is a real mismatch, not an
		// outage, so the block stands and no degradation action fires.
		Actions\expectDone( 'ip-location-block-precision-degraded' )->never();

		$settings = array( 'matching_rule' => 0, 'white_list' => 'US:State:Washington', 'black_list' => '' );
		$validate = array( 'code' => 'US', 'city' => 'Portland', 'state' => 'Oregon' );

		$this->assertSame(
			'blocked',
			Validator::validate_lookup_result( false, $validate, $settings, true )
		);
	}

	public function test_whitelist_plain_country_match_needs_no_degradation(): void {
		// Already whitelisted at country level on the original list: the degradation
		// path is never entered and no action fires.
		Actions\expectDone( 'ip-location-block-precision-degraded' )->never();

		$settings = array( 'matching_rule' => 0, 'white_list' => 'US', 'black_list' => '' );
		$validate = array( 'code' => 'US', 'city' => '', 'state' => '' );

		$this->assertSame(
			'passed',
			Validator::validate_lookup_result( false, $validate, $settings, true )
		);
	}

	public function test_whitelist_degraded_pass_sets_hook_result(): void {
		// With a hook name the degraded pass returns the merged 'passed' result.
		Actions\expectDone( 'ip-location-block-precision-degraded' )->once();

		$settings = array( 'matching_rule' => 0, 'white_list' => 'US:State:Washington', 'black_list' => '' );
		$validate = array( 'code' => 'US', 'city' => '', 'state' => '' );

		$out = Validator::validate_lookup_result( 'public', $validate, $settings, true );

		$this->assertSame( 'passed', $out['result'] );
		$this->assertSame( 'US', $out['code'] );
	}

	/** ===== validate_lookup_result(): blacklist is unchanged (no degradation) ===== */

	public function test_blacklist_absent_precision_data_passes_unchanged(): void {
		// Blacklist + empty precision: the precision rule cannot match, so the
		// visitor passes (as today). Degradation NEVER applies to the blacklist —
		// it must not start blocking the whole country.
		Actions\expectDone( 'ip-location-block-precision-degraded' )->never();

		$settings = array( 'matching_rule' => 1, 'white_list' => '', 'black_list' => 'US:State:Washington' );
		$validate = array( 'code' => 'US', 'city' => '', 'state' => '' );

		$this->assertSame(
			'passed',
			Validator::validate_lookup_result( false, $validate, $settings, true )
		);
	}

	public function test_blacklist_present_precision_match_still_blocks(): void {
		Actions\expectDone( 'ip-location-block-precision-degraded' )->never();

		$settings = array( 'matching_rule' => 1, 'white_list' => '', 'black_list' => 'US:State:Washington' );
		$validate = array( 'code' => 'US', 'city' => '', 'state' => 'Washington' );

		$this->assertSame(
			'blocked',
			Validator::validate_lookup_result( false, $validate, $settings, true )
		);
	}

	/** ===== result predicates ===== */

	public function test_is_passed(): void {
		$this->assertTrue( Validator::is_passed( 'passed' ) );
		$this->assertTrue( Validator::is_passed( 'pass' ) );
		$this->assertFalse( Validator::is_passed( 'blocked' ) );
		$this->assertFalse( Validator::is_passed( '' ) );
		$this->assertFalse( Validator::is_passed( null ) );
	}

	public function test_is_failed(): void {
		$this->assertTrue( Validator::is_failed( 'failed' ) );
		$this->assertFalse( Validator::is_failed( 'passed' ) );
		$this->assertFalse( Validator::is_failed( '' ) );
	}

	public function test_is_blocked(): void {
		$this->assertTrue( Validator::is_blocked( 'blocked' ) );
		$this->assertTrue( Validator::is_blocked( 'failed' ) );
		$this->assertTrue( Validator::is_blocked( 'limited' ) );
		$this->assertFalse( Validator::is_blocked( 'passed' ) );
		$this->assertFalse( Validator::is_blocked( '' ) );
	}

	public function test_is_listed(): void {
		$this->assertTrue( Validator::is_listed( 'US', 'US,FR' ) );
		$this->assertFalse( Validator::is_listed( 'JP', 'US,FR' ) );
		$this->assertFalse( Validator::is_listed( 'ZZ', '' ) );
	}
}
