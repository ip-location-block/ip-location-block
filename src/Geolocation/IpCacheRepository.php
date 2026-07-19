<?php
/**
 * IP address cache repository.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Geolocation;

use IPLocationBlock\Logging\Logs;

/**
 * Ports the IP_Location_Block_API_Cache logic. The IP cache is NO LONGER a
 * pseudo-provider — GeolocationResolver reads it cache-first and
 * IP_Location_Block::endof_validate writes to it after the precision gate, so a
 * cached city/state can only ever have been native-resolved.
 *
 * The static memcache is shared across all instances (matching the legacy
 * `protected static $memcache`).
 */
final class IpCacheRepository {

	/**
	 * Request-scoped memory cache: ip => cache row.
	 *
	 * @var array<string,array<string,mixed>|null>
	 */
	private static array $memcache = array();

	/**
	 * Read a cache row (legacy get_cache()).
	 *
	 * @return array<string,mixed>|null
	 */
	public function find( string $ip, bool $useCache = true ): ?array {
		if ( isset( self::$memcache[ $ip ] ) ) {
			return self::$memcache[ $ip ];
		}

		if ( ! $useCache ) {
			return null;
		}

		$row = Logs::search_cache( $ip );

		return self::$memcache[ $ip ] = ( is_array( $row ) ? $row : null );
	}

	/**
	 * Write a cache row (legacy update_cache()).
	 *
	 * @param array<string,mixed> $validate
	 * @param array<string,mixed> $settings
	 *
	 * @return array<string,mixed>
	 */
	public function update( string $hook, array $validate, array $settings, bool $countup = true ): array {
		$time  = $_SERVER['REQUEST_TIME'];
		$cache = $this->find( $ip = $validate['ip'], (bool) $settings['cache_hold'] );

		if ( $cache ) {
			$fail = isset( $validate['fail'] ) ? $validate['fail'] : 0;
			$call = $cache['reqs'] + ( $countup ? 1 : 0 ); // prevent duplicate count up
			$last = $cache['last'];
			$view = $cache['view'];
		} else { // if new cache then reset these values
			$fail = 0;
			$call = 1;
			$last = $time;
			$view = 1;
		}

		if ( $cache && 'public' === $hook ) {
			if ( $time - $last > $settings['behavior']['time'] ) {
				$view = 1;
			} else {
				++ $view;
			}
			$last = $time;
		}

		$cache = array(
			'time'  => $time,
			'ip'    => $ip,
			'hook'  => $hook,
			'asn'   => isset( $validate['asn'] ) ? $validate['asn'] : '',
			'code'  => isset( $validate['code'] ) ? $validate['code'] : '',
			'auth'  => isset( $validate['auth'] ) ? $validate['auth'] : '',
			'city'  => isset( $validate['city'] ) ? $validate['city'] : '',
			'state' => isset( $validate['state'] ) ? $validate['state'] : '',
			'fail'  => $fail,
			'reqs'  => $settings['save_statistics'] ? $call : 0,
			'last'  => $last,
			'view'  => $view,
			'host'  => isset( $validate['host'] ) && $validate['host'] !== $ip ? $validate['host'] : '',
		);

		// Do not update cache while installing geolocation databases.
		if ( $settings['cache_hold'] && ! ( $validate['auth'] && 'ZZ' === $validate['code'] ) ) {
			Logs::update_cache( $cache );
		}

		return self::$memcache[ $ip ] = $cache;
	}

	/**
	 * Clear the cache (legacy clear_cache()).
	 */
	public function clear(): void {
		Logs::clear_cache();
		self::$memcache = array();
	}

	/**
	 * Reset only the in-memory cache (test seam).
	 */
	public static function flushMemory(): void {
		self::$memcache = array();
	}
}
