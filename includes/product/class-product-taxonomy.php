<?php
/**
 * Product Taxonomy Mapper Class
 *
 * Handles mapping of product taxonomy (categories, tags, etc.) between Faire and WooCommerce.
 * Supports bidirectional mapping and taxonomy synchronization.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Product;

use WC_Product;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Product Taxonomy Mapper Class
 */
class ProductTaxonomy {
    /**
     * Map taxonomy from Faire to WooCommerce format.
     *
     * @param array $faire_product Faire product data.
     * @return array WooCommerce formatted taxonomy data.
     */
    public static function map_faire_to_wc($faire_product) {
        $taxonomy_data = array(
            'categories' => array(),
            'tags' => array(),
        );

        // Map categories
        if (!empty($faire_product['categories'])) {
            foreach ($faire_product['categories'] as $category) {
                $taxonomy_data['categories'][] = sanitize_text_field($category);
            }
        }

        // Map tags
        if (!empty($faire_product['tags'])) {
            foreach ($faire_product['tags'] as $tag) {
                $taxonomy_data['tags'][] = sanitize_text_field($tag);
            }
        }

        return $taxonomy_data;
    }

    /**
     * Map taxonomy from WooCommerce to Faire format.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array Faire formatted taxonomy data.
     */
    public static function map_wc_to_faire($product) {
        $faire_data = array(
            'categories' => array(),
            'tags' => array(),
        );

        // Get categories
        $category_ids = $product->get_category_ids();
        foreach ($category_ids as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $faire_data['categories'][] = $term->name;
            }
        }

        // Get tags
        $tag_ids = $product->get_tag_ids();
        foreach ($tag_ids as $tag_id) {
            $term = get_term($tag_id, 'product_tag');
            if ($term && !is_wp_error($term)) {
                $faire_data['tags'][] = $term->name;
            }
        }

        return $faire_data;
    }

    /**
     * Update WooCommerce product with Faire taxonomy.
     *
     * @param WC_Product $product      WooCommerce product object.
     * @param array      $faire_product Faire product data.
     * @return WC_Product|WP_Error Updated product or error.
     */
    public static function update_wc_taxonomy($product, $faire_product) {
        try {
            $taxonomy_data = self::map_faire_to_wc($faire_product);

            // Set categories
            if (!empty($taxonomy_data['categories'])) {
                $cat_ids = array();
                foreach ($taxonomy_data['categories'] as $cat_name) {
                    $term = term_exists($cat_name, 'product_cat');
                    if (!$term) {
                        $term = wp_insert_term($cat_name, 'product_cat');
                    }
                    if (!is_wp_error($term)) {
                        $cat_ids[] = is_array($term) ? $term['term_id'] : $term;
                    }
                }
                $product->set_category_ids($cat_ids);
            }

            // Set tags
            if (!empty($taxonomy_data['tags'])) {
                $tag_ids = array();
                foreach ($taxonomy_data['tags'] as $tag_name) {
                    $term = term_exists($tag_name, 'product_tag');
                    if (!$term) {
                        $term = wp_insert_term($tag_name, 'product_tag');
                    }
                    if (!is_wp_error($term)) {
                        $tag_ids[] = is_array($term) ? $term['term_id'] : $term;
                    }
                }
                $product->set_tag_ids($tag_ids);
            }

            $product->save();
            return $product;
        } catch (\Exception $e) {
            return new WP_Error(
                'taxonomy_update_failed',
                sprintf(__('Failed to update taxonomy: %s', 'faire-woo'), $e->getMessage())
            );
        }
    }

    /**
     * Compare taxonomy between Faire and WooCommerce.
     *
     * @param array      $faire_product Faire product data.
     * @param WC_Product $wc_product   WooCommerce product object.
     * @return array Array of differences found.
     */
    public static function compare_taxonomy($faire_product, $wc_product) {
        $differences = array();
        $faire_tax = self::map_faire_to_wc($faire_product);

        // Compare categories
        $wc_cats = array();
        foreach ($wc_product->get_category_ids() as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $wc_cats[] = $term->name;
            }
        }
        if (array_diff($faire_tax['categories'], $wc_cats) || array_diff($wc_cats, $faire_tax['categories'])) {
            $differences['categories'] = array(
                'faire' => $faire_tax['categories'],
                'wc'    => $wc_cats,
            );
        }

        // Compare tags
        $wc_tags = array();
        foreach ($wc_product->get_tag_ids() as $tag_id) {
            $term = get_term($tag_id, 'product_tag');
            if ($term && !is_wp_error($term)) {
                $wc_tags[] = $term->name;
            }
        }
        if (array_diff($faire_tax['tags'], $wc_tags) || array_diff($wc_tags, $faire_tax['tags'])) {
            $differences['tags'] = array(
                'faire' => $faire_tax['tags'],
                'wc'    => $wc_tags,
            );
        }

        return $differences;
    }
} 