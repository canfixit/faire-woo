<?php
/**
 * Product Transformer Class
 *
 * Handles transformation of product attributes between Faire and WooCommerce formats.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

defined('ABSPATH') || exit;

/**
 * Product Transformer Class
 */
class ProductTransformer {
    /**
     * Transform price from Faire to WooCommerce format.
     *
     * @param mixed $faire_price Price from Faire API.
     * @return string|null WooCommerce formatted price or null if invalid.
     */
    public static function transform_price($faire_price) {
        if (!is_numeric($faire_price)) {
            return null;
        }
        // Faire prices are in cents, WooCommerce expects decimal
        return number_format($faire_price / 100, 2, '.', '');
    }

    /**
     * Transform stock quantity from Faire to WooCommerce format.
     *
     * @param mixed $faire_quantity Quantity from Faire API.
     * @return int|null WooCommerce formatted quantity or null if invalid.
     */
    public static function transform_stock_quantity($faire_quantity) {
        if (!is_numeric($faire_quantity)) {
            return null;
        }
        return max(0, intval($faire_quantity));
    }

    /**
     * Transform stock status from Faire to WooCommerce format.
     *
     * @param bool $faire_in_stock In stock status from Faire API.
     * @return string WooCommerce stock status ('instock' or 'outofstock').
     */
    public static function transform_stock_status($faire_in_stock) {
        return $faire_in_stock ? 'instock' : 'outofstock';
    }

    /**
     * Transform weight from Faire to WooCommerce format.
     *
     * @param array $faire_weight Weight data from Faire API.
     * @return float|null WooCommerce formatted weight or null if invalid.
     */
    public static function transform_weight($faire_weight) {
        if (empty($faire_weight) || !isset($faire_weight['value']) || !isset($faire_weight['unit'])) {
            return null;
        }

        $value = floatval($faire_weight['value']);
        $unit = strtolower($faire_weight['unit']);

        // Convert to WooCommerce's weight unit setting
        $wc_weight_unit = strtolower(get_option('woocommerce_weight_unit', 'kg'));

        switch ($unit) {
            case 'g':
                $value = $value / 1000; // Convert to kg
                break;
            case 'oz':
                $value = $value * 0.0283495; // Convert to kg
                break;
            case 'lb':
                $value = $value * 0.453592; // Convert to kg
                break;
        }

        // Now convert from kg to WooCommerce's unit
        switch ($wc_weight_unit) {
            case 'g':
                return $value * 1000;
            case 'oz':
                return $value * 35.274;
            case 'lb':
                return $value * 2.20462;
            default: // kg
                return $value;
        }
    }

    /**
     * Transform dimension from Faire to WooCommerce format.
     *
     * @param array $faire_dimension Dimension data from Faire API.
     * @return float|null WooCommerce formatted dimension or null if invalid.
     */
    public static function transform_dimension($faire_dimension) {
        if (empty($faire_dimension) || !isset($faire_dimension['value']) || !isset($faire_dimension['unit'])) {
            return null;
        }

        $value = floatval($faire_dimension['value']);
        $unit = strtolower($faire_dimension['unit']);

        // Convert to WooCommerce's dimension unit setting
        $wc_dimension_unit = strtolower(get_option('woocommerce_dimension_unit', 'cm'));

        switch ($unit) {
            case 'mm':
                $value = $value / 10; // Convert to cm
                break;
            case 'in':
                $value = $value * 2.54; // Convert to cm
                break;
            case 'm':
                $value = $value * 100; // Convert to cm
                break;
        }

        // Now convert from cm to WooCommerce's unit
        switch ($wc_dimension_unit) {
            case 'mm':
                return $value * 10;
            case 'in':
                return $value * 0.393701;
            case 'm':
                return $value / 100;
            default: // cm
                return $value;
        }
    }

    /**
     * Transform categories from Faire to WooCommerce format.
     *
     * @param array $faire_categories Categories from Faire API.
     * @return array Array of WooCommerce category IDs.
     */
    public static function transform_categories($faire_categories) {
        if (empty($faire_categories)) {
            return array();
        }

        $wc_categories = array();
        foreach ($faire_categories as $faire_category) {
            // Create or get WooCommerce category
            $term = self::get_or_create_term($faire_category['name'], 'product_cat');
            if ($term && !is_wp_error($term)) {
                $wc_categories[] = $term['term_id'];
            }
        }

        return array_unique($wc_categories);
    }

