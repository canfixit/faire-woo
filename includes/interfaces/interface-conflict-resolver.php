<?php
/**
 * Conflict Resolver Interface
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Interfaces;

defined('ABSPATH') || exit;

/**
 * Conflict Resolver Interface
 */
interface ConflictResolver {
    /**
     * Resolve conflicts between WooCommerce and Faire orders.
     *
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @param array     $differences Array of differences from OrderComparator.
     * @return array Array containing resolved values and actions taken.
     */
    public function resolve_conflicts(\WC_Order $wc_order, array $faire_order, array $differences);

    /**
     * Resolve a specific field conflict.
     *
     * @param string    $field       Field name with conflict.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @param array     $difference  The specific difference data for this field.
     * @return array Array containing resolved value and action taken.
     */
    public function resolve_field_conflict($field, \WC_Order $wc_order, array $faire_order, array $difference);

    /**
     * Get resolution strategy for a specific field.
     *
     * @param string $field Field name.
     * @return string Strategy to use ('wc_wins', 'faire_wins', 'newer_wins', 'manual').
     */
    public function get_field_resolution_strategy($field);

    /**
     * Apply resolved values to the WooCommerce order.
     *
     * @param \WC_Order $wc_order       WooCommerce order object.
     * @param array     $resolved_values Array of resolved values to apply.
     * @return bool True if successful, false otherwise.
     */
    public function apply_resolved_values(\WC_Order $wc_order, array $resolved_values);

    /**
     * Log conflict resolution actions.
     *
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @param array     $resolutions Array of resolutions applied.
     * @return void
     */
    public function log_resolutions(\WC_Order $wc_order, array $faire_order, array $resolutions);
} 