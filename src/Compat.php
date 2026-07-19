<?php
/**
 * Deprecation helper.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock;

/**
 * One-shot deprecation notices for the compat layer.
 *
 * The documented Codex / drop-in API stays silent; only genuinely removed
 * surfaces (register_addon(), validate_country()) route through here. Each
 * symbol warns once per request: it fires the `ip-location-block-deprecated`
 * action and, when available, WordPress's _deprecated_function().
 */
final class Compat {

	/**
	 * Symbols already announced this request.
	 *
	 * @var array<string,true>
	 */
	private static array $fired = array();

	private function __construct() {}

	/**
	 * Announce a deprecated symbol (idempotent per symbol).
	 */
	public static function deprecated( string $symbol, string $version, string $replacement = '' ): void {
		if ( isset( self::$fired[ $symbol ] ) ) {
			return;
		}
		self::$fired[ $symbol ] = true;

		if ( function_exists( 'do_action' ) ) {
			do_action( 'ip-location-block-deprecated', $symbol, $version, $replacement );
		}

		if ( function_exists( '_deprecated_function' ) ) {
			_deprecated_function( $symbol, $version, $replacement );
		}
	}

	/**
	 * Reset the one-shot registry (test seam).
	 */
	public static function reset(): void {
		self::$fired = array();
	}
}
