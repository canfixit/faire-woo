<?php
/**
 * Inventory Status Mapper Class
 *
 * Handles mapping of product inventory status and availability windows between Faire and WooCommerce.
 * Supports bidirectional mapping and synchronization of status, availability dates, and related fields.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

use WC_Product;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Inventory Status Mapper Class
 */
class InventoryStatusMapper {
    /**
     * Map inventory status and availability from Faire to WooCommerce format.
     *
     * @param array $faire_product Faire product data.
     * @return array WooCommerce formatted status data.
     */
    public static function map_faire_to_wc($faire_product) {
        $status_data = array(
            'stock_status' => !empty($faire_product['is_active']) ? 'instock' : 'outofstock',
            'availability_start' => isset($faire_product['available_from']) ? sanitize_text_field($faire_product['available_from']) : '',
            'availability_end'   => isset($faire_product['available_until']) ? sanitize_text_field($faire_product['available_until']) : '',
        );
        return $status_data;
    }

    /**
     * Map inventory status and availability from WooCommerce to Faire format.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array Faire formatted status data.
     */
    public static function map_wc_to_faire($product) {
        $faire_data = array(
            'is_active' => $product->get_stock_status() === 'instock',
            'available_from' => '',
            'available_until' => '',
        );
        // Custom fields for availability windows (if used)
        $from = get_post_meta($product->get_id(), '_faire_available_from', true);
        $until = get_post_meta($product->get_id(), '_faire_available_until', true);
        if ($from) {
            $faire_data['available_from'] = $from;
        }
        if ($until) {
            $faire_data['available_until'] = $until;
        }
        return $faire_data;
    }

    /**
     * Update WooCommerce product with Faire inventory status and availability.
     *
     * @param WC_Product $product      WooCommerce product object.
     * @param array      $faire_product Faire product data.
     * @return WC_Product|WP_Error Updated product or error.
     */
    public static function update_wc_status($product, $faire_product) {
        try {
            $status_data = self::map_faire_to_wc($faire_product);
            $product->set_stock_status($status_data['stock_status']);
            if (!empty($status_data['availability_start'])) {
                update_post_meta($product->get_id(), '_faire_available_from', $status_data['availability_start']);
            }
            if (!empty($status_data['availability_end'])) {
                update_post_meta($product->get_id(), '_faire_available_until', $status_data['availability_end']);
            }
            $product->save();
            return $product;
        } catch (\Exception $e) {
            return new WP_Error(
                'status_update_failed',
                sprintf(__('Failed to update inventory status: %s', 'faire-woo'), $e->getMessage())
            );
        }
    }

    /**
     * Compare inventory status and availability between Faire and WooCommerce.
     *
     * @param array      $faire_product Faire product data.
     * @param WC_Product $wc_product   WooCommerce product object.
     * @return array Array of differences found.
     */
    public static function compare_status($faire_product, $wc_product) {
        $differences = array();
        $faire_status = self::map_faire_to_wc($faire_product);
        if ($wc_product->get_stock_status() !== $faire_status['stock_status']) {
            $differences['stock_status'] = array('faire' => $faire_status['stock_status'], 'wc' => $wc_product->get_stock_status());
        }
        $from = get_post_meta($wc_product->get_id(), '_faire_available_from', true);
        $until = get_post_meta($wc_product->get_id(), '_faire_available_until', true);
        if ($from !== $faire_status['availability_start']) {
            $differences['availability_start'] = array('faire' => $faire_status['availability_start'], 'wc' => $from);
        }
        if ($until !== $faire_status['availability_end']) {
            $differences['availability_end'] = array('faire' => $faire_status['availability_end'], 'wc' => $until);
        }
        return $differences;
    }
} 