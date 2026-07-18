<?php
/**
 * Backward-compatibility bootstrap (stub).
 *
 * Phase 1 scaffold placeholder. In later phases this file will:
 *   - define the plugin constants when missing (incl. VERSION under
 *     WP_UNINSTALL_PLUGIN),
 *   - load the composer autoloaders,
 *   - register the legacy class aliases and facades.
 *
 * It registers NO hooks. Nothing functional lives here yet — the classmap
 * autoload entry (`compat/`) in composer.json only needs the directory to
 * exist during Phase 1.
 */

defined('WPINC') || defined('WP_UNINSTALL_PLUGIN') || die;
