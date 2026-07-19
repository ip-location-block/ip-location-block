<?php
/**
 * Sealed provider registry — the monetization core.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

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
	 * Look up a provider by exact id (legacy get_provider is case-sensitive).
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
	 * Configurable selection list: locals first, then non-locals (reproduces the
	 * legacy get_providers('key', …, $all = true) ordering).
	 *
	 * @return ProviderInterface[]
	 */
	public function selectionList(): array {
		return array_merge( $this->localProviders(), $this->remoteProviders() );
	}

	/**
	 * Ids of local providers that are addon-download candidates (mirrors the
	 * legacy IP_Location_Block_Provider::get_addons).
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
	 * Ids of the providers active for a lookup — the legacy get_valid_providers
	 * selection rule WITHOUT the synthetic 'Cache' entry:
	 *   truthy stored key  OR  (unset AND implicitly enabled).
	 * restrict_api limits candidates to local providers; locals keep their fixed
	 * order and remotes are shuffled only when requested.
	 *
	 * @param array<string,mixed> $settings
	 *
	 * @return string[]
	 */
	public function activeProviderIds( array $settings, bool $shuffle = false, bool $ignoreRestrictApi = false ): array {
		$include_remotes = $ignoreRestrictApi || empty( $settings['restrict_api'] );

		$candidates = $this->localProviders();
		if ( $include_remotes ) {
			$remotes = $this->remoteProviders();
			if ( $shuffle ) {
				shuffle( $remotes );
			}
			$candidates = array_merge( $candidates, $remotes );
		}

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
	public function activeProviders( array $settings, bool $shuffle = false, bool $ignoreRestrictApi = false ): array {
		$out = array();
		foreach ( $this->activeProviderIds( $settings, $shuffle, $ignoreRestrictApi ) as $id ) {
			$provider = $this->get( $id );
			if ( $provider ) {
				$out[] = $provider;
			}
		}

		return $out;
	}

	/**
	 * Whether the native provider is the ONLY active provider (drives the
	 * "Native" precision mode). Mirrors the legacy is_native().
	 *
	 * @param array<string,mixed> $settings
	 */
	public function isNativeOnly( array $settings ): bool {
		$ids = $this->activeProviderIds( $settings, true, false );

		return count( $ids ) === 1 && NativeProvider::ID === $ids[0];
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
