#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ZIP_PATH=""

usage() {
  cat <<EOF
Usage: $(basename "$0") [--zip <path>]

Validates plugin source headers, PHP syntax, bundled Update Machine updater,
readme version parity, and optional release ZIP contents.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --zip)
      ZIP_PATH="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown arg: $1" >&2
      usage
      exit 2
      ;;
  esac
done

REPO_SLUG="$(basename "$(git rev-parse --show-toplevel 2>/dev/null || pwd)")"
if [ -f "${REPO_SLUG}.php" ]; then
  SLUG="$REPO_SLUG"
else
  PLUGIN_FILE="$(find . -maxdepth 1 -name "*.php" -type f ! -name "uninstall.php" ! -name "render.php" | sort | head -1)"
  if [ -z "$PLUGIN_FILE" ]; then
    echo "::error::No main plugin PHP file found" >&2
    exit 1
  fi
  SLUG="$(basename "$PLUGIN_FILE" .php)"
fi

MAIN_FILE="$SLUG.php"
if [ ! -f "$MAIN_FILE" ]; then
  echo "::error::Main plugin file not found: $MAIN_FILE" >&2
  exit 1
fi

if ! head -n 1 "$MAIN_FILE" | grep -q '^<?php'; then
  echo "::error::$MAIN_FILE must start with <?php" >&2
  exit 1
fi

PLUGIN_NAME="$(grep -m1 '^[[:space:]]*\* Plugin Name:' "$MAIN_FILE" | sed 's/.*Plugin Name:[[:space:]]*//' | xargs)"
PLUGIN_VERSION="$(grep -m1 '^[[:space:]]*\* Version:' "$MAIN_FILE" | sed 's/.*Version:[[:space:]]*//' | xargs)"
REQUIRES_PHP="$(grep -m1 '^[[:space:]]*\* Requires PHP:' "$MAIN_FILE" | sed 's/.*Requires PHP:[[:space:]]*//' | xargs)"
TEXT_DOMAIN="$(grep -m1 '^[[:space:]]*\* Text Domain:' "$MAIN_FILE" | sed 's/.*Text Domain:[[:space:]]*//' | xargs)"

if [ -z "$PLUGIN_NAME" ] || [ -z "$PLUGIN_VERSION" ]; then
  echo "::error::$MAIN_FILE must include Plugin Name and Version headers" >&2
  exit 1
fi

if [ -z "$REQUIRES_PHP" ]; then
  echo "::error::$MAIN_FILE must include a non-empty Requires PHP header" >&2
  exit 1
fi

if [ "$TEXT_DOMAIN" != "little-lightbox" ]; then
  echo "::error::Unexpected Text Domain: ${TEXT_DOMAIN:-missing}" >&2
  exit 1
fi

if [ ! -s "includes/um-updater.php" ]; then
  echo "::error::includes/um-updater.php is missing or empty" >&2
  exit 1
fi

if ! head -n 1 "includes/um-updater.php" | grep -q '^<?php'; then
  echo "::error::includes/um-updater.php is not a PHP file" >&2
  exit 1
fi

for admin_asset in \
  assets/admin/index.js \
  assets/admin/index.asset.php \
  assets/admin/style-index.css \
  assets/admin/style-index-rtl.css; do
  if [ ! -s "$admin_asset" ]; then
    echo "::error::Required React admin asset is missing or empty: $admin_asset" >&2
    exit 1
  fi
done

php -r '
  $asset = require "assets/admin/index.asset.php";
  $required = ["wp-components", "wp-element", "wp-i18n"];
  if (!is_array($asset) || !isset($asset["dependencies"], $asset["version"])) {
    fwrite(STDERR, "::error::Invalid React admin asset metadata\n");
    exit(1);
  }
  foreach ($required as $dependency) {
    if (!in_array($dependency, $asset["dependencies"], true)) {
      fwrite(STDERR, "::error::Missing React admin dependency: {$dependency}\n");
      exit(1);
    }
  }
'

EXPECTED_UPDATER_SHA256="0186130818053f3b65820645c5f6d354269701d07eb1e0729a083cbc3525687a"
UPDATER_SHA256="$(sha256sum "includes/um-updater.php" | awk '{print $1}')"
if [ "$UPDATER_SHA256" != "$EXPECTED_UPDATER_SHA256" ]; then
  echo "::error::includes/um-updater.php does not match the tagged um-updater v4.6.0 release" >&2
  exit 1
