<?php
/**
 * Legacy provider-registry facade.
 *
 * `class IP_Location_Block_Provider` — the full legacy static provider API. The
 * legacy provider-meta reshaping now lives in src/ (IPLocationBlock\Providers\
 * LegacyMeta); this facade delegates to it, so the dependency direction is
 * compat -> src. Thin surfaces delegate straight to the sealed ProviderRegistry
 * and the NativeQuotaService. The two external registration mechanisms are gone:
 * register_addon() is a deprecated no-op and there is no uploads-dir scan.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

use IPLocationBlock\Compat;
use IPLocationBlock\Providers\CredentialFingerprint;
use IPLocationBlock\Providers\LegacyMeta;
use IPLocationBlock\Providers\NativeQuotaService;
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
	 * All providers including the synthetic Cache, in the legacy all() order.
	 *
	 * @return array
	 */
	public static function all() {
		return LegacyMeta::all();
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

		// The React (Beta) screen hides every admin notice via its stylesheet,
		// so rendering here would echo into a hidden node and then delete the
		// option unseen. On that screen render nothing and keep the option: the
		// Diagnostics `ignored-addon-providers` check surfaces it instead.
		if ( self::is_react_admin_screen() ) {
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
	 * Whether the current admin request is the React (Beta) admin screen, whose
	 * stylesheet hides all admin notices.
	 *
	 * @return bool
	 */
	private static function is_react_admin_screen() {
		$slug = class_exists( '\\IPLocationBlock\\Admin\\ReactAdmin' )
			? \IPLocationBlock\Admin\ReactAdmin::SLUG
			: 'ip-location-block-beta';

		if ( isset( $_GET['page'] ) ) {
			$page = function_exists( 'sanitize_key' )
				? sanitize_key( function_exists( 'wp_unslash' ) ? wp_unslash( $_GET['page'] ) : $_GET['page'] )
				: (string) $_GET['page'];
			if ( $slug === $page ) {
				return true;
			}
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && isset( $screen->id ) && false !== strpos( (string) $screen->id, $slug ) ) {
				return true;
			}
		}

		return false;
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
		return LegacyMeta::get_addons( $providers, $force );
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
		return LegacyMeta::get_providers( $key, $rand, $cache, $all );
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
		return LegacyMeta::get_valid_providers( $settings, $rand, $cache, $all );
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
		return LegacyMeta::get_provider( $name );
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
		return LegacyMeta::supports( $name, $feature );
	}

	/**
	 * Providers advertising a feature (legacy get_providers_by_feature).
	 *
	 * @return array
	 */
	public static function get_providers_by_feature( $feature ) {
		return LegacyMeta::get_providers_by_feature( $feature );
	}

	/**
	 * Format one provider meta value for display (legacy format_provider_meta).
	 *
	 * @return mixed|string
	 */
	public static function format_provider_meta( $provider, $meta_key ) {
		return LegacyMeta::format_provider_meta( $provider, $meta_key );
	}
}
