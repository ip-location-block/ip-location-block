<?php
/**
 * Immutable geolocation lookup result.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Geolocation;

/**
 * Immutable value object returned by every provider's lookup().
 *
 * Carries the legacy transform keys. A "rejected" result models the legacy
 * `false` return of fetch_provider (IP family the provider cannot answer);
 * the compat adapter maps it back to `false`.
 */
final class LocationResult {

	public function __construct(
		public readonly ?string $countryCode = null,
		public readonly ?string $countryName = null,
		public readonly ?string $regionName = null,
		public readonly ?string $cityName = null,
		public readonly ?string $stateName = null,
		public readonly int|float|string|null $latitude = null,
		public readonly int|float|string|null $longitude = null,
		public readonly ?string $asn = null,
		public readonly ?string $errorMessage = null,
		private readonly bool $rejected = false,
	) {}

	/**
	 * Build from a legacy transform-shaped array. Empty strings collapse to
	 * null so isUsable()/toLegacyArray() behave like the legacy engine.
	 *
	 * @param array<string,mixed> $data
	 */
	public static function fromArray( array $data ): self {
		if ( isset( $data['errorMessage'] ) && '' !== $data['errorMessage'] ) {
			return self::error( (string) $data['errorMessage'] );
		}

		$string = static function ( $value ): ?string {
			if ( null === $value ) {
				return null;
			}
			$value = is_string( $value ) ? $value : (string) $value;

			return '' === $value ? null : $value;
		};

		$scalar = static function ( $value ) {
			if ( null === $value || '' === $value ) {
				return null;
			}

			return $value;
		};

		return new self(
			countryCode: $string( $data['countryCode'] ?? null ),
			countryName: $string( $data['countryName'] ?? null ),
			regionName: $string( $data['regionName'] ?? null ),
			cityName: $string( $data['cityName'] ?? null ),
			stateName: $string( $data['stateName'] ?? null ),
			latitude: $scalar( $data['latitude'] ?? null ),
			longitude: $scalar( $data['longitude'] ?? null ),
			asn: $string( $data['asn'] ?? null ),
		);
	}

	/**
	 * Error result (carries only errorMessage).
	 */
	public static function error( string $message ): self {
		return new self( errorMessage: $message );
	}

	/**
	 * Rejected result — the provider cannot answer this IP family. The legacy
	 * engine returned bare `false` here; the compat adapter restores that.
	 */
	public static function rejected(): self {
		return new self( rejected: true );
	}

	public function isRejected(): bool {
		return $this->rejected;
	}

	/**
	 * Normalize a raw country code the way legacy fetch_provider did:
	 * keep the leading two uppercase letters, otherwise null.
	 */
	public static function normalizeCountryCode( mixed $code ): ?string {
		if ( ! is_string( $code ) ) {
			return null;
		}

		return preg_match( '/^[A-Z]{2}/', $code, $m ) ? $m[0] : null;
	}

	/**
	 * Usable == a valid 2-letter country code and no error. Mirrors the legacy
	 * gate `! empty( countryCode ) && empty( errorMessage )` (codes are already
	 * normalized to two uppercase letters or null upstream).
	 */
	public function isUsable(): bool {
		return null === $this->errorMessage
			&& null !== $this->countryCode
			&& 1 === preg_match( '/^[A-Z]{2}$/', $this->countryCode );
	}

	/**
	 * Strip region/city/state precision — THE monetization gate's mutation for
	 * every non-native provider result. Country + ASN are preserved.
	 */
	public function withoutPrecision(): self {
		return new self(
			countryCode: $this->countryCode,
			countryName: $this->countryName,
			regionName: null,
			cityName: null,
			stateName: null,
			latitude: $this->latitude,
			longitude: $this->longitude,
			asn: $this->asn,
			errorMessage: $this->errorMessage,
			rejected: $this->rejected,
		);
	}

	/**
	 * Return a copy with the ASN set.
	 */
	public function withAsn( ?string $asn ): self {
		return new self(
			countryCode: $this->countryCode,
			countryName: $this->countryName,
			regionName: $this->regionName,
			cityName: $this->cityName,
			stateName: $this->stateName,
			latitude: $this->latitude,
			longitude: $this->longitude,
			asn: $asn,
			errorMessage: $this->errorMessage,
			rejected: $this->rejected,
		);
	}

	/**
	 * Render back to the legacy get_location() array shape: only non-empty keys
	 * in the canonical transform order, or `[ 'errorMessage' => ... ]`.
	 *
	 * @return array<string,mixed>
	 */
	public function toLegacyArray(): array {
		if ( null !== $this->errorMessage ) {
			return array( 'errorMessage' => $this->errorMessage );
		}

		$out = array();
		if ( null !== $this->countryCode ) {
			$out['countryCode'] = $this->countryCode;
		}
		if ( null !== $this->countryName ) {
			$out['countryName'] = $this->countryName;
		}
		if ( null !== $this->regionName ) {
			$out['regionName'] = $this->regionName;
		}
		if ( null !== $this->cityName ) {
			$out['cityName'] = $this->cityName;
		}
		if ( null !== $this->stateName ) {
			$out['stateName'] = $this->stateName;
		}
		if ( null !== $this->latitude ) {
			$out['latitude'] = $this->latitude;
		}
		if ( null !== $this->longitude ) {
			$out['longitude'] = $this->longitude;
		}
		if ( null !== $this->asn ) {
			$out['asn'] = $this->asn;
		}

		return $out;
	}
}
