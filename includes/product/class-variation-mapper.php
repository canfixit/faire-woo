<?php
/**
 * Variation Mapper Class
 *
 * Handles mapping of product variations between Faire and WooCommerce.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Variation Mapper Class
 */
class VariationMapper {
    /**
     * Map variations from Faire to WooCommerce format.
     *
     * @param array $faire_variants Faire variant data.
     * @return array WooCommerce formatted variation data.
     */
    public static function map_faire_to_wc($faire_variants) {
        if (empty($faire_variants)) {
            return array();
        }

        $variations = array();
        $attributes = self::extract_variation_attributes($faire_variants);

        foreach ($faire_variants as $variant) {
            $variation = array(
                'attributes' => array(),
                'sku' => isset($variant['sku']) ? $variant['sku'] : '',
                'regular_price' => isset($variant['retail_price']) ? 
                    ProductTransformer::transform_price($variant['retail_price']) : '',
                'stock_quantity' => isset($variant['quantity']) ? 
                    ProductTransformer::transform_stock_quantity($variant['quantity']) : 0,
                'manage_stock' => true,
                'stock_status' => isset($variant['in_stock']) ? 
                    ProductTransformer::transform_stock_status($variant['in_stock']) : 'instock',
            );

            // Map variation attributes
            if (!empty($variant['options'])) {
                foreach ($variant['options'] as $option) {
                    $attr_name = wc_sanitize_taxonomy_name($option['name']);
                    $variation['attributes']['attribute_' . $attr_name] = $option['value'];
                }
            }

            $variations[] = $variation;
        }

        return array(
            'attributes' => $attributes,
            'variations' => $variations,
        );
    }

    /**
     * Map variations from WooCommerce to Faire format.
     *
     * @param WC_Product_Variable $product WooCommerce variable product.
     * @return array Faire formatted variant data.
     */
    public static function map_wc_to_faire($product) {
        if (!$product->is_type('variable')) {
            return array();
        }

        $variants = array();
        $variations = $product->get_available_variations();

        foreach ($variations as $variation_data) {
            $variation = wc_get_product($variation_data['variation_id']);
            if (!$variation) {
                continue;
            }

            $variant = array(
                'sku' => $variation->get_sku(),
                'retail_price' => ProductTransformer::transform_price_to_faire($variation->get_regular_price()),
                'quantity' => $variation->get_stock_quantity(),
                'in_stock' => $variation->is_in_stock(),
                'options' => array(),
            );

            // Map variation attributes
            foreach ($variation->get_attributes() as $attr_name => $attr_value) {
                $taxonomy = str_replace('attribute_', '', $attr_name);
                $label = wc_attribute_label($taxonomy, $product);
                
                $variant['options'][] = array(
                    'name' => $label,
                    'value' => $attr_value,
                );
            }

            $variants[] = $variant;
        }

        return $variants;
    }

    /**
     * Compare variations between Faire and WooCommerce.
     *
     * @param array              $faire_variants Faire variant data.
     * @param WC_Product_Variable $wc_product    WooCommerce variable product.
     * @return array Array of differences found.
     */
    public static function compare_variations($faire_variants, $wc_product) {
        if (!$wc_product->is_type('variable')) {
            return array('error' => 'WooCommerce product is not variable');
        }

        $differences = array(
            'attributes' => array(),
            'variations' => array(),
            'missing_in_wc' => array(),
            'missing_in_faire' => array(),
        );

        // Compare attributes
        $faire_attrs = self::extract_variation_attributes($faire_variants);
        $wc_attrs = $wc_product->get_attributes();

        foreach ($faire_attrs as $name => $attr) {
            if (!isset($wc_attrs[$name]) || 
                count(array_diff($attr['values'], $wc_attrs[$name]->get_options())) > 0) {
                $differences['attributes'][$name] = array(
                    'faire_values' => $attr['values'],
                    'wc_values' => isset($wc_attrs[$name]) ? $wc_attrs[$name]->get_options() : array(),
                );
            }
        }

        // Compare variations
        $wc_variations = $wc_product->get_available_variations();
        $faire_skus = array_column($faire_variants, 'sku');
        $wc_skus = array_map(function($var) {
            return wc_get_product($var['variation_id'])->get_sku();
        }, $wc_variations);

        // Find variations missing in WooCommerce
        foreach ($faire_variants as $variant) {
            if (!in_array($variant['sku'], $wc_skus)) {
                $differences['missing_in_wc'][] = $variant['sku'];
            }
        }

        // Find variations missing in Faire
        foreach ($wc_variations as $variation) {
            $sku = wc_get_product($variation['variation_id'])->get_sku();
            if (!in_array($sku, $faire_skus)) {
                $differences['missing_in_faire'][] = $sku;
            }
        }

        // Compare existing variations
        foreach ($faire_variants as $faire_variant) {
            foreach ($wc_variations as $wc_variation) {
                $wc_var_product = wc_get_product($wc_variation['variation_id']);
                if ($wc_var_product->get_sku() === $faire_variant['sku']) {
                    $var_diff = self::compare_variation_data($faire_variant, $wc_var_product);
                    if (!empty($var_diff)) {
                        $differences['variations'][$faire_variant['sku']] = $var_diff;
                    }
                }
            }
        }

        return $differences;
    }

