<?php
/**
 * Provider credential tester.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

/**
 * Encapsulates the REST /providers/test flow: inject the candidate credential
 * into a throwaway settings copy, perform a fresh 8.8.8.8 lookup, validate a
 * two-letter country, and gate the native provider on its quota status.
 *
 * The verification transient name is unchanged
 * (`ip_location_block_verify_<fingerprint40>`).
 */
final class ProviderTester {

	/** IP probed by a provider test. */
	public const PROBE_IP = '8.8.8.8';

	public function __construct(
		private readonly NativeQuotaService $quota = new NativeQuotaService(),
	) {}

	/**
	 * Test a candidate credential without persisting it.
	 *
	 * @param array<string,mixed> $settings Live settings (NOT mutated).
	 * @param array<string,mixed> $httpArgs wp_remote_get args (no fresh/asn).
	 *
	 * @return \WP_Error|array<string,mixed> WP_Error for reject cases; otherwise
	 *                                       the test envelope.
	 */
	public function test( string $provider, string $credential, array $settings, array $httpArgs = array() ) {
		$registry = ProviderRegistry::instance();

		if ( 'Cache' === $provider || ! $registry->has( $provider ) ) {
			return new \WP_Error(
				'ip_location_block_unknown_provider',
				__( 'Unknown geolocation provider.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		$instance = $registry->get( $provider );
		$auth     = $instance->authMode();
		if ( ( 'IP Location Block' === $provider || ProviderRegistry::AUTH_REQUIRED === $auth ) && '' === $credential ) {
			return new \WP_Error(
				'ip_location_block_missing_provider_key',
				__( 'Enter an API key before testing this provider.', 'ip-location-block' ),
				array( 'status' => 400 )
			);
		}

		// Inject into a COPY (PHP arrays copy on write — the caller's settings
		// are never mutated).
		$probe = $settings;
		if ( ! isset( $probe['providers'] ) || ! is_array( $probe['providers'] ) ) {
			$probe['providers'] = array();
		}
		$probe['providers'][ $provider ] = '' !== $credential ? $credential : '@';

		$context = new LookupContext( $probe, $httpArgs, true );

		try {
			$result = $instance->lookup( self::PROBE_IP, $context );
		} catch ( \Throwable $e ) {
			$result = \IPLocationBlock\Geolocation\LocationResult::error( $e->getMessage() );
		}

		$country = null !== $result->countryCode ? strtoupper( $result->countryCode ) : '';
		if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
			$message = ( null !== $result->errorMessage && '' !== $result->errorMessage )
				? $result->errorMessage
				: __( 'The provider did not return a valid country result.', 'ip-location-block' );

			return array(
				'provider' => $provider,
				'ok'       => false,
				'message'  => $message,
			);
		}

		$quota = null;
		if ( 'IP Location Block' === $provider ) {
			$quota = $this->quota->status( $credential, true );
			if ( $this->quota->isBlocking( $quota ) ) {
				return array(
					'provider'    => $provider,
					'ok'          => false,
					'countryCode' => $country,
					'message'     => $quota['message'],
					'quota'       => $quota,
				);
			}
		}

		$verified = $this->rememberVerification( $provider, '' !== $credential ? $credential : '@' );

		return array(
			'provider'    => $provider,
			'ok'          => true,
			'countryCode' => $country,
			'verifiedAt'  => (int) $verified['verifiedAt'],
			'quota'       => $quota,
		);
	}

	/**
	 * Persist successful verification metadata keyed by credential fingerprint.
	 *
	 * @return array<string,mixed>
	 */
	public function rememberVerification( string $provider, string $credential ): array {
		$fingerprint = CredentialFingerprint::of( $provider, $credential );
		$verified    = array(
			'provider'   => $provider,
			'verifiedAt' => time(),
		);

		set_transient( 'ip_location_block_verify_' . substr( $fingerprint, 0, 40 ), $verified, DAY_IN_SECONDS );

		return $verified;
	}

	/**
	 * Read a successful verification for the exact credential.
	 *
	 * @return array<string,mixed>|false
	 */
	public static function getVerification( string $provider, string $credential ) {
		if ( '' === $credential || '@' === $credential ) {
			return false;
		}

		$fingerprint = CredentialFingerprint::of( $provider, $credential );
		$verified    = get_transient( 'ip_location_block_verify_' . substr( $fingerprint, 0, 40 ) );

		return is_array( $verified ) ? $verified : false;
	}
}
