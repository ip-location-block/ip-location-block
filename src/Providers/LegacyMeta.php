<?php
/**
 * Legacy provider-meta reshaping.
 *
 * The provider "meta array" shapes and selection lists that the legacy
 * IP_Location_Block_Provider static API exposed (all(), get_providers(),
 * get_valid_providers(), the synthetic 'Cache' entry, supports(),
 * format_provider_meta(), …) live here — inside src/ — so the REST layer and
 * other new code can consume the exact legacy payload shapes WITHOUT depending
 * on the compat facade. The compat facade (IP_Location_Block_Provider) now
 * delegates to this class, so the dependency direction is compat -> src.
 *
 * Loose-typed on purpose: callers rely on PHP's loose-type coercion behavior
 * (no declare(strict_types=1)).
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

namespace IPLocationBlock\Providers;

/**
 * Legacy provider metadata / selection reshaping over the sealed registry.
 */
final class LegacyMeta {

	/**
	 * Build a provider's legacy meta array in the exact legacy key order:
	 * key, type, link, supports, [limits], requests, api_auth, local.
	 *
	 * @return array
	 */
	private static function provider_meta( ProviderInterface $p ) {
		$meta = array(
			'key'      => $p->implicitlyEnabled() ? null : '',
			'type'     => $p->typeLabel(),
			'link'     => $p->link(),
			'supports' => $p->legacySupports(),
		);
		if ( null !== $p->limits() ) {
			$meta['limits'] = $p->limits();
		}
		$meta['requests'] = $p->requestQuota();
		$meta['api_auth'] = $p->authMode();
		$meta['local']    = $p->isLocal();

		return $meta;
	}

	/**
	 * The synthetic 'Cache' meta entry (verbatim legacy shape).
	 *
	 * @return array
	 */
	private static function cache_meta() {
		return array(
			'key'      => null,
			'type'     => 'IPv4, IPv6',
			'link'     => null,
			'supports' => array(),
		);
	}

	/**
	 * All providers including the synthetic Cache, in the legacy all() order:
	 * remotes, Cache, locals.
	 *
	 * @return array
	 */
	public static function all() {
		static $providers = null;
		if ( null !== $providers ) {
			return $providers;
		}

		$registry = ProviderRegistry::instance();
		$out      = array();
		foreach ( $registry->remoteProviders() as $p ) {
			$out[ $p->id() ] = self::provider_meta( $p );
		}
		$out['Cache'] = self::cache_meta();
		foreach ( $registry->localProviders() as $p ) {
			$out[ $p->id() ] = self::provider_meta( $p );
		}

		return $providers = $out;
	}

	/**
	 * Local addon providers eligible for database download (legacy get_addons).
	 *
	 * @param array $providers settings['providers'].
	 * @param bool  $force
	 *
	 * @return array
	 */
	public static function get_addons( $providers = array(), $force = false ) {
		return ProviderRegistry::instance()->localProviderIds(
			is_array( $providers ) ? $providers : array(),
			(bool) $force
		);
	}

	/**
	 * Provider name => meta-field pairs (legacy get_providers).
	 *
	 * @param string $key
	 * @param bool   $rand
	 * @param bool   $cache
	 * @param bool   $all
	 *
	 * @return array
	 */
	public static function get_providers( $key = 'key', $rand = false, $cache = false, $all = true ) {
		$registry = ProviderRegistry::instance();
		$meta     = self::all();
		$list     = array();

		if ( $cache ) {
			$cache_meta    = self::cache_meta();
			$list['Cache'] = array_key_exists( $key, $cache_meta ) ? $cache_meta[ $key ] : null;
		}
		foreach ( $registry->localProviders() as $p ) {
			$id          = $p->id();
			$list[ $id ] = array_key_exists( $key, $meta[ $id ] ) ? $meta[ $id ][ $key ] : null;
		}

		if ( $all ) {
			$ids = array();
			foreach ( $registry->remoteProviders() as $p ) {
				$ids[] = $p->id();
			}
			if ( $rand ) {
				shuffle( $ids );
			}
			foreach ( $ids as $id ) {
				$list[ $id ] = array_key_exists( $key, $meta[ $id ] ) ? $meta[ $id ][ $key ] : null;
			}
		}

		return $list;
	}

