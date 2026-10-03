#!/usr/bin/env bash
# Lints shipped PHP with PHP 7.4 and greps for syntax newer than PHP 7.2.
set -euo pipefail
cd "$(dirname "$0")/.."
PHP74="${PHP74:-$HOME/Library/Application Support/Herd/bin/php74}"
status=0
files=$(find proofage.php src controllers -name '*.php' 2>/dev/null || true)
for f in $files; do
  out=$("$PHP74" -l "$f" 2>&1) || { echo "$out"; status=1; }
done
# Typed properties, arrow functions, null-coalescing assignment (all PHP 7.4+).
if grep -nE '^\s*(public|private|protected)( static)? \??[A-Za-z_\\]+ \$|\bfn\s*\(|\?\?=' $files | grep -vE '^[^:]+:[0-9]+:\s*(public|private|protected) static \$'; then
  echo "PHP 7.4+ syntax found (see above)"; status=1
fi
exit $status
