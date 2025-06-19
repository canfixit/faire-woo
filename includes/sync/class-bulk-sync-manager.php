<?php
/**
 * Bulk Synchronization Manager Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

use FaireWoo\Sync\OrderComparator;
use FaireWoo\Sync\ConflictResolver;
use FaireWoo\Sync\ErrorLogger;
use FaireWoo\Sync\OrderSyncStateMachine;
use FaireWoo\Sync\OrderSyncStateManager;

defined('ABSPATH') || exit;

/**
 * Bulk Synchronization Manager Class
 */
class BulkSyncManager {
    /**
     * Default batch size for processing orders.
     */
    const DEFAULT_BATCH_SIZE = 50;

    /**
     * Order sync manager instance.
     *
     * @var OrderSyncManager
     */
    private $order_sync;

    /**
     * Current sync job data.
     *
     * @var array
     */
    private $current_job;

    /**
     * Constructor.
     */
    public function __construct() {
        // Initialize dependencies
        $order_comparator = new OrderComparator();
        $conflict_resolver = new ConflictResolver();
        $error_logger = new ErrorLogger();
        $state_machine = new OrderSyncStateMachine();
        $state_manager = new OrderSyncStateManager($error_logger, $state_machine);

        // Initialize OrderSyncManager with dependencies
        $this->order_sync = new OrderSyncManager(
            $order_comparator,
            $conflict_resolver,
            $error_logger,
            $state_manager
        );

        add_action('faire_woo_process_sync_batch', array($this, 'process_batch'));
    }

    /**
     * Start a bulk synchronization job.
     *
     * @param array $args {
     *     Optional. Array of arguments for bulk sync.
     *
     *     @type string $start_date      Start date for order sync (Y-m-d format).
     *     @type string $end_date        End date for order sync (Y-m-d format).
     *     @type int    $batch_size      Number of orders to process per batch.
     *     @type bool   $include_pending Include pending orders.
     * }
     * @return array|WP_Error Job data on success, WP_Error on failure.
     */
    public function start_sync($args = array()) {
        try {
            $defaults = array(
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d'),
                'batch_size' => self::DEFAULT_BATCH_SIZE,
                'include_pending' => true,
            );
            $args = wp_parse_args($args, $defaults);

            // Validate dates
            if (!$this->validate_dates($args['start_date'], $args['end_date'])) {
                return new \WP_Error('invalid_dates', 'Invalid date range provided');
            }

            // Get total orders to process
            $orders = $this->get_faire_orders($args['start_date'], $args['end_date']);
            if (is_wp_error($orders)) {
                return $orders;
            }

            $total_orders = count($orders);
            if ($total_orders === 0) {
                return new \WP_Error('no_orders', 'No orders found in the specified date range');
            }

            // Create sync job
            $job_id = uniqid('faire_sync_');
            $job = array(
                'id' => $job_id,
                'status' => 'processing',
                'args' => $args,
                'total_orders' => $total_orders,
                'processed_orders' => 0,
                'failed_orders' => array(),
                'start_time' => current_time('mysql'),
                'last_processed_id' => null,
                'orders' => $orders,
            );

            update_option("faire_woo_sync_job_{$job_id}", $job);

            // Schedule first batch
            wp_schedule_single_event(
                time(),
                'faire_woo_process_sync_batch',
                array($job_id)
            );

            return $job;
        } catch (\Exception $e) {
            return new \WP_Error('sync_error', $e->getMessage());
        }
    }