fi

if [ -f "readme.txt" ]; then
  STABLE_TAG="$(grep -m1 '^Stable tag:' readme.txt | sed 's/Stable tag:[[:space:]]*//' | xargs)"
  if [ "$STABLE_TAG" != "$PLUGIN_VERSION" ]; then
    echo "::error::readme.txt Stable tag ($STABLE_TAG) does not match plugin Version ($PLUGIN_VERSION)" >&2
    exit 1
  fi
fi

if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
  done < <(find . \
    -path './.git' -prune -o \
    -path './release' -prune -o \
    -name '*.php' -type f -print0)
  php tests/feature-telemetry.php
  php tests/admin-interface.php
  php tests/onboarding.php
  php tests/updater-bootstrap.php
else
  echo "::warning::php not found; skipping PHP lint"
fi

if [ -n "$ZIP_PATH" ]; then
  if [ ! -f "$ZIP_PATH" ]; then
    echo "::error::ZIP not found: $ZIP_PATH" >&2
    exit 1
  fi

  ZIP_LIST="$(mktemp)"
  trap 'rm -f "$ZIP_LIST"' EXIT
  if command -v unzip >/dev/null 2>&1; then
    unzip -Z1 "$ZIP_PATH" > "$ZIP_LIST"
  elif command -v python3 >/dev/null 2>&1; then
    python3 - "$ZIP_PATH" > "$ZIP_LIST" <<'PY'
import sys
import zipfile

with zipfile.ZipFile(sys.argv[1]) as zf:
    for name in zf.namelist():
        print(name)
PY
  else
    echo "::error::unzip or python3 is required for ZIP validation" >&2
    exit 1
  fi

  grep -qx "$SLUG/$SLUG.php" "$ZIP_LIST" || {
    echo "::error::ZIP does not contain $SLUG/$SLUG.php" >&2
    exit 1
  }

  grep -qx "$SLUG/includes/um-updater.php" "$ZIP_LIST" || {
    echo "::error::ZIP does not contain $SLUG/includes/um-updater.php" >&2
    exit 1
  }

  ARCHIVED_UPDATER_SHA256="$(unzip -p "$ZIP_PATH" "$SLUG/includes/um-updater.php" | sha256sum | awk '{print $1}')"
  if [ "$ARCHIVED_UPDATER_SHA256" != "$EXPECTED_UPDATER_SHA256" ]; then
    echo "::error::ZIP updater does not match the tagged um-updater v4.6.0 release" >&2
    exit 1
  fi

  FORBIDDEN='(^|/)(\.git|\.github|\.claude|\.openclaw|node_modules|vendor|tests|docs|scripts|src|release)(/|$)|(^|/)(README\.md|\.distignore|\.gitignore|babel\.config\.js|composer\..*|package.*\.json|phpunit\..*|.*\.map|.*\.zip)$'
  if grep -Eq "$FORBIDDEN" "$ZIP_LIST"; then
    echo "::error::ZIP contains repository-only files:" >&2
    grep -E "$FORBIDDEN" "$ZIP_LIST" >&2
    exit 1
  fi

  UNEXPECTED="$(
    awk -v slug="$SLUG" '
      $0 == slug "/" { next }
      $0 == slug "/" slug ".php" { next }
      $0 == slug "/uninstall.php" { next }
      $0 == slug "/readme.txt" { next }
      $0 == slug "/assets/" { next }
      $0 ~ "^" slug "/assets/[^/]+\\.(css|js)$" { next }
      $0 == slug "/assets/admin/" { next }
      $0 ~ "^" slug "/assets/admin/[^/]+\\.(css|js|php)$" { next }
      $0 == slug "/includes/" { next }
      $0 ~ "^" slug "/includes/[^/]+\\.php$" { next }
      { print }
    ' "$ZIP_LIST"
  )"
  if [ -n "$UNEXPECTED" ]; then
    echo "::error::ZIP contains unexpected plugin package files:" >&2
    echo "$UNEXPECTED" >&2
    exit 1
  fi
fi

echo "Validated $PLUGIN_NAME ($SLUG) v$PLUGIN_VERSION"
