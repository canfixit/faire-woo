<?php
/**
 * Plugin Name: FaireWoo
 * Plugin URI: https://github.com/canfixit/faire-woo
 * Description: WooCommerce plugin for Faire retailers to sync orders, products, and inventory.
 * Version: 1.0.0
 * Author: Haibin Li
 * Author URI: https://github.com/canfixit
 * Text Domain: faire-woo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package FaireWoo
 */

defined('ABSPATH') || exit;

if (!defined('FAIRE_WOO_PLUGIN_FILE')) {
    define('FAIRE_WOO_PLUGIN_FILE', __FILE__);
}

if (!defined('FAIRE_WOO_PLUGIN_DIR')) {
    define('FAIRE_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('FAIRE_WOO_PLUGIN_URL')) {
    define('FAIRE_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Include the autoloader and the main plugin class
require_once FAIRE_WOO_PLUGIN_DIR . 'includes/class-faire-woo-autoloader.php';
require_once FAIRE_WOO_PLUGIN_DIR . 'includes/class-faire-woo.php';

// Declare HPOS compatibility
add_action(
    'before_woocommerce_init',
    function () {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
);

// Ensure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Returns the main instance of FaireWoo.
 *
 * @since  1.0.0
 * @return \FaireWoo\FaireWoo
 */
function FaireWoo() {
    return \FaireWoo\FaireWoo::instance();
}

// Global for backwards compatibility
$GLOBALS['faire_woo'] = FaireWoo(); 