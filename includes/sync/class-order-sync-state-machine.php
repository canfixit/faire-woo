<?php
/**
 * Order Sync State Machine Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

defined('ABSPATH') || exit;

/**
 * OrderSyncStateMachine
 *
 * Manages order synchronization states and transitions.
 */
class OrderSyncStateMachine {
    // State constants
    const STATE_PENDING    = 'pending';
    const STATE_SYNCING    = 'syncing';
    const STATE_SYNCED     = 'synced';
    const STATE_FAILED     = 'failed';
    const STATE_CONFLICT   = 'conflict';
    const STATE_EXCLUDED   = 'excluded';
    const STATE_RECOVERED  = 'recovered';
    const STATE_CANCELLED  = 'cancelled';

    /**
     * Valid state transitions map
     * @var array
     */
    private static $transitions = [
        self::STATE_PENDING => [self::STATE_SYNCING, self::STATE_EXCLUDED, self::STATE_CANCELLED],
        self::STATE_SYNCING => [self::STATE_SYNCED, self::STATE_FAILED, self::STATE_CONFLICT, self::STATE_CANCELLED],
        self::STATE_FAILED => [self::STATE_PENDING, self::STATE_RECOVERED, self::STATE_EXCLUDED, self::STATE_CANCELLED],
        self::STATE_CONFLICT => [self::STATE_PENDING, self::STATE_EXCLUDED, self::STATE_CANCELLED],
        self::STATE_SYNCED => [self::STATE_RECOVERED, self::STATE_EXCLUDED, self::STATE_CANCELLED],
        self::STATE_EXCLUDED => [],
        self::STATE_RECOVERED => [self::STATE_PENDING, self::STATE_EXCLUDED, self::STATE_CANCELLED],
        self::STATE_CANCELLED => [],
    ];

    /**
     * Get all valid states
     *
     * @return array
     */
    public static function get_states() {
        return [
            self::STATE_PENDING,
            self::STATE_SYNCING,
            self::STATE_SYNCED,
            self::STATE_FAILED,
            self::STATE_CONFLICT,
            self::STATE_EXCLUDED,
            self::STATE_RECOVERED,
            self::STATE_CANCELLED,
        ];
    }

    /**
     * Get valid next states for a given state
     *
     * @param string $current_state
     * @return array
     */
    public static function get_valid_transitions($current_state) {
        return self::$transitions[$current_state] ?? [];
    }

    /**
     * Check if a transition is valid
     *
     * @param string $from_state
     * @param string $to_state
     * @return bool
     */
    public static function is_valid_transition($from_state, $to_state) {
        return in_array($to_state, self::get_valid_transitions($from_state), true);
    }

    /**
     * Validate a state value
     *
     * @param string $state
     * @return bool
     */
    public static function is_valid_state($state) {
        return in_array($state, self::get_states(), true);
    }

    /**
     * Add a new state and its transitions (for extensibility)
     *
     * @param string $state
     * @param array $transitions
     */
    public static function add_state($state, array $transitions = []) {
        if (!self::is_valid_state($state)) {
            self::$transitions[$state] = $transitions;
        }
    }

    /**
     * Get a human-readable label for a state
     *
     * @param string $state
     * @return string
     */
    public static function get_label($state) {
        $labels = [
            self::STATE_PENDING => __('Pending', 'faire-woo'),
            self::STATE_SYNCING => __('Syncing', 'faire-woo'),
            self::STATE_SYNCED => __('Synced', 'faire-woo'),
            self::STATE_FAILED => __('Failed', 'faire-woo'),
            self::STATE_CONFLICT => __('Conflict', 'faire-woo'),
            self::STATE_EXCLUDED => __('Excluded', 'faire-woo'),
            self::STATE_RECOVERED => __('Recovered', 'faire-woo'),
            self::STATE_CANCELLED => __('Cancelled', 'faire-woo'),
        ];
        return $labels[$state] ?? $state;
    }
} 