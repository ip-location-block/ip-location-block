<?php
/**
 * Precision-source marker interface.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

/**
 * EMPTY, behaviorless marker interface.
 *
 * Implemented ONLY by {@see NativeProvider}. GeolocationResolver's precision
 * gate keys on it: any lookup result produced by a provider that does NOT
 * implement this interface has its region/city/state precision stripped
 * (LocationResult::withoutPrecision()).
 *
 * This is the structural enforcement of the monetization invariant — only
 * api.iplocationblock.com (the native provider) may deliver city/state
 * precision. The gate checks `instanceof`, never a capability bit, so the
 * invariant cannot be circumvented by editing metadata.
 *
 * Do NOT implement this interface on any other class.
 */
interface PrecisionLocationSource {}
