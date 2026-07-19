<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Core;

use IPLocationBlock\Core\Validator;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The country/city/state rule matcher and the result predicates. These are the
 * pure, WP-free static methods carried over 1:1 from the legacy
 * IP_Location_Block class — their behavior is the country-blocking decision
 * surface, so they are pinned here.
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

	/** ===== validate_list_match(): CC:City:A~B (tilde stays literal) ===== */

	public function test_city_rule_with_tilde_is_literal(): void {
		// validate_list_match does NOT expand '~'; the whole value is compared
		// verbatim (case-insensitively). This pins the legacy behavior.
		$this->assertTrue(
			Validator::validate_list_match( 'US:City:Seattle~Tacoma', array( 'code' => 'US', 'city' => 'Seattle~Tacoma' ) )
		);
		$this->assertFalse(
			Validator::validate_list_match( 'US:City:Seattle~Tacoma', array( 'code' => 'US', 'city' => 'Seattle' ) )
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
