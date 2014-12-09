<?php
/**
 * Plugin Name: Plugins Last Updated Column
 * Plugin URI: http://stevenkohlmeyer.com/plugins-last-updated-column/
 * Description: This plugin adds a 'Last Updated' column to the admin plugins page.
 * Version: 0.0.5
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

            $color = "";
            $msg = "";

            $pluginDirectory = explode('/', $plugin_file);
            $lastUpdated = $this->getPluginsLastUpdated($pluginDirectory[0]);

            ?>
            <span class="lastUpdatedMobileTitle">Last Updated: </span><?php

            if( $lastUpdated !== "-1" && $lastUpdated !== -1 ) {

                if( ! isset( $this->currentDateTime ) ) {

                    $this->currentDateTime = new DateTime();

                }

                $stringTime             = strtotime( $lastUpdated );
                $dateLastUpdated        = Date( 'Y-m-d', $stringTime );
                $lastUpdatedDateTime    = new DateTime( $dateLastUpdated );

                $dayDiff    = $this->currentDateTime->diff(    $lastUpdatedDateTime, true )->d;
                $monthDiff  = $this->currentDateTime->diff(  $lastUpdatedDateTime, true )->m;
                $yearDiff   = $this->currentDateTime->diff(   $lastUpdatedDateTime, true )->y;


                $warningLevel = 1;


                if( $yearDiff === 0 ) {
                    if( $monthDiff > 6 ) {
                        $warningLevel = 2;
                    }
                } else {
                    $msg .= $yearDiff . " Years ";
                    if( $yearDiff < 2 ) {
                        $warningLevel = 3;
                        if( $yearDiff < 1 ) {
                            $warningLevel = 2;
                        }
                    } else {
                        $warningLevel = 4;
                    }
                }

                if( $monthDiff !== 0 ) {
                    $msg .= $monthDiff . " Mon. ";
                }

                if( $dayDiff !== 0 ) {
                    $msg .=  $dayDiff . " Days";
                }

                switch( $warningLevel ) {

                    case 1:
                        // Green
                        $color = "#00ff00";
                        break;
                    case 2:
                        // Yellow
                        $color = "#F2FF00";
                        break;
                    case 3:
                        // Orange
                        $color = "#FFA600";
                        break;
                    case 4:
                        // Red
                        $color = "#ff0000";
                        break;

                }


                ?>
                <span><?php echo $lastUpdated; ?></span>

            <?php

            } else {
                ?>
                <span>Not Available</span><?php
            }

            ?>

            <span style="background-color: <?php echo $color; ?>"><?php echo $msg; ?></span>
            <?php



        } elseif ( 'sk_plugin_last_upgraded' == $column_name ) {

            ?><span class="lastUpgradedMobileTitle">Last Upgraded: </span><?php



            $version = $plugin_data['Version'];

            if( isset( $plugin_data['slug'] ) ) {

                $slug = $plugin_data['slug'];

            } else {

                $slug = sanitize_title( $plugin_file );

            }

            $lastUpgradedOutput = "";

            $lastUpgradedSlug   = 'plugin_last_upgraded_version_' . $slug;
            $lastUpgradedDate   = 'plugin_last_upgraded_date_' . $slug;
            $lastVersion        = get_option( $lastUpgradedSlug, false );
            $lastDate           = get_option( $lastUpgradedDate, false );

            if( $lastDate === false ) {

                add_option( $lastUpgradedDate, "Not Available" );
                $lastUpgradedOutput = "Not Available";

            } else {

                $lastUpgradedOutput = $lastDate;

            }

            if( ! $lastVersion or $lastVersion !== $version ) {

                if( $lastVersion === false ) {

                    add_option( $lastUpgradedSlug, $version );

                } else {

                    update_option( $lastUpgradedSlug, $version );

                }

                if( $lastDate !== false ) {

                    $lastUpgradedOutput = Date( 'Y-m-d' );
                    update_option( $lastUpgradedDate, $lastUpgradedOutput );

                }

            }


            ?><span><?php echo $lastUpgradedOutput; ?></span><?php



        }

    }

    function getPluginsLastUpdated($pluginSlug) {


        if( ! get_transient( 'sk_plugins_last_updated' . $pluginSlug ) ) {

            include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );

            $call_api = plugins_api( 'plugin_information', array( 'slug' => $pluginSlug, 'fields' => array('last_updated') ) );

            /** Check for Errors & Display the results */
            if ( is_wp_error( $call_api ) ) {

                set_transient('sk_plugins_last_updated' . $pluginSlug, -1, 86400);
                return -1;

            } else {

                if ( ! empty( $call_api->last_updated ) ) {

                    set_transient( 'sk_plugins_last_updated' . $pluginSlug, $call_api->last_updated, 86400 );
                    return $call_api->last_updated;

                } else {

                    set_transient( 'sk_plugins_last_updated' . $pluginSlug, -1, 86400 );
                    return -1;

                }

            }

        } else {

            //Debugging purposes:
            //delete_transient( 'sk_plugins_last_updated' . $pluginSlug );


            return get_transient( 'sk_plugins_last_updated' . $pluginSlug );

        }
    }

    function columnHeading( $columns ) {

        $columns['sk_plugin_last_updated'] = '<span>Last Updated</span>';
        $columns['sk_plugin_last_upgraded'] = '<span>Last Upgraded</span>';
        return $columns;

    }

    function css() {
        ?>
        <style type="text/css">
            @media screen and (max-width:782px) {
                #the-list .column-sk_plugin_last_updated,
                #the-list .column-sk_plugin_last_upgraded {
                    display: block;
                    width: auto;
                }
                #the-list span.lastUpdatedMobileTitle,
                #the-list span.lastUpgradedMobileTitle {
                    display: inline;
                }
                tfoot .column-sk_plugin_last_updated,
                tfoot .column-sk_plugin_last_upgraded {
                    display: none;
                }
            }
            .column-sk_plugin_last_updated span {
                white-space: nowrap;
            }
            .lastUpdatedMobileTitle,
            .lastUpgradedMobileTitle
            {
                display: none;
            }
        </style>
        <?php
    }

}

$SK_Plugins_Last_Updated_Column = new SK_Plugins_Last_Updated_Column();

?>