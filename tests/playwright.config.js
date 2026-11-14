// Shared by both test runners. They set WP_URL (and WP_USER / WP_PASS) before calling `npx playwright test`.
const fs = require( 'fs' );
const { defineConfig } = require( '@playwright/test' );

// Use the system Chromium when there is one, so `npx playwright install` isn't needed.
const systemChromium = [ process.env.CHROMIUM_PATH, '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome' ]
	.find( ( p ) => p && fs.existsSync( p ) );

module.exports = defineConfig( {
	testDir: './e2e',
	// Each runner gets its own folder so Option A and Option B can run at the same time.
	outputDir: process.env.PW_OUTPUT_DIR || './.out/test-results',
	timeout: 120000,
	workers: 1,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: process.env.WP_URL || 'http://127.0.0.1:9400',
		launchOptions: {
			...( systemChromium ? { executablePath: systemChromium } : {} ),
			// e.g. "MAP multisite.test 127.0.0.1:9400": lets a portless site URL (required by multisite) reach a local port.
			args: process.env.PW_HOST_MAP ? [ `--host-resolver-rules=${ process.env.PW_HOST_MAP }` ] : [],
		},
		trace: 'retain-on-failure',
	},
} );
