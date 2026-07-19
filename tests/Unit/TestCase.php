<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case: Brain Monkey lifecycle + the WordPress function stubs shared
 * by the provider-subsystem suite (escapers, translation, filter passthrough,
 * is_wp_error). Individual tests override these (e.g. wp_remote_get with canned
 * fixtures) as needed.
 */
abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( '__' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'trailingslashit' )->alias(
			static fn( $s ) => rtrim( (string) $s, '/\\' ) . '/'
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( $hook, $value = null ) {
				return $value;
			}
		);
		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ) => $thing instanceof \WP_Error
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
