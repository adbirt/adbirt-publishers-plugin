=== Adbirt Publisher ===
Contributors: adbirt
Tags: ads, advertising, monetization, adbirt
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show Adbirt ads on your WordPress site and earn from them.

== Description ==

Adbirt is an advertising platform. With this plugin, Adbirt advertisers' ads appear in the ad slots you place on your site, and you are credited for each viewable impression.

* `[adbirt_slot id="slot_..."]` shortcode, anywhere shortcodes work.
* "Adbirt Ad Slot" block for the block editor.
* "Adbirt Ad Slot" widget for sidebars and footers.
* Loads the Adbirt publisher script once per page, only on pages that have a slot. Ads load lazily, are measured for viewability (50% visible for one second) and render in an isolated shadow root; empty slots collapse.

== Installation ==

1. Install and activate the plugin.
2. In your Adbirt dashboard, open Publisher, add your site and verify it with the DNS TXT record shown there.
3. Create ad slots there and paste each slot id into a shortcode, block or widget.

== Frequently Asked Questions ==

= Why does a slot stay empty? =

Ads are served only on verified sites, only on pages of the registered domain, and only when a campaign targets your visitor. Otherwise the slot collapses.

= What happened to [adbirt_ads_display]? =

It used Adbirt's old platform, which has been retired. It now renders nothing; replace it with `[adbirt_slot]`.

== Changelog ==

= 2.0.0 =
* Rebuilt for the new Adbirt ad server: slot shortcode, block and widget, settings page, publisher script.
* Removed the 1.x campaign manager, which called retired adbirt.com endpoints.

= 1.2.0 =
* Last 1.x release.
