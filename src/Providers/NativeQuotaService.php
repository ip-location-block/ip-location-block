<?php
/**
 * Native quota service.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

use IPLocationBlock\Support\Util;

/**
 * Fetches and normalizes the api.iplocationblock.com per-key quota. Blocking
 * statuses gate provider readiness in REST.
 *
 * Transient name: `ip_location_block_quota_<fingerprint40>`.
 */
final class NativeQuotaService {

	/**
	 * Statuses that block native readiness.
	 */
	public const STATUS_BLOCKING = array( 'exhausted', 'rate_limited', 'key_upgrade_required' );

	/**
	 * Per-request memo keyed by credential fingerprint.
	 *
	 * @var array<string,mixed>
	 */
	private static array $memo = array();

	/**
	 * Fetch the raw quota response (or a WP_Error / sub-value).
	 *
	 * @return \WP_Error|array<string,mixed>|mixed
	 */
	public function fetch( string $key, string $subkey = '', bool $refresh = false ) {
		if ( '' === $key || '@' === $key ) {
			return new \WP_Error( 'ip_location_block_missing_api_key', __( 'An API key is required.', 'ip-location-block' ) );
		}

		$fingerprint = CredentialFingerprint::of( 'IP Location Block', $key );
		$cache_key   = 'ip_location_block_quota_' . substr( $fingerprint, 0, 40 );

		if ( ! $refresh && array_key_exists( $fingerprint, self::$memo ) ) {
			$contents = self::$memo[ $fingerprint ];

			return isset( $contents[ $subkey ] ) && '' !== $subkey ? $contents[ $subkey ] : $contents;
		}

		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				self::$memo[ $fingerprint ] = $cached;

				return isset( $cached[ $subkey ] ) && '' !== $subkey ? $cached[ $subkey ] : $cached;
			}
		}

		$response = wp_remote_get(
			esc_url( NativeProvider::QUOTA_ENDPOINT . rawurlencode( $key ) ),
			array( 'timeout' => 5 )
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$contents = wp_remote_retrieve_body( $response );
		if ( empty( $contents ) || ! Util::json_validate( $contents ) ) {
			return new \WP_Error(
				'ip_location_block_invalid_quota',
				__( 'The quota service returned an invalid response.', 'ip-location-block' )
			);
		}

		$contents = json_decode( $contents, true );
		if ( ! is_array( $contents ) ) {
			return new \WP_Error(
				'ip_location_block_invalid_quota',
				__( 'The quota service returned an invalid response.', 'ip-location-block' )
			);
		}
		$contents['_ilb_checked_at'] = time();

		self::$memo[ $fingerprint ] = $contents;
		set_transient( $cache_key, $contents, 5 * MINUTE_IN_SECONDS );

		return isset( $contents[ $subkey ] ) && '' !== $subkey ? $contents[ $subkey ] : $contents;
	}

	/**
	 * Collapse any quota response into one stable shape.
	 *
	 * @param mixed $quota
	 *
	 * @return array<string,mixed>
	 */
	public function normalize( $quota ): array {
		$status = array(
			'provider'   => 'IP Location Block',
			'status'     => 'unavailable',
			'planName'   => '',
			'recurring'  => null,
			'limit'      => null,
			'oneTime'    => null,
			'total'      => null,
			'unlimited'  => false,
			'message'    => '',
			'checkedAt'  => is_array( $quota ) && isset( $quota['_ilb_checked_at'] ) ? (int) $quota['_ilb_checked_at'] : time(),
			'accountUrl' => NativeProvider::ACCOUNT_URL,
			'upgradeUrl' => NativeProvider::UPGRADE_URL,
		);

		if ( is_wp_error( $quota ) ) {
			$status['message'] = $quota->get_error_message();

			return $status;
		}

		if ( ! is_array( $quota ) || empty( $quota ) ) {
			$status['message'] = __( 'Quota information is temporarily unavailable.', 'ip-location-block' );

			return $status;
		}

		if ( isset( $quota['subscription']['plan_name'] ) ) {
			$status['planName'] = (string) $quota['subscription']['plan_name'];
		} elseif ( isset( $quota['name'] ) && 'requires-api-key-upgrade' !== $quota['name'] ) {
			$status['planName'] = (string) $quota['name'];
		}

		if ( isset( $quota['balance'] ) && is_array( $quota['balance'] ) ) {
			if ( array_key_exists( 'recurring', $quota['balance'] ) ) {
				$status['recurring'] = (int) $quota['balance']['recurring'];
			}
			if ( array_key_exists( 'onetime', $quota['balance'] ) ) {
				$status['oneTime'] = (int) $quota['balance']['onetime'];
			}
			if ( array_key_exists( 'total', $quota['balance'] ) ) {
				$status['total'] = (int) $quota['balance']['total'];
			}
		}
		if ( isset( $quota['subscription'] ) && is_array( $quota['subscription'] ) && array_key_exists( 'tokens', $quota['subscription'] ) ) {
			$status['limit'] = (int) $quota['subscription']['tokens'];
		}

		$status['unlimited'] = -1 === $status['recurring'];
		if ( isset( $quota['error'] ) && is_scalar( $quota['error'] ) ) {
			$status['message'] = (string) $quota['error'];
		}

		if ( isset( $quota['name'] ) && 'requires-api-key-upgrade' === $quota['name'] ) {
			$status['status'] = 'key_upgrade_required';
			if ( '' === $status['message'] ) {
				$status['message'] = __( 'This API key must be upgraded before it can be used.', 'ip-location-block' );
			}
		} elseif ( isset( $quota['status'] ) && 'rate_limited' === $quota['status'] ) {
			$status['status'] = 'rate_limited';
			if ( '' === $status['message'] ) {
				$status['message'] = __( 'The API rate limit has been reached.', 'ip-location-block' );
			}
		} elseif ( ! empty( $quota['error'] ) ) {
			$status['status'] = 'unavailable';
		} elseif ( $status['unlimited'] ) {
			$status['status'] = 'unlimited';
		} elseif ( null !== $status['total'] && $status['total'] <= 0 ) {
			$status['status'] = 'exhausted';
			if ( '' === $status['message'] ) {
				$status['message'] = __( 'The account has no remaining requests.', 'ip-location-block' );
			}
		} else {
			$status['status'] = 'ok';
		}

		return $status;
	}

	/**
	 * Fetch + normalize in one call.
	 *
	 * @return array<string,mixed>
	 */
	public function status( string $key, bool $refresh = false ): array {
		return $this->normalize( $this->fetch( $key, '', $refresh ) );
	}

	/**
	 * The normalized quota status string from CACHED data only — never triggers
	 * an HTTP refresh. Returns null when nothing is cached (memo miss + transient
	 * miss) or the key is absent, so read-only callers (the registry's native
	 * enforcement quota fast-skip) can distinguish "no cached signal" from a real
	 * blocking status without ever making a network call.
	 */
	public function cachedStatus( string $key ): ?string {
		if ( '' === $key || '@' === $key ) {
			return null;
		}

		$fingerprint = CredentialFingerprint::of( 'IP Location Block', $key );

		if ( array_key_exists( $fingerprint, self::$memo ) ) {
			$contents = self::$memo[ $fingerprint ];
		} else {
			$cached = get_transient( 'ip_location_block_quota_' . substr( $fingerprint, 0, 40 ) );
			if ( false === $cached ) {
				return null;
			}
			self::$memo[ $fingerprint ] = $cached;
			$contents                   = $cached;
		}

		$status = $this->normalize( $contents );

		return isset( $status['status'] ) ? (string) $status['status'] : null;
	}

	/**
	 * Whether a normalized status blocks native readiness.
	 *
	 * @param array<string,mixed> $status
	 */
	public function isBlocking( array $status ): bool {
		return isset( $status['status'] ) && in_array( $status['status'], self::STATUS_BLOCKING, true );
	}

	/**
	 * Reset the per-request memo (test seam).
	 */
	public static function flushMemo(): void {
		self::$memo = array();
	}
}
