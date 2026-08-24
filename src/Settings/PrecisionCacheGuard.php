<?php
/**
 * Precision-rule cache guard.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Settings;

use IPLocationBlock\Core\Validator;
use IPLocationBlock\Geolocation\IpCacheRepository;

/**
 * Clears the IP address cache whenever the set of precision (":"-bearing) rules
 * changes on save.
 *
 * A row cached before a precision rule existed carries empty city/state and
 * would otherwise replay forever ("I fixed the rule but it still doesn't block
 * me"). The `update_option_{$option}` action is the single choke point that
 * fires — with the old and new value — for BOTH the REST admin and the classic
 * admin saves, and only when the option actually changed.
 *
 * The full clear is safe: the cache rebuilds on subsequent lookups. It runs on
 * the current blog only, matching the existing per-blog cache clears.
 */
final class PrecisionCacheGuard {

	/**
	 * Register the settings-updated listener.
	 */
	public static function register(): void {
		\add_action(
			'update_option_' . Validator::OPTION_NAME,
			array( self::class, 'maybe_clear' ),
			10,
			2
		);
	}

	/**
	 * Clear the IP cache when the precision fingerprint changed.
	 *
	 * @param mixed $old_value previous settings array
	 * @param mixed $new_value new settings array
	 */
	public static function maybe_clear( $old_value, $new_value ): void {
		$old = \is_array( $old_value ) ? $old_value : array();
		$new = \is_array( $new_value ) ? $new_value : array();

		if ( self::fingerprint( $old ) !== self::fingerprint( $new ) ) {
			( new IpCacheRepository() )->clear();
		}
	}

	/**
	 * The precision fingerprint: the sorted, de-duplicated set of ":"-bearing
	 * entries across white_list, black_list and their public.* counterparts.
	 * Changes iff a precision rule is added, removed or altered.
	 *
	 * @param array<string,mixed> $settings
	 *
	 * @return list<string>
	 */
	public static function fingerprint( array $settings ): array {
		$lists = array();
		foreach ( array( 'white_list', 'black_list' ) as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$lists[] = (string) $settings[ $key ];
			}
		}
		if ( isset( $settings['public'] ) && \is_array( $settings['public'] ) ) {
			foreach ( array( 'white_list', 'black_list' ) as $key ) {
				if ( isset( $settings['public'][ $key ] ) ) {
					$lists[] = (string) $settings['public'][ $key ];
				}
			}
		}

		$entries = array();
		foreach ( $lists as $list ) {
			foreach ( \explode( ',', $list ) as $entry ) {
				$entry = \trim( $entry );
				if ( '' !== $entry && false !== \strpos( $entry, ':' ) ) {
					$entries[ \strtolower( $entry ) ] = true;
				}
			}
		}

		$keys = \array_keys( $entries );
		\sort( $keys );

		return $keys;
	}
}
