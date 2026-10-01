<?php
/**
 * Native provider — api.iplocationblock.net (overridable, see apiBase()).
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
 * The URL and transform map must match the native API wire contract exactly.
 */
final class NativeProvider extends AbstractRemoteProvider implements PrecisionLocationSource {

	public const ID = 'IP Location Block';

	/**
	 * Default API base URL (no trailing slash). Define IP_LOCATION_BLOCK_API_BASE
	 * (e.g. in wp-config.php) to point lookups and quota checks elsewhere.
	 */
	public const DEFAULT_API_BASE = 'https://api.iplocationblock.net';

	/** Quota endpoint on the default API base; requests use quotaEndpoint(). */
	public const QUOTA_ENDPOINT = self::DEFAULT_API_BASE . '/quota/';

	/** Account dashboard. */
	public const ACCOUNT_URL = 'https://app.iplocationblock.net/login';

	/** Upgrade / pricing (quota UI). */
	public const UPGRADE_URL = 'https://iplocationblock.net/pricing/?utm_source=wordpress&utm_medium=site&utm_campaign=cloud';

	/** Registry sign-up link. */
	public const PRICING_URL = 'https://iplocationblock.net/pricing';

	/**
	 * The API base URL in effect: IP_LOCATION_BLOCK_API_BASE when it holds an
	 * absolute http(s) URL, otherwise DEFAULT_API_BASE.
	 */
	public static function apiBase(): string {
		$override = defined( 'IP_LOCATION_BLOCK_API_BASE' ) ? constant( 'IP_LOCATION_BLOCK_API_BASE' ) : null;

		return self::normalizeApiBase( $override ) ?? self::DEFAULT_API_BASE;
	}

	/**
	 * Validate an API base override. Returns it without a trailing slash, or
	 * null unless it is an absolute http(s) URL with no query or fragment.
	 *
	 * @param mixed $value
	 */
	public static function normalizeApiBase( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$base  = rtrim( trim( $value ), '/' );
		$parts = parse_url( $base );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return null;
		}

		$scheme = strtolower( $parts['scheme'] ?? '' );

		return 'https' === $scheme || 'http' === $scheme ? $base : null;
	}

	/** Quota service endpoint (per-key balance). */
	public static function quotaEndpoint(): string {
		return self::apiBase() . '/quota/';
	}

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
		return self::apiBase() . '/v1/%API_IP%?api_key=%API_KEY%';
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
