# Compatibility tests

Run these to confirm the plugin works with a new WordPress release. Everything runs locally and on demand; there's no CI and nothing is scheduled.

**Requirements:** Node 18+ and Podman. Chromium is used from the system if available; otherwise run `npx playwright install chromium` once.

```sh
cd tests
npm install            # first time only
npm test               # both options (about 5 minutes)
npm run test:playground
npm run test:container
```

## Option A: WordPress Playground (`playground/run.sh`)

Boots WordPress in WebAssembly (no PHP, database or containers) and runs the browser tests in `e2e/`.

- Default: latest WordPress on PHP 8.3, about 30 seconds.
- Matrix: `WP_VERSIONS="latest 6.0 nightly" PHP_VERSIONS="7.4 8.3 8.5" bash playground/run.sh`
- Multisite: `MULTISITE=1 bash playground/run.sh` network-activates the plugin and tests Network Admin > Plugins. Multisite doesn't allow a port in the site URL, so the site runs as `http://multisite.test` and the test browser is routed to the local port.
- Extra arguments go to Playwright, for example `bash playground/run.sh --headed`.

## Option B: Containers (`container/run.sh`)

Real WordPress, Apache and MariaDB from the official images. For each target it installs WordPress with `WP_DEBUG` on, activates the plugin with WP-CLI, runs WordPress.org's **Plugin Check** (fails on any ERROR), runs the browser tests, and checks `debug.log`. It also runs **PHPCompatibility** for PHP 7.4+ once per run.

- Default targets: `php8.3` (latest WordPress) and `6.0-php7.4` (the minimum supported).
- Any image tag works: `bash container/run.sh php8.5 beta-php8.4`.
- `KEEP=1` leaves the last site running at http://127.0.0.1:8089 (admin / password).

Plugin Check needs WordPress 6.3+, so it's skipped on older targets. Its "restricted term: plugin" warning is about the plugin's name; existing plugins are grandfathered.

## What the browser tests check (`e2e/plugin.spec.js`)

- The "Last Updated" and "Last Upgraded" columns appear for every plugin and can be toggled in Screen Options.
- Every age color, using fixed ages that never drift: 10 days (green), 8 months (yellow), 18 months (orange), 3 years (red). The `plu-test-fake-wporg` helper plugin answers the WordPress.org lookup for the `plu-test-age-*` stubs, so these run offline.
- A live check: a `woocommerce` stub (a real, frequently updated plugin) should show green. If WooCommerce ever goes 6 months without a release, switch the stub to another busy plugin.
- This plugin's own row shows a date and a colored age from the live WordPress.org lookup.
- A closed plugin (`easy-testimonials`) and a missing plugin (`plu-test-no-such-plugin-xyz`) are flagged in red.
- The "Clear Update Cache" button sits next to the plugin count, clears the cache, and returns you to the same filtered view without the one-time action in the URL. A bad nonce is rejected, and the old settings page is gone.
- No PHP warnings, notices or deprecations come from this plugin, and its script throws no JavaScript errors.

`fixtures/stub-plugins/` holds the empty stub plugins; only their folder names matter. Both runners install everything in that folder, so adding a stub is just adding a folder.

These tests call api.wordpress.org. Set `EXPECT_WPORG=0` to skip the checks that need it when you're offline.

## After a new WordPress release

1. `cd tests && npm test`
2. If everything passes, update `Tested up to:` in `../readme.txt` to the new major.minor version.

Tests run against a copy of the plugin without the files in `../.distignore`, so they cover only what ships to WordPress.org.
