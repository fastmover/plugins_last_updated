<?php
/**
 * Plugin Name: Plugins Last Updated Column
 * Plugin URI: http://stevenkohlmeyer.com/plugins-last-updated-column/
 * Description: This plugin adds a 'Last Updated' column to the admin plugins page.
 * Version: 0.0.6
 * Author: Fastmover
 * Author URI: http://StevenKohlmeyer.com
 * License: GPLv2 or later
 */

if ( ! defined ( 'ABSPATH' ) ) {
    exit;
}

class SK_Plugins_Last_Updated_Column
{

    public $cacheTime    = 1800;
    public $slugUpdated  = "sk-plugin-last-updated";
    public $slugUpgraded = "sk-plugin-last-upgraded";
    public $slugSettings = "plugins-last-updated-settings";

    function __construct ()
    {

        add_filter ( 'manage_plugins_columns', array ( $this, 'columnHeading' ) );
        add_action ( 'manage_plugins_custom_column', array ( $this, 'columnData' ), 10, 3 );
        add_action ( 'admin_head', array ( $this, 'css' ) );
        add_action ( 'admin_menu', array ( $this, 'menu' ) );

        add_action( 'admin_notices', array( $this, 'notices' ) );

        $this->firstColumnHeading = true;

    }

    function timeDiff ( $start, $end )
    {

//        To Add php 5.2 support or not?

//        http://stackoverflow.com/questions/4033224/what-can-use-for-datetimediff-for-php-5-2

//        https://github.com/symphonycms/symphony-2/commit/c8b0ee87ce0f72cad2ac5ba1c88ddd7c258bfc62

    }

