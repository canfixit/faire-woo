<?php
/**
 * Order Sync Manager Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

use FaireWoo\Abstracts\FaireWooSync;

defined('ABSPATH') || exit;

/**
 * OrderSyncManager
 *
 * Manages the synchronization of orders between WooCommerce and Faire.
 */
class OrderSyncManager extends FaireWooSync {
    /**
     * Order comparator instance
     *
     * @var OrderComparator
     */
    private $order_comparator;

    /**
     * Conflict resolver instance
     *
     * @var ConflictResolver
     */
    private $conflict_resolver;

    /**
     * Error logger instance
     *
     * @var ErrorLogger
     */
    private $error_logger;

    /**
     * State manager instance
     *
     * @var OrderSyncStateManager
     */
    private $state_manager;

    /**
     * Constructor
     *
     * @param OrderComparator      $order_comparator  Order comparator instance.
     * @param ConflictResolver     $conflict_resolver Conflict resolver instance.
     * @param ErrorLogger         $error_logger      Error logger instance.
     * @param OrderSyncStateManager $state_manager    State manager instance.
     */
    public function __construct(
        OrderComparator $order_comparator,
        ConflictResolver $conflict_resolver,
        ErrorLogger $error_logger,
        OrderSyncStateManager $state_manager
    ) {
        parent::__construct();

        $this->order_comparator = $order_comparator;
        $this->conflict_resolver = $conflict_resolver;
        $this->error_logger = $error_logger;
        $this->state_manager = $state_manager;

        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    protected function init_hooks() {
        // Hook into WooCommerce order status changes
        add_action('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);

        // Hook into order updates
        add_action('woocommerce_update_order', array($this, 'handle_order_update'), 10, 2);

        // Add custom order actions
        add_action('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_faire_sync', array($this, 'sync_order_with_faire'));
    }

    /**
     * Handle order status changes.
     *
     * @param int    $order_id Order ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     * @param object $order Order object.
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        try {
            $this->log_with_context(
                sprintf('Order status changed from %s to %s', $old_status, $new_status),
                array('order_id' => $order_id)
            );

            // Get Faire order ID
            $faire_order_id = $order->get_meta('_faire_order_id');
            if (!$faire_order_id) {
                return;
            }

            // Get Faire order data
            $faire_order = $this->get_faire_order($faire_order_id);
            if (!$faire_order) {
                return;
            }

            // Compare and resolve differences
            $this->sync_orders($order, $faire_order);
        } catch (\Exception $e) {
            $this->handle_sync_error($e, $order_id);
        }
    }

    /**
     * Handle order updates.
     *
     * @param int      $order_id Order ID.
     * @param \WP_Post $post Post object.
     */
    public function handle_order_update($order_id, $post) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }

            // Get Faire order ID
            $faire_order_id = $order->get_meta('_faire_order_id');
            if (!$faire_order_id) {
                return;
            }

            $this->log_with_context(
                'Order updated',
                array('order_id' => $order_id, 'faire_order_id' => $faire_order_id)
            );

            // Get Faire order data
            $faire_order = $this->get_faire_order($faire_order_id);
            if (!$faire_order) {
                return;
            }

            // Compare and resolve differences
            $this->sync_orders($order, $faire_order);
        } catch (\Exception $e) {
            $this->handle_sync_error($e, $order_id);
        }
    }

    /**
     * Add custom order actions.
     *
     * @param array $actions Existing actions.
     * @return array Modified actions.
     */
    public function add_order_actions($actions) {
        global $theorder;

        // Only add the action if the order has a Faire ID
        if ($theorder && $theorder->get_meta('_faire_order_id')) {
            $actions['faire_sync'] = __('Sync with Faire', 'faire-woo');
        }

        return $actions;
    }

    /**
     * Sync order with Faire (triggered by order action).
     *
     * @param \WC_Order $order Order object.
     */
    public function sync_order_with_faire($order) {
        try {
            $faire_order_id = $order->get_meta('_faire_order_id');
            if (!$faire_order_id) {
                throw new \Exception('No Faire order ID found');
            }

            $this->log_with_context(
                'Manual sync initiated',
                array('order_id' => $order->get_id(), 'faire_order_id' => $faire_order_id)
            );

            // Get Faire order data
            $faire_order = $this->get_faire_order($faire_order_id);
            if (!$faire_order) {
                throw new \Exception('Failed to fetch Faire order');
            }

            // Compare and resolve differences
            $this->sync_orders($order, $faire_order);

            $order->add_order_note(__('Manual sync with Faire completed successfully.', 'faire-woo'));
        } catch (\Exception $e) {
            $this->handle_sync_error($e, $order->get_id());
            $order->add_order_note(sprintf(
                __('Manual sync with Faire failed: %s', 'faire-woo'),
                $e->getMessage()
            ));
        }
    }

    /**
     * Sync WooCommerce and Faire orders.
     *
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return bool True if successful, false otherwise.
     */
    public function sync_orders(\WC_Order $wc_order, array $faire_order) {
        try {
            // Compare orders
            $differences = $this->order_comparator->compare_orders($wc_order, $faire_order);
            if (empty($differences)) {
                $this->log_with_context(
                    'No differences found between WC and Faire orders',
                    array('order_id' => $wc_order->get_id(), 'faire_order_id' => $faire_order['id'])
                );
                return true;
            }

            // Log differences found
            $this->log_with_context(
                'Differences found between WC and Faire orders',
                array(
                    'order_id' => $wc_order->get_id(),
                    'faire_order_id' => $faire_order['id'],
                    'differences' => $differences,
                )
            );

            // Resolve conflicts
            $resolutions = $this->conflict_resolver->resolve_conflicts($wc_order, $faire_order, $differences);

            // Update sync metadata
            $this->update_sync_metadata($wc_order, $faire_order);

            return true;
        } catch (\Exception $e) {
            $this->handle_sync_error($e, $wc_order->get_id());
            return false;
        }
    }

    /**
     * Get Faire order data.
     *
     * @param string $faire_order_id Faire order ID.
     * @return array|false Order data or false on failure.
     */
    protected function get_faire_order($faire_order_id) {
        try {
            // TODO: Implement actual API call to Faire
            // This is a placeholder that should be replaced with actual API integration
            return array(
                'id' => $faire_order_id,
                'status' => 'pending',
                'total_amount' => 0,
                // Add other required fields
            );
        } catch (\Exception $e) {
            $this->set_error(sprintf('Error fetching Faire order: %s', $e->getMessage()));
            return false;
        }
    }

    /**
     * Update sync metadata.
     *
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     */
    protected function update_sync_metadata(\WC_Order $wc_order, array $faire_order) {
        $wc_order->update_meta_data('_faire_last_sync', current_time('mysql'));
        $wc_order->update_meta_data('_faire_order_status', $faire_order['status']);
        $wc_order->save();
    }

    /**
     * Handle sync errors.
     *
     * @param \Exception $exception Exception object.
     * @param int        $order_id  Order ID.
     */
    protected function handle_sync_error(\Exception $exception, $order_id) {
        $message = sprintf(
            'Error syncing order #%d: %s',
            $order_id,
            $exception->getMessage()
        );

        $this->set_error($message);

        // Add error note to the order
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note(sprintf(
                __('Faire Sync Error: %s', 'faire-woo'),
                $exception->getMessage()
            ));
        }
    }

    /**
     * Synchronize a single order
     *
     * @param int $wc_order_id WooCommerce order ID.
     * @return bool True if sync was successful, false otherwise.
     */
    public function sync_order($wc_order_id) {
        try {
            // Allow pre-sync filtering
            $should_sync = apply_filters('faire_woo_pre_sync_order', true, $wc_order_id);
            if (!$should_sync) {
                return false;
            }

            // Allow modification of initial state
            $initial_state = apply_filters(
                'faire_woo_initial_sync_state',
                OrderSyncStateMachine::STATE_SYNCING,
                $wc_order_id
            );

            // Set initial state with filtered metadata
            $initial_metadata = apply_filters('faire_woo_state_transition_metadata', 
                ['sync_start_time' => current_time('mysql')],
                $wc_order_id,
                $initial_state
            );
            $this->state_manager->set_state($wc_order_id, $initial_state, $initial_metadata);

            // Get WooCommerce order
            $wc_order = wc_get_order($wc_order_id);
            if (!$wc_order) {
                throw new \Exception('WooCommerce order not found');
            }

            // Get Faire order ID from meta
            $faire_order_id = $wc_order->get_meta('_faire_order_id');
            if (!$faire_order_id) {
                throw new \Exception('Faire order ID not found');
            }

            // Allow modification of comparison result
            $comparison_result = apply_filters(
                'faire_woo_compare_orders',
                $this->order_comparator->compare_orders($wc_order_id, $faire_order_id),
                $wc_order_id,
                $faire_order_id
            );

            if ($comparison_result['has_conflicts']) {
                // Allow conflict handling customization
                $conflict_action = apply_filters(
                    'faire_woo_handle_conflict',
                    'set_conflict_state',
                    $wc_order_id,
                    $comparison_result['conflicts']
                );

                if ($conflict_action === 'set_conflict_state') {
                    $conflict_metadata = apply_filters('faire_woo_state_transition_metadata',
                        [
                            'conflicts' => $comparison_result['conflicts'],
                            'conflict_time' => current_time('mysql')
                        ],
                        $wc_order_id,
                        OrderSyncStateMachine::STATE_CONFLICT
                    );
                    $this->state_manager->set_state(
                        $wc_order_id,
                        OrderSyncStateMachine::STATE_CONFLICT,
                        $conflict_metadata
                    );
                    return false;
                }
            }

            // Allow sync success customization
            $success_state = apply_filters(
                'faire_woo_success_state',
                OrderSyncStateMachine::STATE_SYNCED,
                $wc_order_id
            );

            $success_metadata = apply_filters('faire_woo_state_transition_metadata',
                [
                    'sync_complete_time' => current_time('mysql'),
                    'sync_result' => 'success'
                ],
                $wc_order_id,
                $success_state
            );

            $this->state_manager->set_state($wc_order_id, $success_state, $success_metadata);
            
            do_action('faire_woo_after_sync_success', $wc_order_id);
            
            return true;

        } catch (\Exception $e) {
            // Allow error handling customization
            $error_state = apply_filters(
                'faire_woo_error_state',
                OrderSyncStateMachine::STATE_FAILED,
                $wc_order_id,
                $e
            );

            $error_metadata = apply_filters('faire_woo_state_transition_metadata',
                [
                    'error' => $e->getMessage(),
                    'error_time' => current_time('mysql'),
                    'error_code' => $e->getCode()
                ],
                $wc_order_id,
                $error_state
            );

            $this->state_manager->set_state($wc_order_id, $error_state, $error_metadata);
            
            do_action('faire_woo_sync_error', $wc_order_id, $e);
            
            $this->error_logger->log_error(
                $e->getMessage(),
                'sync',
                'high',
                ['order_id' => $wc_order_id]
            );

            return false;
        }
    }

    /**
     * Get orders that need synchronization
     *
     * @param int $limit Optional limit on number of orders to return.
     * @return array Array of WooCommerce order IDs.
     */
    public function get_orders_needing_sync($limit = 50) {
        global $wpdb;

        // Get orders that have never been synced
        $never_synced = $wpdb->get_col($wpdb->prepare(
            "SELECT posts.ID FROM {$wpdb->posts} posts
            LEFT JOIN {$wpdb->prefix}" . OrderSyncStateManager::TABLE_NAME . " states
            ON posts.ID = states.wc_order_id
            WHERE posts.post_type = 'shop_order'
            AND states.wc_order_id IS NULL
            LIMIT %d",
            $limit
        ));

        // Get orders that need retry
        $need_retry = $this->state_manager->get_recoverable_orders($limit);

        // Get orders in conflict state that have been resolved
        $resolved_conflicts = $wpdb->get_col($wpdb->prepare(
            "SELECT wc_order_id FROM {$wpdb->prefix}" . OrderSyncStateManager::TABLE_NAME . "
            WHERE state = %s
            AND metadata LIKE %s
            LIMIT %d",
            OrderSyncStateMachine::STATE_CONFLICT,
            '%"resolved":true%',
            $limit
        ));

        // Combine and limit results
        $orders = array_merge($never_synced, $need_retry, $resolved_conflicts);
        return array_slice(array_unique($orders), 0, $limit);
    }

    /**
     * Exclude an order from synchronization
     *
     * @param int    $wc_order_id WooCommerce order ID.
     * @param string $reason      Reason for exclusion.
     * @return bool True if order was excluded, false otherwise.
     */
    public function exclude_order($wc_order_id, $reason = '') {
        return $this->state_manager->set_state(
            $wc_order_id,
            OrderSyncStateMachine::STATE_EXCLUDED,
            [
                'reason' => $reason,
                'excluded_at' => current_time('mysql'),
                'excluded_by' => get_current_user_id()
            ]
        );
    }

    /**
     * Get sync statistics
     *
     * @return array Array of sync statistics.
     */
    public function get_sync_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . OrderSyncStateManager::TABLE_NAME;

        $stats = [];
        $states = [
            OrderSyncStateMachine::STATE_PENDING,
            OrderSyncStateMachine::STATE_SYNCING,
            OrderSyncStateMachine::STATE_SYNCED,
            OrderSyncStateMachine::STATE_FAILED,
            OrderSyncStateMachine::STATE_CONFLICT,
            OrderSyncStateMachine::STATE_EXCLUDED,
            OrderSyncStateMachine::STATE_RECOVERED
        ];

        foreach ($states as $state) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE state = %s",
                $state
            ));
            $stats[$state] = (int) $count;
        }

        // Add additional statistics
        $stats['total_orders'] = array_sum($stats);
        $stats['success_rate'] = $stats['total_orders'] > 0
            ? round(($stats[OrderSyncStateMachine::STATE_SYNCED] / $stats['total_orders']) * 100, 2)
            : 0;
        $stats['last_sync'] = $wpdb->get_var(
            "SELECT MAX(updated_at) FROM {$table_name}"
        );

        return $stats;
    }

    /**
     * Clean up old sync history
     *
     * @param int $days Number of days of history to keep.
     * @return int Number of records deleted.
     */
    public function cleanup_sync_history($days = 30) {
        return $this->state_manager->cleanup_history($days);
    }
} 