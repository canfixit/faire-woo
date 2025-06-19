<?php
/**
 * Conflict Resolution Configuration Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

defined('ABSPATH') || exit;

/**
 * Conflict Resolution Configuration Class
 */
class ConflictResolutionConfig {
    /**
     * Get field resolution strategies.
     *
     * @return array
     */
    public static function get_field_strategies() {
        $default_strategies = array(
            'status' => array(
                'strategy' => 'faire_wins',
                'reason' => 'Faire is the source of truth for order status',
                'conditions' => array(
                    'wc_processing_to_completed' => array(
                        'check' => array(__CLASS__, 'check_wc_processing_to_completed'),
                        'strategy' => 'wc_wins',
                        'reason' => 'Allow WC to complete processing orders',
                    ),
                    'wc_cancelled' => array(
                        'check' => array(__CLASS__, 'check_wc_cancelled'),
                        'strategy' => 'manual',
                        'reason' => 'Manual review needed for cancelled orders',
                    ),
                ),
            ),
            'total' => array(
                'strategy' => 'faire_wins',
                'reason' => 'Faire is the source of truth for order totals',
                'conditions' => array(
                    'significant_difference' => array(
                        'check' => array(__CLASS__, 'check_significant_amount_difference'),
                        'strategy' => 'manual',
                        'reason' => 'Manual review needed for significant total differences',
                    ),
                ),
            ),
            'shipping_total' => array(
                'strategy' => 'faire_wins',
                'reason' => 'Faire calculates shipping costs',
            ),
            'tax_total' => array(
                'strategy' => 'faire_wins',
                'reason' => 'Faire handles tax calculations',
            ),
            'discount_total' => array(
                'strategy' => 'faire_wins',
                'reason' => 'Faire manages discounts',
            ),
            'billing_address' => array(
                'strategy' => 'newer_wins',
                'reason' => 'Use the most recently updated address',
                'conditions' => array(
                    'required_fields_missing' => array(
                        'check' => array(__CLASS__, 'check_required_address_fields'),
                        'strategy' => 'keep_complete',
                        'reason' => 'Keep the address with all required fields',
                    ),
                ),
            ),
            'shipping_address' => array(
                'strategy' => 'newer_wins',
                'reason' => 'Use the most recently updated address',
                'conditions' => array(
                    'required_fields_missing' => array(
                        'check' => array(__CLASS__, 'check_required_address_fields'),
                        'strategy' => 'keep_complete',
                        'reason' => 'Keep the address with all required fields',
                    ),
                ),
            ),
            'line_items' => array(
                'strategy' => 'faire_wins',
                'reason' => 'Faire is the source of truth for order items',
                'conditions' => array(
                    'quantity_increase' => array(
                        'check' => array(__CLASS__, 'check_quantity_increase'),
                        'strategy' => 'manual',
                        'reason' => 'Manual review needed for quantity increases',
                    ),
                ),
            ),
            'payment_method' => array(
                'strategy' => 'wc_wins',
                'reason' => 'WooCommerce manages payment methods',
            ),
            'customer_note' => array(
                'strategy' => 'newer_wins',
                'reason' => 'Use the most recent customer note',
            ),
        );

        /**
         * Filter the field resolution strategies.
         *
         * @param array $strategies Field resolution strategies.
         */
        return apply_filters('faire_woo_field_resolution_strategies', $default_strategies);
    }

    /**
     * Check if WC order is transitioning from processing to completed.
     *
     * @param mixed     $wc_value    WooCommerce value.
     * @param mixed     $faire_value Faire value.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return bool
     */
    public static function check_wc_processing_to_completed($wc_value, $faire_value, $wc_order, $faire_order) {
        return $faire_value === 'processing' && $wc_value === 'completed';
    }

    /**
     * Check if WC order is cancelled.
     *
     * @param mixed     $wc_value    WooCommerce value.
     * @param mixed     $faire_value Faire value.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return bool
     */
    public static function check_wc_cancelled($wc_value, $faire_value, $wc_order, $faire_order) {
        return $wc_value === 'cancelled';
    }

    /**
     * Check if there's a significant difference in amounts.
     *
     * @param mixed     $wc_value    WooCommerce value.
     * @param mixed     $faire_value Faire value.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return bool
     */
    public static function check_significant_amount_difference($wc_value, $faire_value, $wc_order, $faire_order) {
        $threshold = apply_filters('faire_woo_significant_amount_difference', 1.00);
        return abs(floatval($wc_value) - floatval($faire_value)) > $threshold;
    }

    /**
     * Check if required address fields are missing.
     *
     * @param mixed     $wc_value    WooCommerce value.
     * @param mixed     $faire_value Faire value.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return bool
     */
    public static function check_required_address_fields($wc_value, $faire_value, $wc_order, $faire_order) {
        $required_fields = array('first_name', 'last_name', 'address_1', 'city', 'country');

        $wc_missing = self::check_missing_fields($wc_value, $required_fields);
        $faire_missing = self::check_missing_fields($faire_value, $required_fields);

        return $wc_missing || $faire_missing;
    }

    /**
     * Check if quantity is being increased.
     *
     * @param mixed     $wc_value    WooCommerce value.
     * @param mixed     $faire_value Faire value.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return bool
     */
    public static function check_quantity_increase($wc_value, $faire_value, $wc_order, $faire_order) {
        if (!is_array($wc_value) || !is_array($faire_value)) {
            return false;
        }

        foreach ($faire_value as $faire_item) {
            $product_id = $faire_item['product_id'];
            $faire_qty = $faire_item['quantity'];

            // Find matching WC item
            $wc_item = self::find_matching_item($wc_value, $product_id);
            if ($wc_item && $faire_qty > $wc_item['quantity']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for missing fields in an array.
     *
     * @param array $data   Data array to check.
     * @param array $fields Required fields.
     * @return bool
     */
    private static function check_missing_fields($data, $fields) {
        if (!is_array($data)) {
            return true;
        }

        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find matching item in WC items array.
     *
     * @param array $items      WC items array.
     * @param int   $product_id Product ID to find.
     * @return array|null
     */
    private static function find_matching_item($items, $product_id) {
        foreach ($items as $item) {
            if ($item['product_id'] == $product_id) {
                return $item;
            }
        }
        return null;
    }
} 