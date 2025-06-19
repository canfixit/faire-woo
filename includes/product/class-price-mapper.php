<?php
/**
 * Price Mapper Class
 *
 * Handles mapping of pricing information between Faire and WooCommerce.
 * Specifically focuses on RRP (Recommended Retail Price) as per requirements.
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
 * Price Mapper Class
 */
class PriceMapper {
    /**
     * Default markup percentage if RRP is not available.
     * This can be configured through settings.
     */
    const DEFAULT_MARKUP_PERCENTAGE = 100; // 100% markup by default

    /**
     * Map pricing from Faire to WooCommerce format.
     * Only maps RRP as per requirements.
     *
     * @param array $faire_product Faire product data.
     * @return array WooCommerce formatted price data.
     */
    public static function map_faire_to_wc($faire_product) {
        $price_data = array();
        $pricing_attrs = ProductAttributes::get_attributes_by_category('pricing');

        // We only care about RRP (retail_price) from Faire
        if (isset($faire_product['retail_price'])) {
            $price_data['regular_price'] = ProductTransformer::transform_price($faire_product['retail_price']);
        }

        // Sale price is not imported from Faire per requirements
        $price_data['sale_price'] = '';

        return $price_data;
    }

    /**
     * Map pricing from WooCommerce to Faire format.
     * Only maps regular price to RRP.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return array Faire formatted price data.
     */
    public static function map_wc_to_faire($product) {
        $faire_data = array();
        $pricing_attrs = ProductAttributes::get_attributes_by_category('pricing');

        // Only map regular price to RRP
        if ($regular_price = $product->get_regular_price()) {
            $faire_data['retail_price'] = ProductTransformer::transform_price_to_faire($regular_price);
        }

        return $faire_data;
    }

    /**
     * Compare pricing between Faire and WooCommerce.
     *
     * @param array      $faire_product Faire product data.
     * @param WC_Product $wc_product   WooCommerce product object.
     * @return array Array of differences found.
     */
    public static function compare_prices($faire_product, $wc_product) {
        $differences = array();

        // Compare RRP
        if (isset($faire_product['retail_price'])) {
            $faire_rrp = ProductTransformer::transform_price($faire_product['retail_price']);
            $wc_price = $wc_product->get_regular_price();

            if ($faire_rrp != $wc_price) {
                $differences['regular_price'] = array(
                    'faire_rrp' => $faire_rrp,
                    'wc_price' => $wc_price,
                    'difference' => abs($faire_rrp - $wc_price),
                );
            }
        }

        return $differences;
    }

    /**
     * Update WooCommerce product with Faire pricing.
     *
     * @param WC_Product $product      WooCommerce product object.
     * @param array      $faire_product Faire product data.
     * @return WC_Product|WP_Error Updated product or error.
     */
    public static function update_wc_prices($product, $faire_product) {
        try {
            // Only update if RRP is available
            if (isset($faire_product['retail_price'])) {
                $rrp = ProductTransformer::transform_price($faire_product['retail_price']);
                
                // Set regular price to RRP
                $product->set_regular_price($rrp);
                
                // Clear sale price as per requirements
                $product->set_sale_price('');

                // If it's a variable product, update variation prices
                if ($product->is_type('variable')) {
                    self::update_variation_prices($product, $faire_product);
                }

                $product->save();
            }

            return $product;

        } catch (\Exception $e) {
            return new WP_Error(
                'price_update_failed',
                sprintf(__('Failed to update prices: %s', 'faire-woo'), $e->getMessage())
            );
        }
    }

    /**
     * Update variation prices based on Faire data.
     *
     * @param WC_Product_Variable $product      WooCommerce variable product.
     * @param array              $faire_product Faire product data.
     */
    private static function update_variation_prices($product, $faire_product) {
        if (empty($faire_product['variants'])) {
            return;
        }

        foreach ($faire_product['variants'] as $variant) {
            // Find matching variation by SKU
            $variation_id = self::find_variation_by_sku($product, $variant['sku']);
            if (!$variation_id) {
                continue;
            }

            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }

            // Update variation RRP if available
            if (isset($variant['retail_price'])) {
                $rrp = ProductTransformer::transform_price($variant['retail_price']);
                $variation->set_regular_price($rrp);
                $variation->set_sale_price('');
                $variation->save();
            }
        }
    }

    /**
     * Calculate RRP if not provided by Faire.
     *
     * @param float $wholesale_price Wholesale price from Faire.
     * @return float Calculated RRP.
     */
    public static function calculate_rrp($wholesale_price) {
        // Get markup percentage from settings or use default
        $markup_percentage = get_option('faire_woo_markup_percentage', self::DEFAULT_MARKUP_PERCENTAGE);
        
        // Calculate RRP: wholesale price + markup
        $markup_multiplier = (100 + $markup_percentage) / 100;
        return round($wholesale_price * $markup_multiplier, 2);
    }

    /**
     * Validate price data.
     *
     * @param array $price_data Price data to validate.
     * @return true|WP_Error True if valid, WP_Error if invalid.
     */
    public static function validate_prices($price_data) {
        $errors = array();

        // Check if RRP is present and valid
        if (!isset($price_data['retail_price'])) {
            $errors[] = __('Retail price (RRP) is required', 'faire-woo');
        } elseif (!is_numeric($price_data['retail_price'])) {
            $errors[] = __('Retail price (RRP) must be numeric', 'faire-woo');
        } elseif ($price_data['retail_price'] < 0) {
            $errors[] = __('Retail price (RRP) cannot be negative', 'faire-woo');
        }

        if (!empty($errors)) {
            return new WP_Error(
                'invalid_price_data',
                implode('. ', $errors)
            );
        }

        return true;
    }

    /**
     * Find variation by SKU.
     *
     * @param WC_Product_Variable $product WooCommerce variable product.
     * @param string             $sku     SKU to find.
     * @return int|null Variation ID if found, null otherwise.
     */
    private static function find_variation_by_sku($product, $sku) {
        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->get_sku() === $sku) {
                return $variation_id;
            }
        }
        return null;
    }
} 