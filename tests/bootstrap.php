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

// WordPress time constants used by the provider subsystem.
defined('MINUTE_IN_SECONDS') || define('MINUTE_IN_SECONDS', 60);
defined('HOUR_IN_SECONDS') || define('HOUR_IN_SECONDS', 3600);
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);

// Minimal WP_Error so quota/tester code paths return a usable object without a
// full WordPress install. is_wp_error() is stubbed per test via Brain Monkey.
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var string */
        public $code;
        /** @var string */
        public $message;
        /** @var mixed */
        public $data;

        public function __construct($code = '', $message = '', $data = '')
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_message()
        {
            return $this->message;
        }

        public function get_error_code()
        {
            return $this->code;
        }
    }
}
