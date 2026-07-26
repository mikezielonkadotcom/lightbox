#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

RELEASE_DIR="${RELEASE_DIR:-release}"
REPO_SLUG="$(basename "$(git rev-parse --show-toplevel 2>/dev/null || pwd)")"
SLUG="${PLUGIN_SLUG:-}"

if [ -z "$SLUG" ]; then
  if [ -f "${REPO_SLUG}.php" ]; then
    SLUG="$REPO_SLUG"
  else
    PLUGIN_FILE="$(find . -maxdepth 1 -name "*.php" -type f ! -name "uninstall.php" ! -name "render.php" | sort | head -1)"
    if [ -n "$PLUGIN_FILE" ]; then
      SLUG="$(basename "$PLUGIN_FILE" .php)"
    else
      SLUG="$REPO_SLUG"
    fi
  fi
fi

VERSION="${PLUGIN_VERSION:-}"
if [ -z "$VERSION" ]; then
  if [ -n "${GITHUB_REF:-}" ] && [[ "$GITHUB_REF" =~ refs/tags/v(.+) ]]; then
    VERSION="${BASH_REMATCH[1]}"
  elif [ -f "$SLUG.php" ]; then
    VERSION="$(grep -m1 '^[[:space:]]*\* Version:' "$SLUG.php" | sed 's/.*Version:[[:space:]]*//' | xargs)"
  else
    VERSION="dev"
  fi
fi

DEST="$RELEASE_DIR/$SLUG"
rm -rf "$RELEASE_DIR"
mkdir -p "$DEST"

export RELEASE_DIR SLUG
python3 <<'PY'
import fnmatch
import os
import shutil
import stat
import zipfile

release_dir = os.environ["RELEASE_DIR"]
slug = os.environ["SLUG"]
dest = os.path.join(release_dir, slug)

default_patterns = [
    ".git/",
    ".github/",
    ".githooks/",
    ".claude/",
    ".openclaw/",
    "node_modules/",
    "vendor/",
    "tests/",
    "docs/",
    "scripts/",
    "src/",
    "release/",
    "*.map",
    "*.zip",
    "babel.config.js",
    "composer.*",
    "package*.json",
    "phpunit.*",
    ".distignore",
    ".gitignore",
    "README.md",
    ".DS_Store",
    "Thumbs.db",
]

if os.path.exists(".distignore"):
    with open(".distignore", "r", encoding="utf-8") as fh:
        patterns = [
            line.strip()
            for line in fh
            if line.strip() and not line.lstrip().startswith("#")
        ]
else:
    patterns = default_patterns

def is_ignored(rel_path):
    rel_path = rel_path.replace(os.sep, "/")
    name = os.path.basename(rel_path)
    for pattern in patterns:
        pattern = pattern.replace(os.sep, "/")
        if pattern.endswith("/"):
            directory = pattern.rstrip("/")
            if rel_path == directory or rel_path.startswith(pattern):
                return True
        elif (
            fnmatch.fnmatch(rel_path, pattern)
            or fnmatch.fnmatch(name, pattern)
            or rel_path == pattern
        ):
            return True
    return False

for root, dirs, files in os.walk("."):
    rel_root = os.path.relpath(root, ".")
    if rel_root == ".":
        rel_root = ""

    dirs[:] = [
        directory
        for directory in dirs
        if not is_ignored(os.path.join(rel_root, directory).strip("/"))
    ]

    for filename in files:
        rel_path = os.path.join(rel_root, filename).strip("/")
        if is_ignored(rel_path):
            continue
        src = os.path.join(root, filename)
        target = os.path.join(dest, rel_path)
        os.makedirs(os.path.dirname(target), exist_ok=True)
        shutil.copy2(src, target)

zip_path = os.path.join(release_dir, f"{slug}.zip")
with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
    zf.write(dest, f"{slug}/")
    for root, dirs, files in os.walk(dest):
        dirs.sort()
        files.sort()
        rel_root = os.path.relpath(root, release_dir).replace(os.sep, "/")
        for directory in dirs:
            zf.write(os.path.join(root, directory), f"{rel_root}/{directory}/")
        for filename in files:
            path = os.path.join(root, filename)
            arcname = os.path.relpath(path, release_dir).replace(os.sep, "/")
            info = zipfile.ZipInfo.from_file(path, arcname)
            if os.access(path, os.X_OK):
                info.external_attr = (stat.S_IFREG | 0o755) << 16
            zf.writestr(info, open(path, "rb").read(), compress_type=zipfile.ZIP_DEFLATED)
PY

if [ ! -f "$DEST/$SLUG.php" ]; then
  echo "::error::Plugin file missing from release package: $DEST/$SLUG.php" >&2
  exit 1
fi

echo "Built $RELEASE_DIR/$SLUG.zip for $SLUG v$VERSION"

if [ -n "${GITHUB_OUTPUT:-}" ]; then
  {
    echo "slug=$SLUG"
    echo "version=$VERSION"
    echo "zip=$RELEASE_DIR/$SLUG.zip"
  } >> "$GITHUB_OUTPUT"
fi
