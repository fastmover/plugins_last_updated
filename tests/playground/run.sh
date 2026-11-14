#!/usr/bin/env bash
# Option A: quick smoke test in WordPress Playground (WordPress + PHP compiled to WebAssembly).
# Needs only Node. Each WordPress/PHP combination boots in a few seconds.
#
#   tests/playground/run.sh                         # latest WordPress on PHP 8.3
#   WP_VERSIONS="latest 6.0" PHP_VERSIONS="7.4 8.3 8.5" tests/playground/run.sh
#   MULTISITE=1 tests/playground/run.sh             # network-activated, tested on Network Admin > Plugins
#
# Extra arguments are passed to `playwright test` (e.g. --headed, -g "settings").
set -uo pipefail

TESTS=$(cd "$(dirname "$0")/.." && pwd)
WP_VERSIONS=${WP_VERSIONS:-latest}
PHP_VERSIONS=${PHP_VERSIONS:-8.3}
PORT=${PORT:-9400}
MULTISITE=${MULTISITE:-0}
SITE_URL="http://127.0.0.1:$PORT"
CURL_ARGS=()
if [ "$MULTISITE" = 1 ]; then
	# Multisite doesn't allow a port in the site URL, so the site is http://multisite.test
	# and the test browser (and curl) are pointed at the local port behind the scenes.
	BLUEPRINT=playground/blueprint-multisite.json
	SITE_URL="http://multisite.test"
	CURL_ARGS=(--connect-to "multisite.test:80:127.0.0.1:$PORT")
	export WP_ADMIN_PATH=/wp-admin/network/
	export PW_HOST_MAP="MAP multisite.test 127.0.0.1:$PORT"
else
	BLUEPRINT=playground/blueprint.json
fi
LOGS="$TESTS/.out/playground"

cd "$TESTS"
[ -d node_modules ] || npm install --no-audit --no-fund
DIST=$("$TESTS/lib/build-dist.sh")
mkdir -p "$LOGS"

# Every folder in fixtures/stub-plugins is mounted as an installed plugin.
STUB_MOUNTS=()
for d in "$TESTS"/fixtures/stub-plugins/*/; do
	d=${d%/}
	STUB_MOUNTS+=(--mount="$d:/wordpress/wp-content/plugins/$(basename "$d")")
done

SERVER_PID=
cleanup() { [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null; wait 2>/dev/null; }
trap cleanup EXIT

results=()
failed=0
for wp in $WP_VERSIONS; do
	for php in $PHP_VERSIONS; do
		label="wp-$wp php-$php$([ "$MULTISITE" = 1 ] && echo " multisite")"
		log="$LOGS/server-$wp-$php.log"
		echo "=== Playground: $label ==="

		# One worker: parallel workers can corrupt Playground's in-memory SQLite database.
		npx wp-playground-cli server --port="$PORT" --wp="$wp" --php="$php" --workers=1 --site-url="$SITE_URL" \
			--blueprint="$BLUEPRINT" \
			--mount="$DIST:/wordpress/wp-content/plugins/plugins-last-updated-column" \
			"${STUB_MOUNTS[@]}" \
			--login >"$log" 2>&1 &
		SERVER_PID=$!

		ready=0
		for _ in $(seq 1 180); do
			if ! kill -0 "$SERVER_PID" 2>/dev/null; then break; fi
			if grep -q "Ready!\|WordPress is running on" "$log" && curl -fsS -o /dev/null "${CURL_ARGS[@]}" "$SITE_URL/"; then ready=1; break; fi
			sleep 1
		done

		if [ "$ready" = 1 ]; then
			PW_OUTPUT_DIR="$LOGS/test-results-$wp-$php" WP_URL="$SITE_URL" npx playwright test "$@" && status=PASS || status=FAIL
		else
			echo "Playground did not start. Last lines of $log:"; tail -20 "$log"
			status=FAIL
		fi

		kill "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID" 2>/dev/null; SERVER_PID=
		[ "$status" = PASS ] || failed=1
		results+=("$status  $label")
	done
done

echo; echo "=== Playground summary ==="; printf '%s\n' "${results[@]}"
exit $failed
