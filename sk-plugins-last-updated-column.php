<?php
/**
 * Plugin Name: Plugins Last Updated Column
 * Plugin URI: http://StevenKohlmeyer.com/sk_post_type_access_plugin
 * Description: This restricts access to content types and menu links to those content types by role
 * Version: 0.0.1
 * Author: Fastmover
 * Author URI: http://StevenKohlmeyer.com
 * License: GPLv2 or later
 */


class SK_Plugins_Last_Updated_Column {

    function __construct() {

        add_filter( 'manage_plugins_columns', array( $this, 'columnHeading' ) );
        add_action( 'manage_plugins_custom_column' , array( $this, 'columnData' ), 10, 3 );

    }

    function columnData( $column_name, $plugin_file, $plugin_data ) {

        if ( 'sk_plugin_last_updated' == $column_name ) {

            $pluginDirectory = explode('/', $plugin_file);
            $lastUpdated = $this->getPluginsLastUpdated($pluginDirectory[0]);
            echo $lastUpdated;

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

        $columns['sk_plugin_last_updated'] = 'Last Updated';
        return $columns;

    }

}

$SK_Plugins_Last_Updated_Column = new SK_Plugins_Last_Updated_Column();

?>