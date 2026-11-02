<?php
/*
 * Plugin Name:       Adbirt Publisher
 * Plugin URI:        https://github.com/adbirt/adbirt-publishers-plugin
 * Description:       Show Adbirt ads on your WordPress site and earn from them. Add ad slots with the [adbirt_slot] shortcode, the Adbirt Ad Slot block or the widget.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Adbirt
 * Author URI:        https://adbirt.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       adbirt-ads-display
 *
 * @package adbirt-ads-display
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ADBIRT_PUBLISHER_VERSION', '2.0.0');
define('ADBIRT_PUBLISHER_FILE', __FILE__);
define('ADBIRT_PUBLISHER_DIR', plugin_dir_path(__FILE__));

require_once ADBIRT_PUBLISHER_DIR . 'class-Adbirt_Publisher.php';
require_once ADBIRT_PUBLISHER_DIR . 'includes/class-AAD_widget.php';

Adbirt_Publisher::instance();
