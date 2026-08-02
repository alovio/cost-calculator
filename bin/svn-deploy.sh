#!/usr/bin/env bash
#
# Deploy the built plugin to its WordPress.org SVN repo.
#   Prereqs:  brew install svn   +   bash bin/build-dist.sh   (dist/ must exist)
#   Usage:    bash bin/svn-deploy.sh [wporg-username]      (default: 74h1r)
#
# It prepares a local SVN working copy (trunk + tags/<version> + assets) and
# STOPS before committing. Review `svn status`, then run the printed `svn ci`.
# Publishing to wp.org is effectively irreversible, so the commit is left to you.
#
set -euo pipefail

SLUG="alovio-calculator"
WPUSER="${1:-74h1r}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist/$SLUG"
WO="$ROOT/.wporg"
WC="${SVN_WC:-/tmp/$SLUG-svn}"

command -v svn >/dev/null || { echo "ERROR: svn not found — run: brew install svn"; exit 1; }
[ -d "$DIST" ] || { echo "ERROR: build first → bash bin/build-dist.sh"; exit 1; }

VER="$(grep -i '^Stable tag:' "$ROOT/readme.txt" | awk '{print $3}')"
echo "Slug=$SLUG  Version=$VER  User=$WPUSER  WC=$WC"

# Fresh checkout of the (currently empty) repo skeleton.
rm -rf "$WC"
svn co "https://plugins.svn.wordpress.org/$SLUG/" "$WC"

# trunk = exact mirror of the built plugin.
rsync -a --delete --exclude='.svn' --exclude='.DS_Store' "$DIST/" "$WC/trunk/"

# tags/<version> = a full copy of this release (wp.org serves the Stable tag).
mkdir -p "$WC/tags/$VER"
rsync -a --delete --exclude='.svn' --exclude='.DS_Store' "$DIST/" "$WC/tags/$VER/"

# assets/ = listing graphics (icon, banner, screenshots) — NOT inside the plugin.
mkdir -p "$WC/assets"
cp "$WO/icon-256x256.png" "$WO/icon-128x128.png" \
   "$WO/banner-1544x500.png" "$WO/banner-772x250.png" \
   "$WO"/screenshot-*.png "$WC/assets/"

cd "$WC"
# Schedule adds for everything new; schedule removes for anything deleted upstream.
svn add --force trunk tags assets >/dev/null 2>&1 || true
svn status | awk '/^!/{print $2}' | xargs -r svn rm >/dev/null 2>&1 || true

echo
echo "=== svn status (what WILL be committed) ==="
svn status
echo
echo "Review the list above (trunk/ + tags/$VER/ + assets/ should all be 'A')."
echo "To PUBLISH the plugin, run:"
echo
echo "    cd \"$WC\" && svn ci -m \"Release $VER\" --username $WPUSER"
echo
echo "(svn will prompt for your wordpress.org account password.)"
