<?php
/**
 * Error Logger Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

defined('ABSPATH') || exit;

/**
 * Error Logger Class
 * 
 * Handles error logging format and management.
 */
class ErrorLogger {
    /**
     * WooCommerce logger instance.
     *
     * @var \WC_Logger
     */
    private $wc_logger;

    /**
     * Log file handle.
     *
     * @var string
     */
    private $log_handle = 'faire-woo-sync';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->wc_logger = wc_get_logger();
    }

    /**
     * Log an error.
     *
     * @param ErrorInfo $error_info Error information object.
     * @return bool True if logged successfully, false otherwise.
     */
    public function log_error(ErrorInfo $error_info) {
        try {
            // Get error data
            $error_data = $error_info->to_array();
            
            // Log to WooCommerce logs
            $this->log_to_woocommerce($error_data);

            // Log to database for persistence and querying
            $this->log_to_database($error_data);

            // Handle notifications based on severity
            $this->handle_notifications($error_data);

            return true;
        } catch (\Exception $e) {
            // If logging fails, use WP's error log as fallback
            error_log(sprintf(
                'FaireWoo Error Logger failed: %s. Original error: %s',
                $e->getMessage(),
                $error_info->get_formatted_message()
            ));
            return false;
        }
    }

    /**
     * Log error to WooCommerce logs.
     *
     * @param array $error_data Error data array.
     */
    private function log_to_woocommerce(array $error_data) {
        $context = array(
            'source' => $this->log_handle,
            'error_id' => $error_data['id'],
        );

        $level = ErrorSeverity::to_wc_log_level($error_data['severity']);
        
        // Format the log message
        $log_message = $this->format_log_message($error_data);

        // Log to WooCommerce
        $this->wc_logger->log($level, $log_message, $context);
    }

    /**
     * Log error to database.
     *
     * @param array $error_data Error data array.
     */
    private function log_to_database(array $error_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'faire_woo_error_log';

        // Ensure the error log table exists
        $this->maybe_create_log_table();

        // Prepare data for insertion
        $data = array(
            'error_id' => $error_data['id'],
            'message' => $error_data['message'],
            'category' => $error_data['category'],
            'severity' => $error_data['severity'],
            'timestamp' => $error_data['timestamp'],
            'context' => wp_json_encode($error_data['context']),
            'stack_trace' => $error_data['stack_trace'],
            'user_id' => $error_data['user_id'],
            'request_data' => wp_json_encode($error_data['request_data']),
        );

        // Insert into database
        $wpdb->insert($table_name, $data, array(
            '%s', // error_id
            '%s', // message
            '%s', // category
            '%s', // severity
            '%s', // timestamp
            '%s', // context
            '%s', // stack_trace
            '%d', // user_id
            '%s', // request_data
        ));
    }

    /**
     * Handle notifications based on error severity.
     *
     * @param array $error_data Error data array.
     */
    private function handle_notifications(array $error_data) {
        $notification_settings = ErrorSeverity::get_notification_settings($error_data['severity']);

        if ($notification_settings['email']) {
            $this->send_email_notification($error_data);
        }

        if ($notification_settings['admin_notice']) {
            $this->add_admin_notice($error_data);
        }

        if ($notification_settings['slack']) {
            $this->send_slack_notification($error_data);
        }
    }

    /**
     * Format log message.
     *
     * @param array $error_data Error data array.
     * @return string
     */
    private function format_log_message(array $error_data) {
        $message_parts = array(
            sprintf('[%s]', $error_data['timestamp']),
            sprintf('[%s]', strtoupper($error_data['severity'])),
            sprintf('[%s]', strtoupper($error_data['category'])),
            sprintf('[ID:%s]', $error_data['id']),
            $error_data['message'],
        );

        $log_message = implode(' ', $message_parts);

        // Add context if available
        if (!empty($error_data['context'])) {
            $log_message .= "\nContext: " . wp_json_encode($error_data['context'], JSON_PRETTY_PRINT);
        }

        // Add stack trace if available
        if (!empty($error_data['stack_trace'])) {
            $log_message .= "\nStack Trace:\n" . $error_data['stack_trace'];
        }

        return $log_message;
    }

    /**
     * Send email notification.
     *
     * @param array $error_data Error data array.
     */
    private function send_email_notification(array $error_data) {
        $to = get_option('admin_email');
        $subject = sprintf(
            '[FaireWoo] %s Error: %s',
            strtoupper($error_data['severity']),
            wp_strip_all_tags($error_data['message'])
        );

        $body = $this->format_email_body($error_data);
        
        wp_mail($to, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));
    }

    /**
     * Add admin notice.
     *
     * @param array $error_data Error data array.
     */
    private function add_admin_notice(array $error_data) {
        $notice_key = 'faire_woo_error_' . $error_data['id'];
        $notice_message = sprintf(
            '%s Error: %s',
            strtoupper($error_data['severity']),
            wp_strip_all_tags($error_data['message'])
        );

        set_transient($notice_key, $notice_message, HOUR_IN_SECONDS);
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }

    /**
     * Send Slack notification.
     *
     * @param array $error_data Error data array.
     */
    private function send_slack_notification(array $error_data) {
        $webhook_url = get_option('faire_woo_slack_webhook_url');
        if (empty($webhook_url)) {
            return;
        }

        $payload = array(
            'text' => sprintf(
                '*[FaireWoo] %s Error*\n%s',
                strtoupper($error_data['severity']),
                $error_data['message']
            ),
            'attachments' => array(
                array(
                    'fields' => array(
                        array(
                            'title' => 'Category',
                            'value' => strtoupper($error_data['category']),
                            'short' => true,
                        ),
                        array(
                            'title' => 'Error ID',
                            'value' => $error_data['id'],
                            'short' => true,
                        ),
                    ),
                    'color' => $this->get_slack_color($error_data['severity']),
                ),
            ),
        );

        wp_remote_post($webhook_url, array(
            'body' => wp_json_encode($payload),
            'headers' => array('Content-Type' => 'application/json'),
        ));
    }

    /**
     * Format email body.
     *
     * @param array $error_data Error data array.
     * @return string
     */
    private function format_email_body(array $error_data) {
        ob_start();
        ?>
        <h2>FaireWoo Error Report</h2>
        <table>
            <tr>
                <th>Error ID:</th>
                <td><?php echo esc_html($error_data['id']); ?></td>
            </tr>
            <tr>
                <th>Severity:</th>
                <td><?php echo esc_html(strtoupper($error_data['severity'])); ?></td>
            </tr>
            <tr>
                <th>Category:</th>
                <td><?php echo esc_html(strtoupper($error_data['category'])); ?></td>
            </tr>
            <tr>
                <th>Message:</th>
                <td><?php echo esc_html($error_data['message']); ?></td>
            </tr>
            <tr>
                <th>Timestamp:</th>
                <td><?php echo esc_html($error_data['timestamp']); ?></td>
            </tr>
        </table>

        <?php if (!empty($error_data['context'])): ?>
            <h3>Context</h3>
            <pre><?php echo esc_html(wp_json_encode($error_data['context'], JSON_PRETTY_PRINT)); ?></pre>
        <?php endif; ?>

        <?php if (!empty($error_data['stack_trace'])): ?>
            <h3>Stack Trace</h3>
            <pre><?php echo esc_html($error_data['stack_trace']); ?></pre>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Get Slack message color based on severity.
     *
     * @param string $severity Error severity.
     * @return string
     */
    private function get_slack_color($severity) {
        $colors = array(
            ErrorSeverity::CRITICAL => '#FF0000', // Red
            ErrorSeverity::HIGH => '#FFA500',     // Orange
            ErrorSeverity::MEDIUM => '#FFFF00',   // Yellow
            ErrorSeverity::LOW => '#00FF00',      // Green
            ErrorSeverity::DEBUG => '#808080',    // Gray
        );

        return $colors[$severity] ?? '#808080';
    }

    /**
     * Display admin notices.
     */
    public function display_admin_notices() {
        global $wpdb;
        
        $notices = $wpdb->get_results(
            "SELECT option_name, option_value FROM $wpdb->options 
            WHERE option_name LIKE '_transient_faire_woo_error_%'"
        );

        foreach ($notices as $notice) {
            $message = get_transient(str_replace('_transient_', '', $notice->option_name));
            if ($message) {
                printf(
                    '<div class="notice notice-error"><p>%s</p></div>',
                    esc_html($message)
                );
                delete_transient(str_replace('_transient_', '', $notice->option_name));
            }
        }
    }

    /**
     * Create error log table if it doesn't exist.
     */
    private function maybe_create_log_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'faire_woo_error_log';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                error_id varchar(50) NOT NULL,
                message text NOT NULL,
                category varchar(50) NOT NULL,
                severity varchar(50) NOT NULL,
                timestamp datetime NOT NULL,
                context longtext,
                stack_trace longtext,
                user_id bigint(20),
                request_data longtext,
                PRIMARY KEY  (id),
                KEY error_id (error_id),
                KEY category (category),
                KEY severity (severity),
                KEY timestamp (timestamp)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
} 