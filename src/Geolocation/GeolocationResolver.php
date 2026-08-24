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
use IPLocationBlock\Providers\ProviderRegistry;
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
		$started           = microtime( true );
		$precision_capable = $this->hasPrecisionProvider( $providers );

		// Loop back / private address. Private IPs short-circuit before the
		// cache is consulted.
		if ( Util::is_private_ip( $ip ) ) {
			return array( 'time' => 0, 'provider' => 'Private', 'code' => 'XX' );
		}

		// Cache-first read; emits provider => 'Cache', the value logs and
		// statistics expect. Country and ASN may replay under any provider
		// selection. Previously stored city/state replay only while a precision
		// provider is active, so an old native cache row cannot keep regional
		// rules running after a switch. This read precedes the empty-provider
		// check because cached country data remains useful during an outage.
		//
		// Self-heal: a row cached BEFORE precision was enabled carries empty
		// city/state and would replay forever. When the active lists hold a
		// precision rule and the native provider is the sole source, treat such a
		// row as a miss so the live provider loop below (gate included) refreshes
		// it.
		if ( $useCache && ! empty( $settings['cache_hold'] ) ) {
			$hit = $this->cache->find( $ip );
			if ( $hit && ! $this->shouldRefreshStalePrecision( $hit, $settings ) ) {
				return array(
					'time'     => microtime( true ) - $started,
					'provider' => 'Cache',
					'asn'      => isset( $hit['asn'] ) && '' !== $hit['asn'] ? $hit['asn'] : null,
					'code'     => isset( $hit['code'] ) ? $hit['code'] : null,
					'city'     => $precision_capable && isset( $hit['city'] ) && '' !== $hit['city'] ? $hit['city'] : null,
					'state'    => $precision_capable && isset( $hit['state'] ) && '' !== $hit['state'] ? $hit['state'] : null,
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

		// Every provider failed after the cache miss. Keep the error metadata for
		// diagnostics, but use the same non-blocking location code as the empty-
		// provider path. Protection stays configured and resumes automatically when
		// a provider recovers, without turning an outage into a site-wide lockout.
		return array( 'errorMessage' => 'unknown', 'code' => 'XX' );
	}

	/**
	 * Whether the current provider selection may use cached regional data.
	 *
	 * Cache rows can outlive a provider switch. A row created by the native
	 * provider may contain state/region data, but that precision must not keep
	 * running after the user switches to a country-only provider.
	 *
	 * @param array<int,ProviderInterface> $providers
	 */
	private function hasPrecisionProvider( array $providers ): bool {
		foreach ( $providers as $provider ) {
			if ( $provider instanceof PrecisionLocationSource ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a cache hit is a stale pre-precision row that a live lookup should
	 * refresh instead of replaying.
	 *
	 * True only when ALL of: the row carries no precision data (empty city AND
	 * state); the active lists contain a precision rule; and the native provider
	 * is either the sole active source OR native-enforced (mixed providers with a
	 * real key + precision rules, where native is prioritized to index 0). That
	 * guard prevents repeated futile refreshes under provider selections that can
	 * never return city/state.
	 *
	 * @param array<string,mixed> $hit
	 * @param array<string,mixed> $settings
	 */
	private function shouldRefreshStalePrecision( array $hit, array $settings ): bool {
		$city  = isset( $hit['city'] ) ? (string) $hit['city'] : '';
		$state = isset( $hit['state'] ) ? (string) $hit['state'] : '';
		if ( '' !== $city || '' !== $state ) {
			return false;
		}

		if ( ! $this->activeListsHavePrecisionRule( $settings ) ) {
			return false;
		}

		$registry = ProviderRegistry::instance();

		return $registry->isNativeOnly( $settings ) || $registry->isNativeEnforced( $settings );
	}

	/**
	 * Cheap check: do any of the active country lists (globals + public) contain
	 * a precision (":"-bearing) entry?
	 *
	 * @param array<string,mixed> $settings
	 */
	private function activeListsHavePrecisionRule( array $settings ): bool {
		$lists = array();
		foreach ( array( 'white_list', 'black_list' ) as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				$lists[] = (string) $settings[ $key ];
			}
		}
		if ( ! empty( $settings['public'] ) && is_array( $settings['public'] ) ) {
			foreach ( array( 'white_list', 'black_list' ) as $key ) {
				if ( ! empty( $settings['public'][ $key ] ) ) {
					$lists[] = (string) $settings['public'][ $key ];
				}
			}
		}
		foreach ( $lists as $list ) {
			if ( false !== strpos( $list, ':' ) ) {
				return true;
			}
		}

		return false;
	}
}
