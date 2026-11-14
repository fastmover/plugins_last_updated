// Browser tests for Plugins Last Updated Column, run against any WordPress at WP_URL.
//
// Expects these plugins to be installed (both runners mount everything in fixtures/stub-plugins):
//   plugins-last-updated-column            (active)  -> on WordPress.org, so it shows a date
//   plu-test-fake-wporg                    (active)  -> test helper: fixed ages for the plu-test-age-* stubs
//   plu-test-age-fresh / -8-months /
//     -18-months / -3-years                (stubs)   -> green / yellow / orange / red, no network needed
//   woocommerce                            (stub)    -> real, frequently updated plugin: live check for green
//   easy-testimonials                      (stub)    -> closed on WordPress.org
//   plu-test-no-such-plugin-xyz            (stub)    -> not on WordPress.org
// Set EXPECT_WPORG=0 if the site cannot reach api.wordpress.org; the WordPress.org-dependent checks are skipped.
// Set WP_ADMIN_PATH=/wp-admin/network/ to run everything against a multisite network's Plugins screen.

const { test, expect } = require( '@playwright/test' );

const USER = process.env.WP_USER || 'admin';
const PASS = process.env.WP_PASS || 'password';
const EXPECT_WPORG = process.env.EXPECT_WPORG !== '0';
const ADMIN = process.env.WP_ADMIN_PATH || '/wp-admin/';

// PHP notices/warnings/deprecations/errors raised from this plugin's own files (WP_DEBUG_DISPLAY is on).
const OUR_PHP_ERROR = /(Warning|Notice|Deprecated|Fatal error|Parse error|Error)(<\/b>)?:.{0,500}?plugins-last-updated-column\/[^\s<]+\.php(<\/b>)? on line/is;

let jsErrors = [];

test.beforeEach( async ( { page } ) => {
	jsErrors = [];
	// Only errors thrown from this plugin's script; WordPress core's own page-transition errors are ignored.
	page.on( 'pageerror', ( err ) => {
		if ( /plugins-last-updated\.js/.test( err.stack || '' ) ) {
			jsErrors.push( err.message );
		}
	} );
	page.on( 'console', ( msg ) => {
		if ( msg.type() === 'error' && /plugins-last-updated\.js/.test( msg.text() + msg.location().url ) ) {
			jsErrors.push( msg.text() );
		}
	} );

	await page.goto( ADMIN );
	if ( page.url().includes( 'wp-login.php' ) ) {
		await page.fill( '#user_login', USER );
		await page.fill( '#user_pass', PASS );
		await page.click( '#wp-submit' );
		await page.waitForURL( /wp-admin/ );
	}
} );

test.afterEach( async ( { page } ) => {
	await expectHealthyPage( page );
	expect( jsErrors, 'JavaScript errors' ).toEqual( [] );
} );

async function expectHealthyPage( page ) {
	const html = await page.content();
	expect( html ).not.toContain( 'There has been a critical error' );
	const match = html.match( OUR_PHP_ERROR );
	expect( match ? match[ 0 ].replace( /<[^>]+>/g, '' ) : null, 'PHP error from this plugin' ).toBeNull();
}

// Rows are found by folder name; WordPress derives data-slug from the plugin name for plugins it does not know.
const row = ( page, slug ) => page.locator( `#the-list tr[data-plugin^="${ slug }/"]` ).first();
const updatedCell = ( page, slug ) => row( page, slug ).locator( 'td.column-sk-plugin-last-updated' );
const upgradedCell = ( page, slug ) => row( page, slug ).locator( 'td.column-sk-plugin-last-upgraded' );

test( 'plugins page shows both columns for every plugin', async ( { page } ) => {
	await page.goto( `${ ADMIN }plugins.php` );

	await expect( page.locator( 'thead th', { hasText: 'Last Updated' } ) ).toHaveCount( 1 );
	await expect( page.locator( 'thead th', { hasText: 'Last Upgraded' } ) ).toHaveCount( 1 );

	const rows = page.locator( '#the-list tr[data-plugin]:not(.plugin-update-tr)' ); // skip "update available" notice rows
	const count = await rows.count();
	expect( count ).toBeGreaterThanOrEqual( 3 );
	await expect( page.locator( '#the-list td.column-sk-plugin-last-updated' ) ).toHaveCount( count );
	await expect( page.locator( '#the-list td.column-sk-plugin-last-upgraded' ) ).toHaveCount( count );

	// Screen Options lets you hide the columns.
	await expect( page.locator( 'input.hide-column-tog[value^="sk-plugin-last-updated"]' ) ).toHaveCount( 1 );
	await expect( page.locator( 'input.hide-column-tog[value^="sk-plugin-last-upgraded"]' ) ).toHaveCount( 1 );
} );

test( 'Last Updated shows a date and a colored age for a plugin on WordPress.org', async ( { page } ) => {
	test.skip( ! EXPECT_WPORG, 'WordPress.org is not reachable from this site' );
	await page.goto( `${ ADMIN }plugins.php` );

	const cell = updatedCell( page, 'plugins-last-updated-column' );
	await expect( cell ).toContainText( /\d{4}-\d{2}-\d{2}/ );
	const age = cell.locator( '.plugin-last-updated-humanreadable' );
	await expect( age ).toHaveText( /\d+ (Years?|Mon\.|Days?)/ );

	// plugins-last-updated.js copies the color to the whole cell.
	const color = await age.getAttribute( 'data-color' );
	expect( [ '#00ff00', '#F2FF00', '#FFA600', '#ff0000' ] ).toContain( color );
	await expect( cell ).not.toHaveCSS( 'background-color', 'rgba(0, 0, 0, 0)' );
} );

