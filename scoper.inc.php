<?php
/**
 * PHP-Scoper configuration for IP Location Block.
 *
 * Prefixes the bundled geolocation vendor namespaces under
 * `IPLocationBlock\Vendor` so they cannot collide with copies of the same
 * libraries shipped by other plugins.
 *
 * NetDNS2 v2 is namespaced and can be scoped safely. phpunit / brain-monkey /
 * mockery are dev-only and never enter vendor_prefixed/.
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

        // NetDNS2 v2 resolver.
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json/')
            ->exclude(['doc', 'docs', 'test', 'tests', 'Tests'])
            ->in('vendor/mikepultz/netdns2/src'),

        // phpseclib3 + the bcmath_compat BCMath class. Its lib/bcmath.php is left
        // out: it declares global bc*() functions, which other plugins detect
        // with function_exists() and then call. IPLocationBlock\Support\BcMath
        // reaches the BCMath class directly instead.
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json/')
            ->notPath('bcmath_compat/lib')
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

    // WordPress core functions must stay global.
    'exclude-functions' => $loadExcludes('exclude-wordpress-functions.json'),

    // WordPress core constants must stay global.
    'exclude-constants' => $loadExcludes('exclude-wordpress-constants.json'),

    // Patchers for specific files that need manual adjustments.
    'patchers' => [
        // phpseclib builds some class names from string literals
        // ('phpseclib3\\Math\\BigInteger\\Engines\\' . $engine) that php-scoper
        // doesn't rewrite. Unpatched, the scoped BigInteger finds no engine and
        // every BCMath polyfill call throws "No valid BigInteger found".
        static function (string $filePath, string $prefix, string $contents): string {
            if (!str_contains(str_replace('\\', '/', $filePath), 'phpseclib/phpseclib/')) {
                return $contents;
            }

            $escaped = str_replace('\\', '\\\\', $prefix);

            return str_replace(
                ["'phpseclib3\\\\", "'\\\\phpseclib3\\\\"],
                ["'{$escaped}\\\\phpseclib3\\\\", "'\\\\{$escaped}\\\\phpseclib3\\\\"],
                $contents
            );
        },

        // IP2Location does its IPv6 math with bc*(), and the BCMath polyfill
        // itself calls bccomp() once (in pow). Route those calls through
        // IPLocationBlock\Support\BcMath, which uses the native extension when
        // it's loaded and the scoped BCMath polyfill otherwise.
        static function (string $filePath, string $prefix, string $contents): string {
            $path = str_replace('\\', '/', $filePath);
            if (!str_ends_with($path, 'ip2location-php/IP2Location.php') && !str_ends_with($path, 'bcmath_compat/src/BCMath.php')) {
                return $contents;
            }

            return preg_replace(
                '/(?<![\w\\\\$>:])\\\\?bc(add|comp|div|mul|pow|sub)\s*\(/',
                '\\\\IPLocationBlock\\\\Support\\\\BcMath::$1(',
                $contents
            );
        },
    ],
];
