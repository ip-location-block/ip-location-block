<?php
/**
 * ipapi remote provider.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers\Remote;

use IPLocationBlock\Geolocation\LocationResult;
use IPLocationBlock\Providers\AbstractRemoteProvider;
use IPLocationBlock\Providers\Capability;
use IPLocationBlock\Providers\ProviderRegistry;

/**
 * Ports IP_Location_Block_API_ipapi. The legacy get_location() override
 * re-escaped the four scalar fields on success and, on failure, surfaced the
 * provider `error.info` message — reproduced verbatim in mapResult().
 */
final class IpApiProvider extends AbstractRemoteProvider {

	public function id(): string {
		return 'ipapi';
	}

	public function typeLabel(): string {
		return 'IPv4, IPv6 / free for non-commercial use';
	}

	public function link(): ?string {
		return 'https://ipapi.com/';
	}

	public function capabilities(): int {
		return Capability::IPV4 | Capability::IPV6;
	}

	public function authMode(): int {
		return ProviderRegistry::AUTH_REQUIRED;
	}

	public function requestQuota(): ?array {
		return array( 'total' => 1000, 'term' => 'month' );
	}

	protected function urlTemplate(): string {
		return 'http://api.ipapi.com/%API_IP%?access_key=%API_KEY%';
	}

	protected function transformMap(): array {
		return array(
			'countryCode' => 'country_code',
			'countryName' => 'country_name',
			'cityName'    => 'city',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
			'error'       => 'error',
		);
	}

	/**
	 * @param array<string,mixed> $raw
	 */
	protected function mapResult( array $raw ): LocationResult {
		if ( ! isset( $raw['countryName'] ) ) {
			$info = isset( $raw['error']['info'] ) ? $raw['error']['info'] : '';

			return LocationResult::error( esc_html( (string) $info ) );
		}

		$esc = static function ( $value ): ?string {
			if ( null === $value ) {
				return null;
			}
			$value = esc_html( (string) $value );

			return '' === $value ? null : $value;
		};

		return new LocationResult(
			countryCode: $esc( $raw['countryCode'] ?? null ),
			countryName: $esc( $raw['countryName'] ),
			cityName: isset( $raw['cityName'] ) ? (string) $raw['cityName'] : null,
			latitude: $esc( $raw['latitude'] ?? null ),
			longitude: $esc( $raw['longitude'] ?? null ),
		);
	}
}
