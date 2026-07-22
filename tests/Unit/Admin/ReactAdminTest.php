<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use IPLocationBlock\Admin\ReactAdmin;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The single view resolver (current_view / is_react_screen) that every
 * notice/enqueue guard keys on once the two admin slugs merge. Exercises the
 * param / nonce / meta / default decision matrix and the WP < 5.0 fallback.
 */
final class ReactAdminTest extends TestCase {

	/** @var array<int,array{0:int,1:string,2:string}> update_user_meta spy log */
	private array $metaWrites = array();

	protected function setUp(): void {
		parent::setUp();

		$_GET = array();
		$this->metaWrites = array();
		$this->resetViewMemo();

		// WordPress is 5.0+ (React-capable) unless a test overrides this.
		Functions\when( 'get_bloginfo' )->justReturn( '6.4' );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			static fn( $v ) => strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) )
		);
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		// No stored preference by default.
		Functions\when( 'get_user_meta' )->justReturn( '' );
		// Capability + nonce succeed by default; individual tests weaken them.
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\when( 'update_user_meta' )->alias(
			function ( $user_id, $key, $value ) {
				$this->metaWrites[] = array( (int) $user_id, (string) $key, (string) $value );
				return true;
			}
		);
	}

	protected function tearDown(): void {
		$_GET = array();
		$this->resetViewMemo();
		parent::tearDown();
	}

	private function resetViewMemo(): void {
		$property = new \ReflectionProperty( ReactAdmin::class, 'view' );
		$property->setValue( null, null );
	}

	/** @param mixed $return */
	private function withStoredView( $return ): void {
		Functions\when( 'get_user_meta' )->justReturn( $return );
	}

	public function test_default_view_is_new_with_no_param_and_no_meta(): void {
		$_GET = array( 'page' => 'ip-location-block' );

		$this->assertSame( 'new', ReactAdmin::current_view() );
		$this->assertTrue( ReactAdmin::is_react_screen() );
	}

	public function test_stored_classic_preference_wins_over_default(): void {
		$_GET = array( 'page' => 'ip-location-block' );
		$this->withStoredView( 'classic' );

		$this->assertSame( 'classic', ReactAdmin::current_view() );
		$this->assertFalse( ReactAdmin::is_react_screen() );
	}

	public function test_explicit_view_param_without_nonce_applies_but_does_not_persist(): void {
		$_GET = array( 'page' => 'ip-location-block', 'view' => 'classic' );

		$this->assertSame( 'classic', ReactAdmin::current_view() );
		$this->assertSame( array(), $this->metaWrites, 'no nonce means no persistence' );
	}

	public function test_explicit_view_param_with_valid_nonce_persists(): void {
		$_GET = array(
			'page'     => 'ip-location-block',
			'view'     => 'classic',
			'_wpnonce' => 'valid',
		);

		$this->assertSame( 'classic', ReactAdmin::current_view() );
		$this->assertSame(
			array( array( 1, 'ip_location_block_admin_ui', 'classic' ) ),
			$this->metaWrites
		);
	}

	public function test_switch_back_to_new_persists_new(): void {
		$_GET = array(
			'page'     => 'ip-location-block',
			'view'     => 'new',
			'_wpnonce' => 'valid',
		);
		$this->withStoredView( 'classic' );

		$this->assertSame( 'new', ReactAdmin::current_view() );
		$this->assertSame(
			array( array( 1, 'ip_location_block_admin_ui', 'new' ) ),
			$this->metaWrites
		);
	}

	public function test_invalid_nonce_does_not_persist_but_view_still_applies(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		$_GET = array(
			'page'     => 'ip-location-block',
			'view'     => 'classic',
			'_wpnonce' => 'bogus',
		);

		$this->assertSame( 'classic', ReactAdmin::current_view() );
		$this->assertSame( array(), $this->metaWrites );
	}

	public function test_persistence_skipped_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$_GET = array(
			'page'     => 'ip-location-block',
			'view'     => 'classic',
			'_wpnonce' => 'valid',
		);

		$this->assertSame( 'classic', ReactAdmin::current_view() );
		$this->assertSame( array(), $this->metaWrites );
	}

	public function test_invalid_view_param_is_ignored(): void {
		$_GET = array( 'page' => 'ip-location-block', 'view' => 'bogus' );
		$this->withStoredView( 'classic' );

		// Falls through to the stored preference.
		$this->assertSame( 'classic', ReactAdmin::current_view() );
	}

	public function test_is_react_screen_false_off_the_plugin_page(): void {
		$_GET = array( 'page' => 'some-other-plugin' );

		$this->assertFalse( ReactAdmin::is_react_screen() );
	}

	public function test_wp_below_5_0_forces_classic(): void {
		Functions\when( 'get_bloginfo' )->justReturn( '4.9' );
		$_GET = array( 'page' => 'ip-location-block', 'view' => 'new' );

		$this->assertSame( 'classic', ReactAdmin::current_view() );
		$this->assertFalse( ReactAdmin::is_react_screen() );
	}

	public function test_legacy_slug_is_not_the_react_screen(): void {
		// Old bookmarks 302 to the merged slug; before that the resolver must not
		// treat the opt-in slug as the React screen.
		$_GET = array( 'page' => 'ip-location-block-beta' );

		$this->assertFalse( ReactAdmin::is_react_screen() );
	}
}
