<?php
/**
 * Product Handler Class
 *
 * Orchestrates product import/export between Faire and WooCommerce.
 * Utilizes all mapping classes and supports both single and bulk operations.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Product Handler Class
 */
class ProductHandler {
    /**
     * Import a single product from Faire to WooCommerce.
     *
     * @param array $faire_product Faire product data.
     * @return int|WP_Error WooCommerce product ID or error.
     */
    public static function import_from_faire($faire_product) {
        // Determine product type
        $type = !empty($faire_product['variants']) ? 'variable' : 'simple';
        $product = null;

        if ($type === 'variable') {
            $product = new WC_Product_Variable();
        } else {
            $product = new WC_Product_Simple();
        }

        // Set core attributes
        if (isset($faire_product['name'])) {
            $product->set_name($faire_product['name']);
        }
        if (isset($faire_product['description'])) {
            $product->set_description($faire_product['description']);
        }
        if (isset($faire_product['short_description'])) {
            $product->set_short_description($faire_product['short_description']);
        }
        if (isset($faire_product['sku'])) {
            $product->set_sku($faire_product['sku']);
        }

        // Map and set pricing
        $price_data = PriceMapper::map_faire_to_wc($faire_product);
        if (isset($price_data['regular_price'])) {
            $product->set_regular_price($price_data['regular_price']);
        }
        if (isset($price_data['sale_price'])) {
            $product->set_sale_price($price_data['sale_price']);
        }

        // Map and set inventory
        $inventory_data = InventoryMapper::map_faire_to_wc($faire_product);
        if (isset($inventory_data['stock_quantity'])) {
            $product->set_stock_quantity($inventory_data['stock_quantity']);
        }
        if (isset($inventory_data['stock_status'])) {
            $product->set_stock_status($inventory_data['stock_status']);
        }
        if (isset($inventory_data['backorders'])) {
            $product->set_backorders($inventory_data['backorders']);
        }

        // Map and set taxonomy
        ProductTaxonomy::update_wc_taxonomy($product, $faire_product);

        // Map and set dimensions/shipping
        ProductDimensions::update_wc_dimensions($product, $faire_product);

        // Map and set media/images
        MediaMapper::update_wc_media($product, $faire_product);

        // Map and set inventory status/availability
        InventoryStatusMapper::update_wc_status($product, $faire_product);

        // Map and set meta/custom fields
        ProductMetaMapper::update_wc_meta($product, $faire_product);

        // Save product
        $product_id = $product->save();
        if (!$product_id) {
            return new WP_Error('product_import_failed', __('Failed to import product from Faire.', 'faire-woo'));
        }

        // Handle variations if variable product
        if ($type === 'variable' && !empty($faire_product['variants'])) {
            foreach ($faire_product['variants'] as $variant) {
                $variation = new \WC_Product_Variation();
                $variation->set_parent_id($product_id);
                if (isset($variant['sku'])) {
                    $variation->set_sku($variant['sku']);
                }
                if (isset($variant['attributes'])) {
                    $variation->set_attributes($variant['attributes']);
                }
                // Map and set pricing for variation
                $var_price = PriceMapper::map_faire_to_wc($variant);
                if (isset($var_price['regular_price'])) {
                    $variation->set_regular_price($var_price['regular_price']);
                }
                if (isset($var_price['sale_price'])) {
                    $variation->set_sale_price($var_price['sale_price']);
                }
                // Map and set inventory for variation
                $var_inventory = InventoryMapper::map_faire_to_wc($variant);
                if (isset($var_inventory['stock_quantity'])) {
                    $variation->set_stock_quantity($var_inventory['stock_quantity']);
                }
                if (isset($var_inventory['stock_status'])) {
                    $variation->set_stock_status($var_inventory['stock_status']);
                }
                if (isset($var_inventory['backorders'])) {
                    $variation->set_backorders($var_inventory['backorders']);
                }
                // Map and set media for variation
                if (isset($variant['images'])) {
                    MediaMapper::update_wc_media($variation, $variant);
                }
                // Map and set meta for variation
                ProductMetaMapper::update_wc_meta($variation, $variant);
                $variation->save();
            }
        }

        return $product_id;
    }

    /**
     * Export a single WooCommerce product to Faire format.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array Faire formatted product data.
     */
    public static function export_to_faire($product) {
        $faire_product = array();
        $faire_product['name'] = $product->get_name();
        $faire_product['description'] = $product->get_description();
        $faire_product['short_description'] = $product->get_short_description();
        $faire_product['sku'] = $product->get_sku();
        $faire_product = array_merge($faire_product, PriceMapper::map_wc_to_faire($product));
        $faire_product = array_merge($faire_product, InventoryMapper::map_wc_to_faire($product));
        $faire_product = array_merge($faire_product, ProductTaxonomy::map_wc_to_faire($product));
        $faire_product = array_merge($faire_product, ProductDimensions::map_wc_to_faire($product));
        $faire_product = array_merge($faire_product, MediaMapper::map_wc_to_faire($product));
        $faire_product = array_merge($faire_product, InventoryStatusMapper::map_wc_to_faire($product));
        $faire_product = array_merge($faire_product, ProductMetaMapper::map_wc_to_faire($product));
        // Handle variations if variable product
        if ($product->is_type('variable')) {
            $faire_product['variants'] = array();
            foreach ($product->get_children() as $child_id) {
                $variation = wc_get_product($child_id);
                if ($variation) {
                    $variant_data = array();
                    $variant_data['sku'] = $variation->get_sku();
                    $variant_data['attributes'] = $variation->get_attributes();
                    $variant_data = array_merge($variant_data, PriceMapper::map_wc_to_faire($variation));
                    $variant_data = array_merge($variant_data, InventoryMapper::map_wc_to_faire($variation));
                    $variant_data = array_merge($variant_data, MediaMapper::map_wc_to_faire($variation));
                    $variant_data = array_merge($variant_data, ProductMetaMapper::map_wc_to_faire($variation));
                    $faire_product['variants'][] = $variant_data;
                }
            }
        }
        return $faire_product;
    }

    /**
     * Bulk import products from Faire to WooCommerce.
     *
     * @param array $faire_products Array of Faire product data.
     * @return array Array of WooCommerce product IDs or WP_Error objects.
     */
    public static function bulk_import_from_faire($faire_products) {
        $results = array();
        foreach ($faire_products as $faire_product) {
            $results[] = self::import_from_faire($faire_product);
        }
        return $results;
    }

    /**
     * Bulk export WooCommerce products to Faire format.
     *
     * @param array $products Array of WC_Product objects.
     * @return array Array of Faire formatted product data.
     */
    public static function bulk_export_to_faire($products) {
        $results = array();
        foreach ($products as $product) {
            $results[] = self::export_to_faire($product);
        }
        return $results;
    }
} 