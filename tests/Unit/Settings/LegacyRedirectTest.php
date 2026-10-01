<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Settings;

use IPLocationBlock\Settings\Options;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The 1.4.2 upgrade moves only redirect values still on the old hosted blocked
 * page (blocked.iplocationblock.com) to the new default. The matcher is the
 * decision surface, so a custom URL must never match.
 */
final class LegacyRedirectTest extends TestCase {

	public function test_default_points_at_geoblocked_im(): void {
		$this->assertSame( 'https://geoblocked.im/', Options::DEFAULT_REDIRECT_URL );
	}

	/**
	 * @dataProvider legacyUrls
	 */
	public function test_matches_old_hosted_page( string $url ): void {
		$this->assertTrue( Options::is_legacy_redirect_url( $url ) );
	}

	/** @return array<string, array{string}> */
	public static function legacyUrls(): array {
		return array(
			'default'        => array( 'https://blocked.iplocationblock.com/' ),
			'no slash'       => array( 'https://blocked.iplocationblock.com' ),
			'http'           => array( 'http://blocked.iplocationblock.com/' ),
			'upper case'     => array( 'https://BLOCKED.IPLocationBlock.com/' ),
			'path and query' => array( 'https://blocked.iplocationblock.com/?from=site' ),
		);
	}

	/**
	 * @dataProvider customValues
	 *
	 * @param mixed $value
	 */
	public function test_leaves_custom_and_empty_values( $value ): void {
		$this->assertFalse( Options::is_legacy_redirect_url( $value ) );
	}

	/** @return array<string, array{mixed}> */
	public static function customValues(): array {
		return array(
			'empty'            => array( '' ),
			'null'             => array( null ),
			'array'            => array( array( 'https://blocked.iplocationblock.com/' ) ),
			'new default'      => array( 'https://geoblocked.im/' ),
			'own site'         => array( 'https://example.com/blocked/' ),
			'apex domain'      => array( 'https://iplocationblock.com/' ),
			'look-alike host'  => array( 'https://blocked.iplocationblock.com.evil.test/' ),
			'host in path'     => array( 'https://example.com/blocked.iplocationblock.com/' ),
			'subdomain of old' => array( 'https://x.blocked.iplocationblock.com/' ),
			'relative path'    => array( '/blocked/' ),
		);
	}
}
