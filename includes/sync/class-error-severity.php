<?php
/**
 * Error Severity Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

defined('ABSPATH') || exit;

/**
 * Error Severity Class
 * 
 * Defines and manages error severity levels for the FaireWoo plugin.
 */
class ErrorSeverity {
    /**
     * Critical severity level.
     * System-wide issues that require immediate attention.
     */
    const CRITICAL = 'critical';

    /**
     * High severity level.
     * Issues that significantly impact business operations.
     */
    const HIGH = 'high';

    /**
     * Medium severity level.
     * Issues that affect functionality but have workarounds.
     */
    const MEDIUM = 'medium';

    /**
     * Low severity level.
     * Minor issues that don't significantly impact operations.
     */
    const LOW = 'low';

    /**
     * Debug severity level.
     * Informational messages for debugging purposes.
     */
    const DEBUG = 'debug';

    /**
     * Get severity level descriptions.
     *
     * @return array
     */
    public static function get_descriptions() {
        return array(
            self::CRITICAL => __('System-wide issues requiring immediate attention', 'faire-woo'),
            self::HIGH => __('Issues significantly impacting business operations', 'faire-woo'),
            self::MEDIUM => __('Issues affecting functionality with available workarounds', 'faire-woo'),
            self::LOW => __('Minor issues with minimal operational impact', 'faire-woo'),
            self::DEBUG => __('Informational messages for debugging', 'faire-woo'),
        );
    }

    /**
     * Get severity level criteria.
     *
     * @return array
     */
    public static function get_criteria() {
        return array(
            self::CRITICAL => array(
                'response_time' => __('Immediate (within 15 minutes)', 'faire-woo'),
                'impact' => __('System-wide failure or data loss', 'faire-woo'),
                'notification' => __('Immediate notification to administrators', 'faire-woo'),
                'examples' => array(
                    __('Database connection failure', 'faire-woo'),
                    __('API authentication system failure', 'faire-woo'),
                    __('Complete sync process failure', 'faire-woo'),
                ),
            ),
            self::HIGH => array(
                'response_time' => __('Within 1 hour', 'faire-woo'),
                'impact' => __('Major feature or process failure', 'faire-woo'),
                'notification' => __('Notification to administrators', 'faire-woo'),
                'examples' => array(
                    __('Order sync failures', 'faire-woo'),
                    __('Payment processing issues', 'faire-woo'),
                    __('Critical data mismatch', 'faire-woo'),
                ),
            ),
            self::MEDIUM => array(
                'response_time' => __('Within 4 hours', 'faire-woo'),
                'impact' => __('Feature degradation with workarounds', 'faire-woo'),
                'notification' => __('Logged for review', 'faire-woo'),
                'examples' => array(
                    __('Non-critical sync delays', 'faire-woo'),
                    __('Minor data inconsistencies', 'faire-woo'),
                    __('Performance degradation', 'faire-woo'),
                ),
            ),
            self::LOW => array(
                'response_time' => __('Within 24 hours', 'faire-woo'),
                'impact' => __('Minimal impact on operations', 'faire-woo'),
                'notification' => __('Logged for monitoring', 'faire-woo'),
                'examples' => array(
                    __('UI/UX issues', 'faire-woo'),
                    __('Non-critical warnings', 'faire-woo'),
                    __('Minor display errors', 'faire-woo'),
                ),
            ),
            self::DEBUG => array(
                'response_time' => __('No immediate response needed', 'faire-woo'),
                'impact' => __('No impact on operations', 'faire-woo'),
                'notification' => __('Logged for debugging', 'faire-woo'),
                'examples' => array(
                    __('Debug information', 'faire-woo'),
                    __('Process tracking', 'faire-woo'),
                    __('Performance metrics', 'faire-woo'),
                ),
            ),
        );
    }

    /**
     * Get severity level for an error category.
     *
     * @param string $category Error category from ErrorCategories class.
     * @param string $message  Error message for context.
     * @return string
     */
    public static function get_severity_for_category($category, $message = '') {
        // Critical severity patterns
        $critical_patterns = array(
            'database' => array('connection', 'corrupt', 'lost'),
            'auth' => array('invalid token', 'expired token'),
            'system' => array('out of memory', 'fatal error'),
            'sync' => array('data loss', 'corruption'),
        );

        // High severity patterns
        $high_patterns = array(
            'api' => array('rate limit', 'timeout'),
            'sync' => array('failed', 'conflict'),
            'validation' => array('invalid data', 'missing required'),
        );

        // Check for critical patterns
        if (isset($critical_patterns[$category])) {
            foreach ($critical_patterns[$category] as $pattern) {
                if (stripos($message, $pattern) !== false) {
                    return self::CRITICAL;
                }
            }
        }

        // Check for high severity patterns
        if (isset($high_patterns[$category])) {
            foreach ($high_patterns[$category] as $pattern) {
                if (stripos($message, $pattern) !== false) {
                    return self::HIGH;
                }
            }
        }

        // Default severity by category
        $category_severity = array(
            ErrorCategories::DATABASE => self::HIGH,
            ErrorCategories::AUTH => self::HIGH,
            ErrorCategories::API => self::MEDIUM,
            ErrorCategories::SYNC => self::MEDIUM,
            ErrorCategories::VALIDATION => self::MEDIUM,
            ErrorCategories::NETWORK => self::MEDIUM,
            ErrorCategories::SYSTEM => self::HIGH,
        );

        return $category_severity[$category] ?? self::LOW;
    }

    /**
     * Check if a severity level is valid.
     *
     * @param string $severity Severity level to check.
     * @return bool
     */
    public static function is_valid($severity) {
        return in_array($severity, array(
            self::CRITICAL,
            self::HIGH,
            self::MEDIUM,
            self::LOW,
            self::DEBUG,
        ), true);
    }

    /**
     * Get WooCommerce log level for severity.
     *
     * @param string $severity Severity level.
     * @return string
     */
    public static function to_wc_log_level($severity) {
        $map = array(
            self::CRITICAL => 'emergency',
            self::HIGH => 'error',
            self::MEDIUM => 'warning',
            self::LOW => 'notice',
            self::DEBUG => 'debug',
        );

        return $map[$severity] ?? 'info';
    }

    /**
     * Get notification settings for severity level.
     *
     * @param string $severity Severity level.
     * @return array
     */
    public static function get_notification_settings($severity) {
        $settings = array(
            self::CRITICAL => array(
                'email' => true,
                'admin_notice' => true,
                'slack' => true,
                'response_time' => 900, // 15 minutes in seconds
            ),
            self::HIGH => array(
                'email' => true,
                'admin_notice' => true,
                'slack' => false,
                'response_time' => 3600, // 1 hour in seconds
            ),
            self::MEDIUM => array(
                'email' => false,
                'admin_notice' => true,
                'slack' => false,
                'response_time' => 14400, // 4 hours in seconds
            ),
            self::LOW => array(
                'email' => false,
                'admin_notice' => false,
                'slack' => false,
                'response_time' => 86400, // 24 hours in seconds
            ),
            self::DEBUG => array(
                'email' => false,
                'admin_notice' => false,
                'slack' => false,
                'response_time' => 0,
            ),
        );

        return $settings[$severity] ?? $settings[self::LOW];
    }
} 