<?php
/**
 * Build script for generating the vendor_prefixed autoload configuration.
 *
 * Run after php-scoper (see the "scope" composer script) to create the
 * .gitignore and composer.json that autoload the scoped dependencies under
 * the IPLocationBlock\Vendor prefix.
 *
 * The autoload map below is hand-maintained: php-scoper rewrites namespaces
 * but does not emit a composer.json for the output tree, so we describe the
 * scoped package layout here. Paths are relative to vendor_prefixed/.
 */

$vendorPrefixedDir = dirname(__DIR__) . '/vendor_prefixed';

// Create .gitignore so the generated (non-committed) prefixed files are ignored
// while the folder itself is kept for the classmap autoloader to function.
$gitignore = <<<'GITIGNORE'
# This folder must exist for the composer autoload classmap to function.
# We don't want to commit the prefixed files, thus excluding them from the repository here.
*
!.gitignore
GITIGNORE;

file_put_contents($vendorPrefixedDir . '/.gitignore', $gitignore . "\n");
echo "Created vendor_prefixed/.gitignore\n";

// Create composer.json for autoloading the scoped dependencies.
$composerJson = [
    'name' => 'iplocationblock/scoped-deps',
    'autoload' => [
        'psr-4' => [
            'IPLocationBlock\\Vendor\\GeoIp2\\' => 'geoip2/geoip2/src/',
            'IPLocationBlock\\Vendor\\MaxMind\\Db\\' => 'maxmind-db/reader/src/MaxMind/Db/',
            'IPLocationBlock\\Vendor\\MaxMind\\' => 'maxmind/web-service-common/src/',
            'IPLocationBlock\\Vendor\\Composer\\CaBundle\\' => 'composer/ca-bundle/src/',
            'IPLocationBlock\\Vendor\\phpseclib3\\' => 'phpseclib/phpseclib/phpseclib/',
            'IPLocationBlock\\Vendor\\ParagonIE\\ConstantTime\\' => 'paragonie/constant_time_encoding/src/',
            'IPLocationBlock\\Vendor\\bcmath_compat\\' => 'phpseclib/bcmath_compat/src/',
        ],
        'classmap' => [
            'ip2location/ip2location-php/IP2Location.php',
        ],
        'files' => [
            'phpseclib/bcmath_compat/lib/bcmath.php',
        ],
    ],
];

file_put_contents(
    $vendorPrefixedDir . '/composer.json',
    json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);
echo "Created vendor_prefixed/composer.json\n";
