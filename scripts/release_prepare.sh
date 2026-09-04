#!/bin/bash
# Build and verify the exact distributable ZIP without publishing it.

set -euo pipefail

SCRIPT_DIR="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
PLUGIN_DIR="$( cd -- "$(dirname "$SCRIPT_DIR")" >/dev/null 2>&1 ; pwd -P )"
PLUGINS_ROOT_DIR="$( cd -- "$(dirname "$PLUGIN_DIR")" >/dev/null 2>&1 ; pwd -P )"
PLUGIN_SLUG="$(basename "$PLUGIN_DIR")"
ZIP_PATH="$PLUGINS_ROOT_DIR/$PLUGIN_SLUG.zip"
STAGE_ROOT=""
RESTORE_DEV=false

fail() {
	echo "" >&2
	echo "release_prepare.sh: ABORT: $1" >&2
	exit 1
}

step() {
	echo ""
	echo "==> $1"
}

cleanup() {
	local status=$?
	trap - EXIT

	if [[ -n "$STAGE_ROOT" && -d "$STAGE_ROOT" ]]; then
		rm -rf "$STAGE_ROOT"
	fi

	if [[ "$RESTORE_DEV" == true ]]; then
		echo ""
		echo "==> Restoring Composer development dependencies after an interrupted build"
		composer install --no-interaction || status=1
	fi

	exit "$status"
}
trap cleanup EXIT

cd "$PLUGIN_DIR"
[[ -f composer.json ]] || fail "composer.json not found at $PLUGIN_DIR."
[[ -f .distignore ]] || fail ".distignore not found at $PLUGIN_DIR."

step "[1/9] Validating release metadata and dependency declarations"
bash "$SCRIPT_DIR/validate_release.sh"
composer validate --no-check-publish
composer audit --locked

step "[2/9] Installing development dependencies and building scoped libraries"
composer install --optimize-autoloader --no-interaction
RESTORE_DEV=true

step "[3/9] Verifying the generated scoped runtime"
[[ -f vendor_prefixed/vendor/autoload.php ]] || fail "scoped autoloader was not generated."
[[ -f vendor_prefixed/licenses/mikepultz/netdns2/LICENSE ]] || fail "scoped NetDNS2 license was not copied."
php -r 'require "vendor_prefixed/vendor/autoload.php"; exit( class_exists("IPLocationBlock\\Vendor\\NetDNS2\\Resolver") ? 0 : 1 );' \
	|| fail "scoped NetDNS2 Resolver is not autoloadable."
echo "OK: scoped NetDNS2 runtime and license are present."

step "[4/9] Running PHP unit tests"
composer test

step "[5/9] Pruning development-only Composer packages"
composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist --no-interaction
bash "$SCRIPT_DIR/verify_scoped_build.sh"

step "[6/9] Running JavaScript unit tests and building admin assets"
npm ci
npm run test:unit
npm run build

step "[7/9] Staging the release with .distignore"
STAGE_ROOT="$(mktemp -d)"
STAGE_DIR="$STAGE_ROOT/$PLUGIN_SLUG"
mkdir -p "$STAGE_DIR"
rsync -a --exclude-from="$PLUGIN_DIR/.distignore" "$PLUGIN_DIR/" "$STAGE_DIR/"

step "[8/9] Verifying and archiving the staged release"
bash "$SCRIPT_DIR/verify_dist.sh" "$STAGE_DIR"
rm -f "$ZIP_PATH"
( cd "$STAGE_ROOT" && zip -rq "$ZIP_PATH" "$PLUGIN_SLUG" )
[[ -s "$ZIP_PATH" ]] || fail "release ZIP was not created: $ZIP_PATH"
echo "OK: release ZIP written to $ZIP_PATH"

step "[9/9] Restoring Composer development dependencies"
composer install --no-interaction
RESTORE_DEV=false

rm -rf "$STAGE_ROOT"
STAGE_ROOT=""
trap - EXIT

echo ""
echo "Verified release ready: $ZIP_PATH"
