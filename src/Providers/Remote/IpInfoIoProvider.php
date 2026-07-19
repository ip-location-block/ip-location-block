<?php
/**
 * ipinfo.io remote provider.
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
use IPLocationBlock\Support\Util;

/**
 * mapResult() splits the combined `loc` field into lat/long and parses the
 * `org` field into an ASN.
 *
 * Note: %API_FORMAT%/%API_OPTION% do not appear in the URL template, so
 * overriding lookupCountry() to set them would be a no-op; the default
 * lookupCountry() (same endpoint as lookup()) is used instead.
 */
final class IpInfoIoProvider extends AbstractRemoteProvider {

	public function id(): string {
		return 'ipinfo.io';
	}

	public function typeLabel(): string {
		return 'IPv4, IPv6 / free for non-commercial use';
	}

	public function link(): ?string {
		return 'https://ipinfo.io/pricing';
	}

	public function capabilities(): int {
		return Capability::IPV4 | Capability::IPV6;
	}

	public function authMode(): int {
		return ProviderRegistry::AUTH_REQUIRED;
	}

	public function requestQuota(): ?array {
		return array( 'total' => 50000, 'term' => 'month' );
	}

	protected function urlTemplate(): string {
		return 'https://ipinfo.io/%API_IP%?token=%API_KEY%';
	}

	protected function transformMap(): array {
		return array(
			'countryCode' => 'country',
			'countryName' => 'country',
			'regionName'  => 'region',
			'cityName'    => 'city',
			'latitude'    => 'loc',
			'longitude'   => 'loc',
			'asn'         => 'org',
		);
	}

	/**
	 * @param array<string,mixed> $raw
	 */
	protected function mapResult( array $raw ): LocationResult {
		$lat = $raw['latitude'] ?? null;
		$lng = $raw['longitude'] ?? null;

		if ( ! empty( $lat ) ) {
			$loc = explode( ',', (string) $lat );
			if ( count( $loc ) === 2 ) {
				$lat = $loc[0];
				$lng = $loc[1];
			}
		}

		$asn = $raw['asn'] ?? null;
		if ( ! empty( $asn ) ) {
			$asn = Util::parse_asn( (string) $asn );
		}

		return new LocationResult(
			countryCode: $raw['countryCode'] ?? null, // already normalized in fetch()
			countryName: $raw['countryName'] ?? null,
			regionName: $raw['regionName'] ?? null,
			cityName: $raw['cityName'] ?? null,
			latitude: ( null === $lat || '' === $lat ) ? null : $lat,
			longitude: ( null === $lng || '' === $lng ) ? null : $lng,
			asn: ( null === $asn || '' === $asn ) ? null : (string) $asn,
		);
	}
}