    function columnData ( $columnName, $pluginFile, $pluginData )
    {

        if ( $this->slugUpdated == $columnName ) {

            $color = "";
            $msg   = "";

            $pluginDirectory = explode ( '/', $pluginFile );
            $lastUpdated     = $this->getPluginsLastUpdated ( $pluginDirectory[ 0 ] );

            ?>
            <span class="lastUpdatedMobileTitle">Last Updated: </span><?php

            if ( $lastUpdated !== "-1" && $lastUpdated !== -1 ) {

                if ( ! isset( $this->currentDateTime ) ) {

                    $this->currentDateTime = new DateTime();

                }

                $stringTime          = strtotime ( $lastUpdated );
                $dateLastUpdated     = Date ( 'Y-m-d', $stringTime );
                $lastUpdatedDateTime = new DateTime( $dateLastUpdated );

                $dayDiff   = $this->currentDateTime->diff ( $lastUpdatedDateTime, true )->d;
                $monthDiff = $this->currentDateTime->diff ( $lastUpdatedDateTime, true )->m;
                $yearDiff  = $this->currentDateTime->diff ( $lastUpdatedDateTime, true )->y;


                $warningLevel = 1;


                if ( $yearDiff === 0 ) {
                    if ( $monthDiff > 6 ) {
                        $warningLevel = 2;
                    }
                } else {
                    $msg .= $yearDiff . " Years ";
                    if ( $yearDiff < 2 ) {
                        $warningLevel = 3;
                        if ( $yearDiff < 1 ) {
                            $warningLevel = 2;
                        }
                    } else {
                        $warningLevel = 4;
                    }
                }

                if ( $monthDiff !== 0 ) {
                    $msg .= $monthDiff . " Mon. ";
                }

                if ( $dayDiff !== 0 ) {
                    $msg .= $dayDiff . " Days";
                }

                switch ( $warningLevel ) {

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
                <span><?php echo $dateLastUpdated; ?></span>

                <?php

            } else {
                ?>
                <span>Not Available</span><?php
            }

            ?>
            <br/>
            <span style="background-color: <?php echo $color; ?>"><?php echo $msg; ?></span>
            <?php


        } elseif ( $this->slugUpgraded == $columnName ) {

            ?><span class="lastUpgradedMobileTitle">Last Upgraded: </span><?php


            $version = $pluginData[ 'Version' ];

            if ( isset( $pluginData[ 'slug' ] ) ) {

                $slug = $pluginData[ 'slug' ];

            } else {

                $slug = sanitize_title ( $pluginFile );

            }

            $lastUpgradedOutput = "";

            $lastUpgradedSlug = 'plugin_last_upgraded_version_' . $slug;
            $lastUpgradedDate = 'plugin_last_upgraded_date_' . $slug;
            $lastVersion      = get_option ( $lastUpgradedSlug, false );
            $lastDate         = get_option ( $lastUpgradedDate, false );

            if ( $lastDate === false ) {

                add_option ( $lastUpgradedDate, "Not Available" );
                $lastUpgradedOutput = "Not Available";

            } else {

                $lastUpgradedOutput = $lastDate;

            }

            if ( ! $lastVersion or $lastVersion !== $version ) {

                if ( $lastVersion === false ) {

                    add_option ( $lastUpgradedSlug, $version );

                } else {

                    update_option ( $lastUpgradedSlug, $version );

                }

                if ( $lastDate !== false ) {

                    $lastUpgradedOutput = Date ( 'Y-m-d' );
                    update_option ( $lastUpgradedDate, $lastUpgradedOutput );

                }

            }


            ?><span><?php echo $lastUpgradedOutput; ?></span><?php


        }

    }

    function getPluginsLastUpdated ( $pluginSlug )
    {


        if ( ! get_transient ( $this->slugUpdated . $pluginSlug ) ) {

            include_once ( ABSPATH . 'wp-admin/includes/plugin-install.php' );

            $call_api = plugins_api ( 'plugin_information',
                    array ( 'slug' => $pluginSlug, 'fields' => array ( 'last_updated' ) ) );

            /** Check for Errors & Display the results */
            if ( is_wp_error ( $call_api ) ) {

                set_transient ( $this->slugUpdated . $pluginSlug, -1, $this->cacheTime );

                return -1;

            } else {

                if ( ! empty( $call_api->last_updated ) ) {

                    set_transient ( $this->slugUpdated . $pluginSlug, $call_api->last_updated,
                            $this->cacheTime );

                    return $call_api->last_updated;

                } else {

                    set_transient ( $this->slugUpdated . $pluginSlug, -1, $this->cacheTime );

                    return -1;

                }

            }

        } else {

            //Debugging purposes:
            //delete_transient( 'sk_plugins_last_updated' . $pluginSlug );


            return get_transient ( $this->slugUpdated . $pluginSlug );

        }
    }

    function columnHeading ( $columns )
    {

        $columns[ $this->slugUpdated ]  = '<span>Last Updated</span>';
        $columns[ $this->slugUpgraded ] = '<span>Last Upgraded</span>';

        return $columns;

    }

    function css ()
    {

        ?>
        <style type="text/css">
            @media screen and (max-width: 782px) {
                #the-list .column-<?= $this->slugUpdated; ?>,
                #the-list .column-<?= $this->slugUpgraded; ?> {
                    display: block;
                    width: auto;
                }

                #the-list span.lastUpdatedMobileTitle,
                #the-list span.lastUpgradedMobileTitle {
                    display: inline;
                }

                tfoot .column-<?= $this->slugUpdated; ?>,
                tfoot .column-<?= $this->slugUpgraded; ?> {
                    display: none;
                }
            }

            .column-<?= $this->slugUpdated; ?> span {
                white-space: nowrap;
            }

            .lastUpdatedMobileTitle,
            .lastUpgradedMobileTitle {
                display: none;
            }
        </style>
        <?php
    }

    public function menu()
    {

        add_submenu_page( 'plugins.php', 'Plugins Columns', 'Plugins Columns', 'manage_options', $this->slugSettings, array( $this, 'settings' ) );

    }

    public function notices()
    {

        $screen = get_current_screen();

        if( isset( $screen ) and $screen->base === ( "plugins_page_" . $this->slugSettings ) and $_REQUEST[ 'clear-cache' ] == "true" ):

            global $wpdb;

            $wpdb->query( "DELETE FROM `" . $wpdb->options . "` WHERE `option_name` LIKE ('%" . $this->slugUpdated . "%')" );

            ?>
            <div class="updated">
                <p>
                    Cache Cleared.
                </p>
            </div>
            <?php

        endif;

    }

    public function settings()
    {

        $url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

        ?>
            <div class="wrap">
                <h1>Clear Plugin Cache</h1>
                <a href="<?= $url; ?>&clear-cache=true">Clear Update Cache</a>
            </div>
<?php


    }

}

$SK_Plugins_Last_Updated_Column = new SK_Plugins_Last_Updated_Column();

?>
