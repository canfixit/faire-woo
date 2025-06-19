<?php
/**
 * Product Mapper Class
 *
 * Handles mapping of basic product information between Faire and WooCommerce.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

use WC_Product;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Product Mapper Class
 */
class ProductMapper {
    /**
     * Map basic product information from Faire to WooCommerce format.
     *
     * @param array $faire_product Faire product data.
     * @return array WooCommerce formatted product data.
     */
    public static function map_faire_to_wc($faire_product) {
        $wc_data = array();
        $basic_attrs = ProductAttributes::get_attributes_by_category('basic');

        foreach ($basic_attrs as $key => $attr) {
            if ($faire_field = ProductAttributes::get_faire_field($key)) {
                if (isset($faire_product[$faire_field])) {
                    $wc_data[$key] = $faire_product[$faire_field];
                }
            }
        }

        return $wc_data;
    }

    /**
     * Map basic product information from WooCommerce to Faire format.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array Faire formatted product data.
     */
    public static function map_wc_to_faire($product) {
        $faire_data = array();
        $basic_attrs = ProductAttributes::get_attributes_by_category('basic');

        foreach ($basic_attrs as $key => $attr) {
            if ($faire_field = ProductAttributes::get_faire_field($key)) {
                $getter = ProductAttributes::get_wc_getter($key);
                if ($getter && method_exists($product, $getter)) {
                    $faire_data[$faire_field] = $product->$getter();
                }
            }
        }

        return $faire_data;
    }

    /**
     * Compare basic product information between Faire and WooCommerce.
     *
     * @param array      $faire_product Faire product data.
     * @param WC_Product $wc_product   WooCommerce product object.
     * @return array Array of differences found.
     */
    public static function compare_basic_info($faire_product, $wc_product) {
        $differences = array();
        $basic_attrs = ProductAttributes::get_attributes_by_category('basic');

        foreach ($basic_attrs as $key => $attr) {
            if ($faire_field = ProductAttributes::get_faire_field($key)) {
                $getter = ProductAttributes::get_wc_getter($key);
                
                if ($getter && method_exists($wc_product, $getter) && isset($faire_product[$faire_field])) {
                    $wc_value = $wc_product->$getter();
                    $faire_value = $faire_product[$faire_field];

                    // Compare values
                    if (self::values_differ($wc_value, $faire_value)) {
                        $differences[$key] = array(
                            'attribute' => $attr['description'],
                            'wc_value' => $wc_value,
                            'faire_value' => $faire_value,
                        );
                    }
                }
            }
        }

        return $differences;
    }

    /**
     * Update WooCommerce product with Faire basic information.
     *
     * @param WC_Product $product      WooCommerce product object.
     * @param array      $faire_product Faire product data.
     * @return WC_Product|WP_Error Updated product or error.
     */
    public static function update_wc_product($product, $faire_product) {
        try {
            $basic_attrs = ProductAttributes::get_attributes_by_category('basic');

            foreach ($basic_attrs as $key => $attr) {
                if ($faire_field = ProductAttributes::get_faire_field($key)) {
                    if (isset($faire_product[$faire_field])) {
                        $setter = 'set_' . $key;
                        if (method_exists($product, $setter)) {
                            $product->$setter($faire_product[$faire_field]);
                        }
                    }
                }
            }

            $product->save();
            return $product;

        } catch (\Exception $e) {
            return new WP_Error(
                'update_failed',
                sprintf(__('Failed to update product: %s', 'faire-woo'), $e->getMessage())
            );
        }
    }

    /**
     * Compare two values for differences.
     *
     * @param mixed $wc_value    WooCommerce value.
     * @param mixed $faire_value Faire value.
     * @return bool True if values differ, false otherwise.
     */
    private static function values_differ($wc_value, $faire_value) {
        // Handle null values
        if (is_null($wc_value) && is_null($faire_value)) {
            return false;
        }
        if (is_null($wc_value) || is_null($faire_value)) {
            return true;
        }

        // Handle numeric values
        if (is_numeric($wc_value) && is_numeric($faire_value)) {
            return (float)$wc_value !== (float)$faire_value;
        }

        // Handle arrays
        if (is_array($wc_value) && is_array($faire_value)) {
            return count(array_diff($wc_value, $faire_value)) > 0 ||
                   count(array_diff($faire_value, $wc_value)) > 0;
        }

        // Handle strings (case-sensitive comparison)
        return (string)$wc_value !== (string)$faire_value;
    }

    /**
     * Validate required basic attributes.
     *
     * @param array $product_data Product data to validate.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_basic_attributes($product_data) {
        $basic_attrs = ProductAttributes::get_attributes_by_category('basic');
        $missing = array();

        foreach ($basic_attrs as $key => $attr) {
            if (!empty($attr['required'])) {
                if ($faire_field = ProductAttributes::get_faire_field($key)) {
                    if (!isset($product_data[$faire_field]) || empty($product_data[$faire_field])) {
                        $missing[] = $attr['description'];
                    }
                }
            }
        }

        if (!empty($missing)) {
            return new WP_Error(
                'missing_required_fields',
                sprintf(
                    __('Missing required basic fields: %s', 'faire-woo'),
                    implode(', ', $missing)
                )
            );
        }

        return true;
    }
} 