    /**
     * Transform tags from Faire to WooCommerce format.
     *
     * @param array $faire_tags Tags from Faire API.
     * @return array Array of WooCommerce tag IDs.
     */
    public static function transform_tags($faire_tags) {
        if (empty($faire_tags)) {
            return array();
        }

        $wc_tags = array();
        foreach ($faire_tags as $faire_tag) {
            // Create or get WooCommerce tag
            $term = self::get_or_create_term($faire_tag, 'product_tag');
            if ($term && !is_wp_error($term)) {
                $wc_tags[] = $term['term_id'];
            }
        }

        return array_unique($wc_tags);
    }

    /**
     * Transform main product image from Faire to WooCommerce format.
     *
     * @param array $faire_images Images from Faire API.
     * @return int|null WooCommerce attachment ID or null if no image.
     */
    public static function transform_main_image($faire_images) {
        if (empty($faire_images)) {
            return null;
        }

        // Get the first image URL
        $main_image_url = $faire_images[0]['url'];
        return self::get_or_upload_image($main_image_url);
    }

    /**
     * Transform gallery images from Faire to WooCommerce format.
     *
     * @param array $faire_images Images from Faire API.
     * @return array Array of WooCommerce attachment IDs.
     */
    public static function transform_gallery_images($faire_images) {
        if (empty($faire_images) || count($faire_images) <= 1) {
            return array();
        }

        $gallery_ids = array();
        // Skip the first image as it's used as the main image
        for ($i = 1; $i < count($faire_images); $i++) {
            $attachment_id = self::get_or_upload_image($faire_images[$i]['url']);
            if ($attachment_id) {
                $gallery_ids[] = $attachment_id;
            }
        }

        return $gallery_ids;
    }

    /**
     * Transform product attributes/variations from Faire to WooCommerce format.
     *
     * @param array $faire_variants Variants from Faire API.
     * @return array WooCommerce formatted attributes.
     */
    public static function transform_attributes($faire_variants) {
        if (empty($faire_variants)) {
            return array();
        }

        $attributes = array();
        foreach ($faire_variants as $variant) {
            if (!empty($variant['options'])) {
                foreach ($variant['options'] as $option) {
                    $attr_name = wc_sanitize_taxonomy_name($option['name']);
                    if (!isset($attributes[$attr_name])) {
                        $attributes[$attr_name] = array(
                            'name' => $option['name'],
                            'value' => array(),
                            'visible' => true,
                            'variation' => true,
                        );
                    }
                    $attributes[$attr_name]['value'][] = $option['value'];
                }
            }
        }

        // Deduplicate values
        foreach ($attributes as &$attribute) {
            $attribute['value'] = array_unique($attribute['value']);
        }

        return $attributes;
    }

    /**
     * Transform default attributes from Faire to WooCommerce format.
     *
     * @param array $faire_default_variant Default variant from Faire API.
     * @return array WooCommerce formatted default attributes.
     */
    public static function transform_default_attributes($faire_default_variant) {
        if (empty($faire_default_variant) || empty($faire_default_variant['options'])) {
            return array();
        }

        $default_attributes = array();
        foreach ($faire_default_variant['options'] as $option) {
            $attr_name = wc_sanitize_taxonomy_name($option['name']);
            $default_attributes[$attr_name] = $option['value'];
        }

        return $default_attributes;
    }

    /**
     * Get or create a term in WooCommerce.
     *
     * @param string $name Term name.
     * @param string $taxonomy Taxonomy name.
     * @return array|null Term array or null on failure.
     */
    private static function get_or_create_term($name, $taxonomy) {
        $term = get_term_by('name', $name, $taxonomy);
        if ($term) {
            return array(
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }

        $result = wp_insert_term($name, $taxonomy);
        if (is_wp_error($result)) {
            return null;
        }

        return array(
            'term_id' => $result['term_id'],
            'name' => $name,
            'slug' => sanitize_title($name),
        );
    }

    /**
     * Get or upload an image to WooCommerce media library.
     *
     * @param string $image_url Image URL from Faire.
     * @return int|null Attachment ID or null on failure.
     */
    private static function get_or_upload_image($image_url) {
        // Check if image already exists
        $attachment_id = attachment_url_to_postid($image_url);
        if ($attachment_id) {
            return $attachment_id;
        }

        // Download and upload the image
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $temp_file = download_url($image_url);
        if (is_wp_error($temp_file)) {
            return null;
        }

        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $temp_file,
        );

        $attachment_id = media_handle_sideload($file_array, 0);
        @unlink($temp_file);

        return is_wp_error($attachment_id) ? null : $attachment_id;
    }
} 