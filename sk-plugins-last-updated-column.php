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

        if ( 'sk_plugin_last_updated' == $column_name && 'My Plugin Name' == $plugin_data['Name'] ) {

            echo 'My Plugin custom column data';

        }

    }

    function columnHeading( $columns ) {

        $columns['sk_plugin_last_updated'] = 'Last Updated';
        return $columns;

    }

}



?>