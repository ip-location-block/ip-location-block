<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Fakes;

use IPLocationBlock\Geolocation\LocationResult;
use IPLocationBlock\Providers\Capability;
use IPLocationBlock\Providers\LookupContext;
use IPLocationBlock\Providers\ProviderInterface;
use IPLocationBlock\Providers\ProviderRegistry;

/**
 * Deterministic ProviderInterface test double. Returns a canned LocationResult
 * (and an optional separate result for the ASN second pass) without any network
 * access, so GeolocationResolver behaviour can be asserted precisely.
 *
 * This class does NOT implement PrecisionLocationSource — see
 * {@see FakePrecisionProvider} for the precision-source variant.
 */
class FakeProvider implements ProviderInterface {

	public int $lookupCalls = 0;

	public function __construct(
		private string $id,
		private LocationResult $result,
		private int $capabilities = Capability::IPV4 | Capability::IPV6,
		private ?LocationResult $asnResult = null,
		private bool $local = false,
		private bool $implicit = false,
	) {}

	public function id(): string {
		return $this->id;
	}

	public function typeLabel(): string {
		return 'fake';
	}

	public function link(): ?string {
		return null;
	}

	public function capabilities(): int {
		return $this->capabilities;
	}

	public function legacySupports(): array {
		return Capability::toLegacyList( $this->capabilities );
	}

	public function authMode(): int {
		return ProviderRegistry::AUTH_NOT_REQUIRED;
	}

	public function isLocal(): bool {
		return $this->local;
	}

	public function implicitlyEnabled(): bool {
		return $this->implicit;
	}

	public function requestQuota(): ?array {
		return null;
	}

	public function limits(): ?array {
		return null;
	}

	public function lookup( string $ip, LookupContext $context ): LocationResult {
		++$this->lookupCalls;

		if ( $context->asnPass && null !== $this->asnResult ) {
			return $this->asnResult;
		}

		return $this->result;
	}

	public function lookupCountry( string $ip, LookupContext $context ): LocationResult {
		return $this->lookup( $ip, $context );
	}
}
