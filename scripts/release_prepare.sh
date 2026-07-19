#!/bin/bash
#
# Builds the distributable release zip for IP Location Block.
#
# Pipeline (each step aborts the release hard on failure):
#   1. `composer install` (dev) — post-autoload-dump auto-scopes third-party
#      geolocation libraries into vendor_prefixed/ via php-scoper.
#   2. Assert the scoped autoloader exists.
#   3. `composer test` — the unit test suite must be green.
#   4. `composer install --no-dev --no-scripts` — prunes dev-only packages
#      (phpunit, brain/monkey, humbug/php-scoper, sniccowp, and the raw
#      geoip2/ip2location/phpseclib copies used only to feed the scoper).
#      --no-scripts is CRITICAL: php-scoper itself is gone after the prune,
#      so the post-autoload-dump "scope" script must not attempt to re-run.
#   5. Grep-guards: fail the build if any unscoped/unprefixed vendor
#      namespace leaked into the parts of the tree that ship.
#   6. npm build of the React (Beta) admin.
#   7. Zip the release tree, honoring .distignore (rsync --exclude-from
#      semantics — see .distignore's own header comment).
#   8. Restore the working tree to dev state (`composer install`).
#
set -euo pipefail

SCRIPT_DIR="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
PLUGIN_DIR="$( cd -- "$(dirname "$SCRIPT_DIR")" >/dev/null 2>&1 ; pwd -P )"
PLUGINS_ROOT_DIR="$( cd -- "$(dirname "$PLUGIN_DIR")" >/dev/null 2>&1 ; pwd -P )"
PLUGIN_SLUG=$(basename "$PLUGIN_DIR")

cd "$PLUGIN_DIR"

fail() {
	echo ""
	echo "release_prepare.sh: ABORT: $1" >&2
	exit 1
}

step() {
	echo ""
	echo "==> $1"
}

if [[ ! -f "$PLUGIN_DIR/composer.json" ]]; then
	fail "composer.json not found at $PLUGIN_DIR — nothing to build."
fi

step "[1/8] composer install (dev) — triggers the php-scoper build via post-autoload-dump"
composer install --optimize-autoloader

step "[2/8] Verifying the scoped autoloader was generated"
if [[ ! -f "$PLUGIN_DIR/vendor_prefixed/vendor/autoload.php" ]]; then
	fail "vendor_prefixed/vendor/autoload.php is missing after 'composer install'. The php-scoper build (composer 'scope' script, run via post-autoload-dump) did not run or failed."
fi
echo "OK: vendor_prefixed/vendor/autoload.php present."

step "[3/8] Running the unit test suite (composer test)"
if ! composer test; then
	fail "Unit test suite failed. Fix the failing tests before building a release."
fi

step "[4/8] composer install --no-dev --no-scripts (prune dev deps; php-scoper is gone after this, so --no-scripts is mandatory)"
composer install --no-dev --optimize-autoloader --no-scripts

step "[5/8] Grep-guards: unscoped vendor namespaces must never leak into the release"

# (a) Unprefixed `use` statements for the scoped libraries inside vendor_prefixed/
#     itself would mean php-scoper failed to rewrite something.
UNPREFIXED_USES=$(grep -RInE '^use (GeoIp2|MaxMind|IP2Location|phpseclib3)\\' \
	"$PLUGIN_DIR/vendor_prefixed" --include='*.php' 2>/dev/null || true)
if [[ -n "$UNPREFIXED_USES" ]]; then
	fail "Found unprefixed GeoIp2/MaxMind/IP2Location/phpseclib3 'use' statements inside vendor_prefixed/ — php-scoper did not prefix everything:
$UNPREFIXED_USES"
fi
echo "OK (a): no unprefixed 'use' statements in vendor_prefixed/."

