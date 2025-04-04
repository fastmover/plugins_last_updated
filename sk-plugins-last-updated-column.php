<?php
/**
 * Plugin Name: Plugins Last Updated Column
 * Plugin URI: http://stevenkohlmeyer.com/plugins-last-updated-column/
 * Description: This plugin adds 'Last Updated' and 'Last Upgraded' columns to the admin plugins page.
 * Version: 0.1.5
 * Author: Fastmover
 * Author URI: http://StevenKohlmeyer.com
 * License: GPLv2 or later
 */

if ( ! defined ( 'ABSPATH' ) ) {
    exit;
}

class SK_Plugins_Last_Updated_Column
{
    public $cacheTime    = 86400;
    public $slugUpdated  = "sk-plugin-last-updated ";
    public $slugUpgraded = "sk-plugin-last-upgraded ";
    public $slugSettings = "plugins-last-updated-settings";
    public $currentDateTime = false;

    function __construct ()
    {
        add_filter ( 'manage_plugins_columns', array ( $this, 'columnHeading' ) );
        add_filter ( 'manage_plugins-network_columns', array ( $this, 'columnHeading' ) );
        add_action ( 'manage_plugins_custom_column', array ( $this, 'columnData' ), 10, 3 );
        add_action ( 'admin_head', array ( $this, 'css' ) );
        add_action ( 'admin_menu', array ( $this, 'menu' ) );
        add_action ( 'admin_notices', array ( $this, 'notices' ) );
        add_action ( 'admin_enqueue_scripts', array ( $this, 'js' ) );
    }

    public function color ( $level )
    {
        switch ( $level ) {

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

            default:
                // White
                $color = "#ffffff";
                break;

        }

        return $color;
    }

    function columnData ( $columnName, $pluginFile, $pluginData )
    {
        if ( $this->slugUpdated == $columnName ) {
            $this->columnLastUpdated ( $columnName, $pluginFile, $pluginData );
        } elseif ( $this->slugUpgraded == $columnName ) {
            $this->columnLastUpgraded ( $columnName, $pluginFile, $pluginData );
        }
    }

    public function columnLastUpdated ( $columnName, $pluginFile, $pluginData )
    {

        $color = "";
        $msg   = "";

        $pluginDirectory = explode ( '/', $pluginFile );
        $lastUpdated     = $this->getPluginsLastUpdated ( $pluginDirectory[ 0 ] );

        ?>
        <span class="lastUpdatedMobileTitle">Last Updated: </span>
        <?php

        if ( $lastUpdated === false ) {
           ?>
           <span>Not Avail.</span>
           <?php

        } elseif ( is_numeric( $lastUpdated ) )  {
            if ( $lastUpdated == -2 ) {
                ?>
                <strong class="plugin-last-updated-humanreadable" data-color="#ff0000"><strong>Plugin has been closed!</strong>
                <?php
        } elseif ( $lastUpdated == -3 ) {
                ?>
                <strong class="plugin-last-updated-humanreadable" data-color="#ff0000">Plugin not found</strong>
                <?php
            } else {
                ?>
                <span>Not Avail.</span>
                <?php
            }

            } else {

            if ( ! $this->currentDateTime ) {
                $this->currentDateTime = new DateTime();
            }

            $stringTime          = strtotime ( $lastUpdated );
            $dateLastUpdated     = Date ( 'Y-m-d', $stringTime );
            $lastUpdatedDateTime = new DateTime( $dateLastUpdated );

            if ( phpversion () < "5.3" ) {

                $dayDiff   = (int) ( ( $this->currentDateTime->format ( 'U' ) - $lastUpdatedDateTime->format ( 'U' ) ) / ( 60 * 60 * 24 ) );
                $monthDiff = (int) ( ( $this->currentDateTime->format ( 'U' ) - $lastUpdatedDateTime->format ( 'U' ) ) / ( 60 * 60 * 24 * 30 ) );
                $yearDiff  = (int) ( ( $this->currentDateTime->format ( 'U' ) - $lastUpdatedDateTime->format ( 'U' ) ) / ( 60 * 60 * 24 * 365 ) );

                $dayDiff   = $this->roundDown ( $dayDiff, 30 );
                $monthDiff = $this->roundDown ( $monthDiff, 12 );
                $yearDiff  = $this->roundDown ( $yearDiff, 365 );

            } else {

                $dayDiff   = $this->currentDateTime->diff ( $lastUpdatedDateTime, true )->d;
                $monthDiff = $this->currentDateTime->diff ( $lastUpdatedDateTime, true )->m;
                $yearDiff  = $this->currentDateTime->diff ( $lastUpdatedDateTime, true )->y;

            }

            $warningLevel = $this->warningLevel( $yearDiff, $monthDiff, $dayDiff );
            $msg .= $this->message( $yearDiff, $monthDiff, $dayDiff );

            $color = $this->color ( $warningLevel );

            ?>
            <span><?php echo $dateLastUpdated; ?></span>
            <br/>
            <span class="plugin-last-updated-humanreadable" data-color="<?php echo $color; ?>"
                  style="background-color: <?php echo $color; ?>"><?php echo $msg; ?></span>
        <?php
        }
    }

