<?php
/*
 * Adbirt Publisher
 * @package           adbirt-ads-display
 * @author            Adbirt.com
 * @copyright         2017 - 2021 Adbirt, Inc. All Rights Reserved
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Adbirt Publisher
 * Plugin URI: https://github.com/adbirt/adbirt-ads-display
 * description: A wordpress plugin for managing & displaying adbirt ads on a wordpress site. See https://adbirt.com/privacy & https://adbirt.com/terms for TAC/privacy policy.
 * Version: 1.2.0
 * Tags: Adbirt, Advertisment, CPC, CPA, CPM
 * Requires at least: 5.0.0
 * Tested up to: 5.8.1
 * Requires PHP: 7.0
 * Plugin Slug: adbirt-ads-display
 * Stable tag: 1.2.0
 * Text Domain: adbirt-ads-display
 * Author: Adbirt.com
 * Author URI: https://adbirt.com/contact
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */


/**
 * @package adbirt-ads-display
 * @version 1.2.0
 */


$main_class_path = trailingslashit(plugin_dir_path(__FILE__)) . 'class-Adbirt_Publisher.php';
require $main_class_path;

new Adbirt_Publisher();

// completed
