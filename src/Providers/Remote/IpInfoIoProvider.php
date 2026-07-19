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
 * Ports IP_Location_Block_API_ipinfoio. The legacy get_location() override
 * split the combined `loc` field into lat/long and parsed the `org` field into
 * an ASN — reproduced verbatim in mapResult().
 *
 * Note: the legacy get_country() override set %API_FORMAT%/%API_OPTION% that the
 * URL template does not contain, so it was a no-op (the normal endpoint was
 * always used). We therefore keep the default lookupCountry().
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
