<?php
/**
 * Legacy provider-registry facade.
 *
 * `class IP_Location_Block_Provider` — the full legacy static provider API,
 * delegating to the sealed ProviderRegistry and the NativeQuotaService. The two
 * external registration mechanisms are gone: register_addon() is a deprecated
 * no-op and there is no uploads-dir scan.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

use IPLocationBlock\Compat;
use IPLocationBlock\Providers\CredentialFingerprint;
use IPLocationBlock\Providers\NativeQuotaService;
use IPLocationBlock\Providers\ProviderInterface;
use IPLocationBlock\Providers\ProviderRegistry;

class IP_Location_Block_Provider {

	const API_AUTH_OPTIONAL     = 1;
	const API_AUTH_REQUIRED     = 2;
	const API_AUTH_NOT_REQUIRED = 3;

	/**
	 * Option name for the one-time "ignored add-on" admin notice.
	 */
	const IGNORED_ADDONS_OPTION = 'ip_location_block_ignored_addons';

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
	 * Deprecated no-op — external providers can no longer be registered. Queues
	 * a one-time admin notice naming the ignored keys; mutates nothing.
	 *
	 * @param array $api
	 */
	public static function register_addon( $api ) {
		Compat::deprecated(
			'IP_Location_Block_Provider::register_addon',
			'1.4.0',
			'the sealed provider registry (external providers are no longer supported)'
		);

		$keys = is_array( $api ) ? array_keys( $api ) : array();
		if ( ! empty( $keys ) ) {
			self::queue_addon_notice( $keys );
		}
	}

	/**
	 * Persist the ignored add-on keys and register the one-time notice.
	 *
	 * @param array $keys
	 */
	private static function queue_addon_notice( array $keys ) {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}

		$existing = get_option( self::IGNORED_ADDONS_OPTION, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$keys   = array_map( 'strval', $keys );
		$merged = array_values( array_unique( array_merge( $existing, $keys ) ) );
		if ( $merged !== $existing ) {
			update_option( self::IGNORED_ADDONS_OPTION, $merged, false );
		}

		if ( function_exists( 'add_action' ) && function_exists( 'has_action' )
			&& ! has_action( 'admin_notices', array( __CLASS__, 'render_addon_notice' ) ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_addon_notice' ) );
		}
	}

	/**
	 * Render (once) the ignored add-on notice, then clear it.
	 */
	public static function render_addon_notice() {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}
		$keys = get_option( self::IGNORED_ADDONS_OPTION, array() );
		if ( empty( $keys ) || ! is_array( $keys ) ) {
			return;
		}

		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: comma-separated list of ignored provider names. */
				__( 'IP Location Block: external geolocation providers are no longer supported. The following add-on provider(s) were ignored: %s', 'ip-location-block' ),
				implode( ', ', array_map( 'sanitize_text_field', $keys ) )
			)
		);
		echo '</p></div>';

		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::IGNORED_ADDONS_OPTION );
		}
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
			$cache_meta   = self::cache_meta();
			$list['Cache'] = array_key_exists( $key, $cache_meta ) ? $cache_meta[ $key ] : null;
		}
		foreach ( $registry->localProviders() as $p ) {
			$id           = $p->id();
			$list[ $id ]  = array_key_exists( $key, $meta[ $id ] ) ? $meta[ $id ][ $key ] : null;
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
	 * IP Location Block is the only active provider (legacy is_native).
	 *
	 * @param array $settings
	 *
	 * @return bool
	 */
	public static function is_native( $settings ) {
		return ProviderRegistry::instance()->isNativeOnly( $settings );
	}

	/**
	 * Non-reversible credential fingerprint (legacy credential_fingerprint).
	 *
	 * @return string
	 */
	public static function credential_fingerprint( $provider, $credential ) {
		return CredentialFingerprint::of( (string) $provider, (string) $credential );
	}

	/**
	 * Native quota (legacy get_native_quota).
	 *
	 * @return WP_Error|array|string|int
	 */
	public static function get_native_quota( $key, $subkey = '', $refresh = false ) {
		return ( new NativeQuotaService() )->fetch( (string) $key, (string) $subkey, (bool) $refresh );
	}

	/**
	 * Normalize a native quota response (legacy normalize_native_quota).
	 *
	 * @return array
	 */
	public static function normalize_native_quota( $quota ) {
		return ( new NativeQuotaService() )->normalize( $quota );
	}

	/**
	 * Fetch + normalize native quota (legacy get_native_quota_status).
	 *
	 * @return array
	 */
	public static function get_native_quota_status( $key, $refresh = false ) {
		return ( new NativeQuotaService() )->status( (string) $key, (bool) $refresh );
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
