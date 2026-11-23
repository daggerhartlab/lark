#!/usr/bin/env bash
#
# Builds a Drupal project with this module installed from a path repository,
# for running the module's tests and code checks in CI.
#
# Usage: build-drupal.sh <module-dir> <project-dir> <core-constraint> [stability]
#   build-drupal.sh lark drupal '^11'
#   build-drupal.sh lark drupal 11.x-dev dev

set -euo pipefail

MODULE_DIR="$(realpath "$1")"
PROJECT_DIR="$2"
CORE="$3"
STABILITY="${4:-stable}"

composer create-project "drupal/recommended-project:${CORE}" "$PROJECT_DIR" \
  --no-install --no-interaction --stability="$STABILITY"

cd "$PROJECT_DIR"

composer config --no-plugins allow-plugins.tbachert/spi false
composer config repositories.module \
  "{\"type\": \"path\", \"url\": \"${MODULE_DIR}\", \"options\": {\"symlink\": true}}"

# A dependency's require-dev is never installed, so require it here.
read_module_composer() {
  php -r '$c = json_decode(file_get_contents($argv[1]), TRUE);
    if ($argv[2] === "name") { echo $c["name"]; exit; }
    foreach ($c["require-dev"] ?? [] as $p => $v) { echo "$p:$v\n"; }' \
    "$MODULE_DIR/composer.json" "$1"
}

composer require --no-update "$(read_module_composer name):*@dev"
composer require --dev --no-update "drupal/core-dev:${CORE}"
read_module_composer require-dev | xargs -r composer require --dev --no-update

composer update --no-interaction --no-progress --with-all-dependencies

mkdir -p web/sites/simpletest/browser_output
