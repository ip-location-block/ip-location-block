<?php
/**
 * Persistent, site-wide preference for the Simple Mode suggestion.
 *
 * @package IP_Location_Block
 */

namespace IPLocationBlock\Admin;

final class NativePromoNotice {

	const OPTION = 'ip_location_block_native_promo_dismissed';

	public static function is_dismissed(): bool {
		return (bool) get_option( self::OPTION, false );
	}

	public static function dismiss(): bool {
		// This preference is independent of versions, campaigns and exported
		// settings. An already-dismissed suggestion is a successful no-op.
		return self::is_dismissed()
			|| update_option( self::OPTION, true, false )
			|| self::is_dismissed();
	}
}
