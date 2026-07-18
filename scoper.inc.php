<?php
/**
 * PHP-Scoper configuration for IP Location Block.
 *
 * Prefixes the bundled geolocation vendor namespaces under
 * `IPLocationBlock\Vendor` so they cannot collide with copies of the same
 * libraries shipped by other plugins.
 *
 * NOTE: pear/net_dns2 is intentionally NOT scoped (PEAR-style dynamic
 * class-name construction breaks under prefixing) and stays in vendor/.
 * phpunit / brain-monkey / mockery are dev-only and never enter vendor_prefixed/.
 *
 * @see https://github.com/humbug/php-scoper
 */

declare(strict_types=1);

use Isolated\Symfony\Component\Finder\Finder;

// Path to the sniccowp WordPress excludes (generated JSON lists).
$wpExcludesPath = __DIR__ . '/vendor/sniccowp/php-scoper-wordpress-excludes/generated/';

// Helper to load exclude files safely.
$loadExcludes = function (string $filename) use ($wpExcludesPath): array {
    $path = $wpExcludesPath . $filename;
    if (!file_exists($path)) {
        return [];
    }
    return json_decode(file_get_contents($path), true) ?: [];
};

return [
    // Scoped vendor prefix (same convention as the celersearch reference plugin).
    'prefix' => 'IPLocationBlock\\Vendor',

    // Output directory for the prefixed copies.
    'output-dir' => 'vendor_prefixed',

    // One Finder per scoped package directory.
    'finders' => [
        // IP2Location PHP library (classmap package, single IP2Location.php).
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|.*\\.TXT/')
            ->exclude(['doc', 'docs', 'test', 'tests', 'Tests', 'databases', 'example'])
            ->in('vendor/ip2location'),

        // phpseclib3 + bcmath_compat polyfill.
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json/')
            ->exclude(['doc', 'docs', 'test', 'tests', 'Tests'])
            ->in('vendor/phpseclib'),

        // ParagonIE constant_time_encoding (phpseclib dependency).
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json/')
            ->exclude(['doc', 'docs', 'test', 'tests', 'Tests'])
            ->in('vendor/paragonie'),

        // MaxMind GeoIp2 reader.
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json/')
            ->exclude(['doc', 'docs', 'test', 'tests', 'Tests'])
            ->in('vendor/geoip2'),

        // MaxMind web-service-common.
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json/')
            ->exclude(['doc', 'docs', 'test', 'tests', 'Tests'])
            ->in('vendor/maxmind'),

        // MaxMind DB reader.
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json/')
            ->exclude(['doc', 'docs', 'test', 'tests', 'Tests'])
            ->in('vendor/maxmind-db'),

        // Composer CA bundle (maxmind dependency for TLS verification).
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json/')
            ->exclude(['doc', 'docs', 'test', 'tests', 'Tests'])
            ->in('vendor/composer/ca-bundle'),
    ],

    // Never prefix the plugin's own namespace.
    'exclude-namespaces' => [
        'IPLocationBlock',
    ],

    // WordPress core classes must stay global.
    'exclude-classes' => $loadExcludes('exclude-wordpress-classes.json'),

    // WordPress core functions + the bcmath_compat polyfill functions must stay
    // global so bcmath_compat remains a working global fallback when the native
    // bcmath extension is absent.
    'exclude-functions' => array_merge(
        $loadExcludes('exclude-wordpress-functions.json'),
        [
            'bcadd',
            'bcsub',
            'bcmul',
            'bcdiv',
            'bcmod',
            'bcpow',
            'bcpowmod',
            'bcsqrt',
            'bccomp',
            'bcscale',
        ]
    ),

    // WordPress core constants must stay global.
    'exclude-constants' => $loadExcludes('exclude-wordpress-constants.json'),

    // Patchers for specific files that need manual adjustments.
    'patchers' => [
        // bcmath_compat's lib/bcmath.php declares the global bc* polyfill
        // functions (bcadd, bcsub, ...). php-scoper prefixes the file's
        // namespace, which would turn those declarations into
        // IPLocationBlock\Vendor\bcadd(), while scoped phpseclib still calls
        // the GLOBAL \bcadd() (kept global via exclude-functions above). On a
        // host without the native bcmath extension that mismatch would fatal.
        // Strip the prefixed namespace from this one file so the polyfill
        // declarations stay in the global namespace; the backing BCMath class
        // is still reached through the file's (scoped) `use` import.
        static function (string $filePath, string $prefix, string $contents): string {
            if (!str_ends_with(str_replace('\\', '/', $filePath), 'bcmath_compat/lib/bcmath.php')) {
                return $contents;
            }

            return preg_replace(
                '/^namespace ' . preg_quote($prefix, '/') . ';\R/m',
                '',
                $contents,
                1
            );
        },
    ],
];
