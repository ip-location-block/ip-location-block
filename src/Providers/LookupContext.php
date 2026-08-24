<?php
/**
 * Per-lookup context.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

/**
 * Immutable per-call context handed to every provider lookup().
 *
 * `httpArgs` is already filtered by the `ip-location-block-headers` filter
 * upstream (see IP_Location_Block::get_request_headers) and must NOT contain
 * the `fresh`/`asn` control keys — those are lifted into typed flags here.
 */
final class LookupContext {

	/**
	 * @param array<string,mixed> $settings Full settings snapshot.
	 * @param array<string,mixed> $httpArgs wp_remote_get() args (no fresh/asn).
	 * @param bool                $fresh    Bypass the short-term response cache.
	 * @param bool                $asnPass  Second-pass ASN-database lookup.
	 */
	public function __construct(
		public readonly array $settings,
		private readonly array $httpArgs,
		public readonly bool $fresh = false,
		public readonly bool $asnPass = false,
	) {}

	/**
	 * Build a context from a legacy `$args` array (may carry fresh/asn), lifting
	 * the control keys into flags and leaving the rest as HTTP args.
	 *
	 * @param array<string,mixed> $settings
	 * @param array<string,mixed> $args
	 */
	public static function fromLegacyArgs( array $settings, array $args ): self {
		$fresh   = ! empty( $args['fresh'] );
		$asnPass = ! empty( $args['asn'] );
		unset( $args['fresh'], $args['asn'] );

		return new self( $settings, $args, $fresh, $asnPass );
	}

	/**
	 * The wp_remote_get() argument array.
	 *
	 * @return array<string,mixed>
	 */
	public function httpArgs(): array {
		return $this->httpArgs;
	}

	/**
	 * Return a copy flagged for the ASN-database second pass.
	 */
	public function withAsnPass(): self {
		return new self( $this->settings, $this->httpArgs, $this->fresh, true );
	}

	/**
	 * Return a copy that bypasses the short-term response cache.
	 */
	public function withFresh( bool $fresh = true ): self {
		return new self( $this->settings, $this->httpArgs, $fresh, $this->asnPass );
	}

	/**
	 * Resolve the stored credential for a provider id, reproducing the legacy
	 * IP_Location_Block_API::get_api_key() semantics: case-insensitive lookup in
	 * settings['providers'], empty/falsy stored value collapses to null, any
	 * other value (including the '@' selected-without-key sentinel) passes
	 * through untouched.
	 */
	public function credentialFor( string $id ): ?string {
		$providers = $this->settings['providers'] ?? null;
		if ( '' === $id || ! is_array( $providers ) || array() === $providers ) {
			return null;
		}

		$lower = array_change_key_case( $providers, CASE_LOWER );
		$key   = strtolower( $id );

		return empty( $lower[ $key ] ) ? null : (string) $lower[ $key ];
	}
}
