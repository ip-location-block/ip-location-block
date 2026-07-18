<?php
/**
 * PHPUnit bootstrap for the IP Location Block unit suite.
 *
 * Loads the composer autoloader and defines the plugin constants that the
 * new src/ code expects. No WordPress is loaded here; WordPress functions are
 * stubbed per-test with Brain Monkey.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The scoped vendor autoloader is optional in the test context (the unit suite
// stubs WordPress and does not exercise the geo DB libraries directly), but
// load it when present so scoped classes resolve if a test needs them.
$scopedAutoload = dirname(__DIR__) . '/vendor_prefixed/vendor/autoload.php';
if (file_exists($scopedAutoload)) {
    require_once $scopedAutoload;
}

if (!defined('IP_LOCATION_BLOCK_VERSION')) {
    define('IP_LOCATION_BLOCK_VERSION', '1.4.0-test');
}

if (!defined('IP_LOCATION_BLOCK_PATH')) {
    define('IP_LOCATION_BLOCK_PATH', dirname(__DIR__) . '/');
}

if (!defined('IP_LOCATION_BLOCK_BASE')) {
    define('IP_LOCATION_BLOCK_BASE', 'ip-location-block/ip-location-block.php');
}
