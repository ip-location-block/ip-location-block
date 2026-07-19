<?php
/**
 * Geolocation resolver — cache-first read, provider loop, THE precision gate.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Geolocation;

use IPLocationBlock\Providers\Capability;
use IPLocationBlock\Providers\LookupContext;
use IPLocationBlock\Providers\PrecisionLocationSource;
use IPLocationBlock\Providers\ProviderInterface;
use IPLocationBlock\Support\Util;

/**
 * Provider loop for a single geolocation lookup: cache-first read, per-provider
 * lookup with the precision gate, and ASN enrichment.
 *
 * Returns the legacy-shaped intermediate array
 * (`time / provider / asn / code / city / state`) that the Validator wraps in
 * make_validation(); or the private/empty short-circuit, or the not-found
 * envelope.
 */
final class GeolocationResolver {

	public function __construct(
		private readonly IpCacheRepository $cache = new IpCacheRepository(),
	) {}

	/**
	 * @param ProviderInterface[]  $providers Active providers, in order.
	 * @param array<string,mixed>  $settings
	 *
	 * @return array<string,mixed>
	 */
	public function resolve( string $ip, array $settings, array $providers, LookupContext $context, bool $useCache = true ): array {
		$started = microtime( true );

		// Loop back / private address. Private IPs short-circuit before the
		// cache is consulted.
		if ( Util::is_private_ip( $ip ) ) {
			return array( 'time' => 0, 'provider' => 'Private', 'code' => 'XX' );
		}

		// Cache-first read; emits provider => 'Cache', the value logs and
		// statistics expect. Replays previously-stored — hence
		// native-resolved — city/state. This read precedes the empty-provider
		// check: a cache hit must replay even when every real provider is
		// disabled.
		if ( $useCache && ! empty( $settings['cache_hold'] ) ) {
			$hit = $this->cache->find( $ip );
			if ( $hit ) {
				return array(
					'time'     => microtime( true ) - $started,
					'provider' => 'Cache',
					'asn'      => isset( $hit['asn'] ) && '' !== $hit['asn'] ? $hit['asn'] : null,
					'code'     => isset( $hit['code'] ) ? $hit['code'] : null,
					'city'     => isset( $hit['city'] ) && '' !== $hit['city'] ? $hit['city'] : null,
					'state'    => isset( $hit['state'] ) && '' !== $hit['state'] ? $hit['state'] : null,
				);
			}
		}

		// Empty provider list (after a cache miss).
		if ( count( $providers ) < 1 ) {
			return array( 'time' => 0, 'provider' => 'Private', 'code' => 'XX' );
		}

		foreach ( $providers as $provider ) {
			$time   = microtime( true );
			$result = $provider->lookup( $ip, $context );

			if ( ! $result->isUsable() ) {
				continue; // first non-empty countryCode wins
			}

			// ============================ THE PRECISION GATE ====================
			// city/state/region stripped for every provider that is not the sole
			// PrecisionLocationSource (NativeProvider). The gate checks
			// `instanceof`, never a capability bit.
			if ( ! $provider instanceof PrecisionLocationSource ) {
				$result = $result->withoutPrecision();
			}
			// ====================================================================

			$asn = null;
			if ( ! empty( $settings['use_asn'] ) ) {
				$caps = $provider->capabilities();
				if ( ( $caps & ( Capability::ASN | Capability::ASN_DATABASE ) ) && null !== $result->asn ) {
					$asn = $result->asn;
				}
				if ( empty( $asn ) && ( $caps & Capability::ASN ) ) {
					$second = $provider->lookup( $ip, $context->withAsnPass() );
					$asn    = $second->asn;
				}
			}

			return array(
				'time'     => microtime( true ) - $time,
				'provider' => $provider->id(),
				'asn'      => $asn,
				'code'     => $result->countryCode,
				'city'     => $result->cityName,
				'state'    => $result->stateName,
			);
		}

		return array( 'errorMessage' => 'unknown' );
	}
}