# (b) The raw dev-only package directories must not survive the --no-dev
#     prune under vendor/ (only pear/net_dns2 + the composer autoloader
#     itself are runtime deps; everything scoped lives in vendor_prefixed/).
LEFTOVER_DIRS=$(find "$PLUGIN_DIR/vendor" -mindepth 1 -maxdepth 2 -type d \
	\( -iname 'geoip2' -o -iname 'ip2location' -o -iname 'maxmind*' -o -iname 'phpseclib' \) 2>/dev/null || true)
if [[ -n "$LEFTOVER_DIRS" ]]; then
	fail "Found leftover dev-only package directories under vendor/ after --no-dev install:
$LEFTOVER_DIRS"
fi
echo "OK (b): no geoip2/ip2location/maxmind/phpseclib dirs left under vendor/."

# (c) The pruned autoloader map itself must not register any unprefixed
#     scoped-lib namespace (belt-and-braces on top of (b)).
if [[ -f "$PLUGIN_DIR/vendor/composer/autoload_psr4.php" ]]; then
	UNPREFIXED_PSR4=$(grep -nE '(GeoIp2|MaxMind|IP2Location|phpseclib3)\\\\' \
		"$PLUGIN_DIR/vendor/composer/autoload_psr4.php" 2>/dev/null || true)
	if [[ -n "$UNPREFIXED_PSR4" ]]; then
		fail "Found unprefixed GeoIp2/MaxMind/IP2Location/phpseclib3 namespace(s) registered in vendor/composer/autoload_psr4.php:
$UNPREFIXED_PSR4"
	fi
fi
echo "OK (c): vendor/composer/autoload_psr4.php has no unprefixed scoped-lib namespaces."

# (d) src/ must never reference the unscoped vendor namespaces directly —
#     everything must go through IPLocationBlock\Vendor\...
UNSCOPED_SRC_REFS=$(grep -RInE '(^use |[\\ (,=]|new )(GeoIp2|IP2Location|phpseclib3)\\' \
	"$PLUGIN_DIR/src" --include='*.php' 2>/dev/null | grep -v 'IPLocationBlock\\Vendor\\' || true)
if [[ -n "$UNSCOPED_SRC_REFS" ]]; then
	fail "Found direct references to unscoped GeoIp2\\/IP2Location\\/phpseclib3\\ inside src/ (must go through IPLocationBlock\\Vendor\\...):
$UNSCOPED_SRC_REFS"
fi
echo "OK (d): src/ only references scoped IPLocationBlock\\Vendor\\... vendor namespaces."

step "[6/8] npm build (React admin)"
if [[ -f "$PLUGIN_DIR/package.json" ]]; then
	( cd "$PLUGIN_DIR" && npm ci && npm run build )
fi

step "[7/8] Building the release zip (rsync --exclude-from=.distignore staging, then zip)"
if [[ ! -f "$PLUGIN_DIR/.distignore" ]]; then
	fail ".distignore not found at $PLUGIN_DIR — refusing to guess what to exclude."
fi

ZIP_PATH="$PLUGINS_ROOT_DIR/$PLUGIN_SLUG.zip"
STAGE_ROOT="$(mktemp -d)"
STAGE_DIR="$STAGE_ROOT/$PLUGIN_SLUG"
mkdir -p "$STAGE_DIR"

# rsync honors .distignore's leading "/" anchoring exactly as documented in
# its own header comment — this is what keeps vendor_prefixed/composer.json
# and vendor/composer/* from being swept up by the top-level composer.json /
# composer.lock excludes (a bare `zip -x '*composer.json*'` glob, which this
# script used to do, would strip those too).
rsync -a --exclude-from="$PLUGIN_DIR/.distignore" "$PLUGIN_DIR/" "$STAGE_DIR/"

if [ -f "$ZIP_PATH" ]; then
	rm "$ZIP_PATH"
fi

( cd "$STAGE_ROOT" && zip -rq "$ZIP_PATH" "$PLUGIN_SLUG" )
rm -rf "$STAGE_ROOT"

echo "OK: release zip written to $ZIP_PATH"

step "[8/8] Restoring dev state (composer install)"
composer install

echo ""
echo "New version ready: $ZIP_PATH"
