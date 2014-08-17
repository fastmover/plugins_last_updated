=== Plugin Name ===
Contributors: Fastmover
Tags: access, access restrictions, content type access, content type restrictions, read access
Requires at least: 3.7
Tested up to: 3.9.2
Stable tag: 0.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin adds a column to the admin plugin's page to show when each plugin was last updated.

== Description ==

This plugin adds a column to the plugin's page in Wordpress's admin to show when each plugin was last updated. This causes the plugins page to load very slowly on the first page load due to many API calls made to wordpress.org in order to retrieve the last updated information.  This plugin makes 1 API call for each plugin installed.  This data is cached for 24 hours. The functionality of this plugin was entirely [Karissa Skirmont](http://kissaskreations.com/ "Kissa's Kreations")'s idea.

== Installation ==

1. Install this plugin either via the WordPress.org plugin directory, or by uploading the files to your server
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's it. You're ready to go!


== Changelog ==

= 0.0.1 =
* Plugin adds a last updated column to the plugins page of the admin.