    /**
     * Update WooCommerce product variations with Faire data.
     *
     * @param WC_Product_Variable $product       WooCommerce variable product.
     * @param array              $faire_variants Faire variant data.
     * @return WC_Product_Variable|WP_Error Updated product or error.
     */
    public static function update_wc_variations($product, $faire_variants) {
        try {
            if (!$product->is_type('variable')) {
                return new WP_Error(
                    'invalid_product_type',
                    __('Product must be variable to update variations', 'faire-woo')
                );
            }

            // Update attributes first
            $mapped_data = self::map_faire_to_wc($faire_variants);
            $product->set_attributes($mapped_data['attributes']);
            $product->save();

            // Update/create variations
            foreach ($mapped_data['variations'] as $variation_data) {
                $variation_id = self::find_matching_variation($product, $variation_data['attributes']);
                
                if ($variation_id) {
                    // Update existing variation
                    $variation = wc_get_product($variation_id);
                } else {
                    // Create new variation
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product->get_id());
                }

                // Set variation data
                foreach ($variation_data as $key => $value) {
                    $setter = 'set_' . $key;
                    if (method_exists($variation, $setter)) {
                        $variation->$setter($value);
                    }
                }

                $variation->save();
            }

            // Remove variations that don't exist in Faire
            $faire_skus = array_column($faire_variants, 'sku');
            foreach ($product->get_children() as $child_id) {
                $variation = wc_get_product($child_id);
                if (!in_array($variation->get_sku(), $faire_skus)) {
                    $variation->delete(true);
                }
            }

            $product->save();
            return $product;

        } catch (\Exception $e) {
            return new WP_Error(
                'variation_update_failed',
                sprintf(__('Failed to update variations: %s', 'faire-woo'), $e->getMessage())
            );
        }
    }

    /**
     * Extract variation attributes from Faire variants.
     *
     * @param array $faire_variants Faire variant data.
     * @return array WooCommerce formatted attributes.
     */
    private static function extract_variation_attributes($faire_variants) {
        $attributes = array();

        foreach ($faire_variants as $variant) {
            if (!empty($variant['options'])) {
                foreach ($variant['options'] as $option) {
                    $attr_name = wc_sanitize_taxonomy_name($option['name']);
                    
                    if (!isset($attributes[$attr_name])) {
                        $attributes[$attr_name] = array(
                            'name' => $option['name'],
                            'values' => array(),
                            'visible' => true,
                            'variation' => true,
                        );
                    }
                    
                    if (!in_array($option['value'], $attributes[$attr_name]['values'])) {
                        $attributes[$attr_name]['values'][] = $option['value'];
                    }
                }
            }
        }

        return $attributes;
    }

    /**
     * Compare variation data between Faire and WooCommerce.
     *
     * @param array           $faire_variant Faire variant data.
     * @param WC_Product_Variation $wc_variation  WooCommerce variation object.
     * @return array Array of differences found.
     */
    private static function compare_variation_data($faire_variant, $wc_variation) {
        $differences = array();

        // Compare price
        $faire_price = ProductTransformer::transform_price($faire_variant['retail_price']);
        if ($faire_price != $wc_variation->get_regular_price()) {
            $differences['price'] = array(
                'faire' => $faire_price,
                'wc' => $wc_variation->get_regular_price(),
            );
        }

        // Compare stock
        $faire_stock = ProductTransformer::transform_stock_quantity($faire_variant['quantity']);
        if ($faire_stock != $wc_variation->get_stock_quantity()) {
            $differences['stock'] = array(
                'faire' => $faire_stock,
                'wc' => $wc_variation->get_stock_quantity(),
            );
        }

        // Compare attributes
        $wc_attributes = $wc_variation->get_attributes();
        foreach ($faire_variant['options'] as $option) {
            $attr_name = 'attribute_' . wc_sanitize_taxonomy_name($option['name']);
            if (!isset($wc_attributes[$attr_name]) || 
                $wc_attributes[$attr_name] !== $option['value']) {
                $differences['attributes'][$option['name']] = array(
                    'faire' => $option['value'],
                    'wc' => isset($wc_attributes[$attr_name]) ? $wc_attributes[$attr_name] : null,
                );
            }
        }

        return $differences;
    }

    /**
     * Find matching variation ID by attributes.
     *
     * @param WC_Product_Variable $product    WooCommerce variable product.
     * @param array              $attributes Variation attributes.
     * @return int|null Variation ID if found, null otherwise.
     */
    private static function find_matching_variation($product, $attributes) {
        $variation_ids = $product->get_children();
        
        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }

            $match = true;
            foreach ($attributes as $name => $value) {
                if ($variation->get_attribute(str_replace('attribute_', '', $name)) !== $value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                return $variation_id;
            }
        }

        return null;
    }
} 