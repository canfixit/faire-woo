<?php
/**
 * Example Custom Sync Extension
 *
 * @package FaireWoo\Examples
 * @since   1.0.0
 */

namespace FaireWoo\Examples;

defined('ABSPATH') || exit;

/**
 * CustomSyncExtension
 *
 * Example extension demonstrating FaireWoo sync system extensibility.
 */
class CustomSyncExtension {
    /**
     * Initialize the extension
     */
    public function init() {
        // Pre-sync filter
        add_filter('faire_woo_pre_sync_order', [$this, 'maybe_skip_sync'], 10, 2);

        // State management filters
        add_filter('faire_woo_state_transition_metadata', [$this, 'add_custom_metadata'], 10, 3);
        add_filter('faire_woo_initial_sync_state', [$this, 'modify_initial_state'], 10, 2);

        // Sync process filters
        add_filter('faire_woo_handle_conflict', [$this, 'custom_conflict_handling'], 10, 3);
        add_filter('faire_woo_error_state', [$this, 'custom_error_state'], 10, 3);

        // Actions
        add_action('faire_woo_after_sync_success', [$this, 'notify_success'], 10, 1);
        add_action('faire_woo_sync_error', [$this, 'handle_error'], 10, 2);
    }

    /**
     * Skip sync for orders with specific conditions
     *
     * @param bool $should_sync Whether to sync the order.
     * @param int  $wc_order_id WooCommerce order ID.
     * @return bool
     */
    public function maybe_skip_sync($should_sync, $wc_order_id) {
        // Skip sync for orders with specific meta
        if (get_post_meta($wc_order_id, '_skip_faire_sync', true)) {
            return false;
        }

        // Skip sync for orders with specific status
        $order = wc_get_order($wc_order_id);
        if ($order && $order->get_status() === 'cancelled') {
            return false;
        }

        return $should_sync;
    }

    /**
     * Add custom metadata during state transitions
     *
     * @param array  $metadata    State metadata.
     * @param int    $wc_order_id WooCommerce order ID.
     * @param string $state       New state.
     * @return array
     */
    public function add_custom_metadata($metadata, $wc_order_id, $state) {
        // Add user info
        $metadata['user_id'] = get_current_user_id();
        $metadata['user_role'] = wp_get_current_user()->roles[0] ?? 'unknown';

        // Add environment info
        $metadata['environment'] = wp_get_environment_type();
        $metadata['sync_source'] = defined('DOING_CRON') ? 'cron' : 'manual';

        // Add order info
        $order = wc_get_order($wc_order_id);
        if ($order) {
            $metadata['order_status'] = $order->get_status();
            $metadata['order_total'] = $order->get_total();
        }

        return $metadata;
    }

    /**
     * Modify initial sync state based on conditions
     *
     * @param string $state       Default state.
     * @param int    $wc_order_id WooCommerce order ID.
     * @return string
     */
    public function modify_initial_state($state, $wc_order_id) {
        // Check if order needs special handling
        $order = wc_get_order($wc_order_id);
        if ($order && $order->get_meta('_needs_manual_review')) {
            return 'manual_review';
        }

        return $state;
    }

    /**
     * Custom conflict handling logic
     *
     * @param string $action      Default action.
     * @param int    $wc_order_id WooCommerce order ID.
     * @param array  $conflicts   Detected conflicts.
     * @return string
     */
    public function custom_conflict_handling($action, $wc_order_id, $conflicts) {
        // Auto-resolve specific types of conflicts
        if ($this->can_auto_resolve($conflicts)) {
            return 'auto_resolve';
        }

        // Escalate critical conflicts
        if ($this->is_critical_conflict($conflicts)) {
            $this->notify_admin($wc_order_id, $conflicts);
            return 'escalate';
        }

        return $action;
    }

    /**
     * Customize error state based on error type
     *
     * @param string    $state       Default error state.
     * @param int       $wc_order_id WooCommerce order ID.
     * @param Exception $exception   The thrown exception.
     * @return string
     */
    public function custom_error_state($state, $wc_order_id, $exception) {
        // Use specific states for different error types
        if (strpos($exception->getMessage(), 'API') !== false) {
            return 'api_error';
        }

        if (strpos($exception->getMessage(), 'timeout') !== false) {
            return 'timeout';
        }

        return $state;
    }

    /**
     * Handle successful sync
     *
     * @param int $wc_order_id WooCommerce order ID.
     */
    public function notify_success($wc_order_id) {
        // Send Slack notification
        $this->send_slack_notification([
            'channel' => '#faire-sync-success',
            'message' => sprintf('Order #%d successfully synced', $wc_order_id),
            'color' => 'good'
        ]);

        // Log to custom audit log
        $this->log_audit_event($wc_order_id, 'sync_success');
    }

    /**
     * Handle sync errors
     *
     * @param int       $wc_order_id WooCommerce order ID.
     * @param Exception $exception   The thrown exception.
     */
    public function handle_error($wc_order_id, $exception) {
        // Send Slack notification for errors
        $this->send_slack_notification([
            'channel' => '#faire-sync-errors',
            'message' => sprintf(
                'Sync failed for order #%d: %s',
                $wc_order_id,
                $exception->getMessage()
            ),
            'color' => 'danger'
        ]);

        // Log to custom audit log
        $this->log_audit_event($wc_order_id, 'sync_error', [
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode()
        ]);

        // Maybe trigger auto-retry
        if ($this->should_auto_retry($exception)) {
            wp_schedule_single_event(
                time() + 300,
                'faire_woo_retry_sync',
                [$wc_order_id]
            );
        }
    }

    /**
     * Check if conflicts can be auto-resolved
     *
     * @param array $conflicts Detected conflicts.
     * @return bool
     */
    private function can_auto_resolve($conflicts) {
        // Example: auto-resolve shipping address conflicts
        foreach ($conflicts as $conflict) {
            if ($conflict['type'] !== 'shipping_address') {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if conflict is critical
     *
     * @param array $conflicts Detected conflicts.
     * @return bool
     */
    private function is_critical_conflict($conflicts) {
        // Example: payment-related conflicts are critical
        foreach ($conflicts as $conflict) {
            if (strpos($conflict['type'], 'payment') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Send Slack notification
     *
     * @param array $data Notification data.
     */
    private function send_slack_notification($data) {
        // Implementation would go here
        // This is just a placeholder
    }

    /**
     * Log audit event
     *
     * @param int    $wc_order_id WooCommerce order ID.
     * @param string $event_type  Event type.
     * @param array  $extra_data  Optional extra data.
     */
    private function log_audit_event($wc_order_id, $event_type, $extra_data = []) {
        // Implementation would go here
        // This is just a placeholder
    }

    /**
     * Check if should auto-retry
     *
     * @param Exception $exception The thrown exception.
     * @return bool
     */
    private function should_auto_retry($exception) {
        $retry_codes = [
            'timeout',
            'rate_limit',
            'temporary_error'
        ];

        foreach ($retry_codes as $code) {
            if (strpos($exception->getMessage(), $code) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Notify admin of critical conflicts
     *
     * @param int   $wc_order_id WooCommerce order ID.
     * @param array $conflicts   Detected conflicts.
     */
    private function notify_admin($wc_order_id, $conflicts) {
        $admin_email = get_option('admin_email');
        $subject = sprintf('Critical Faire Order Conflict - Order #%d', $wc_order_id);
        $message = sprintf(
            "Critical conflicts detected for order #%d:\n\n%s",
            $wc_order_id,
            print_r($conflicts, true)
        );

        wp_mail($admin_email, $subject, $message);
    }
} 