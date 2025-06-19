<?php
/**
 * Error Information Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

defined('ABSPATH') || exit;

/**
 * Error Information Class
 * 
 * Defines and manages error information requirements and structure.
 */
class ErrorInfo {
    /**
     * Error ID.
     *
     * @var string
     */
    private $id;

    /**
     * Error message.
     *
     * @var string
     */
    private $message;

    /**
     * Error category.
     *
     * @var string
     */
    private $category;

    /**
     * Error severity.
     *
     * @var string
     */
    private $severity;

    /**
     * Error timestamp.
     *
     * @var string
     */
    private $timestamp;

    /**
     * Error context data.
     *
     * @var array
     */
    private $context;

    /**
     * Stack trace.
     *
     * @var string
     */
    private $stack_trace;

    /**
     * User ID.
     *
     * @var int
     */
    private $user_id;

    /**
     * Request data.
     *
     * @var array
     */
    private $request_data;

    /**
     * Constructor.
     *
     * @param string $message Error message.
     * @param array  $context Optional. Error context data.
     */
    public function __construct($message, array $context = array()) {
        $this->id = $this->generate_error_id();
        $this->message = $message;
        $this->timestamp = current_time('mysql');
        $this->context = $context;
        $this->user_id = get_current_user_id();
        $this->request_data = $this->capture_request_data();

        // Set category and severity
        $this->category = ErrorCategories::categorize($message);
        $this->severity = ErrorSeverity::get_severity_for_category($this->category, $message);

        if (isset($context['exception'])) {
            $this->stack_trace = $this->format_stack_trace($context['exception']);
        }
    }

    /**
     * Generate unique error ID.
     *
     * @return string
     */
    private function generate_error_id() {
        return uniqid('faire_error_', true);
    }

    /**
     * Capture current request data.
     *
     * @return array
     */
    private function capture_request_data() {
        return array(
            'url' => isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '',
            'method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '',
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            'referer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '',
            'timestamp' => current_time('mysql'),
        );
    }

    /**
     * Format exception stack trace.
     *
     * @param \Exception $exception Exception object.
     * @return string
     */
    private function format_stack_trace($exception) {
        if (!($exception instanceof \Exception)) {
            return '';
        }

        return $exception->getTraceAsString();
    }

    /**
     * Get error data as array.
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'message' => $this->message,
            'category' => $this->category,
            'severity' => $this->severity,
            'timestamp' => $this->timestamp,
            'context' => $this->context,
            'stack_trace' => $this->stack_trace,
            'user_id' => $this->user_id,
            'request_data' => $this->request_data,
        );
    }

    /**
     * Get error data as JSON.
     *
     * @return string
     */
    public function to_json() {
        return wp_json_encode($this->to_array());
    }

    /**
     * Get error message with context.
     *
     * @return string
     */
    public function get_formatted_message() {
        $severity_label = strtoupper($this->severity);
        $category_label = strtoupper($this->category);
        
        return sprintf(
            '[%s][%s][%s] %s',
            $this->timestamp,
            $severity_label,
            $category_label,
            $this->message
        );
    }

    /**
     * Get required fields for error types.
     *
     * @return array
     */
    public static function get_required_fields() {
        return array(
            ErrorCategories::API => array(
                'endpoint',
                'method',
                'response_code',
                'response_body',
            ),
            ErrorCategories::DATABASE => array(
                'query',
                'error_code',
                'error_message',
            ),
            ErrorCategories::VALIDATION => array(
                'field',
                'value',
                'constraint',
            ),
            ErrorCategories::SYNC => array(
                'order_id',
                'faire_order_id',
                'sync_stage',
            ),
            ErrorCategories::AUTH => array(
                'token_type',
                'error_code',
                'error_message',
            ),
            ErrorCategories::NETWORK => array(
                'url',
                'method',
                'error_code',
            ),
            ErrorCategories::SYSTEM => array(
                'component',
                'function',
                'error_message',
            ),
        );
    }

    /**
     * Validate error context data.
     *
     * @param string $category Error category.
     * @param array  $context  Context data to validate.
     * @return array Array of missing required fields.
     */
    public static function validate_context($category, array $context) {
        $required_fields = self::get_required_fields();
        
        if (!isset($required_fields[$category])) {
            return array();
        }

        $missing_fields = array();
        foreach ($required_fields[$category] as $field) {
            if (!isset($context[$field]) || empty($context[$field])) {
                $missing_fields[] = $field;
            }
        }

        return $missing_fields;
    }

    /**
     * Create error info from exception.
     *
     * @param \Exception $exception Exception object.
     * @param array      $context   Additional context data.
     * @return self
     */
    public static function from_exception(\Exception $exception, array $context = array()) {
        $context['exception'] = $exception;
        return new self($exception->getMessage(), $context);
    }

    /**
     * Create error info from WP_Error.
     *
     * @param \WP_Error $wp_error WP_Error object.
     * @param array     $context  Additional context data.
     * @return self
     */
    public static function from_wp_error(\WP_Error $wp_error, array $context = array()) {
        $context['wp_error'] = array(
            'code' => $wp_error->get_error_code(),
            'data' => $wp_error->get_error_data(),
        );
        return new self($wp_error->get_error_message(), $context);
    }
} 