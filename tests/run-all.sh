#!/usr/bin/env bash
# Runs both test options. Use this before bumping "Tested up to" or releasing.
#   A: Playground — latest + minimum WordPress, on the oldest and newest supported PHP, plus multisite
#   B: Containers — Plugin Check, PHPCompatibility, and browser tests on real WordPress + MariaDB
set -uo pipefail
TESTS=$(cd "$(dirname "$0")" && pwd)

WP_VERSIONS=${WP_VERSIONS:-"latest 6.0"} PHP_VERSIONS=${PHP_VERSIONS:-"7.4 8.5"} bash "$TESTS/playground/run.sh"; a=$?
MULTISITE=1 bash "$TESTS/playground/run.sh" || a=1
bash "$TESTS/container/run.sh"; b=$?

echo
echo "Option A (Playground):  $([ $a = 0 ] && echo PASS || echo FAIL)"
echo "Option B (Containers):  $([ $b = 0 ] && echo PASS || echo FAIL)"
[ $a = 0 ] && [ $b = 0 ]
