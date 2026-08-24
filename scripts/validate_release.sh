#!/bin/bash
# Validate every release-facing version and compatibility declaration.

set -euo pipefail

SCRIPT_DIR="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
PLUGIN_DIR="$( cd -- "$(dirname "$SCRIPT_DIR")" >/dev/null 2>&1 ; pwd -P )"
TAG_VERSION="${1:-v1.4.0}"
VERSION="${TAG_VERSION#v}"

fail() {
	echo "validate_release.sh: ABORT: $1" >&2
	exit 1
}

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-(test|alpha|beta|rc)(\.[0-9]+)?)?$ ]]; then
	fail "unsupported release version '$TAG_VERSION' (expected vX.Y.Z or vX.Y.Z-{test,alpha,beta,rc}[.N])."
fi

BASE_VERSION="${VERSION%%-*}"
if [[ "$VERSION" == "$BASE_VERSION" ]]; then
	IS_PRERELEASE=false
else
	IS_PRERELEASE=true
fi

header_value() {
	local file="$1"
	local field="$2"
	awk -v field="$field" '
		index($0, field ":") {
			value = substr($0, index($0, field ":") + length(field) + 1)
			gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
			print value
			exit
		}
	' "$file"
}

assert_equal() {
	local expected="$1"
	local actual="$2"
	local label="$3"
	[[ "$actual" == "$expected" ]] || fail "$label is '$actual'; expected '$expected'."
}

PLUGIN_HEADER_VERSION="$(header_value "$PLUGIN_DIR/ip-location-block.php" "Version")"
PLUGIN_CONSTANT_VERSION="$(sed -nE "s/.*define\([[:space:]]*'IP_LOCATION_BLOCK_VERSION',[[:space:]]*'([^']+)'.*/\1/p" "$PLUGIN_DIR/ip-location-block.php" | head -n 1)"
MU_VERSION="$(header_value "$PLUGIN_DIR/wp-content/mu-plugins/ip-location-block-mu.php" "Version")"
README_VERSION="$(header_value "$PLUGIN_DIR/readme.txt" "Stable tag")"
PACKAGE_VERSION="$(sed -nE 's/^[[:space:]]*"version":[[:space:]]*"([^"]+)".*/\1/p' "$PLUGIN_DIR/package.json" | head -n 1)"
LOCK_VERSION="$(sed -nE 's/^[[:space:]]*"version":[[:space:]]*"([^"]+)".*/\1/p' "$PLUGIN_DIR/package-lock.json" | head -n 1)"

assert_equal "$BASE_VERSION" "$PLUGIN_HEADER_VERSION" "Plugin header Version"
assert_equal "$BASE_VERSION" "$PLUGIN_CONSTANT_VERSION" "IP_LOCATION_BLOCK_VERSION"
assert_equal "$BASE_VERSION" "$MU_VERSION" "MU helper Version"
assert_equal "$BASE_VERSION" "$README_VERSION" "readme Stable tag"
assert_equal "$BASE_VERSION" "$PACKAGE_VERSION" "package.json version"
assert_equal "$BASE_VERSION" "$LOCK_VERSION" "package-lock.json version"

grep -Fqx "= $BASE_VERSION =" "$PLUGIN_DIR/changelog.txt" || fail "changelog.txt has no '$BASE_VERSION' heading."
grep -Fqx "= $BASE_VERSION =" "$PLUGIN_DIR/readme.txt" || fail "readme.txt has no '$BASE_VERSION' changelog heading."

PLUGIN_WP="$(header_value "$PLUGIN_DIR/ip-location-block.php" "Requires at least")"
README_WP="$(header_value "$PLUGIN_DIR/readme.txt" "Requires at least")"
PLUGIN_PHP="$(header_value "$PLUGIN_DIR/ip-location-block.php" "Requires PHP")"
README_PHP="$(header_value "$PLUGIN_DIR/readme.txt" "Requires PHP")"
COMPOSER_PHP="$(sed -nE 's/^[[:space:]]*"php":[[:space:]]*">=([^"]+)".*/\1/p' "$PLUGIN_DIR/composer.json" | head -n 1)"
COMPOSER_PLATFORM_PHP="$(awk '
	/"platform"[[:space:]]*:/ { platform = 1; next }
	platform && /"php"[[:space:]]*:/ {
		line = $0
		sub(/.*"php"[[:space:]]*:[[:space:]]*"/, "", line)
		sub(/".*/, "", line)
		print line
		exit
	}
' "$PLUGIN_DIR/composer.json")"
TESTED_WP="$(header_value "$PLUGIN_DIR/readme.txt" "Tested up to")"

assert_equal "$PLUGIN_WP" "$README_WP" "readme Requires at least"
assert_equal "$PLUGIN_PHP" "$README_PHP" "readme Requires PHP"
assert_equal "$PLUGIN_PHP" "$COMPOSER_PHP" "Composer PHP requirement"
assert_equal "$PLUGIN_PHP" "$COMPOSER_PLATFORM_PHP" "Composer platform PHP"
[[ -n "$TESTED_WP" ]] || fail "readme Tested up to is empty."

echo "OK: release $VERSION resolves to plugin $BASE_VERSION (prerelease=$IS_PRERELEASE)."
echo "OK: WordPress $PLUGIN_WP+, tested through $TESTED_WP; PHP $PLUGIN_PHP+."

if [[ -n "${GITHUB_OUTPUT:-}" ]]; then
	{
		echo "version=$VERSION"
		echo "base_version=$BASE_VERSION"
		echo "is_prerelease=$IS_PRERELEASE"
	} >> "$GITHUB_OUTPUT"
fi
