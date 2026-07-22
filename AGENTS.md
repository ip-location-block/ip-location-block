# AGENTS.md

Guidance for AI coding agents working in this repository.

## Project Overview

IP Location Block — a WordPress plugin (fork of the abandoned "IP Geo Block") that blocks access based on IP geolocation. Requires PHP 8.1+. This repo is its own git repository, nested inside the iplocationblock monorepo (the monorepo root's `CLAUDE.md` at `../../../CLAUDE.md` covers the Docker/WP-CLI development environment; WordPress runs at http://wp.iplocationblock.test).

Version 1.4.0 is a major restructuring: the runtime moved from legacy `classes/` into PSR-4 `src/`, and a React admin (Beta) was added alongside the frozen classic admin.

## Commands

```bash
# PHP
composer install          # also auto-runs php-scoper via post-autoload-dump ("scope" script)
composer test             # PHPUnit unit suite (no WordPress needed — Brain Monkey stubs WP)
vendor/bin/phpunit tests/Unit/Core/ValidatorTest.php              # single test file
vendor/bin/phpunit --filter test_method_name                      # single test method

# JavaScript (React admin)
npm run build             # wp-scripts build → admin/app/build/
npm run start             # watch mode
npm run test:unit         # Jest via wp-scripts (co-located *.test.js in admin/app/src/)
npm run lint:js
npm run format

# Release
scripts/release_prepare.sh  # full pipeline: scope → test → prune dev deps → grep-guards → npm build → zip (honors .distignore)
scripts/release_make.sh     # release_prepare + git tag + WordPress.org SVN deploy
```

The PHPUnit suite runs entirely without WordPress: `tests/bootstrap.php` defines plugin constants and a minimal `WP_Error`; WP functions are stubbed per-test with Brain Monkey. Tests live in `tests/Unit/` mirroring `src/` namespaces, with fakes in `tests/Fakes/` and provider response fixtures in `tests/fixtures/`.

CI (`.github/workflows/release.yml`, on `v*` tags) enforces version consistency across the plugin header `Version:`, the `IP_LOCATION_BLOCK_VERSION` constant, and readme.txt `Stable tag:` — all three must match before tagging a release.

## Architecture

### Three layers: src/ (live), classes/ (tombstones), compat/ (bridge)

- **`src/`** (PSR-4 `IPLocationBlock\`) is the live implementation. `ip-location-block.php` boots `\IPLocationBlock\Plugin`, which registers activation, upgrade, `plugins_loaded`, `rest_api_init`, and admin hooks.
- **`classes/`** contains only `@deprecated` tombstones — each file just requires `compat/bootstrap.php`. They exist because third parties, the frozen classic admin, deployed mu-plugin copies, and uninstall.php may `require` them directly. Never add logic here.
- **`compat/`** restores the legacy class names. `legacy-aliases.php` maps them via `class_alias` (e.g. `IP_Location_Block` → `Core\Validator`, `IP_Location_Block_Opts` → `Settings\Options`, `IP_Location_Block_Util` → `Support\Util`, `IP_Location_Block_Rest` → `Rest\RestApi`). The provider-related legacy classes (`IP_Location_Block_Provider`, `IP_Location_Block_API`, `IP_Location_Block_API_Cache`) are real facade classes in `compat/`, not aliases, because their behavior diverges.

**Dependency direction is compat → src, never the reverse.** `tests/Unit/ArchitectureTest.php` machine-enforces this: it tokenizes every file in `src/` and fails if any references a legacy `IP_Location_Block*` name outside a small allowlist.

### Frozen API contracts (do not rename)

These identities are consumed by deployed mu-plugin copies (which `remove_action()` them by exact literal), drop-ins, and third-party code. They must stay under their legacy/global names even though the implementation is namespaced:

- Global functions in `ip-location-block.php`: `ip_location_block_activate`, `_deactivate`, `_update`, `_upgrader_process_complete`.
- Hook callables registered by `src/Plugin.php` as legacy strings: `array( 'IP_Location_Block', 'get_instance' )` on `plugins_loaded`, `array( 'IP_Location_Block_Rest', 'register_routes' )` on `rest_api_init`.
- `wp-content/mu-plugins/ip-location-block-mu.php` is the mu-plugin **template**; `Settings\Options::setup_validation_timing()` copies it to `WPMU_PLUGIN_DIR` and `Options::upgrade()` refreshes deployed copies. Its header documents the contract that must stay in sync with `src/Plugin.php`.
- Public filters `ip-location-block-login/-admin/-public` etc. and the `IP_Location_Block` class name are the guaranteed public API for all 1.x (see `wp-content/drop-in-sample.php`).

### src/ layout

- `Core/` — `Validator` (the request-validation pipeline heart, singleton, option access), `HookLoader`, `Activator` (multisite-aware activation).
- `Settings/Options.php` — option schema/defaults, upgrade migration (including from the old `ip_geo_block_settings`), mu-plugin deployment.
- `Geolocation/` — `GeolocationResolver` (cache-first provider loop and the precision gate), `IpCacheRepository` (encrypted IP cache), `LocationResult` value object.
- `Providers/` — sealed provider subsystem (below).
- `Rest/RestApi.php` — the `ip-location-block/v1` REST namespace (~40 routes) backing the React admin; permission is `manage_options`/`manage_network_options` + `wp_rest` nonce. Handlers delegate to src/ services, or `require_once` the frozen classic admin files to reuse the exact classic sanitizer/ajax logic (e.g. `sanitize_options`, `search_ip`).
- `Logging/Logs.php` — validation-log tables, created in code (no schema files; `database/` is empty).
- `Cron/Scheduler.php`, `Diagnostics/`, `Support/` (`Util`, `Ip` CIDR matcher, `Dns`, `FileSystem`), `Admin/ReactAdmin.php`.

### Sealed provider registry — deliberate, do not "fix"

`src/Providers/ProviderRegistry` is `final` with a private `const PROVIDERS` list and **no registration method and no filters**. This seals the monetization core: only `NativeProvider` (api.iplocationblock.com) implements `PrecisionLocationSource` (city/state precision). External provider registration was removed in 1.4.0 — `IP_Location_Block_Provider::register_addon()` is a deprecated no-op and third-party `IP_Location_Block_API` subclasses are inert. Do not add extensibility filters to the provider subsystem. Other providers: remote (ipinfodb, ipinfo.io, ip-api, ipstack) and local DBs (IP2Location, MaxMind GeoLite2). `LegacyMeta` reshapes the registry into the legacy provider-meta payloads for REST and the compat facade.

### Two admin UIs

- `admin/legacy/` — the frozen classic admin (`IP_Location_Block_Admin` + ajax + tab files), the default UI. Never namespaced, treated as read-only except for bug fixes.
- `admin/app/` — React (Beta) admin built with `@wordpress/scripts`; opt-in submenu (slug `ip-location-block-beta`) registered by `src/Admin/ReactAdmin.php`, which localizes `ipLocationBlockBeta` (REST root/nonce). Source in `admin/app/src/` (tabs, components, lib), built output in `admin/app/build/`, bundled Leaflet in `admin/app/vendor/`.

### Vendored/scoped dependencies

Third-party geo libraries (geoip2/maxmind, ip2location, phpseclib) are dev-dependencies that php-scoper rewrites into `vendor_prefixed/` under the `IPLocationBlock\Vendor\` prefix (config: `scoper.inc.php`, map: `bin/build-scoped.php`; auto-runs on `composer install`). **Code in `src/` must reference these libraries only via `IPLocationBlock\Vendor\...`** — `release_prepare.sh` grep-guards fail the build otherwise. The only unscoped runtime Composer dependency is `pear/net_dns2`. The main plugin file loads the scoped autoloader first, then `vendor/autoload.php`.
