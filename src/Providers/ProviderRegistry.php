<?php
/**
 * Sealed provider registry — the monetization core.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

use IPLocationBlock\Settings\PrecisionCacheGuard;

/**
 * The COMPLETE, CLOSED set of geolocation providers.
 *
 * `final`, singleton, private-const provider list, NO mutators, NO registration
 * method and NO filters. Nothing outside this class can add a provider — the
 * structural guarantee that only the native provider (the sole
 * {@see PrecisionLocationSource}) can ever deliver city/state precision.
 */
final class ProviderRegistry {

	public const AUTH_OPTIONAL     = 1;
	public const AUTH_REQUIRED     = 2;
	public const AUTH_NOT_REQUIRED = 3;

	/**
	 * The sealed provider list, in registry order (non-locals first, then
	 * locals). Compile-time constant; there is no way to extend it at runtime.
	 */
	private const PROVIDERS = array(
		NativeProvider::class,
		Remote\IpInfoDbProvider::class,
		Remote\IpInfoIoProvider::class,
		Remote\IpApiProvider::class,
		Remote\IpStackProvider::class,
		Local\Ip2LocationProvider::class,
		Local\GeoLite2Provider::class,
	);

	private static ?self $instance = null;

	/**
	 * @var array<string,ProviderInterface> id => provider (registry order).
	 */
	private array $providers = array();

	private function __construct() {
		foreach ( self::PROVIDERS as $class ) {
			/** @var ProviderInterface $provider */
			$provider                           = new $class();
			$this->providers[ $provider->id() ] = $provider;
		}
	}

	private function __clone() {}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Look up a provider by exact, case-sensitive id.
	 */
	public function get( string $id ): ?ProviderInterface {
		return $this->providers[ $id ] ?? null;
	}

	public function has( string $id ): bool {
		return isset( $this->providers[ $id ] );
	}

	/**
	 * Full catalog in registry order.
	 *
	 * @return array<string,ProviderInterface>
	 */
	public function catalog(): array {
		return $this->providers;
	}

	/**
	 * Local providers in fixed registry order.
	 *
	 * @return ProviderInterface[]
	 */
	public function localProviders(): array {
		return array_values(
			array_filter( $this->providers, static fn( ProviderInterface $p ): bool => $p->isLocal() )
		);
	}

	/**
	 * Non-local providers in registry order.
	 *
	 * @return ProviderInterface[]
	 */
	public function remoteProviders(): array {
		return array_values(
			array_filter( $this->providers, static fn( ProviderInterface $p ): bool => ! $p->isLocal() )
		);
	}

	/**
	 * Configurable selection list: locals first, then non-locals.
	 *
	 * @return ProviderInterface[]
	 */
	public function selectionList(): array {
		return array_merge( $this->localProviders(), $this->remoteProviders() );
	}

	/**
	 * Ids of local providers that are addon-download candidates.
	 *
	 * @param array<string,mixed> $providersMap settings['providers'].
	 *
	 * @return string[]
	 */
	public function localProviderIds( array $providersMap, bool $force = false ): array {
		$out = array();
		foreach ( $this->localProviders() as $p ) {
			$id = $p->id();
			if ( $force || ! isset( $providersMap[ $id ] ) || ! empty( $providersMap[ $id ] ) ) {
				$out[] = $id;
			}
		}

		return $out;
	}

	/**
	 * Ids of the providers active for a lookup, excluding the synthetic 'Cache'
	 * entry:
	 *   truthy stored key  OR  (unset AND implicitly enabled).
	 * Locals keep their fixed order and remotes are shuffled only when requested.
	 *
	 * Native-first enforcement (see {@see isNativeEnforced()}) is the last step:
	 * when precision rules exist and the native key is real, NativeProvider is
	 * moved ahead of the locals so its city/state precision fills the result and
	 * the IP cache — unless its cached quota is blocking. This is the choke point
	 * the live lookup consumes (via LegacyMeta::get_valid_providers), so the
	 * ordering must be applied here rather than only in {@see activeProviders()}.
	 *
	 * @param array<string,mixed> $settings
	 *
	 * @return string[]
	 */
	public function activeProviderIds( array $settings, bool $shuffle = false ): array {
		return $this->applyNativeEnforcement( $this->selectedProviderIds( $settings, $shuffle ), $settings );
	}

