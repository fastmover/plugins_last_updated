<?php
/**
 * Plugin Name: Plugins Last Updated Column
 * Plugin URI: http://stevenkohlmeyer.com/plugins-last-updated-column/
 * Description: This plugin adds 'Last Updated' and 'Last Upgraded' columns to the admin plugins page.
 * Version: 0.1.6
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Fastmover
 * Author URI: http://StevenKohlmeyer.com
 * License: GPLv2 or later
 */

if ( ! defined ( 'ABSPATH' ) ) {
    exit;
}

class SK_Plugins_Last_Updated_Column
{
    const VERSION = '0.1.6';

    public $cacheTime    = 86400;
    public $slugUpdated  = "sk-plugin-last-updated ";
    public $slugUpgraded = "sk-plugin-last-upgraded ";
    public $argClearCache   = "sk-plu-clear-cache";
    public $argCacheCleared = "sk-plu-cache-cleared";
    public $currentDateTime = false;

    function __construct ()
    {
        add_filter ( 'manage_plugins_columns', array ( $this, 'columnHeading' ) );
        add_filter ( 'manage_plugins-network_columns', array ( $this, 'columnHeading' ) );
        add_action ( 'manage_plugins_custom_column', array ( $this, 'columnData' ), 10, 3 );
        add_action ( 'admin_head', array ( $this, 'css' ) );
        add_action ( 'load-plugins.php', array ( $this, 'clearCache' ) );
        add_action ( 'pre_current_active_plugins', array ( $this, 'clearCacheButton' ) );
        add_action ( 'admin_notices', array ( $this, 'notices' ) );
        add_action ( 'network_admin_notices', array ( $this, 'notices' ) );
        add_filter ( 'removable_query_args', array ( $this, 'removableQueryArgs' ) );
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

        $lastUpdated = $this->getPluginsLastUpdated ( $this->pluginSlug ( $pluginFile ) );

        ?>
        <span class="lastUpdatedMobileTitle">Last Updated: </span>
        <?php

        if ( $lastUpdated === false ) {
            ?>
            <span>Not Avail.</span>
            <?php

        } elseif ( is_numeric ( $lastUpdated ) ) {
            if ( $lastUpdated == -2 ) {
                ?>
                <strong class="plugin-last-updated-humanreadable" data-color="#ff0000">Plugin has been closed!</strong>
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
            $dateLastUpdated     = gmdate ( 'Y-m-d', $stringTime );
            $lastUpdatedDateTime = new DateTime( $dateLastUpdated );

            $diff      = $this->currentDateTime->diff ( $lastUpdatedDateTime, true );
            $dayDiff   = $diff->d;
            $monthDiff = $diff->m;
            $yearDiff  = $diff->y;

            $warningLevel = $this->warningLevel( $yearDiff, $monthDiff, $dayDiff );
            $msg .= $this->message( $yearDiff, $monthDiff, $dayDiff );

            $color = $this->color ( $warningLevel );

            ?>
            <span><?php echo esc_html ( $dateLastUpdated ); ?></span>
            <br/>
            <span class="plugin-last-updated-humanreadable" data-color="<?php echo esc_attr ( $color ); ?>"
                  style="background-color: <?php echo esc_attr ( $color ); ?>"><?php echo esc_html ( $msg ); ?></span>
        <?php
        }
    }

    /**
     * The WordPress.org slug for an installed plugin: its folder name, or the file name for single-file plugins.
     */
    public function pluginSlug ( $pluginFile )
    {
        $pluginDirectory = explode ( '/', $pluginFile );

        return $pluginDirectory[ 0 ];
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
                $lastUpgradedOutput = gmdate ( 'Y-m-d' );
                update_option ( $lastUpgradedDate, $lastUpgradedOutput );
            }
        }

        ?>
            <span><?php echo esc_html ( $lastUpgradedOutput ); ?></span>
        <?php

    }

    function getPluginsLastUpdated ( $pluginSlug )
    {

        $retval = get_transient ( $this->slugUpdated . $pluginSlug );

        if ( ( $retval !== false ) && ( ! defined('WP_DEBUG') || ! WP_DEBUG ) ) {
            return $retval;
        }

            include_once ( ABSPATH . 'wp-admin/includes/plugin-install.php' );

            // plugins_api() raises a PHP warning when WordPress.org can't be reached; that case is handled below.
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
                #the-list .column-<?php echo esc_attr ( $this->slugUpdated ); ?>,
                #the-list .column-<?php echo esc_attr ( $this->slugUpgraded ); ?> {
                    display: block;
                    width: auto;
                }

                #the-list span.lastUpdatedMobileTitle,
                #the-list span.lastUpgradedMobileTitle {
                    display: inline;
                }

                tfoot .column-<?php echo esc_attr ( $this->slugUpdated ); ?>,
                tfoot .column-<?php echo esc_attr ( $this->slugUpgraded ); ?> {
                    display: none;
                }
            }

            .column-<?php echo esc_attr ( $this->slugUpdated ); ?> span {
                white-space: nowrap;
            }

            .lastUpdatedMobileTitle,
            .lastUpgradedMobileTitle {
                display: none;
            }

            .tablenav .displaying-num .sk-plu-clear-cache {
                margin-right: 8px;
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
                self::VERSION,
                true
        );
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

    /**
     * Handles the "Clear Update Cache" button on the Plugins screen (site and network admin),
     * then redirects back so a page refresh doesn't repeat it.
     */
    public function clearCache ()
    {
        if ( ! isset( $_GET[ $this->argClearCache ] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to clear the update cache.', 'plugins-last-updated-column' ), 403 );
        }

        check_admin_referer( 'clear_plugin_cache' );

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ( array_keys( get_plugins() ) as $pluginFile ) {
            delete_transient( $this->slugUpdated . $this->pluginSlug( $pluginFile ) );
        }

        $redirect = remove_query_arg( array( $this->argClearCache, '_wpnonce' ) );
        wp_safe_redirect( add_query_arg( $this->argCacheCleared, '1', $redirect ) );
        exit;
    }

    /**
     * Prints the button above the plugins table; plugins-last-updated.js moves it next to the item count.
     */
    public function clearCacheButton ()
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $url = wp_nonce_url( add_query_arg( $this->argClearCache, '1' ), 'clear_plugin_cache' );
        ?>
        <p class="sk-plu-clear-cache-wrap">
            <a href="<?php echo esc_url( $url ); ?>" class="button button-small sk-plu-clear-cache">Clear Update Cache</a>
        </p>
        <?php
    }

    public function notices ()
    {
        $screen = get_current_screen();

        // "plugins" on a site, "plugins-network" in Network Admin.
        if ( ! isset( $screen ) || ! in_array( $screen->base, array( 'plugins', 'plugins-network' ), true ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows a message; nothing is changed.
        if ( isset( $_GET[ $this->argCacheCleared ] ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>Cache Cleared.</p>
            </div>
            <?php
        }
    }

    /**
     * Lets WordPress remove the "cache cleared" flag from the address bar after the notice is shown.
     */
    public function removableQueryArgs ( $args )
    {
        $args[] = $this->argCacheCleared;

        return $args;
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
