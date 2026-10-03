#!/usr/bin/env bash
# Builds build/proofage-<version>.zip ready for PrestaShop Addons.
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(php -r 'define("_PS_VERSION_", "9.0.0"); require "src/Support/ModuleInfo.php"; echo ProofAge\PrestaShop\Support\ModuleInfo::VERSION;')
BUILD=build/proofage
rm -rf build && mkdir -p "$BUILD"
rsync -a --exclude-from=bin/zip-exclude.txt ./ "$BUILD/"
# The shipped composer.json keeps only the package metadata and the runtime autoload section.
# The autoloader is generated with the runtime config bits, which are then dropped as well.
php -r '
$c = json_decode(file_get_contents("composer.json"), true);
$keep = ["name", "description", "type", "license", "authors"];
$shipped = array_intersect_key($c, array_flip($keep));
$shipped["require"] = ["php" => $c["require"]["php"]];
$shipped["autoload"] = $c["autoload"];
$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
$build = $shipped + ["config" => ["prepend-autoloader" => false, "platform-check" => false]];
file_put_contents($argv[1] . "/composer.json", json_encode($build, $flags) . "\n");
file_put_contents($argv[1] . "/composer.shipped.json", json_encode($shipped, $flags) . "\n");
' "$BUILD"
(cd "$BUILD" && composer install --no-dev --classmap-authoritative --no-interaction --quiet && rm -f composer.lock && mv composer.shipped.json composer.json)
find "$BUILD" -type d -exec sh -c '[ -f "$1/index.php" ] || cp index.php "$1/index.php"' _ {} \;
(cd build && zip -qr "proofage-$VERSION.zip" proofage)
echo "build/proofage-$VERSION.zip"
