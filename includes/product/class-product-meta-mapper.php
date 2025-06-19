<?php
/**
 * Product Meta Mapper Class
 *
 * Handles mapping of product metadata and custom fields between Faire and WooCommerce.
 * Supports bidirectional mapping and synchronization of meta fields, including custom attributes and extra data.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

use WC_Product;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Product Meta Mapper Class
 */
class ProductMetaMapper {
    /**
     * List of meta fields to sync (add more as needed).
     */
    const SYNC_META_FIELDS = [
        'faire_product_id',
        'faire_brand',
        'faire_material',
        'faire_origin',
        'faire_custom_field_1',
        'faire_custom_field_2',
    ];

    /**
     * Map meta from Faire to WooCommerce format.
     *
     * @param array $faire_product Faire product data.
     * @return array WooCommerce formatted meta data.
     */
    public static function map_faire_to_wc($faire_product) {
        $meta_data = array();
        foreach (self::SYNC_META_FIELDS as $field) {
            if (isset($faire_product[$field])) {
                $meta_data[$field] = sanitize_text_field($faire_product[$field]);
            }
        }
        // Support for arbitrary custom fields
        if (!empty($faire_product['custom_fields']) && is_array($faire_product['custom_fields'])) {
            foreach ($faire_product['custom_fields'] as $key => $value) {
                $meta_data[$key] = sanitize_text_field($value);
            }
        }
        return $meta_data;
    }

    /**
     * Map meta from WooCommerce to Faire format.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array Faire formatted meta data.
     */
    public static function map_wc_to_faire($product) {
        $faire_data = array();
        foreach (self::SYNC_META_FIELDS as $field) {
            $value = get_post_meta($product->get_id(), $field, true);
            if ($value !== '') {
                $faire_data[$field] = $value;
            }
        }
        // Support for arbitrary custom fields (if needed)
        // Example: $faire_data['custom_fields'] = ...
        return $faire_data;
    }

    /**
     * Update WooCommerce product with Faire meta.
     *
     * @param WC_Product $product      WooCommerce product object.
     * @param array      $faire_product Faire product data.
     * @return WC_Product|WP_Error Updated product or error.
     */
    public static function update_wc_meta($product, $faire_product) {
        try {
            $meta_data = self::map_faire_to_wc($faire_product);
            foreach ($meta_data as $key => $value) {
                update_post_meta($product->get_id(), $key, $value);
            }
            $product->save();
            return $product;
        } catch (\Exception $e) {
            return new WP_Error(
                'meta_update_failed',
                sprintf(__('Failed to update product meta: %s', 'faire-woo'), $e->getMessage())
            );
        }
    }

    /**
     * Compare meta between Faire and WooCommerce.
     *
     * @param array      $faire_product Faire product data.
     * @param WC_Product $wc_product   WooCommerce product object.
     * @return array Array of differences found.
     */
    public static function compare_meta($faire_product, $wc_product) {
        $differences = array();
        $faire_meta = self::map_faire_to_wc($faire_product);
        foreach ($faire_meta as $key => $value) {
            $wc_value = get_post_meta($wc_product->get_id(), $key, true);
            if ($wc_value != $value) {
                $differences[$key] = array('faire' => $value, 'wc' => $wc_value);
            }
        }
        return $differences;
    }
} 