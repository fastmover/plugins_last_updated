#!/usr/bin/env bash
# Option B: full check on real WordPress + MariaDB containers (Podman), using the official images.
#
#   tests/container/run.sh                      # latest WordPress (PHP 8.3) and the minimum, 6.0 on PHP 7.4
#   tests/container/run.sh php8.5 6.6-php8.1    # any tags from https://hub.docker.com/_/wordpress
#
# For each target it:
#   1. installs a fresh WordPress with WP_DEBUG on
#   2. activates the plugin with WP-CLI
#   3. runs the official WordPress.org Plugin Check (fails on any ERROR)
#   4. runs the browser tests in tests/e2e against the site
#   5. fails if debug.log has anything from this plugin
# and once overall, runs PHPCompatibility from PHP 7.4 up.
#
# Env: PORT (default 8089), KEEP=1 to leave the last site running for poking around.
set -uo pipefail

TESTS=$(cd "$(dirname "$0")/.." && pwd)
TARGETS=${*:-"php8.3 6.0-php7.4"}
PORT=${PORT:-8089}
SLUG=plugins-last-updated-column
OUT="$TESTS/.out/container"
DB_ENV=(-e WORDPRESS_DB_HOST=127.0.0.1 -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp)

cd "$TESTS"
[ -d node_modules ] || npm install --no-audit --no-fund
DIST=$("$TESTS/lib/build-dist.sh")
mkdir -p "$OUT"

# The plugin, plus every folder in fixtures/stub-plugins as an installed plugin.
PLUGIN_MOUNTS=(-v "$DIST:/var/www/html/wp-content/plugins/$SLUG:ro")
for d in "$TESTS"/fixtures/stub-plugins/*/; do
	d=${d%/}
	PLUGIN_MOUNTS+=(-v "$d:/var/www/html/wp-content/plugins/$(basename "$d"):ro")
done

POD=
teardown() {
	if [ -n "$POD" ]; then
		podman pod rm -f "$POD" >/dev/null 2>&1
		podman volume rm -f "$POD-wp" >/dev/null 2>&1
		POD=
	fi
}
trap teardown EXIT

step() { echo "--- $*"; }

# PHPCompatibility: does the code use anything unavailable in PHP 7.4+ (or removed in newer PHP)?
phpcompat() {
	step "PHPCompatibility (PHP 7.4 and up)"
	podman run --rm -v "$TESTS/tools:/tools" -v "$DIST:/src:ro" -w /tools docker.io/library/composer:2 \
		sh -c 'composer install --quiet --no-interaction --no-progress >/dev/null && vendor/bin/phpcs -p --colors --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- --extensions=php /src'
}

run_target() {
	local tag=$1 php cli_tag
	php=$(grep -o 'php[0-9.]*' <<<"$tag" || true)
	cli_tag=cli${php:+-$php}
	POD="plu-test-${tag//./-}"
	local wp="$POD-wp" log="$OUT/$tag"
	mkdir -p "$log"

	wpcli() {
		podman run --rm --pod "$POD" --user 33:33 -v "$wp:/var/www/html" "${PLUGIN_MOUNTS[@]}" "${DB_ENV[@]}" \
			"docker.io/library/wordpress:$cli_tag" wp "$@"
	}

	step "Starting WordPress ($tag) and MariaDB"
	podman pod rm -f "$POD" >/dev/null 2>&1; podman volume rm -f "$wp" >/dev/null 2>&1
	podman pod create --name "$POD" -p "127.0.0.1:$PORT:80" >/dev/null || return 1
	podman run -d --pod "$POD" --name "$POD-db" \
		-e MARIADB_RANDOM_ROOT_PASSWORD=1 -e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp \
		docker.io/library/mariadb:11 >/dev/null || return 1
	podman run -d --pod "$POD" --name "$POD-web" -v "$wp:/var/www/html" "${PLUGIN_MOUNTS[@]}" "${DB_ENV[@]}" \
		-e WORDPRESS_DEBUG=1 \
		-e WORDPRESS_CONFIG_EXTRA="define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', true );" \
		"docker.io/library/wordpress:$tag" >/dev/null || return 1

	for _ in $(seq 1 90); do
		wpcli db check >/dev/null 2>&1 && break
		sleep 2
	done

	step "Installing WordPress"
	wpcli core install --url="http://127.0.0.1:$PORT" --title="Plugin Test" \
		--admin_user=admin --admin_password=password --admin_email=admin@example.com --skip-email || return 1
	echo "WordPress $(wpcli core version), $(wpcli eval 'echo "PHP " . PHP_VERSION;')"

	step "Activating the plugin"
	wpcli plugin activate "$SLUG" plu-test-fake-wporg 2>&1 | tee "$log/activate.txt"
	grep -q "^Success" "$log/activate.txt" || return 1

	step "Plugin Check (WordPress.org's plugin review tool)"
	local pc_ok=1
	if [ "$(wpcli eval 'echo version_compare( get_bloginfo( "version" ), "6.3", "<" ) ? 1 : 0;')" = 1 ]; then
		echo "skipped: Plugin Check needs WordPress 6.3+ (it runs on the newer targets)"
	else
		wpcli plugin install plugin-check --activate >/dev/null 2>&1 || { echo "Could not install Plugin Check"; return 1; }
		wpcli plugin check "$SLUG" --format=csv --fields=line,column,type,code,message \
			--require=./wp-content/plugins/plugin-check/cli.php >"$log/plugin-check.csv" 2>&1
		cat "$log/plugin-check.csv"
		grep -q ',ERROR,' "$log/plugin-check.csv" && pc_ok=0
		wpcli plugin deactivate plugin-check >/dev/null 2>&1
	fi

	step "Browser tests"
	local e2e_ok=1
	PW_OUTPUT_DIR="$log/test-results" WP_URL="http://127.0.0.1:$PORT" npx playwright test || e2e_ok=0

	step "debug.log entries from this plugin"
	podman exec "$POD-web" sh -c 'cat /var/www/html/wp-content/debug.log 2>/dev/null' >"$log/debug.log"
	local log_ok=1
	if grep -E "plugins/$SLUG/" "$log/debug.log"; then log_ok=0; else echo "none"; fi

	[ "$pc_ok" = 1 ] || echo "FAILED: Plugin Check reported errors"
	[ "$e2e_ok" = 1 ] || echo "FAILED: browser tests"
	[ "$log_ok" = 1 ] || echo "FAILED: PHP errors in debug.log"
	[ "$pc_ok$e2e_ok$log_ok" = 111 ]
}

results=()
failed=0

phpcompat && results+=("PASS  PHPCompatibility 7.4+") || { results+=("FAIL  PHPCompatibility 7.4+"); failed=1; }

for tag in $TARGETS; do
	echo; echo "=== Container: wordpress:$tag ==="
	if run_target "$tag"; then results+=("PASS  wordpress:$tag"); else results+=("FAIL  wordpress:$tag"); failed=1; fi
	if [ "${KEEP:-0}" = 1 ] && [ "$tag" = "${TARGETS##* }" ]; then
		echo "KEEP=1: site left running at http://127.0.0.1:$PORT (admin / password). Remove with: podman pod rm -f $POD; podman volume rm $POD-wp"
		POD=
	else
		teardown
	fi
done

echo; echo "=== Container summary (logs in $OUT) ==="; printf '%s\n' "${results[@]}"
exit $failed