// Ages come from the plu-test-fake-wporg helper, so these never drift and need no network.
// Bands: green under 6 months, yellow 6-12 months, orange 1-2 years, red 2+ years.
const AGE_CASES = [
	{ slug: 'plu-test-age-fresh', band: 'up to date (green)', text: /^10 Days$/, rgb: 'rgb(0, 255, 0)' },
	{ slug: 'plu-test-age-8-months', band: 'getting old (yellow)', text: /^[78] Mon\./, rgb: 'rgb(242, 255, 0)' },
	{ slug: 'plu-test-age-18-months', band: 'out of date (orange)', text: /^1 Year /, rgb: 'rgb(255, 166, 0)' },
	{ slug: 'plu-test-age-3-years', band: 'very out of date (red)', text: /^[23] Years /, rgb: 'rgb(255, 0, 0)' },
];

for ( const { slug, band, text, rgb } of AGE_CASES ) {
	test( `Last Updated: ${ band }`, async ( { page } ) => {
		await page.goto( `${ ADMIN }plugins.php` );

		const cell = updatedCell( page, slug );
		await expect( cell ).toContainText( /\d{4}-\d{2}-\d{2}/ );
		await expect( cell.locator( '.plugin-last-updated-humanreadable' ) ).toHaveText( text );
		await expect( cell ).toHaveCSS( 'background-color', rgb );
	} );
}

test( 'Last Updated: live check, a frequently updated real plugin shows green', async ( { page } ) => {
	test.skip( ! EXPECT_WPORG, 'WordPress.org is not reachable from this site' );
	await page.goto( `${ ADMIN }plugins.php` );

	// WooCommerce releases every few weeks. If it ever goes 6 months without one, pick another busy plugin.
	const cell = updatedCell( page, 'woocommerce' );
	await expect( cell ).toContainText( /\d{4}-\d{2}-\d{2}/ );
	await expect( cell ).toHaveCSS( 'background-color', 'rgb(0, 255, 0)' );
} );

test( 'Last Updated flags closed and missing plugins in red', async ( { page } ) => {
	test.skip( ! EXPECT_WPORG, 'WordPress.org is not reachable from this site' );
	await page.goto( `${ ADMIN }plugins.php` );

	const closed = updatedCell( page, 'easy-testimonials' );
	await expect( closed.locator( 'strong.plugin-last-updated-humanreadable' ) ).toHaveText( 'Plugin has been closed!' );
	await expect( closed ).toHaveCSS( 'background-color', 'rgb(255, 0, 0)' );

	const missing = updatedCell( page, 'plu-test-no-such-plugin-xyz' );
	await expect( missing.locator( 'strong.plugin-last-updated-humanreadable' ) ).toHaveText( 'Plugin not found' );
	await expect( missing ).toHaveCSS( 'background-color', 'rgb(255, 0, 0)' );
} );

test( 'Last Upgraded shows "Not Avail." or a date', async ( { page } ) => {
	await page.goto( `${ ADMIN }plugins.php` );
	for ( const slug of [ 'plugins-last-updated-column', 'plu-test-age-fresh', 'easy-testimonials', 'plu-test-no-such-plugin-xyz' ] ) {
		await expect( upgradedCell( page, slug ) ).toContainText( /Not Avail\.|\d{4}-\d{2}-\d{2}/ );
	}
} );

test( 'Clear Update Cache button sits next to the plugin count and clears the cache', async ( { page } ) => {
	// Start on a filtered view to check the redirect keeps you where you were.
	await page.goto( `${ ADMIN }plugins.php?plugin_status=inactive` );

	const button = page.locator( '.tablenav.top .displaying-num a.sk-plu-clear-cache' );
	await expect( button ).toHaveText( 'Clear Update Cache' );
	await expect( page.locator( '.sk-plu-clear-cache-wrap' ) ).toHaveCount( 0 );

	await button.click();
	await expect( page.locator( '.notice-success', { hasText: 'Cache Cleared.' } ) ).toBeVisible();
	await expectHealthyPage( page );

	// Redirected back to the same view, without the one-time action in the URL.
	const url = new URL( page.url() );
	expect( url.pathname ).toBe( `${ ADMIN }plugins.php` );
	expect( url.searchParams.get( 'plugin_status' ) ).toBe( 'inactive' );
	expect( url.searchParams.has( 'sk-plu-clear-cache' ) ).toBe( false );
	expect( url.searchParams.has( '_wpnonce' ) ).toBe( false );
} );

test( 'Clear Update Cache rejects a bad nonce', async ( { page } ) => {
	await page.goto( `${ ADMIN }plugins.php?sk-plu-clear-cache=1&_wpnonce=bad` );
	await expect( page.locator( 'body' ) ).toContainText( 'The link you followed has expired' );
	await expect( page.locator( '.notice-success', { hasText: 'Cache Cleared.' } ) ).toHaveCount( 0 );
} );

test( 'the old settings page is gone', async ( { page } ) => {
	await page.goto( `${ ADMIN }plugins.php?page=plugins-last-updated-settings` );
	await expect( page.locator( 'h1', { hasText: 'Clear Plugin Cache' } ) ).toHaveCount( 0 );
	await expect( page.locator( '#adminmenu a[href*="plugins-last-updated-settings"]' ) ).toHaveCount( 0 );
} );
