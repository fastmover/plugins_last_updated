<?php
/**
 * Plugin Name: Plugins Last Updated Column
 * Plugin URI: http://stevenkohlmeyer.com/plugins-last-updated-column/
 * Description: This plugin adds a 'Last Updated' column to the admin plugins page.
 * Version: 0.0.3
 * Author: Fastmover
 * Author URI: http://StevenKohlmeyer.com
 * License: GPLv2 or later
 */


class SK_Plugins_Last_Updated_Column {

    function __construct() {

        add_filter( 'manage_plugins_columns', array( $this, 'columnHeading' ) );
        add_action( 'manage_plugins_custom_column' , array( $this, 'columnData' ), 10, 3 );
        add_action( 'admin_head', array( $this, 'css' ) );

        $this->firstColumnHeading = true;

    }

    function columnData( $column_name, $plugin_file, $plugin_data ) {

        if ( 'sk_plugin_last_updated' == $column_name ) {

            $pluginDirectory = explode('/', $plugin_file);
            $lastUpdated = $this->getPluginsLastUpdated($pluginDirectory[0]);
            ?>
            <span class="lastUpdatedMobileTitle">Last Updated: </span>
            <span><?php echo $lastUpdated; ?></span>
            <?php

        }

    }

    function getPluginsLastUpdated($pluginSlug) {

        if(!get_transient('sk_plugins_last_updated' . $pluginSlug)) {

            include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');

            $call_api = plugins_api( 'plugin_information', array( 'slug' => $pluginSlug, 'fields' => array('last_updated') ) );

            /** Check for Errors & Display the results */
            if ( is_wp_error( $call_api ) ) {

                set_transient('sk_plugins_last_updated' . $pluginSlug, 'Not Availble', 86400);
                return "Not Available";

            } else {

                if ( ! empty( $call_api->last_updated ) ) {

                    set_transient('sk_plugins_last_updated' . $pluginSlug, $call_api->last_updated, 86400);
                    return $call_api->last_updated;

                } else {

                    set_transient('sk_plugins_last_updated' . $pluginSlug, 'Not Availble', 86400);
                    return "Not Available";

                }

            }

        } else {

            return get_transient('sk_plugins_last_updated' . $pluginSlug);

        }
    }

    function columnHeading( $columns ) {

        $columns['sk_plugin_last_updated'] = '<span>Last Updated</span>';
        return $columns;

    }

    function css() {
        ?>
        <style type="text/css">
            @media screen and (max-width:782px) {
                #the-list .column-sk_plugin_last_updated {
                    display: block;
                    width: auto;
                }
                #the-list span.lastUpdatedMobileTitle {
                    display: inline;
                }
                tfoot .column-sk_plugin_last_updated {
                    display: none;
                }
            }
            .column-sk_plugin_last_updated span {
                white-space: nowrap;
            }
            .lastUpdatedMobileTitle {
                display: none;
            }
        </style>
        <?php
    }

}

$SK_Plugins_Last_Updated_Column = new SK_Plugins_Last_Updated_Column();

?>