	/**
	 * Providers checked in settings (legacy get_valid_providers). Prepends the
	 * synthetic 'Cache' when the IP cache is enabled.
	 *
	 * @param array $settings
	 * @param bool  $rand
	 * @param bool  $cache
	 * @param bool  $all
	 *
	 * @return array
	 */
	public static function get_valid_providers( $settings, $rand = true, $cache = true, $all = false ) {
		$registry = ProviderRegistry::instance();
		$cache_on = $cache && ! empty( $settings['cache_hold'] );

		$ids = $registry->activeProviderIds( $settings, (bool) $rand, (bool) $all );

		if ( $cache_on ) {
			$map = isset( $settings['providers'] ) && is_array( $settings['providers'] ) ? $settings['providers'] : array();
			if ( ! empty( $map['Cache'] ) || ! isset( $map['Cache'] ) ) {
				array_unshift( $ids, 'Cache' );
			}
		}

		return $ids;
	}

	/**
	 * Single provider meta (legacy get_provider).
	 *
	 * @return array
	 */
	public static function get_provider( $name ) {
		$all = self::all();

		return isset( $all[ $name ] ) ? $all[ $name ] : array();
	}

	/**
	 * Whether a provider supports a feature (legacy supports).
	 *
	 * @param string       $name
	 * @param array|string $feature
	 *
	 * @return bool
	 */
	public static function supports( $name, $feature ) {
		$all = self::all();
		if ( isset( $all[ $name ]['supports'] ) && is_array( $all[ $name ]['supports'] ) ) {
			if ( is_array( $feature ) ) {
				return count( array_intersect( $feature, $all[ $name ]['supports'] ) ) > 0;
			}

			return in_array( $feature, $all[ $name ]['supports'] );
		}

		return false;
	}

	/**
	 * Providers advertising a feature (legacy get_providers_by_feature).
	 *
	 * @return array
	 */
	public static function get_providers_by_feature( $feature ) {
		$providers = array();
		foreach ( self::all() as $key => $provider ) {
			if ( self::supports( $key, $feature ) ) {
				$providers[ $key ] = $provider;
			}
		}

		return $providers;
	}

	/**
	 * Format one provider meta value for display (legacy format_provider_meta).
	 *
	 * @return mixed|string
	 */
	public static function format_provider_meta( $provider, $meta_key ) {
		$providers = self::all();

		$value = isset( $providers[ $provider ][ $meta_key ] ) ? $providers[ $provider ][ $meta_key ] : '';

		switch ( $meta_key ) {
			case 'requests':
				if ( ! empty( $providers[ $provider ]['local'] ) ) {
					$value = __( 'Local database', 'ip-location-block' );
					break;
				}
				$total = isset( $value['total'] ) ? $value['total'] : '';
				$term  = isset( $value['term'] ) ? $value['term'] : '';
				if ( is_numeric( $total ) ) {
					if ( $total < 0 ) {
						$total = __( 'Unlimited', 'ip-location-block' );
						$term  = '';
					} else {
						$total = number_format( $total );
					}
				}
				if ( ! empty( $total ) ) {
					$value = empty( $term ) ? sprintf( '%s', $total ) : sprintf( '%s / %s', $total, $term );
				}
				break;
			case 'limits':
				if ( empty( $value ) ) {
					$value = __( 'None known', 'ip-location-block' );
				} elseif ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
				break;
			case 'signup-button':
				if ( ! empty( $providers[ $provider ]['link'] ) && ! is_null( $providers[ $provider ]['key'] ) ) {
					$value = sprintf( '<a href="%s" target="_blank" class="button button-secondary button-small">%s</a>', $providers[ $provider ]['link'], __( 'Register', 'ip-location-block' ) );
				}
				break;
			case 'name':
				if ( 'IP LOCATION BLOCK' === strtoupper( $provider ) ) {
					$value = sprintf( '%s <span class="ip-location-block-recommended">%s</span>', $provider, __( '(Recommended)', 'ip-location-block' ) );
				} else {
					$value = $provider;
				}
				break;
		}

		return $value;
	}
}
