<?php
/**
 * ipstack remote provider.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers\Remote;

use IPLocationBlock\Providers\AbstractRemoteProvider;
use IPLocationBlock\Providers\Capability;
use IPLocationBlock\Providers\ProviderRegistry;

/**
 * No response reshaping — uses the base class's plain transform.
 */
final class IpStackProvider extends AbstractRemoteProvider {

	public function id(): string {
		return 'ipstack';
	}

	public function typeLabel(): string {
		return 'IPv4, IPv6 / free for non-commercial use';
	}

	public function link(): ?string {
		return 'https://ipstack.com/';
	}

	public function capabilities(): int {
		return Capability::IPV4 | Capability::IPV6;
	}

	public function authMode(): int {
		return ProviderRegistry::AUTH_REQUIRED;
	}

	public function requestQuota(): ?array {
		return array( 'total' => 100, 'term' => 'month' );
	}

	protected function urlTemplate(): string {
		return 'http://api.ipstack.com/%API_IP%?access_key=%API_KEY%&output=%API_FORMAT%';
	}

	protected function apiDefaults(): array {
		return array( '%API_FORMAT%' => 'json' );
	}

	protected function transformMap(): array {
		return array(
			'countryCode' => 'country_code',
			'countryName' => 'country_name',
			'regionName'  => 'region_name',
			'cityName'    => 'city',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
		);
	}
}
