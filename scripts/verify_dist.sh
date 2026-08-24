#!/bin/bash
# Verify the exact release directory after .distignore has been applied.

set -euo pipefail

SCRIPT_DIR="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
DIST_DIR="${1:-}"

fail() {
	echo "verify_dist.sh: ABORT: $1" >&2
	exit 1
}

[[ -n "$DIST_DIR" ]] || fail "pass the staged plugin directory to verify."
[[ -d "$DIST_DIR" ]] || fail "distribution directory does not exist: $DIST_DIR"
DIST_DIR="$( cd -- "$DIST_DIR" >/dev/null 2>&1 ; pwd -P )"

REQUIRED_FILES=(
	"ip-location-block.php"
	"readme.txt"
	"changelog.txt"
	"license.txt"
	"vendor/autoload.php"
	"vendor_prefixed/vendor/autoload.php"
	"vendor_prefixed/licenses/mikepultz/netdns2/LICENSE"
	"admin/app/build/index.js"
	"admin/app/build/index.asset.php"
	"admin/app/build/style-index.css"
)

for file in "${REQUIRED_FILES[@]}"; do
	[[ -f "$DIST_DIR/$file" ]] || fail "required release file is missing: $file"
done

FORBIDDEN_PATHS=(
	".git" ".github" ".wordpress-org" "scripts" "tests" "bin" "dist"
	"node_modules" "composer.json" "composer.lock" "package.json"
	"package-lock.json" "scoper.inc.php" "playwright.config.js" "admin/app/src"
)

for path in "${FORBIDDEN_PATHS[@]}"; do
	[[ ! -e "$DIST_DIR/$path" ]] || fail "development-only path leaked into the release: $path"
done

if find "$DIST_DIR" -type l -print -quit | grep -q .; then
	fail "symbolic links are not permitted in the release archive."
fi

bash "$SCRIPT_DIR/verify_scoped_build.sh" "$DIST_DIR"

PHP_FILES=0
while IFS= read -r -d '' file; do
	php -l "$file" >/dev/null || fail "PHP syntax check failed: ${file#"$DIST_DIR/"}"
	PHP_FILES=$((PHP_FILES + 1))
done < <(find "$DIST_DIR" -type f -name '*.php' -print0)

[[ "$PHP_FILES" -gt 0 ]] || fail "release contains no PHP files."
echo "OK: verified distribution structure and syntax for $PHP_FILES PHP files."
