<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit\Providers;

use Brain\Monkey\Functions;
use IPLocationBlock\Providers\CredentialFingerprint;
use IPLocationBlock\Tests\Unit\TestCase;

final class CredentialFingerprintTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_salt' )->justReturn( 'fixed-auth-salt' );
	}

	public function test_fingerprint_is_stable(): void {
		$this->assertSame(
			CredentialFingerprint::of( 'IP Location Block', 'abc123' ),
			CredentialFingerprint::of( 'IP Location Block', 'abc123' )
		);
	}

	public function test_fingerprint_is_sha256_hex(): void {
		$this->assertSame( 64, strlen( CredentialFingerprint::of( 'IP Location Block', 'abc123' ) ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', CredentialFingerprint::of( 'x', 'y' ) );
	}

	public function test_fingerprint_differs_per_credential(): void {
		$this->assertNotSame(
			CredentialFingerprint::of( 'IP Location Block', 'key1' ),
			CredentialFingerprint::of( 'IP Location Block', 'key2' )
		);
	}

	public function test_fingerprint_differs_per_provider(): void {
		$this->assertNotSame(
			CredentialFingerprint::of( 'IP Location Block', 'same' ),
			CredentialFingerprint::of( 'ipstack', 'same' )
		);
	}
}