    /**
     * Process a batch of orders.
     *
     * @param string $job_id Job ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function process_batch($job_id) {
        try {
            $job = get_option("faire_woo_sync_job_{$job_id}");
            if (!$job || $job['status'] !== 'processing') {
                return new \WP_Error('invalid_job', 'Invalid or completed sync job');
            }

            $this->current_job = $job;
            $batch_size = $job['args']['batch_size'];
            $remaining_orders = array_slice(
                $job['orders'],
                $job['processed_orders'],
                $batch_size
            );

            if (empty($remaining_orders)) {
                $this->complete_job($job_id);
                return true;
            }

            // Process batch
            foreach ($remaining_orders as $faire_order) {
                $result = $this->process_single_order($faire_order);
                $job['processed_orders']++;
                
                if (is_wp_error($result)) {
                    $job['failed_orders'][] = array(
                        'faire_id' => $faire_order['id'],
                        'error' => $result->get_error_message(),
                    );
                }

                $job['last_processed_id'] = $faire_order['id'];
            }

            // Update progress
            $job['progress'] = ($job['processed_orders'] / $job['total_orders']) * 100;
            update_option("faire_woo_sync_job_{$job_id}", $job);

            // Schedule next batch if needed
            if ($job['processed_orders'] < $job['total_orders']) {
                wp_schedule_single_event(
                    time() + 1, // 1 second delay between batches
                    'faire_woo_process_sync_batch',
                    array($job_id)
                );
            } else {
                $this->complete_job($job_id);
            }

            return true;
        } catch (\Exception $e) {
            return new \WP_Error('batch_error', $e->getMessage());
        }
    }

    /**
     * Get sync job status.
     *
     * @param string $job_id Job ID.
     * @return array|WP_Error Job status on success, WP_Error on failure.
     */
    public function get_job_status($job_id) {
        $job = get_option("faire_woo_sync_job_{$job_id}");
        if (!$job) {
            return new \WP_Error('invalid_job', 'Sync job not found');
        }

        return array(
            'status' => $job['status'],
            'progress' => isset($job['progress']) ? round($job['progress'], 2) : 0,
            'processed_orders' => $job['processed_orders'],
            'total_orders' => $job['total_orders'],
            'failed_orders' => $job['failed_orders'],
            'start_time' => $job['start_time'],
            'end_time' => isset($job['end_time']) ? $job['end_time'] : null,
        );
    }

    /**
     * Cancel a sync job.
     *
     * @param string $job_id Job ID.
     * @return bool True on success, false on failure.
     */
    public function cancel_job($job_id) {
        $job = get_option("faire_woo_sync_job_{$job_id}");
        if (!$job || $job['status'] === 'completed') {
            return false;
        }

        $job['status'] = 'cancelled';
        $job['end_time'] = current_time('mysql');
        update_option("faire_woo_sync_job_{$job_id}", $job);

        return true;
    }

    /**
     * Complete a sync job.
     *
     * @param string $job_id Job ID.
     */
    private function complete_job($job_id) {
        $job = get_option("faire_woo_sync_job_{$job_id}");
        if (!$job) {
            return;
        }

        $job['status'] = 'completed';
        $job['end_time'] = current_time('mysql');
        $job['progress'] = 100;
        update_option("faire_woo_sync_job_{$job_id}", $job);

        // Clean up old jobs
        $this->cleanup_old_jobs();

        do_action('faire_woo_sync_job_completed', $job);
    }

    /**
     * Process a single order.
     *
     * @param array $faire_order Faire order data.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    private function process_single_order($faire_order) {
        try {
            return $this->order_sync->sync_order($faire_order);
        } catch (\Exception $e) {
            return new \WP_Error('order_sync_error', $e->getMessage());
        }
    }

    /**
     * Get orders from Faire API.
     *
     * @param string $start_date Start date (Y-m-d format).
     * @param string $end_date   End date (Y-m-d format).
     * @return array|WP_Error Array of orders on success, WP_Error on failure.
     */
    private function get_faire_orders($start_date, $end_date) {
        try {
            // This would use the Faire API service to get orders
            // For now, we'll return a placeholder
            return array();
        } catch (\Exception $e) {
            return new \WP_Error('faire_api_error', $e->getMessage());
        }
    }

    /**
     * Validate date range.
     *
     * @param string $start_date Start date.
     * @param string $end_date   End date.
     * @return bool
     */
    private function validate_dates($start_date, $end_date) {
        $start = strtotime($start_date);
        $end = strtotime($end_date);

        return $start && $end && $start <= $end;
    }

    /**
     * Clean up old completed jobs.
     *
     * Keep only the last 10 completed jobs.
     */
    private function cleanup_old_jobs() {
        global $wpdb;

        $jobs = $wpdb->get_results(
            "SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'faire_woo_sync_job_%'",
            ARRAY_A
        );

        if (!$jobs) {
            return;
        }

        $completed_jobs = array();
        foreach ($jobs as $job) {
            $job_data = maybe_unserialize($job['option_value']);
            if ($job_data['status'] === 'completed') {
                $completed_jobs[] = array(
                    'option_name' => $job['option_name'],
                    'end_time' => strtotime($job_data['end_time']),
                );
            }
        }

        if (count($completed_jobs) <= 10) {
            return;
        }

        // Sort by end time, newest first
        usort($completed_jobs, function($a, $b) {
            return $b['end_time'] - $a['end_time'];
        });

        // Delete all but the last 10
        $to_delete = array_slice($completed_jobs, 10);
        foreach ($to_delete as $job) {
            delete_option($job['option_name']);
        }
    }
} 