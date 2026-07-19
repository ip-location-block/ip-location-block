<?php
/**
 * Legacy IP address cache facade.
 *
 * `class IP_Location_Block_API_Cache` — the historic static cache API
 * (update_cache / get_cache / clear_cache) delegating to the new
 * IpCacheRepository, plus the instance surface (get_location / get_country /
 * supports) for the synthetic 'Cache' geo object returned by
 * IP_Location_Block_API::get_instance( 'Cache', ... ).
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

class IP_Location_Block_API_Cache {

	/** @var \IPLocationBlock\Geolocation\IpCacheRepository|null */
	private static $repo = null;

	/** @var self|null */
	private static $adapter = null;

	private static function repo() {
		if ( null === self::$repo ) {
			self::$repo = new \IPLocationBlock\Geolocation\IpCacheRepository();
		}

		return self::$repo;
	}

	/**
	 * Shared instance used as the synthetic 'Cache' geo object.
	 *
	 * @return self
	 */
	public static function adapter() {
		if ( null === self::$adapter ) {
			self::$adapter = new self();
		}

		return self::$adapter;
	}

	/**
	 * Update cache (legacy static API).
	 *
	 * @return array
	 */
	public static function update_cache( $hook, $validate, $settings, $countup = true ) {
		return self::repo()->update(
			(string) $hook,
			is_array( $validate ) ? $validate : array(),
			is_array( $settings ) ? $settings : array(),
			(bool) $countup
		);
	}

	/**
	 * Return a cache row (legacy static API).
	 *
	 * @return array|null
	 */
	public static function get_cache( $ip, $use_cache = true ) {
		return self::repo()->find( (string) $ip, (bool) $use_cache );
	}

	/**
	 * Clear cache (legacy static API).
	 */
	public static function clear_cache() {
		self::repo()->clear();
	}

	/* --- synthetic 'Cache' provider surface ------------------------------- */

	/**
	 * @return array
	 */
	public function get_location( $ip, $args = array() ) {
		$cache = self::get_cache( $ip );
		if ( $cache ) {
			return array(
				'countryCode' => $cache['code'],
				'cityName'    => $cache['city'],
				'stateName'   => $cache['state'],
				'asn'         => $cache['asn'],
			);
		}

		return array( 'errorMessage' => 'not in the cache' );
	}

	/**
	 * @return array|string|null
	 */
	public function get_country( $ip, $args = array() ) {
		return ( $cache = self::get_cache( $ip ) )
			? ( isset( $args['cache'] ) ? $cache : $cache['code'] )
			: null;
	}

	/**
	 * @param string $feature
	 *
	 * @return bool
	 */
	public function supports( $feature ) {
		return false;
	}
}