    public function columnLastUpgraded ( $columnName, $pluginFile, $pluginData )
    {

        ?><span class="lastUpgradedMobileTitle">Last Upgraded: </span><?php

        $version = $pluginData[ 'Version' ];

        if ( isset( $pluginData[ 'slug' ] ) ) {
            $slug = $pluginData[ 'slug' ];
        } else {
            $slug = sanitize_title ( $pluginFile );
        }

        $lastUpgradedOutput = "";
        $lastUpgradedSlug   = 'plugin_last_upgraded_version_' . $slug;
        $lastUpgradedDate   = 'plugin_last_upgraded_date_' . $slug;
        $lastVersion        = get_option ( $lastUpgradedSlug, false );
        $lastDate           = get_option ( $lastUpgradedDate, false );

        if ( $lastDate === false ) {
            add_option ( $lastUpgradedDate, "Not Avail." );
            $lastUpgradedOutput = "Not Avail.";
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

        ?>
            <span><?php echo $lastUpgradedOutput; ?></span>
        <?php

    }

    function getPluginsLastUpdated ( $pluginSlug )
    {

        $retval = get_transient ( $this->slugUpdated . $pluginSlug );

        if ( ( $retval !== false ) && ( ! defined('WP_DEBUG') || ! WP_DEBUG ) ) {
            return $retval;
        }

            include_once ( ABSPATH . 'wp-admin/includes/plugin-install.php' );

            $call_api = @plugins_api (
                    'plugin_information',
                    array (
                            'slug'   => $pluginSlug,
                            'fields' => array ( 'last_updated' )
                    )
            );

            /** Check for Errors & Display the results */
            if ( is_wp_error ( $call_api ) ) {

                /*
                 * plugin_api() doesn't differentiate between a network issue and a successful
                 * API request that returns json that contains a key of "error". Examples:
                 * {"error":"Plugin not found."}
                 * 
                   {
                    "error": "closed",
                    "name": "Easy Testimonials",
                    "slug": "easy-testimonials",
                    "description": "This plugin has been closed as of July 19, 2024 and is not available for download. Reason: Security Issue.",
                    "closed": true,
                    "closed_date": "2024-07-19",
                    "reason": "security-issue",
                    "reason_text": "Security Issue"
                  }

                 * Unfortunately, plugin_api() also doesn't pass the returned json into WP_Error,
                 * so we can't get the "reason" or "closed_date". Best we can do is check the
                 * error message and go from there.
                 */

                $retval = false;
                $errmsg = $call_api->get_error_message();

                if ( $errmsg == 'closed' ) { 
                    $retval = -2;
                } elseif ( $errmsg == 'Plugin not found.' ) {
                    $retval = -3;
                }

                if ( $retval !== false ) {
                    set_transient ( $this->slugUpdated . $pluginSlug, $retval, $this->cacheTime );
                }

                return $retval;
            } else {
                if ( ! empty( $call_api->last_updated ) ) {
                    set_transient ( $this->slugUpdated . $pluginSlug, $call_api->last_updated,
                            $this->cacheTime );

                    return $call_api->last_updated;
                } else {

                    return false;
                }
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

    public function js ( $hook = false )
    {
        if ( 'plugins.php' != $hook ) {
            return;
        }

        wp_enqueue_script (
                'plugins-last-updated-js',
                plugin_dir_url ( __FILE__ ) . 'plugins-last-updated.js',
                array ( 'jquery' ),
                '1.0',
                true
        );
    }

    public function menu ()
    {
        add_submenu_page ( 'plugins.php', 'Plugins Columns', 'Plugin Columns', 'manage_options', $this->slugSettings,
                array ( $this, 'settings' ) );

    }

    public function message( $yearDiff, $monthDiff, $dayDiff )
    {

        $msg = "";
        if ( $yearDiff !== 0 ) {

            $msg .= $yearDiff;

            if( $yearDiff == 1 ) {
                $msg .= " Year ";
            } else {
                $msg .= " Years ";
            }

        }

        if ( $monthDiff !== 0 ) {
            $msg .= $monthDiff . " Mon. ";
        }

        if ( $dayDiff !== 0 ) {

            $msg .= $dayDiff;

            if( $dayDiff == 1 ) {
                $msg .= " Day";
            } else {
                $msg .= " Days";
            }
        }

        return $msg;


    }

    public function notices ()
    {
        $screen = get_current_screen();

        if (
            isset($screen) and
            $screen->base === ("plugins_page_" . $this->slugSettings) and
            isset($_REQUEST['clear-cache']) and $_REQUEST['clear-cache'] == "true"
        ) {
            // Verify nonce
            if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'clear_plugin_cache')) {
                ?>
                <div class="error">
                    <p>
                        Invalid request. Please try again.
                    </p>
                </div>
                <?php
                return;
            }

            global $wpdb;

            $wpdb->query("DELETE FROM `" . $wpdb->options . "` WHERE `option_name` LIKE ('%" . $this->slugUpdated . "%')");

            ?>
            <div class="updated">
                <p>
                    Cache Cleared.
                </p>
            </div>
            <?php

        }
    }

    public function roundDown ( $num, $max )
    {
        if( $num === 0 ) {
            return $num;
        }

        $remainder = ( $num % $max );

        if ( $remainder > 0 ) {
            return $remainder;
        }

        return $num;
    }

    public function settings ()
    {

        $url = ( is_ssl () ? 'https://' : 'http://' ) . $_SERVER[ "HTTP_HOST" ] . $_SERVER[ "REQUEST_URI" ];
        $nonce = wp_create_nonce('clear_plugin_cache');
        ?>
        <div class="wrap">
            <h1>Clear Plugin Cache</h1>

            <p>
                <a href="<?= esc_url(add_query_arg(['clear-cache' => 'true', '_wpnonce' => $nonce], $url)); ?>">Clear Update Cache</a>
            </p>
        </div>
        <?php


    }

    function warningLevel( $yearDiff, $monthDiff, $dayDiff )
    {

        $warningLevel = 1;

        if ( $yearDiff === 0 ) {
            if ( $monthDiff > 6 ) {
                $warningLevel = 2;
            }
        } else {
            if ( $yearDiff < 2 ) {
                $warningLevel = 3;
                if ( $yearDiff < 1 ) {
                    $warningLevel = 2;
                }
            } else {
                $warningLevel = 4;
            }
        }

        return $warningLevel;
    }

}

$SK_Plugins_Last_Updated_Column = new SK_Plugins_Last_Updated_Column();
