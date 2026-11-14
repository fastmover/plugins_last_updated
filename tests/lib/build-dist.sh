#!/usr/bin/env bash
# Copies the plugin, minus everything in .distignore, to tests/.out/dist/plugins-last-updated-column
# so tests run against exactly what would ship to WordPress.org. Prints the output path.
set -euo pipefail
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
OUT="$ROOT/tests/.out/dist/plugins-last-updated-column"
mkdir -p "$OUT"
rsync -a --delete --exclude-from="$ROOT/.distignore" "$ROOT/" "$OUT/"
echo "$OUT"
