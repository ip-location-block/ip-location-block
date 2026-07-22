<?php
/**
 * IPInfoDB remote provider.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers\Remote;

use IPLocationBlock\Geolocation\LocationResult;
use IPLocationBlock\Providers\AbstractRemoteProvider;
use IPLocationBlock\Providers\Capability;
use IPLocationBlock\Providers\LookupContext;
use IPLocationBlock\Providers\ProviderRegistry;

/**
 * Uses the `ip-city` endpoint by default; lookupCountry() switches to the
 * lighter `ip-country` endpoint.
 */
final class IpInfoDbProvider extends AbstractRemoteProvider {

	public function id(): string {
		return 'IPInfoDB';
	}

	public function typeLabel(): string {
		return 'IPv4, IPv6 / free';
	}

	public function link(): ?string {
		return 'https://ipinfodb.com/';
	}

	public function capabilities(): int {
		return Capability::IPV4 | Capability::IPV6;
	}

	public function authMode(): int {
		return ProviderRegistry::AUTH_REQUIRED;
	}

	public function requestQuota(): ?array {
		return array( 'total' => -1, 'term' => 'month' );
	}

	public function limits(): ?array {
		return array( 'Up to 2 requests / second' );
	}

	protected function urlTemplate(): string {
		return 'https://api.ipinfodb.com/v3/%API_OPTION%/?key=%API_KEY%&format=%API_FORMAT%&ip=%API_IP%';
	}

	protected function apiDefaults(): array {
		return array(
			'%API_FORMAT%' => 'xml',
			'%API_OPTION%' => 'ip-city',
		);
	}

	protected function transformMap(): array {
		return array(
			'countryCode' => 'countryCode',
			'countryName' => 'countryName',
			'regionName'  => 'regionName',
			'cityName'    => 'cityName',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
		);
	}

	/**
	 * Lighter ip-country endpoint.
	 */
	public function lookupCountry( string $ip, LookupContext $context ): LocationResult {
		$api                 = $this->apiDefaults();
		$api['%API_OPTION%'] = 'ip-country';

		return $this->wrap( $this->fetch( $ip, $context, $api ) );
	}
}
