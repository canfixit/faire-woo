=== FaireWoo ===
Contributors: Haibin Li
Tags: woocommerce, faire, sync, inventory, products, orders
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

WooCommerce plugin for Faire retailers to seamlessly sync products, orders, and inventory between Faire and WooCommerce.

== Description ==
FaireWoo enables Faire retailers to synchronize products, orders, and inventory with their WooCommerce store. Import and export products, keep inventory in sync, and manage orders across both platforms with ease.

**Features:**
* Import/export products between Faire and WooCommerce
* Sync orders and inventory in real time
* Supports simple and variable products
* Maps all core product data: attributes, pricing, inventory, media, taxonomy, dimensions, status, and custom fields
* Robust error handling and logging
* Extensible via WordPress hooks and filters

== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/faire-woo` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Make sure WooCommerce is active and configured.
4. Configure your Faire API credentials and plugin settings.

== Frequently Asked Questions ==
= Does this plugin support variable products? =
Yes, both simple and variable products (with variations) are fully supported.

= Can I customize the sync logic? =
Yes! Use the provided hooks and filters to modify sync behavior, add custom fields, or integrate with other plugins.

= Where do I report bugs or request features? =
Please open an issue on the [GitHub repository](https://github.com/canfixit/faire-woo).

== Screenshots ==
1. Product import/export screen
2. Sync status dashboard
3. Settings page

== Changelog ==
= 1.0.0 =
* Initial release: product, order, and inventory sync between Faire and WooCommerce.

== Upgrade Notice ==
= 1.0.0 =
First public release.

== License ==
MIT License. See LICENSE file for details. 