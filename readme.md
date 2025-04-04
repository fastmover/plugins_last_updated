![Plugins Last Updated Column Banner](/assets/banner-772x250.png)
Plugins Last Updated Column
=
---
This plugin adds two columns to the plugin's page in WordPress's admin to show when each plugin was "Last Updated" by the developer and when the plugin was "Last Upgraded" on the site. The "Last Updated" column will also show "Plugin not found" OR "Plugin has been closed!" if the plugin isn't on the repo anymore or has been closed.

The first time you load the plugins page, it will load very slowly due to many API calls made to WordPress.org in order to retrieve the last updated information.

This plugin makes 1 API call for each plugin installed. This data is cached for 24 hours, unless you manually clear the cache clearing via Admin Menu > Plugins > Plugin Columns.


The idea for this plugin's functionality and the artwork was by [Karissa Skirmont](http://karissaskirmont.com "karissaskirmont.com")'s of [Profoundly Purple](http://profoundlypurple.com "profoundlypurple.com").
Plugin Developed by [Steven Kohlmeyer](http://stevenkohlmeyer.com "stevenkohlmeyer.com") with contributions by [Michael Preslar (http://drzimp.com "drzimp.com")].

[Plugin Page](https://wordpress.org/plugins/plugins-last-updated-column/#developers "Plugins Last Updated Column")

Artwork compliments of [Karissa Skirmont](http://kissaskreations.com/ "Kissa's Kreations").

&nbsp;
&nbsp;

![Plugins Last Updated Column Screenshot 1](/assets/screenshot-1.png)

&nbsp;
&nbsp;

![Plugins Last Updated Column Screenshot 2](/assets/screenshot-2.png)

&nbsp;
&nbsp;

![Plugins Last Updated Column Screenshot 3](/assets/screenshot-3.png)

---

Changelog
=
* 0.1.5
  * Last Updated Column now displays if the plugin has been closed or isn't on the repo
  * getPluginsLastUpdated() now respects WP_DEBUG
  * Updated plugin description
* 0.1.4
  * Fixed security issue
* 0.1.3
  * Fixed debug warnings
* 0.1.2
  * Version number bump
* 0.1.1
  * Version number bump
* 0.1.0
  * Version number bump
* 0.0.8
  * Version number bump
* 0.0.7
  * Added support for Multisite
* 0.0.6
  * PHP 5.2 Compliance - Calculated months may be just a bit off if you're running PHP 5.2
  * Changed caching from 24 hours to 30 minutes
  * Added a clear cache option in settings page: Admin > Plugins > Plugin Columns
  * Fixed screen options not hiding columns or saving
  * Fixed errors outputting if WordPress's API cannot be reached.
