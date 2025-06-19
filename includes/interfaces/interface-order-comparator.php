<?php
/**
 * Order Comparator Interface
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Interfaces;

defined('ABSPATH') || exit;

/**
 * Order Comparator Interface
 */
interface OrderComparator {
    /**
     * Compare two orders and return the differences.
     *
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return array Array of differences with field names as keys and arrays containing WC and Faire values.
     */
    public function compare_orders(\WC_Order $wc_order, array $faire_order);

    /**
     * Compare specific fields between orders.
     *
     * @param string    $field       Field name to compare.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return array|null Array containing WC and Faire values if different, null if same.
     */
    public function compare_field($field, \WC_Order $wc_order, array $faire_order);

    /**
     * Get the list of fields to compare.
     *
     * @return array Array of field names to compare.
     */
    public function get_comparable_fields();

    /**
     * Validate order data before comparison.
     *
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return bool True if valid, false otherwise.
     */
    public function validate_orders(\WC_Order $wc_order, array $faire_order);
} 