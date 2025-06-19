<?php
/**
 * Error Categories Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

defined('ABSPATH') || exit;

/**
 * Error Categories Class
 * 
 * Defines and manages error categories for the FaireWoo plugin.
 */
class ErrorCategories {
    /**
     * API error category.
     */
    const API = 'api';

    /**
     * Database error category.
     */
    const DATABASE = 'database';

    /**
     * Validation error category.
     */
    const VALIDATION = 'validation';

    /**
     * Sync error category.
     */
    const SYNC = 'sync';

    /**
     * Authentication error category.
     */
    const AUTH = 'auth';

    /**
     * Network error category.
     */
    const NETWORK = 'network';

    /**
     * System error category.
     */
    const SYSTEM = 'system';

    /**
     * Get error category descriptions.
     *
     * @return array
     */
    public static function get_descriptions() {
        return array(
            self::API => __('Errors related to Faire API interactions', 'faire-woo'),
            self::DATABASE => __('Errors related to database operations', 'faire-woo'),
            self::VALIDATION => __('Errors related to data validation', 'faire-woo'),
            self::SYNC => __('Errors related to order synchronization', 'faire-woo'),
            self::AUTH => __('Errors related to authentication', 'faire-woo'),
            self::NETWORK => __('Errors related to network connectivity', 'faire-woo'),
            self::SYSTEM => __('Errors related to system operations', 'faire-woo'),
        );
    }

    /**
     * Get error category examples.
     *
     * @return array
     */
    public static function get_examples() {
        return array(
            self::API => array(
                'Invalid API response format',
                'API rate limit exceeded',
                'API endpoint not found',
            ),
            self::DATABASE => array(
                'Failed to save order data',
                'Database query error',
                'Missing required table',
            ),
            self::VALIDATION => array(
                'Missing required field',
                'Invalid data format',
                'Data type mismatch',
            ),
            self::SYNC => array(
                'Order mismatch detected',
                'Failed to resolve conflict',
                'Sync process interrupted',
            ),
            self::AUTH => array(
                'Invalid API credentials',
                'Token expired',
                'Missing authentication',
            ),
            self::NETWORK => array(
                'Connection timeout',
                'DNS resolution failed',
                'SSL certificate error',
            ),
            self::SYSTEM => array(
                'Insufficient permissions',
                'File system error',
                'Memory limit exceeded',
            ),
        );
    }

    /**
     * Check if a category is valid.
     *
     * @param string $category Category to check.
     * @return bool
     */
    public static function is_valid($category) {
        return in_array($category, array(
            self::API,
            self::DATABASE,
            self::VALIDATION,
            self::SYNC,
            self::AUTH,
            self::NETWORK,
            self::SYSTEM,
        ), true);
    }

    /**
     * Get category from error.
     *
     * @param \Exception|\WP_Error|string $error Error to categorize.
     * @return string
     */
    public static function categorize($error) {
        if ($error instanceof \WP_Error) {
            return self::categorize_wp_error($error);
        }

        if ($error instanceof \Exception) {
            return self::categorize_exception($error);
        }

        // Default categorization based on error message
        $message = (string) $error;
        
        if (stripos($message, 'api') !== false || stripos($message, 'endpoint') !== false) {
            return self::API;
        }

        if (stripos($message, 'database') !== false || stripos($message, 'query') !== false) {
            return self::DATABASE;
        }

        if (stripos($message, 'valid') !== false || stripos($message, 'required') !== false) {
            return self::VALIDATION;
        }

        if (stripos($message, 'sync') !== false || stripos($message, 'conflict') !== false) {
            return self::SYNC;
        }

        if (stripos($message, 'auth') !== false || stripos($message, 'token') !== false) {
            return self::AUTH;
        }

        if (stripos($message, 'network') !== false || stripos($message, 'connection') !== false) {
            return self::NETWORK;
        }

        return self::SYSTEM;
    }

    /**
     * Categorize WP_Error.
     *
     * @param \WP_Error $error Error to categorize.
     * @return string
     */
    private static function categorize_wp_error(\WP_Error $error) {
        $code = $error->get_error_code();
        
        if (stripos($code, 'http') === 0) {
            return self::NETWORK;
        }

        if (stripos($code, 'db_') === 0) {
            return self::DATABASE;
        }

        return self::categorize($error->get_error_message());
    }

    /**
     * Categorize Exception.
     *
     * @param \Exception $error Exception to categorize.
     * @return string
     */
    private static function categorize_exception(\Exception $error) {
        if ($error instanceof \PDOException || $error instanceof \mysqli_sql_exception) {
            return self::DATABASE;
        }

        if ($error instanceof \InvalidArgumentException) {
            return self::VALIDATION;
        }

        if ($error instanceof \RuntimeException) {
            return self::SYSTEM;
        }

        return self::categorize($error->getMessage());
    }
} 