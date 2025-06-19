<?php
/**
 * Product Dimensions Mapper Class
 *
 * Handles mapping of product dimensions and shipping information between Faire and WooCommerce.
 * Supports bidirectional mapping and synchronization of weight, length, width, height, and shipping class.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

use WC_Product;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Product Dimensions Mapper Class
 */
class ProductDimensions {
    /**
     * Map dimensions from Faire to WooCommerce format.
     *
     * @param array $faire_product Faire product data.
     * @return array WooCommerce formatted dimensions data.
     */
    public static function map_faire_to_wc($faire_product) {
        $dimensions = array(
            'weight' => isset($faire_product['weight']) ? floatval($faire_product['weight']) : null,
            'length' => isset($faire_product['length']) ? floatval($faire_product['length']) : null,
            'width'  => isset($faire_product['width']) ? floatval($faire_product['width']) : null,
            'height' => isset($faire_product['height']) ? floatval($faire_product['height']) : null,
            'shipping_class' => isset($faire_product['shipping_class']) ? sanitize_text_field($faire_product['shipping_class']) : '',
        );
        return $dimensions;
    }

    /**
     * Map dimensions from WooCommerce to Faire format.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array Faire formatted dimensions data.
     */
    public static function map_wc_to_faire($product) {
        $faire_data = array(
            'weight' => $product->get_weight(),
            'length' => $product->get_length(),
            'width'  => $product->get_width(),
            'height' => $product->get_height(),
            'shipping_class' => '',
        );
        $shipping_class_id = $product->get_shipping_class_id();
        if ($shipping_class_id) {
            $term = get_term($shipping_class_id, 'product_shipping_class');
            if ($term && !is_wp_error($term)) {
                $faire_data['shipping_class'] = $term->name;
            }
        }
        return $faire_data;
    }

    /**
     * Update WooCommerce product with Faire dimensions.
     *
     * @param WC_Product $product      WooCommerce product object.
     * @param array      $faire_product Faire product data.
     * @return WC_Product|WP_Error Updated product or error.
     */
    public static function update_wc_dimensions($product, $faire_product) {
        try {
            $dimensions = self::map_faire_to_wc($faire_product);
            if (!is_null($dimensions['weight'])) {
                $product->set_weight($dimensions['weight']);
            }
            if (!is_null($dimensions['length'])) {
                $product->set_length($dimensions['length']);
            }
            if (!is_null($dimensions['width'])) {
                $product->set_width($dimensions['width']);
            }
            if (!is_null($dimensions['height'])) {
                $product->set_height($dimensions['height']);
            }
            if (!empty($dimensions['shipping_class'])) {
                $term = term_exists($dimensions['shipping_class'], 'product_shipping_class');
                if (!$term) {
                    $term = wp_insert_term($dimensions['shipping_class'], 'product_shipping_class');
                }
                if (!is_wp_error($term)) {
                    $shipping_class_id = is_array($term) ? $term['term_id'] : $term;
                    $product->set_shipping_class_id($shipping_class_id);
                }
            }
            $product->save();
            return $product;
        } catch (\Exception $e) {
            return new WP_Error(
                'dimensions_update_failed',
                sprintf(__('Failed to update dimensions: %s', 'faire-woo'), $e->getMessage())
            );
        }
    }

    /**
     * Compare dimensions between Faire and WooCommerce.
     *
     * @param array      $faire_product Faire product data.
     * @param WC_Product $wc_product   WooCommerce product object.
     * @return array Array of differences found.
     */
    public static function compare_dimensions($faire_product, $wc_product) {
        $differences = array();
        $faire_dim = self::map_faire_to_wc($faire_product);
        if ($wc_product->get_weight() != $faire_dim['weight']) {
            $differences['weight'] = array('faire' => $faire_dim['weight'], 'wc' => $wc_product->get_weight());
        }
        if ($wc_product->get_length() != $faire_dim['length']) {
            $differences['length'] = array('faire' => $faire_dim['length'], 'wc' => $wc_product->get_length());
        }
        if ($wc_product->get_width() != $faire_dim['width']) {
            $differences['width'] = array('faire' => $faire_dim['width'], 'wc' => $wc_product->get_width());
        }
        if ($wc_product->get_height() != $faire_dim['height']) {
            $differences['height'] = array('faire' => $faire_dim['height'], 'wc' => $wc_product->get_height());
        }
        $wc_shipping_class = '';
        $shipping_class_id = $wc_product->get_shipping_class_id();
        if ($shipping_class_id) {
            $term = get_term($shipping_class_id, 'product_shipping_class');
            if ($term && !is_wp_error($term)) {
                $wc_shipping_class = $term->name;
            }
        }
        if ($wc_shipping_class !== $faire_dim['shipping_class']) {
            $differences['shipping_class'] = array('faire' => $faire_dim['shipping_class'], 'wc' => $wc_shipping_class);
        }
        return $differences;
    }
} 