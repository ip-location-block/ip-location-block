<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Providers;

use IPLocationBlock\Providers\NativeProvider;
use IPLocationBlock\Tests\Unit\TestCase;

/**
 * The native API base: api.iplocationblock.net by default, overridable with
 * the IP_LOCATION_BLOCK_API_BASE constant so a future domain move does not
 * require a plugin release.
 */
final class NativeProviderTest extends TestCase {

	public function test_default_api_base_and_quota_endpoint(): void {
		$this->assertFalse( defined( 'IP_LOCATION_BLOCK_API_BASE' ) );
		$this->assertSame( 'https://api.iplocationblock.net', NativeProvider::apiBase() );
		$this->assertSame( 'https://api.iplocationblock.net/quota/', NativeProvider::quotaEndpoint() );
		$this->assertSame( NativeProvider::QUOTA_ENDPOINT, NativeProvider::quotaEndpoint() );
	}

	/**
	 * @dataProvider validOverrides
	 */
	public function test_normalize_accepts_absolute_http_urls( string $value, string $expected ): void {
		$this->assertSame( $expected, NativeProvider::normalizeApiBase( $value ) );
	}

	public function validOverrides(): array {
		return array(
			'https'          => array( 'https://api.example.net', 'https://api.example.net' ),
			'trailing slash' => array( 'https://api.example.net/', 'https://api.example.net' ),
			'whitespace'     => array( "  https://api.example.net/  ", 'https://api.example.net' ),
			'http with port' => array( 'http://api.example.test:8080', 'http://api.example.test:8080' ),
			'path prefix'    => array( 'https://example.net/geo/', 'https://example.net/geo' ),
		);
	}

	/**
	 * @dataProvider invalidOverrides
	 *
	 * @param mixed $value
	 */
	public function test_normalize_rejects_anything_else( $value ): void {
		$this->assertNull( NativeProvider::normalizeApiBase( $value ) );
	}

	public function invalidOverrides(): array {
		return array(
			'empty'        => array( '' ),
			'no scheme'    => array( 'api.example.net' ),
			'relative'     => array( '/api' ),
			'ftp'          => array( 'ftp://api.example.net' ),
			'javascript'   => array( 'javascript:alert(1)' ),
			'query'        => array( 'https://api.example.net/?x=1' ),
			'fragment'     => array( 'https://api.example.net/#x' ),
			'not a string' => array( array( 'https://api.example.net' ) ),
			'boolean'      => array( true ),
			'null'         => array( null ),
		);
	}
}
