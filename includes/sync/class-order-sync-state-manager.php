<?php
/**
 * Order Sync State Manager Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

defined('ABSPATH') || exit;

/**
 * OrderSyncStateManager
 *
 * Manages the state of orders during the synchronization process.
 */
class OrderSyncStateManager {
    /**
     * Database table name for state storage
     */
    const TABLE_NAME = 'faire_woo_order_states';

    /**
     * Maximum number of retry attempts
     */
    const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Retry delay in seconds
     */
    const RETRY_DELAY = 300; // 5 minutes

    /**
     * Error logger instance
     *
     * @var ErrorLogger
     */
    private $error_logger;

    /**
     * State machine instance
     *
     * @var OrderSyncStateMachine
     */
    private $state_machine;

    /**
     * Constructor
     *
     * @param ErrorLogger          $error_logger  Error logger instance.
     * @param OrderSyncStateMachine $state_machine State machine instance.
     */
    public function __construct(ErrorLogger $error_logger, OrderSyncStateMachine $state_machine) {
        $this->error_logger = $error_logger;
        $this->state_machine = $state_machine;
    }

    /**
     * Get the current state for an order
     *
     * @param string $order_id        WooCommerce order ID.
     * @param string $faire_order_id  Faire order ID.
     * @return string|null Current state or null if not found.
     */
    public function get_state($order_id, $faire_order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT state FROM {$table_name} WHERE order_id = %s AND faire_order_id = %s ORDER BY created_at DESC LIMIT 1",
                $order_id,
                $faire_order_id
            )
        );

        return $result ? $result->state : null;
    }

    /**
     * Set the state for an order
     *
     * @param string $order_id        WooCommerce order ID.
     * @param string $faire_order_id  Faire order ID.
     * @param string $new_state       New state to set.
     * @param array  $metadata        Optional metadata to store with the state.
     * @return bool True on success, false on failure.
     */
    public function set_state($order_id, $faire_order_id, $new_state, $metadata = []) {
        global $wpdb;

        // Get current state
        $current_state = $this->get_state($order_id, $faire_order_id);

        // Validate state transition
        if ($current_state && !$this->state_machine->can_transition($current_state, $new_state)) {
            $this->error_logger->log(
                new ErrorInfo(
                    "Invalid state transition from {$current_state} to {$new_state}",
                    ErrorSeverity::HIGH,
                    ErrorCategories::VALIDATION,
                    [
                        'order_id' => $order_id,
                        'faire_order_id' => $faire_order_id,
                        'current_state' => $current_state,
                        'attempted_state' => $new_state
                    ]
                )
            );
            return false;
        }

        // Insert new state
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $result = $wpdb->insert(
            $table_name,
            [
                'order_id' => $order_id,
                'faire_order_id' => $faire_order_id,
                'state' => $new_state,
                'metadata' => $metadata ? wp_json_encode($metadata) : null,
                'created_at' => current_time('mysql', true)
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            $this->error_logger->log(
                new ErrorInfo(
                    "Failed to set order state",
                    ErrorSeverity::HIGH,
                    ErrorCategories::DATABASE,
                    [
                        'order_id' => $order_id,
                        'faire_order_id' => $faire_order_id,
                        'state' => $new_state,
                        'db_error' => $wpdb->last_error
                    ]
                )
            );
            return false;
        }

        // Archive the previous state in history
        if ($current_state) {
            $history_table = $wpdb->prefix . self::TABLE_NAME . '_history';
            $wpdb->insert(
                $history_table,
                [
                    'order_id' => $order_id,
                    'faire_order_id' => $faire_order_id,
                    'state' => $current_state,
                    'metadata' => $metadata ? wp_json_encode($metadata) : null,
                    'created_at' => current_time('mysql', true)
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }

        return true;
    }

    /**
     * Get state history for an order
     *
     * @param string $order_id        WooCommerce order ID.
     * @param string $faire_order_id  Faire order ID.
     * @return array Array of state history records.
     */
    public function get_state_history($order_id, $faire_order_id) {
        global $wpdb;
        $history_table = $wpdb->prefix . self::TABLE_NAME . '_history';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$history_table} WHERE order_id = %s AND faire_order_id = %s ORDER BY created_at DESC",
                $order_id,
                $faire_order_id
            )
        );

        // Process metadata
        foreach ($results as &$result) {
            if ($result->metadata) {
                $result->metadata = json_decode($result->metadata, true);
            }
        }

        return $results;
    }

    /**
     * Bulk set state for multiple orders
     *
     * @param array  $order_ids       Array of WooCommerce order IDs.
     * @param array  $faire_order_ids Array of Faire order IDs.
     * @param string $new_state       New state to set.
     * @param array  $metadata        Optional metadata to store with the state.
     * @return array Array of results with success/failure for each order.
     */
    public function bulk_set_state($order_ids, $faire_order_ids, $new_state, $metadata = []) {
        if (count($order_ids) !== count($faire_order_ids)) {
            throw new \InvalidArgumentException('Order IDs and Faire order IDs arrays must have the same length');
        }

        $results = [];
        for ($i = 0; $i < count($order_ids); $i++) {
            $results[$order_ids[$i]] = $this->set_state($order_ids[$i], $faire_order_ids[$i], $new_state, $metadata);
        }

        return $results;
    }

    /**
     * Get orders in a specific state
     *
     * @param string $state State to query for.
     * @return array Array of orders in the specified state.
     */
    public function get_orders_in_state($state) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT order_id, faire_order_id, metadata, created_at 
                FROM {$table_name} 
                WHERE state = %s 
                ORDER BY created_at DESC",
                $state
            )
        );
    }

    /**
     * Clean up old state history records
     *
     * @param int $days_to_keep Number of days of history to retain.
     * @return int Number of records deleted.
     */
    public function cleanup_history($days_to_keep = 30) {
        global $wpdb;
        $history_table = $wpdb->prefix . self::TABLE_NAME . '_history';

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$history_table} WHERE created_at < %s",
                $cutoff_date
            )
        );
    }

    /**
     * Attempt to recover a failed order sync
     *
     * @param int    $wc_order_id    WooCommerce order ID.
     * @param string $failure_reason Optional failure reason.
     * @return bool True if recovery was successful, false otherwise.
     */
    public function attempt_recovery($wc_order_id, $failure_reason = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Get current state and metadata
        $current_state = $this->get_state($wc_order_id);
        $metadata = $this->get_state_metadata($wc_order_id);

        // Only attempt recovery for failed states
        if ($current_state !== OrderSyncStateMachine::STATE_FAILED) {
            $this->error_logger->log(
                new ErrorInfo(
                    'Recovery attempted for non-failed order',
                    ErrorSeverity::MEDIUM,
                    ErrorCategories::SYNC,
                    [
                        'wc_order_id' => $wc_order_id,
                        'current_state' => $current_state
                    ]
                )
            );
            return false;
        }

        // Check retry attempts
        $retry_count = isset($metadata['retry_count']) ? (int) $metadata['retry_count'] : 0;
        if ($retry_count >= self::MAX_RETRY_ATTEMPTS) {
            $this->error_logger->log(
                new ErrorInfo(
                    'Maximum retry attempts reached',
                    ErrorSeverity::HIGH,
                    ErrorCategories::SYNC,
                    [
                        'wc_order_id' => $wc_order_id,
                        'retry_count' => $retry_count,
                        'failure_reason' => $failure_reason
                    ]
                )
            );
            return false;
        }

        // Check retry delay
        $last_attempt = isset($metadata['last_retry']) ? strtotime($metadata['last_retry']) : 0;
        if (time() - $last_attempt < self::RETRY_DELAY) {
            return false;
        }

        // Update metadata for retry
        $retry_metadata = array_merge($metadata, [
            'retry_count' => $retry_count + 1,
            'last_retry' => current_time('mysql'),
            'previous_failure' => $failure_reason
        ]);

        // Transition to recovery state
        $recovery_success = $this->set_state(
            $wc_order_id,
            OrderSyncStateMachine::STATE_RECOVERED,
            $retry_metadata
        );

        if ($recovery_success) {
            $this->error_logger->log(
                new ErrorInfo(
                    'Order recovery initiated',
                    ErrorSeverity::LOW,
                    ErrorCategories::SYNC,
                    [
                        'wc_order_id' => $wc_order_id,
                        'retry_attempt' => $retry_count + 1
                    ]
                )
            );
        }

        return $recovery_success;
    }

    /**
     * Get orders that need recovery attempts
     *
     * @param int $limit Optional limit on number of orders to return.
     * @return array Array of order IDs that need recovery.
     */
    public function get_recoverable_orders($limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $query = $wpdb->prepare(
            "SELECT wc_order_id, metadata FROM {$table_name} 
            WHERE state = %s 
            AND (
                metadata NOT LIKE %s 
                OR CAST(JSON_EXTRACT(metadata, '$.retry_count') AS UNSIGNED) < %d
            )
            AND (
                metadata NOT LIKE %s 
                OR TIMESTAMPDIFF(SECOND, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.last_retry')), NOW()) >= %d
            )
            LIMIT %d",
            OrderSyncStateMachine::STATE_FAILED,
            '%retry_count%',
            self::MAX_RETRY_ATTEMPTS,
            '%last_retry%',
            self::RETRY_DELAY,
            $limit
        );

        $results = $wpdb->get_results($query);
        return array_map(function($row) {
            return (int) $row->wc_order_id;
        }, $results);
    }

    /**
     * Force recovery of an order regardless of retry limits
     *
     * @param int    $wc_order_id WooCommerce order ID.
     * @param string $reason      Reason for forced recovery.
     * @return bool True if recovery was initiated, false otherwise.
     */
    public function force_recovery($wc_order_id, $reason = '') {
        $current_state = $this->get_state($wc_order_id);
        
        // Only allow force recovery for failed or conflict states
        if (!in_array($current_state, [
            OrderSyncStateMachine::STATE_FAILED,
            OrderSyncStateMachine::STATE_CONFLICT
        ])) {
            return false;
        }

        $metadata = [
            'force_recovered' => true,
            'force_recovery_reason' => $reason,
            'force_recovery_time' => current_time('mysql'),
            'previous_state' => $current_state
        ];

        return $this->set_state(
            $wc_order_id,
            OrderSyncStateMachine::STATE_RECOVERED,
            $metadata
        );
    }

    /**
     * Reset retry count for an order
     *
     * @param int $wc_order_id WooCommerce order ID.
     * @return bool True if reset was successful, false otherwise.
     */
    public function reset_retry_count($wc_order_id) {
        $metadata = $this->get_state_metadata($wc_order_id);
        unset($metadata['retry_count']);
        unset($metadata['last_retry']);

        return $this->update_state_metadata($wc_order_id, $metadata);
    }
} 