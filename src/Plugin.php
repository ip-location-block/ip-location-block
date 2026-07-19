<?php

declare(strict_types=1);

namespace IPLocationBlock;

/**
 * Plugin kernel.
 *
 * Holds the WordPress hook registrations for the plugin bootstrap. The main
 * plugin file (ip-location-block.php) constructs it with its own __FILE__ and
 * calls register().
 *
 * CRITICAL (deployed mu-plugin contract): the `plugins_loaded` callables MUST
 * stay the exact legacy identities — the string class name 'IP_Location_Block'
 * (exact casing) and the global function name 'ip_location_block_update'. The
 * deployed mu-plugin copies do `remove_action()` on those literal identities;
 * registering Validator::class or a closure here would silently double-run the
 * plugin. The global (de)activation/update callables live in the main file (the
 * documented procedural API).
 */
final class Plugin
{
    public function __construct(private readonly string $mainFile)
    {
    }

    /**
     * Absolute path to the plugin's main file.
     */
    public function mainFile(): string
    {
        return $this->mainFile;
    }

    /**
     * Register the plugin's WordPress hooks.
     */
    public function register(): void
    {
        // (De)activation + post-update hooks — the callables are the global
        // functions defined in the main plugin file.
        \register_activation_hook($this->mainFile, 'ip_location_block_activate');
        \register_deactivation_hook($this->mainFile, 'ip_location_block_deactivate');
        \add_action('upgrader_process_complete', 'ip_location_block_upgrader_process_complete', 10, 2);

        // check version and update before instantiation.
        \add_action('plugins_loaded', 'ip_location_block_update');

        // Instantiate. EXACT legacy callable identity (string class name, exact
        // casing) — the deployed mu-plugin copies remove_action() this literal.
        \add_action('plugins_loaded', array('IP_Location_Block', 'get_instance'));

        // Register the REST API (ip-location-block/v1) used by the React admin.
        // Contract-bound legacy identity — do not namespace (see header note).
        \add_action('rest_api_init', array('IP_Location_Block_Rest', 'register_routes'));

        // Dashboard / administrative functionality (classic + React admin).
        if (\is_admin()) {
            // Contract-bound legacy identity — the classic admin class exists
            // ONLY under its legacy name (frozen admin layer); do not namespace.
            require IP_LOCATION_BLOCK_PATH . 'admin/legacy/class-ip-location-block-admin.php';
            \add_action('plugins_loaded', array('IP_Location_Block_Admin', 'get_instance'));

            // React (Beta) admin — separate opt-in menu; classic admin stays
            // default. New code (not frozen), so it is autoloaded under its
            // namespaced identity rather than a legacy require + class name.
            \add_action('plugins_loaded', array(Admin\ReactAdmin::class, 'get_instance'));
        }
    }
}
