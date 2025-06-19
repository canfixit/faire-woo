<?php
/**
 * Inventory Mapper Class
 *
 * Handles mapping of inventory/stock information between Faire and WooCommerce.
 * Supports both simple and variable products, including stock quantity, status, and backorders.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

use WC_Product;
use WC_Product_Variable;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Inventory Mapper Class
 */
class InventoryMapper {
    /**
     * Map inventory from Faire to WooCommerce format.
     *
     * @param array $faire_product Faire product data.
     * @return array WooCommerce formatted inventory data.
     */
    public static function map_faire_to_wc($faire_product) {
        $inventory_data = array(
            'stock_quantity' => isset($faire_product['inventory_quantity']) ? intval($faire_product['inventory_quantity']) : null,
            'stock_status'   => !empty($faire_product['is_active']) ? 'instock' : 'outofstock',
            'backorders'     => !empty($faire_product['allow_backorder']) ? 'yes' : 'no',
        );

        // Handle variants
        if (!empty($faire_product['variants'])) {
            $inventory_data['variants'] = array();
            foreach ($faire_product['variants'] as $variant) {
                $inventory_data['variants'][$variant['sku']] = array(
                    'stock_quantity' => isset($variant['inventory_quantity']) ? intval($variant['inventory_quantity']) : null,
                    'stock_status'   => !empty($variant['is_active']) ? 'instock' : 'outofstock',
                    'backorders'     => !empty($variant['allow_backorder']) ? 'yes' : 'no',
                );
            }
        }

        return $inventory_data;
    }

    /**
     * Map inventory from WooCommerce to Faire format.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array Faire formatted inventory data.
     */
    public static function map_wc_to_faire($product) {
        $faire_data = array(
            'inventory_quantity' => $product->get_stock_quantity(),
            'is_active'          => $product->get_stock_status() === 'instock',
            'allow_backorder'    => $product->get_backorders() === 'yes',
        );

        // Handle variants
        if ($product->is_type('variable')) {
            $faire_data['variants'] = array();
            foreach ($product->get_children() as $child_id) {
                $variation = wc_get_product($child_id);
                if (!$variation) {
                    continue;
                }
                $faire_data['variants'][$variation->get_sku()] = array(
                    'inventory_quantity' => $variation->get_stock_quantity(),
                    'is_active'          => $variation->get_stock_status() === 'instock',
                    'allow_backorder'    => $variation->get_backorders() === 'yes',
                );
            }
        }

        return $faire_data;
    }

    /**
     * Update WooCommerce product with Faire inventory.
     *
     * @param WC_Product $product      WooCommerce product object.
     * @param array      $faire_product Faire product data.
     * @return WC_Product|WP_Error Updated product or error.
     */
    public static function update_wc_inventory($product, $faire_product) {
        try {
            $inventory_data = self::map_faire_to_wc($faire_product);

            if (!is_null($inventory_data['stock_quantity'])) {
                $product->set_stock_quantity($inventory_data['stock_quantity']);
            }
            $product->set_stock_status($inventory_data['stock_status']);
            $product->set_backorders($inventory_data['backorders']);

            // Update variants
            if ($product->is_type('variable') && !empty($inventory_data['variants'])) {
                self::update_variation_inventory($product, $inventory_data['variants']);
            }

            $product->save();
            return $product;
        } catch (\Exception $e) {
            return new WP_Error(
                'inventory_update_failed',
                sprintf(__('Failed to update inventory: %s', 'faire-woo'), $e->getMessage())
            );
        }
    }

    /**
     * Compare inventory between Faire and WooCommerce.
     *
     * @param array      $faire_product Faire product data.
     * @param WC_Product $wc_product   WooCommerce product object.
     * @return array Array of differences found.
     */
    public static function compare_inventory($faire_product, $wc_product) {
        $differences = array();
        $faire_inventory = self::map_faire_to_wc($faire_product);

        // Compare stock quantity
        if ($wc_product->get_stock_quantity() != $faire_inventory['stock_quantity']) {
            $differences['stock_quantity'] = array(
                'faire' => $faire_inventory['stock_quantity'],
                'wc'    => $wc_product->get_stock_quantity(),
            );
        }

        // Compare stock status
        if ($wc_product->get_stock_status() !== $faire_inventory['stock_status']) {
            $differences['stock_status'] = array(
                'faire' => $faire_inventory['stock_status'],
                'wc'    => $wc_product->get_stock_status(),
            );
        }

        // Compare backorders
        if ($wc_product->get_backorders() !== $faire_inventory['backorders']) {
            $differences['backorders'] = array(
                'faire' => $faire_inventory['backorders'],
                'wc'    => $wc_product->get_backorders(),
            );
        }

        // Compare variants
        if ($wc_product->is_type('variable') && !empty($faire_inventory['variants'])) {
            foreach ($faire_inventory['variants'] as $sku => $variant_data) {
                $variation = self::find_variation_by_sku($wc_product, $sku);
                if ($variation) {
                    if ($variation->get_stock_quantity() != $variant_data['stock_quantity']) {
                        $differences['variants'][$sku]['stock_quantity'] = array(
                            'faire' => $variant_data['stock_quantity'],
                            'wc'    => $variation->get_stock_quantity(),
                        );
                    }
                    if ($variation->get_stock_status() !== $variant_data['stock_status']) {
                        $differences['variants'][$sku]['stock_status'] = array(
                            'faire' => $variant_data['stock_status'],
                            'wc'    => $variation->get_stock_status(),
                        );
                    }
                    if ($variation->get_backorders() !== $variant_data['backorders']) {
                        $differences['variants'][$sku]['backorders'] = array(
                            'faire' => $variant_data['backorders'],
                            'wc'    => $variation->get_backorders(),
                        );
                    }
                }
            }
        }

        return $differences;
    }

    /**
     * Update variation inventory.
     *
     * @param WC_Product_Variable $product         Variable product.
     * @param array               $variant_data    Array of variant inventory keyed by SKU.
     */
    private static function update_variation_inventory($product, $variant_data) {
        foreach ($variant_data as $sku => $data) {
            $variation = self::find_variation_by_sku($product, $sku);
            if (!$variation) {
                continue;
            }
            if (!is_null($data['stock_quantity'])) {
                $variation->set_stock_quantity($data['stock_quantity']);
            }
            $variation->set_stock_status($data['stock_status']);
            $variation->set_backorders($data['backorders']);
            $variation->save();
        }
    }

    /**
     * Find variation by SKU.
     *
     * @param WC_Product_Variable $product WooCommerce variable product.
     * @param string              $sku     SKU to find.
     * @return WC_Product|false Product if found, false otherwise.
     */
    private static function find_variation_by_sku($product, $sku) {
        foreach ($product->get_children() as $child_id) {
            $variation = wc_get_product($child_id);
            if ($variation && $variation->get_sku() === $sku) {
                return $variation;
            }
        }
        return false;
    }
} 