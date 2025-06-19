<?php
/**
 * Product Attributes Class
 *
 * Defines and manages product attributes mapping between Faire and WooCommerce.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

defined('ABSPATH') || exit;

/**
 * Product Attributes Class
 */
class ProductAttributes {
    /**
     * Core product attributes that are essential for both Faire and WooCommerce.
     *
     * @var array
     */
    private static $core_attributes = array(
        // Basic Information
        'basic' => array(
            'name' => array(
                'wc' => 'get_name',
                'faire' => 'title',
                'required' => true,
                'description' => 'Product name/title',
            ),
            'sku' => array(
                'wc' => 'get_sku',
                'faire' => 'sku',
                'required' => true,
                'description' => 'Stock keeping unit',
            ),
            'description' => array(
                'wc' => 'get_description',
                'faire' => 'description',
                'required' => false,
                'description' => 'Full product description',
            ),
            'short_description' => array(
                'wc' => 'get_short_description',
                'faire' => 'short_description',
                'required' => false,
                'description' => 'Short product description',
            ),
        ),

        // Pricing
        'pricing' => array(
            'regular_price' => array(
                'wc' => 'get_regular_price',
                'faire' => 'retail_price', // RRP from Faire
                'required' => true,
                'description' => 'Regular retail price',
                'transformer' => 'transform_price',
            ),
            'sale_price' => array(
                'wc' => 'get_sale_price',
                'faire' => null, // Not imported from Faire per requirements
                'required' => false,
                'description' => 'Sale price (if applicable)',
            ),
        ),

        // Inventory
        'inventory' => array(
            'stock_quantity' => array(
                'wc' => 'get_stock_quantity',
                'faire' => 'quantity',
                'required' => true,
                'description' => 'Current stock quantity',
                'transformer' => 'transform_stock_quantity',
            ),
            'manage_stock' => array(
                'wc' => 'get_manage_stock',
                'faire' => null, // Always true for synced products
                'required' => true,
                'description' => 'Whether to manage stock',
                'default' => true,
            ),
            'stock_status' => array(
                'wc' => 'get_stock_status',
                'faire' => 'in_stock',
                'required' => true,
                'description' => 'Stock status (in stock, out of stock)',
                'transformer' => 'transform_stock_status',
            ),
        ),

        // Dimensions and Shipping
        'dimensions' => array(
            'weight' => array(
                'wc' => 'get_weight',
                'faire' => 'weight',
                'required' => false,
                'description' => 'Product weight',
                'transformer' => 'transform_weight',
            ),
            'length' => array(
                'wc' => 'get_length',
                'faire' => 'length',
                'required' => false,
                'description' => 'Product length',
                'transformer' => 'transform_dimension',
            ),
            'width' => array(
                'wc' => 'get_width',
                'faire' => 'width',
                'required' => false,
                'description' => 'Product width',
                'transformer' => 'transform_dimension',
            ),
            'height' => array(
                'wc' => 'get_height',
                'faire' => 'height',
                'required' => false,
                'description' => 'Product height',
                'transformer' => 'transform_dimension',
            ),
        ),

        // Categories and Tags
        'taxonomy' => array(
            'categories' => array(
                'wc' => 'get_category_ids',
                'faire' => 'categories',
                'required' => false,
                'description' => 'Product categories',
                'transformer' => 'transform_categories',
            ),
            'tags' => array(
                'wc' => 'get_tag_ids',
                'faire' => 'tags',
                'required' => false,
                'description' => 'Product tags',
                'transformer' => 'transform_tags',
            ),
        ),

        // Media
        'media' => array(
            'image_id' => array(
                'wc' => 'get_image_id',
                'faire' => 'images',
                'required' => false,
                'description' => 'Main product image',
                'transformer' => 'transform_main_image',
            ),
            'gallery_image_ids' => array(
                'wc' => 'get_gallery_image_ids',
                'faire' => 'additional_images',
                'required' => false,
                'description' => 'Product gallery images',
                'transformer' => 'transform_gallery_images',
            ),
        ),

        // Variations
        'variations' => array(
            'attributes' => array(
                'wc' => 'get_attributes',
                'faire' => 'variants',
                'required' => false,
                'description' => 'Product attributes/variations',
                'transformer' => 'transform_attributes',
            ),
            'default_attributes' => array(
                'wc' => 'get_default_attributes',
                'faire' => 'default_variant',
                'required' => false,
                'description' => 'Default variation attributes',
                'transformer' => 'transform_default_attributes',
            ),
        ),
    );

    /**
     * Get all core product attributes.
     *
     * @return array Array of core product attributes.
     */
    public static function get_core_attributes() {
        return self::$core_attributes;
    }

    /**
     * Get attributes by category.
     *
     * @param string $category Category name (e.g., 'basic', 'pricing', etc.).
     * @return array|null Array of attributes in the category or null if category not found.
     */
    public static function get_attributes_by_category($category) {
        return isset(self::$core_attributes[$category]) ? self::$core_attributes[$category] : null;
    }

    /**
     * Get required attributes.
     *
     * @return array Array of required attributes.
     */
    public static function get_required_attributes() {
        $required = array();
        foreach (self::$core_attributes as $category => $attributes) {
            foreach ($attributes as $key => $attr) {
                if (!empty($attr['required'])) {
                    $required[$key] = $attr;
                }
            }
        }
        return $required;
    }

    /**
     * Get WooCommerce getter method for an attribute.
     *
     * @param string $attribute_key Attribute key.
     * @return string|null WooCommerce getter method or null if not found.
     */
    public static function get_wc_getter($attribute_key) {
        foreach (self::$core_attributes as $category => $attributes) {
            if (isset($attributes[$attribute_key]['wc'])) {
                return $attributes[$attribute_key]['wc'];
            }
        }
        return null;
    }

    /**
     * Get Faire field name for an attribute.
     *
     * @param string $attribute_key Attribute key.
     * @return string|null Faire field name or null if not found.
     */
    public static function get_faire_field($attribute_key) {
        foreach (self::$core_attributes as $category => $attributes) {
            if (isset($attributes[$attribute_key]['faire'])) {
                return $attributes[$attribute_key]['faire'];
            }
        }
        return null;
    }

    /**
     * Get transformer method for an attribute if it exists.
     *
     * @param string $attribute_key Attribute key.
     * @return string|null Transformer method name or null if not found.
     */
    public static function get_transformer($attribute_key) {
        foreach (self::$core_attributes as $category => $attributes) {
            if (isset($attributes[$attribute_key]['transformer'])) {
                return $attributes[$attribute_key]['transformer'];
            }
        }
        return null;
    }

    /**
     * Check if an attribute is required.
     *
     * @param string $attribute_key Attribute key.
     * @return bool True if required, false otherwise.
     */
    public static function is_required($attribute_key) {
        foreach (self::$core_attributes as $category => $attributes) {
            if (isset($attributes[$attribute_key])) {
                return !empty($attributes[$attribute_key]['required']);
            }
        }
        return false;
    }

    /**
     * Get attribute description.
     *
     * @param string $attribute_key Attribute key.
     * @return string|null Attribute description or null if not found.
     */
    public static function get_description($attribute_key) {
        foreach (self::$core_attributes as $category => $attributes) {
            if (isset($attributes[$attribute_key]['description'])) {
                return $attributes[$attribute_key]['description'];
            }
        }
        return null;
    }
} 