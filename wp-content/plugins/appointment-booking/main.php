<?php
/*
Plugin Name: Bookly [Shared by themes24x7.com]
Plugin (shared on null24.ir) URI: http://booking-wp-plugin.com
Description: Bookly is a great easy-to-use and easy-to-manage appointment booking tool for Service providers who think about their customers. Plugin supports wide range of services, provided by business and individuals service providers offering reservations through websites. Setup any reservations quickly, pleasantly and easy with Bookly!
Version: 6.1
Author: Ladela Interactive
Author URI: http://www.ladela.com
License: Commercial
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'AB_PATH', __DIR__ );

include 'includes.php';
include 'autoload.php';

// auto updating
require 'lib/utils/plugin-updates/ab-plugin-update-checker.php';
$MyUpdateChecker = new AB_PluginUpdateChecker(
    'http://booking-wp-plugin.com/index.php',
    __FILE__,
    basename( __DIR__ )
);

// Activate/deactivate/uninstall hooks
register_activation_hook(  __FILE__, 'ab_activate' );
register_deactivation_hook(  __FILE__, 'ab_deactivate' );
register_uninstall_hook( __FILE__, 'ab_uninstall' );

// Fix possible errors (appearing if "Nextgen Gallery" Plugin is installed) when Bookly is being updated.
add_filter( 'http_request_args', function ( $args ) { $args[ 'reject_unsafe_urls' ] = false; return $args; } );

// I10n.
add_action( 'plugins_loaded', function () {
    if ( function_exists( 'load_plugin_textdomain' ) ) {
        load_plugin_textdomain( 'ab', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
} );

// Update DB.
add_action( 'plugins_loaded', 'ab_plugin_update_db' );

is_admin() ? new AB_Backend() : new AB_Frontend();

/**
 * Hook functions.
 */

function ab_activate() {
    $installer = new AB_Installer();
    $installer->install();
}

function ab_deactivate() {
    // unload l10n
    unload_textdomain( 'ab' );
}

function ab_uninstall() {
    $installer = new AB_Installer();
    $installer->uninstall();
}