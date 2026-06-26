#!/usr/bin/env bash
#
# NavinDBhudiya ProductRecommendation — test runner.
#
# Runs the module's unit + integration suites and phpcs without depending on Warden
# or on `composer`/`phpunit` being on PATH. Uses dev/demo/phpunit-launcher.php to work
# around the host's PHPUnit 10 / Magento `exclude-from-classmap` incompatibility
# (see dev/demo/AUDIT.md). Safe and read-only: it never touches Warden or the database.
#
# Usage:
#   bash dev/demo/run-tests.sh            # unit + integration + phpcs
#   bash dev/demo/run-tests.sh --unit     # unit only
#   bash dev/demo/run-tests.sh --integration
#   bash dev/demo/run-tests.sh --phpcs    # coding standard only
#
set -uo pipefail

# Module root = two levels up from this script (dev/demo -> module root).
MODULE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODULE_DIR="$(dirname "$MODULE_DIR")"

# Walk up to find the Magento root (vendor/autoload.php).
ROOT="$MODULE_DIR"
while [ "$ROOT" != "/" ] && [ ! -f "$ROOT/vendor/autoload.php" ]; do
    ROOT="$(dirname "$ROOT")"
done
if [ ! -f "$ROOT/vendor/autoload.php" ]; then
    echo "ERROR: could not locate Magento root (vendor/autoload.php) above $MODULE_DIR" >&2
    exit 1
fi

LAUNCHER="$MODULE_DIR/dev/demo/phpunit-launcher.php"
PHPCS_BIN="$ROOT/vendor/bin/phpcs"
status=0

run_unit() {
    echo "==> Unit tests"
    php "$LAUNCHER" -c "$MODULE_DIR/Test/Unit/phpunit.xml.dist" || status=1
}

run_integration() {
    echo "==> Integration tests (skeleton)"
    php "$LAUNCHER" -c "$MODULE_DIR/Test/Integration/phpunit.xml.dist" || status=1
}

run_phpcs() {
    echo "==> phpcs (Magento2 ruleset)"
    if [ -x "$PHPCS_BIN" ]; then
        php "$PHPCS_BIN" --standard="$MODULE_DIR/phpcs.xml" "$MODULE_DIR" || status=1
    else
        echo "WARN: $PHPCS_BIN not found; skipping phpcs." >&2
    fi
}

case "${1:-all}" in
    --unit)        run_unit ;;
    --integration) run_integration ;;
    --phpcs)       run_phpcs ;;
    all|"")        run_unit; run_integration; run_phpcs ;;
    *) echo "Unknown option: $1" >&2; exit 2 ;;
esac

if [ "$status" -eq 0 ]; then
    echo "==> ALL GREEN"
else
    echo "==> FAILURES (exit $status)"
fi
exit "$status"
