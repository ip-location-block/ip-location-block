<?php
/**
 * Provider credential fingerprint.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

/**
 * Non-reversible identifier for a provider credential. Safe to embed in
 * transient names and verification metadata; the credential itself is never
 * persisted outside the option.
 */
final class CredentialFingerprint {

	private function __construct() {}

	/**
	 * HMAC-SHA256 over wp_salt('auth'), keyed by provider + credential.
	 */
	public static function of( string $provider, string $credential ): string {
		return hash_hmac(
			'sha256',
			$provider . "\0" . $credential,
			wp_salt( 'auth' )
		);
	}
}
