<?php

/**
 * @package adbirt-ads-display
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('adbirt_publisher_settings');
// 1.x settings.
delete_option('adbirt_ads_display');
