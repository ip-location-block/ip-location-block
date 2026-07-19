<?php
/**
 * Native provider — api.iplocationblock.com.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

/**
 * The native geolocation provider and the ONLY {@see PrecisionLocationSource}.
 *
 * `final` and the sole implementer of the precision marker: it is the only
 * provider whose city/state precision survives GeolocationResolver's gate. It
 * also owns every monetization URL as a constant.
 *
 * URL + transform are verbatim from the legacy
 * IP_Location_Block_API_iplocationblock class.
 */
final class NativeProvider extends AbstractRemoteProvider implements PrecisionLocationSource {

	public const ID = 'IP Location Block';

	/** Quota service endpoint (per-key balance). */
	public const QUOTA_ENDPOINT = 'https://api.iplocationblock.com/quota/';

	/** Account dashboard. */
	public const ACCOUNT_URL = 'https://app.iplocationblock.com/login';

	/** Upgrade / pricing (quota UI). */
	public const UPGRADE_URL = 'https://iplocationblock.com/pricing/?utm_source=wordpress&utm_medium=site&utm_campaign=cloud';

	/** Registry sign-up link. */
	public const PRICING_URL = 'https://iplocationblock.com/pricing';

	public function id(): string {
		return self::ID;
	}

	public function typeLabel(): string {
		return 'IPv4, IPv6 / free for non-commercial use';
	}

	public function link(): ?string {
		return self::PRICING_URL;
	}

	public function capabilities(): int {
		return Capability::IPV4 | Capability::IPV6 | Capability::ASN | Capability::CITY | Capability::STATE;
	}

	public function authMode(): int {
		return ProviderRegistry::AUTH_OPTIONAL;
	}

	public function requestQuota(): ?array {
		return array( 'total' => 15000, 'term' => 'month' );
	}

	protected function urlTemplate(): string {
		return 'https://api.iplocationblock.com/v1/%API_IP%?api_key=%API_KEY%';
	}

	protected function transformMap(): array {
		return array(
			'countryCode' => 'country_code',
			'countryName' => 'country_name',
			'regionName'  => 'region',
			'cityName'    => 'city',
			'stateName'   => 'region',
			'latitude'    => 'latitude',
			'longitude'   => 'longitude',
			'asn'         => 'asn_number',
		);
	}
}
