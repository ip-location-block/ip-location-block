<?php

declare(strict_types=1);

namespace IPLocationBlock;

/**
 * Plugin kernel.
 *
 * Phase 1 scaffold: constructed with the main plugin file path; register()
 * will wire the WordPress hooks (with the exact legacy callable identities)
 * in a later phase. It is deliberately NOT wired into the bootstrap yet.
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
     *
     * Populated in a later migration phase.
     */
    public function register(): void
    {
    }
}
