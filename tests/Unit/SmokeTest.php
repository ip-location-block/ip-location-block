<?php

declare(strict_types=1);

namespace IPLocationBlock\Tests\Unit;

use IPLocationBlock\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Sanity check that the PSR-4 autoloader resolves the plugin kernel.
 * Keeps the suite non-empty and green during the Phase 1 scaffold.
 */
final class SmokeTest extends TestCase
{
    public function test_autoloader_resolves_plugin_class(): void
    {
        $this->assertTrue(
            class_exists(Plugin::class),
            'IPLocationBlock\\Plugin should be autoloadable via the composer PSR-4 map.'
        );
    }

    public function test_plugin_constants_defined(): void
    {
        $this->assertTrue(defined('IP_LOCATION_BLOCK_VERSION'));
        $this->assertTrue(defined('IP_LOCATION_BLOCK_PATH'));
        $this->assertTrue(defined('IP_LOCATION_BLOCK_BASE'));
    }
}
