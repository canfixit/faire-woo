<?php
/**
 * Conflict Resolver Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

use FaireWoo\Interfaces\ConflictResolver as ConflictResolverInterface;

defined('ABSPATH') || exit;

/**
 * Conflict Resolver Class
 */
class ConflictResolver implements ConflictResolverInterface {
    /**
     * Field resolution strategies.
     *
     * @var array
     */
    private $strategies;

    /**
     * Manual resolution queue.
     *
     * @var array
     */
    private $manual_queue = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->strategies = ConflictResolutionConfig::get_field_strategies();
    }

    /**
     * Resolve conflicts between WooCommerce and Faire order data.
     *
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @param array     $conflicts   Array of conflicts to resolve.
     * @return array Resolved data and any conflicts requiring manual intervention.
     */
    public function resolve_conflicts($wc_order, $faire_order, $conflicts) {
        $resolved_data = array();
        $manual_conflicts = array();
        $resolution_log = array();

        foreach ($conflicts as $field => $values) {
            $strategy = $this->get_field_strategy($field, $values['wc'], $values['faire'], $wc_order, $faire_order);
            $resolution = $this->apply_strategy($strategy, $field, $values, $wc_order, $faire_order);

            if ($resolution['requires_manual']) {
                $manual_conflicts[$field] = array(
                    'wc_value' => $values['wc'],
                    'faire_value' => $values['faire'],
                    'reason' => $resolution['reason'],
                );
                $this->queue_manual_resolution($wc_order->get_id(), $field, $values, $resolution['reason']);
            } else {
                $resolved_data[$field] = $resolution['value'];
            }

            $resolution_log[] = array(
                'field' => $field,
                'strategy' => $strategy['strategy'],
                'reason' => $strategy['reason'],
                'requires_manual' => $resolution['requires_manual'],
                'resolution_details' => $resolution['reason'],
            );
        }

        // Log the resolution process
        $this->log_resolutions($wc_order->get_id(), $resolution_log);

        return array(
            'resolved' => $resolved_data,
            'manual' => $manual_conflicts,
        );
    }

    /**
     * Get the appropriate strategy for a field.
     *
     * @param string    $field       Field name.
     * @param mixed     $wc_value    WooCommerce value.
     * @param mixed     $faire_value Faire value.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return array
     */
    private function get_field_strategy($field, $wc_value, $faire_value, $wc_order, $faire_order) {
        if (!isset($this->strategies[$field])) {
            return array(
                'strategy' => 'faire_wins',
                'reason' => 'Default to Faire as source of truth',
            );
        }

        $field_config = $this->strategies[$field];

        // Check conditions if they exist
        if (isset($field_config['conditions'])) {
            foreach ($field_config['conditions'] as $condition) {
                if (is_callable($condition['check']) && 
                    call_user_func($condition['check'], $wc_value, $faire_value, $wc_order, $faire_order)) {
                    return array(
                        'strategy' => $condition['strategy'],
                        'reason' => $condition['reason'],
                    );
                }
            }
        }

        return array(
            'strategy' => $field_config['strategy'],
            'reason' => $field_config['reason'],
        );
    }

    /**
     * Apply a resolution strategy.
     *
     * @param array     $strategy    Strategy configuration.
     * @param string    $field       Field name.
     * @param array     $values      Conflicting values.
     * @param \WC_Order $wc_order    WooCommerce order object.
     * @param array     $faire_order Faire order data.
     * @return array
     */
    private function apply_strategy($strategy, $field, $values, $wc_order, $faire_order) {
        $result = array(
            'requires_manual' => false,
            'value' => null,
            'reason' => $strategy['reason'],
        );

        switch ($strategy['strategy']) {
            case 'faire_wins':
                $result['value'] = $values['faire'];
                break;

            case 'wc_wins':
                $result['value'] = $values['wc'];
                break;

            case 'newer_wins':
                $wc_modified = $wc_order->get_date_modified();
                $faire_modified = isset($faire_order['updated_at']) 
                    ? strtotime($faire_order['updated_at']) 
                    : null;

                if (!$faire_modified || ($wc_modified && $wc_modified->getTimestamp() > $faire_modified)) {
                    $result['value'] = $values['wc'];
                } else {
                    $result['value'] = $values['faire'];
                }
                break;

            case 'keep_complete':
                $wc_complete = !$this->has_empty_values($values['wc']);
                $faire_complete = !$this->has_empty_values($values['faire']);

                if ($wc_complete && !$faire_complete) {
                    $result['value'] = $values['wc'];
                } elseif (!$wc_complete && $faire_complete) {
                    $result['value'] = $values['faire'];
                } else {
                    // If both complete or both incomplete, use newer
                    $result = $this->apply_strategy(
                        array('strategy' => 'newer_wins', 'reason' => 'Both values complete/incomplete'),
                        $field,
                        $values,
                        $wc_order,
                        $faire_order
                    );
                }
                break;

            case 'manual':
                $result['requires_manual'] = true;
                $result['value'] = $values['wc']; // Keep WC value until manual resolution
                break;

            default:
                $result['value'] = $values['faire']; // Default to Faire
                $result['reason'] = 'Unknown strategy, defaulting to Faire';
                break;
        }

        return $result;
    }

    /**
     * Queue a conflict for manual resolution.
     *
     * @param int    $order_id Order ID.
     * @param string $field    Field name.
     * @param array  $values   Conflicting values.
     * @param string $reason   Reason for manual resolution.
     */
    private function queue_manual_resolution($order_id, $field, $values, $reason) {
        $this->manual_queue[$order_id][] = array(
            'field' => $field,
            'wc_value' => $values['wc'],
            'faire_value' => $values['faire'],
            'reason' => $reason,
            'timestamp' => current_time('mysql'),
        );

        update_post_meta($order_id, '_faire_manual_resolution_queue', $this->manual_queue[$order_id]);

        // Add order note
        $order = wc_get_order($order_id);
        if ($order) {
            $note = sprintf(
                'Faire Sync: Manual review needed for field "%s". Reason: %s',
                $field,
                $reason
            );
            $order->add_order_note($note);
        }
    }

    /**
     * Log resolutions for an order.
     *
     * @param int   $order_id Order ID.
     * @param array $log      Resolution log entries.
     */
    private function log_resolutions($order_id, $log) {
        $existing_log = get_post_meta($order_id, '_faire_resolution_log', true);
        if (!is_array($existing_log)) {
            $existing_log = array();
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'resolutions' => $log,
        );

        array_push($existing_log, $log_entry);
        update_post_meta($order_id, '_faire_resolution_log', $existing_log);
    }

    /**
     * Check if an array or value contains empty values.
     *
     * @param mixed $value Value to check.
     * @return bool
     */
    private function has_empty_values($value) {
        if (is_array($value)) {
            foreach ($value as $v) {
                if ($this->has_empty_values($v)) {
                    return true;
                }
            }
            return false;
        }
        return empty($value) && $value !== '0' && $value !== 0;
    }
} 