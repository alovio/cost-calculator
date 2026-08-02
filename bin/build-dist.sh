#!/usr/bin/env bash
#
# Build the shippable plugin zip. Exclusions come from .distignore, which is
# already the source of truth in this repo — the sibling plugins hard-code
# their excludes in the script, which drifts.
#   Usage: bash bin/build-dist.sh     Output: dist/<slug>.zip
set -euo pipefail

SLUG="alovio-calculator"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist"
DEST="$OUT/$SLUG"

command -v rsync >/dev/null || { echo "ERROR: rsync not found"; exit 1; }

echo "==> Building assets"
( cd "$ROOT" && npm run build --silent )

rm -rf "$DEST"
mkdir -p "$DEST"

EXCLUDES=()
while IFS= read -r line; do
	[ -z "$line" ] && continue
	case "$line" in \#*) continue ;; esac
	EXCLUDES+=( "--exclude=$line" )
done < "$ROOT/.distignore"
EXCLUDES+=( "--exclude=.git" "--exclude=.DS_Store" "--exclude=dist" )

rsync -a "${EXCLUDES[@]}" "$ROOT/" "$DEST/"

# Production Composer autoloader (no dev dependencies).
if [ -f "$ROOT/composer.json" ]; then
	cp "$ROOT/composer.json" "$DEST/"
	[ -f "$ROOT/composer.lock" ] && cp "$ROOT/composer.lock" "$DEST/"
	( cd "$DEST" && composer install --no-dev --optimize-autoloader --quiet )
fi

( cd "$OUT" && rm -f "$SLUG.zip" && zip -rqX "$SLUG.zip" "$SLUG" )
echo "Built: $OUT/$SLUG.zip"
