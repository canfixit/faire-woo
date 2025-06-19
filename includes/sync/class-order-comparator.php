<?php
/**
 * Order Comparator Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

use FaireWoo\Abstracts\FaireWooSync;
use FaireWoo\Interfaces\OrderComparator as OrderComparatorInterface;

defined('ABSPATH') || exit;

/**
 * Order Comparator Class
 */
class OrderComparator extends FaireWooSync implements OrderComparatorInterface {
    /**
     * Fields to compare between WooCommerce and Faire orders.
     *
     * @var array
     */
    protected $comparable_fields = array(
        'status',
        'total',
        'shipping_total',
        'tax_total',
        'discount_total',
        'billing_address',
        'shipping_address',
        'line_items',
        'payment_method',
        'customer_note',
    );

    /**
     * Field mapping between WooCommerce and Faire.
     *
     * @var array
     */
    protected $field_mapping = array(
        'status' => array(
            'wc' => 'get_status',
            'faire' => 'status',
            'transformer' => 'transform_status',
        ),
        'total' => array(
            'wc' => 'get_total',
            'faire' => 'total_amount',
            'transformer' => 'transform_amount',
        ),
        'shipping_total' => array(
            'wc' => 'get_shipping_total',
            'faire' => 'shipping_amount',
            'transformer' => 'transform_amount',
        ),
        'tax_total' => array(
            'wc' => 'get_total_tax',
            'faire' => 'tax_amount',
            'transformer' => 'transform_amount',
        ),
        'discount_total' => array(
            'wc' => 'get_discount_total',
            'faire' => 'discount_amount',
            'transformer' => 'transform_amount',
        ),
    );

    /**
     * Compare two orders and return the differences.
     *
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return array Array of differences with field names as keys and arrays containing WC and Faire values.
     */
    public function compare_orders(\WC_Order $wc_order, array $faire_order) {
        if (!$this->validate_orders($wc_order, $faire_order)) {
            return array();
        }

        $differences = array();
        foreach ($this->get_comparable_fields() as $field) {
            $field_diff = $this->compare_field($field, $wc_order, $faire_order);
            if ($field_diff !== null) {
                $differences[$field] = $field_diff;
            }
        }

        return $differences;
    }

    /**
     * Compare specific fields between orders.
     *
     * @param string    $field       Field name to compare.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return array|null Array containing WC and Faire values if different, null if same.
     */
    public function compare_field($field, \WC_Order $wc_order, array $faire_order) {
        if (!in_array($field, $this->get_comparable_fields(), true)) {
            $this->log(sprintf('Invalid field for comparison: %s', $field), 'warning');
            return null;
        }

        $wc_value = $this->get_wc_field_value($field, $wc_order);
        $faire_value = $this->get_faire_field_value($field, $faire_order);

        // Transform values if needed
        if (isset($this->field_mapping[$field]['transformer'])) {
            $transformer = $this->field_mapping[$field]['transformer'];
            $wc_value = $this->$transformer($wc_value, 'wc');
            $faire_value = $this->$transformer($faire_value, 'faire');
        }

        if ($this->values_are_different($wc_value, $faire_value)) {
            return array(
                'wc' => $wc_value,
                'faire' => $faire_value,
            );
        }

        return null;
    }

    /**
     * Get the list of fields to compare.
     *
     * @return array Array of field names to compare.
     */
    public function get_comparable_fields() {
        return $this->comparable_fields;
    }

    /**
     * Validate order data before comparison.
     *
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return bool True if valid, false otherwise.
     */
    public function validate_orders(\WC_Order $wc_order, array $faire_order) {
        if (!$wc_order || !$wc_order->get_id()) {
            $this->set_error('Invalid WooCommerce order');
            return false;
        }

        $required_faire_fields = array('id', 'status', 'total_amount');
        if (!$this->validate_required_fields($faire_order, $required_faire_fields)) {
            return false;
        }

        return true;
    }

    /**
     * Get WooCommerce field value.
     *
     * @param string    $field    Field name.
     * @param \WC_Order $wc_order WooCommerce order object.
     * @return mixed
     */
    protected function get_wc_field_value($field, \WC_Order $wc_order) {
        if (isset($this->field_mapping[$field]['wc'])) {
            $method = $this->field_mapping[$field]['wc'];
            return $wc_order->$method();
        }

        // Handle special cases
        switch ($field) {
            case 'billing_address':
                return $this->get_wc_address($wc_order, 'billing');
            case 'shipping_address':
                return $this->get_wc_address($wc_order, 'shipping');
            case 'line_items':
                return $this->get_wc_line_items($wc_order);
            default:
                return null;
        }
    }

