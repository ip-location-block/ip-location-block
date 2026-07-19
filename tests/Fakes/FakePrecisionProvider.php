<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Fakes;

use IPLocationBlock\Providers\PrecisionLocationSource;

/**
 * A precision-source test double: identical to {@see FakeProvider} but flagged
 * with the PrecisionLocationSource marker, so GeolocationResolver's precision
 * gate lets its city/state survive.
 *
 * NOTE: in production the marker is implemented ONLY by NativeProvider; this
 * double exists purely to exercise the gate's `instanceof` branch in isolation.
 * ProviderRegistryTest separately asserts NativeProvider is the sole real
 * implementer.
 */
final class FakePrecisionProvider extends FakeProvider implements PrecisionLocationSource {}
