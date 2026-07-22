#!/bin/bash
#
# Grep-guards shared by scripts/release_prepare.sh and .github/workflows/release.yml:
# fail if any unscoped/unprefixed vendor namespace leaked into the parts of the
# tree that ship. Run AFTER `composer install --no-dev --no-scripts` (the pruned,
# release-shaped vendor tree is what these checks are about).
#
set -euo pipefail

SCRIPT_DIR="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
PLUGIN_DIR="$( cd -- "$(dirname "$SCRIPT_DIR")" >/dev/null 2>&1 ; pwd -P )"

fail() {
	echo ""
	echo "verify_scoped_build.sh: ABORT: $1" >&2
	exit 1
}

if [[ ! -f "$PLUGIN_DIR/vendor_prefixed/vendor/autoload.php" ]]; then
	fail "vendor_prefixed/vendor/autoload.php is missing. The php-scoper build (composer 'scope' script, run via post-autoload-dump) did not run or failed."
fi
echo "OK: vendor_prefixed/vendor/autoload.php present."

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