    /**
     * Get Faire field value.
     *
     * @param string $field       Field name.
     * @param array  $faire_order Faire order data.
     * @return mixed
     */
    protected function get_faire_field_value($field, array $faire_order) {
        if (isset($this->field_mapping[$field]['faire'])) {
            $key = $this->field_mapping[$field]['faire'];
            return isset($faire_order[$key]) ? $faire_order[$key] : null;
        }

        // Handle special cases
        switch ($field) {
            case 'billing_address':
                return $this->get_faire_address($faire_order, 'billing');
            case 'shipping_address':
                return $this->get_faire_address($faire_order, 'shipping');
            case 'line_items':
                return $this->get_faire_line_items($faire_order);
            default:
                return null;
        }
    }

    /**
     * Transform status values for comparison.
     *
     * @param string $status Status value.
     * @param string $source Source of the status ('wc' or 'faire').
     * @return string
     */
    protected function transform_status($status, $source) {
        $status = strtolower($status);

        $status_map = array(
            'wc' => array(
                'pending' => 'pending',
                'processing' => 'processing',
                'completed' => 'completed',
                'cancelled' => 'cancelled',
                'refunded' => 'refunded',
                'failed' => 'failed',
            ),
            'faire' => array(
                'pending' => 'pending',
                'processing' => 'processing',
                'shipped' => 'completed',
                'cancelled' => 'cancelled',
                'refunded' => 'refunded',
                'failed' => 'failed',
            ),
        );

        return isset($status_map[$source][$status]) ? $status_map[$source][$status] : $status;
    }

    /**
     * Transform amount values for comparison.
     *
     * @param mixed  $amount Amount value.
     * @param string $source Source of the amount ('wc' or 'faire').
     * @return float
     */
    protected function transform_amount($amount, $source) {
        return round(floatval($amount), 2);
    }

    /**
     * Get WooCommerce address data.
     *
     * @param \WC_Order $order Order object.
     * @param string    $type  Address type ('billing' or 'shipping').
     * @return array
     */
    protected function get_wc_address(\WC_Order $order, $type) {
        $fields = array(
            'first_name',
            'last_name',
            'company',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode',
            'country',
            'email',
            'phone',
        );

        $address = array();
        foreach ($fields as $field) {
            $method = "get_{$type}_{$field}";
            $address[$field] = $order->$method();
        }

        return array_filter($address);
    }

    /**
     * Get Faire address data.
     *
     * @param array  $order Faire order data.
     * @param string $type  Address type ('billing' or 'shipping').
     * @return array
     */
    protected function get_faire_address(array $order, $type) {
        $address_key = "{$type}_address";
        return isset($order[$address_key]) ? $order[$address_key] : array();
    }

    /**
     * Get WooCommerce line items.
     *
     * @param \WC_Order $order Order object.
     * @return array
     */
    protected function get_wc_line_items(\WC_Order $order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'product_id' => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                'tax' => $item->get_total_tax(),
            );
        }
        return $items;
    }

    /**
     * Get Faire line items.
     *
     * @param array $order Faire order data.
     * @return array
     */
    protected function get_faire_line_items(array $order) {
        return isset($order['items']) ? $order['items'] : array();
    }

    /**
     * Compare two values for differences.
     *
     * @param mixed $wc_value    WooCommerce value.
     * @param mixed $faire_value Faire value.
     * @return bool True if different, false if same.
     */
    protected function values_are_different($wc_value, $faire_value) {
        // Handle arrays (like addresses and line items)
        if (is_array($wc_value) && is_array($faire_value)) {
            return $this->arrays_are_different($wc_value, $faire_value);
        }

        // Handle scalar values
        return $wc_value !== $faire_value;
    }

    /**
     * Compare two arrays for differences.
     *
     * @param array $array1 First array.
     * @param array $array2 Second array.
     * @return bool True if different, false if same.
     */
    protected function arrays_are_different(array $array1, array $array2) {
        // Sort arrays to ensure consistent comparison
        $array1 = $this->sort_array_recursively($array1);
        $array2 = $this->sort_array_recursively($array2);

        return $array1 !== $array2;
    }

    /**
     * Sort array recursively.
     *
     * @param array $array Array to sort.
     * @return array
     */
    protected function sort_array_recursively(array $array) {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = $this->sort_array_recursively($value);
            }
        }
        ksort($array);
        return $array;
    }
} 