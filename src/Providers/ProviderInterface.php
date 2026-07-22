<?php
/**
 * Geolocation provider contract.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

use IPLocationBlock\Geolocation\LocationResult;

/**
 * Contract implemented by every registry provider.
 */
interface ProviderInterface {

	/**
	 * Registry id / display name (e.g. 'IP Location Block', 'IP2Location').
	 */
	public function id(): string;

	/**
	 * Human-readable `type` metadata string (licensing + families).
	 */
	public function typeLabel(): string;

	/**
	 * Sign-up / info link, or null when there is none.
	 */
	public function link(): ?string;

	/**
	 * Capability bitmask ({@see Capability}).
	 */
	public function capabilities(): int;

	/**
	 * Legacy `supports` string list derived from the capability bitmask.
	 *
	 * @return string[]
	 */
	public function legacySupports(): array;

	/**
	 * Authentication mode ({@see ProviderRegistry}::AUTH_*).
	 */
	public function authMode(): int;

	/**
	 * Whether the provider resolves from a bundled local database.
	 */
	public function isLocal(): bool;

	/**
	 * Whether the provider is selected by default when the settings map has no
	 * explicit entry for it.
	 */
	public function implicitlyEnabled(): bool;

	/**
	 * Request allowance metadata: `[ 'total' => int, 'term' => string ]`, or
	 * null when the provider has none.
	 *
	 * @return array{total:int,term:string}|null
	 */
	public function requestQuota(): ?array;

	/**
	 * Free-form limits metadata (array of strings), or null when unset.
	 *
	 * @return string[]|null
	 */
	public function limits(): ?array;

	/**
	 * Resolve geolocation for an IP.
	 */
	public function lookup( string $ip, LookupContext $context ): LocationResult;

	/**
	 * Resolve only the country code (some providers expose a lighter endpoint).
	 * Defaults to lookup().
	 */
	public function lookupCountry( string $ip, LookupContext $context ): LocationResult;
}
