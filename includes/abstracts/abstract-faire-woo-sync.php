<?php
/**
 * Abstract Sync Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Abstracts;

defined('ABSPATH') || exit;

/**
 * Abstract Sync Class
 */
abstract class FaireWooSync {
    /**
     * Logger instance.
     *
     * @var \WC_Logger
     */
    protected $logger;

    /**
     * Last error message.
     *
     * @var string
     */
    protected $last_error = '';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->logger = wc_get_logger();
    }

    /**
     * Log a message.
     *
     * @param string $message Message to log.
     * @param string $level   Optional. Log level. Default 'info'.
     * @return void
     */
    protected function log($message, $level = 'info') {
        $context = array('source' => 'faire-woo-sync');
        $this->logger->log($level, $message, $context);
    }

    /**
     * Set error message.
     *
     * @param string $message Error message.
     * @return void
     */
    protected function set_error($message) {
        $this->last_error = $message;
        $this->log($message, 'error');
    }

    /**
     * Get last error message.
     *
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Check if there was an error.
     *
     * @return bool
     */
    public function has_error() {
        return !empty($this->last_error);
    }

    /**
     * Clear error message.
     *
     * @return void
     */
    protected function clear_error() {
        $this->last_error = '';
    }

    /**
     * Format data for logging.
     *
     * @param mixed $data Data to format.
     * @return string
     */
    protected function format_log_data($data) {
        if (is_array($data) || is_object($data)) {
            return print_r($data, true);
        }
        return (string) $data;
    }

    /**
     * Get timestamp for logging.
     *
     * @return string
     */
    protected function get_log_timestamp() {
        return current_time('mysql');
    }

    /**
     * Log with context.
     *
     * @param string $message Message to log.
     * @param array  $context Context data.
     * @param string $level   Optional. Log level. Default 'info'.
     * @return void
     */
    protected function log_with_context($message, array $context = array(), $level = 'info') {
        $context_str = $this->format_log_data($context);
        $timestamp = $this->get_log_timestamp();
        $this->log(sprintf('[%s] %s | Context: %s', $timestamp, $message, $context_str), $level);
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return bool
     */
    protected function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Validate data array against required fields.
     *
     * @param array $data           Data to validate.
     * @param array $required_fields Required field names.
     * @return bool True if valid, false otherwise.
     */
    protected function validate_required_fields(array $data, array $required_fields) {
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->set_error(sprintf('Required field "%s" is missing or empty', $field));
                return false;
            }
        }
        return true;
    }

    /**
     * Get WooCommerce order by ID.
     *
     * @param int $order_id Order ID.
     * @return \WC_Order|false Order object or false on failure.
     */
    protected function get_wc_order($order_id) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                $this->set_error(sprintf('WooCommerce order %d not found', $order_id));
                return false;
            }
            return $order;
        } catch (\Exception $e) {
            $this->set_error(sprintf('Error getting WooCommerce order %d: %s', $order_id, $e->getMessage()));
            return false;
        }
    }

    /**
     * Save WooCommerce order.
     *
     * @param \WC_Order $order Order object.
     * @return bool True on success, false on failure.
     */
    protected function save_wc_order(\WC_Order $order) {
        try {
            $order->save();
            return true;
        } catch (\Exception $e) {
            $this->set_error(sprintf('Error saving WooCommerce order %d: %s', $order->get_id(), $e->getMessage()));
            return false;
        }
    }
} 