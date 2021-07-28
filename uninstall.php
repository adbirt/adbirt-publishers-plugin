<?php

/**
 * @package adbirt-ads-display
 */

if (defined('WP_UNINSTALL_PLUGIN')) {

    update_option( 'adbirt_ads_display' , '' );
    delete_option( 'adbirt_ads_display' );

}