	/**
	 * The raw selected-provider id list, in registry order (locals first, remotes
	 * shuffled only when requested) — WITHOUT native-first enforcement. Callers
	 * that only need the selected SET/COUNT (e.g. {@see isNativeOnly()}) use this
	 * so they never trigger the enforcement quota read; enforcement never changes
	 * the set, only the order.
	 *
	 * @param array<string,mixed> $settings
	 *
	 * @return string[]
	 */
	private function selectedProviderIds( array $settings, bool $shuffle ): array {
		$remotes = $this->remoteProviders();
		if ( $shuffle ) {
			shuffle( $remotes );
		}
		$candidates = array_merge( $this->localProviders(), $remotes );

		$map = isset( $settings['providers'] ) && is_array( $settings['providers'] ) ? $settings['providers'] : array();
		$out = array();
		foreach ( $candidates as $p ) {
			$id = $p->id();
			if ( ! empty( $map[ $id ] ) || ( ! isset( $map[ $id ] ) && $p->implicitlyEnabled() ) ) {
				$out[] = $id;
			}
		}

		return $out;
	}

	/**
	 * Provider instances active for a lookup.
	 *
	 * @param array<string,mixed> $settings
	 *
	 * @return ProviderInterface[]
	 */
	public function activeProviders( array $settings, bool $shuffle = false ): array {
		$out = array();
		foreach ( $this->activeProviderIds( $settings, $shuffle ) as $id ) {
			$provider = $this->get( $id );
			if ( $provider ) {
				$out[] = $provider;
			}
		}

		return $out;
	}

	/**
	 * Whether the native provider is the ONLY active provider (drives the
	 * "Native" precision mode). Uses the raw selection: enforcement ordering never
	 * changes the active set, so this stays a pure set/count check.
	 *
	 * @param array<string,mixed> $settings
	 */
	public function isNativeOnly( array $settings ): bool {
		$ids = $this->selectedProviderIds( $settings, true );

		return count( $ids ) === 1 && NativeProvider::ID === $ids[0];
	}

	/**
	 * Whether native-first enforcement applies: precision (":"-bearing) rules
	 * exist AND the native provider is selected with a REAL key (not ''/'@').
	 *
	 * When true, the native provider is prioritized automatically and the other
	 * selected providers become country-level fallback — so mixed providers no
	 * longer silently break city/state precision. The precision-rule signal is
	 * the shared fingerprint used by PrecisionCacheGuard (one definition).
	 *
	 * @param array<string,mixed> $settings
	 */
	public function isNativeEnforced( array $settings ): bool {
		$map = isset( $settings['providers'] ) && is_array( $settings['providers'] ) ? $settings['providers'] : array();
		$key = isset( $map[ NativeProvider::ID ] ) ? (string) $map[ NativeProvider::ID ] : '';
		if ( '' === $key || '@' === $key ) {
			return false;
		}

		return array() !== PrecisionCacheGuard::fingerprint( $settings );
	}

	/**
	 * Promote NativeProvider to index 0 when enforcement applies.
	 *
	 * A no-op unless native coexists with at least one other active provider and
	 * is not already first. Quota fast-skip: when the native key's CACHED quota
	 * status is blocking (read-only — no HTTP refresh), the promotion is skipped
	 * so an exhausted key does not trigger a failed live API call per request;
	 * the normal order applies as fallback.
	 *
	 * @param string[]            $ids
	 * @param array<string,mixed> $settings
	 *
	 * @return string[]
	 */
	private function applyNativeEnforcement( array $ids, array $settings ): array {
		if ( count( $ids ) < 2 ) {
			return $ids;
		}

		$pos = array_search( NativeProvider::ID, $ids, true );
		if ( false === $pos || 0 === $pos ) {
			return $ids;
		}

		if ( ! $this->isNativeEnforced( $settings ) || self::nativeQuotaBlocked( $settings ) ) {
			return $ids;
		}

		unset( $ids[ $pos ] );
		array_unshift( $ids, NativeProvider::ID );

		return array_values( $ids );
	}

	/**
	 * Whether the native key's cached quota status blocks readiness. Read-only:
	 * it consults the existing transient/memo via NativeQuotaService WITHOUT ever
	 * triggering an HTTP refresh, so the registry path never makes a network call.
	 *
	 * @param array<string,mixed> $settings
	 */
	private static function nativeQuotaBlocked( array $settings ): bool {
		$map = isset( $settings['providers'] ) && is_array( $settings['providers'] ) ? $settings['providers'] : array();
		$key = isset( $map[ NativeProvider::ID ] ) ? (string) $map[ NativeProvider::ID ] : '';

		$status = ( new NativeQuotaService() )->cachedStatus( $key );

		return null !== $status && in_array( $status, NativeQuotaService::STATUS_BLOCKING, true );
	}

	/**
	 * Providers advertising any of the given capability bits, in registry order.
	 *
	 * @return ProviderInterface[]
	 */
	public function providersWithCapability( int $bits ): array {
		return array_values(
			array_filter(
				$this->providers,
				static fn( ProviderInterface $p ): bool => ( $p->capabilities() & $bits ) !== 0
			)
		);
	}
}
