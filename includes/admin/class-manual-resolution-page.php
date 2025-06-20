<?php
/**
 * Manual Resolution Admin Page
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Admin;

use FaireWoo\Sync\ManualResolutionHandler;

defined('ABSPATH') || exit;

/**
 * Manual Resolution Admin Page Class
 */
class ManualResolutionPage {
    /**
     * Resolution handler instance.
     *
     * @var ManualResolutionHandler
     */
    private $handler;

    /**
     * Constructor.
     *
     * @param ManualResolutionHandler $handler Resolution handler instance.
     */
    public function __construct(ManualResolutionHandler $handler) {
        $this->handler = $handler;
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_post_faire_resolve_conflict', array($this, 'handle_resolution_submission'));
    }

    /**
     * Add menu item.
     */
    public function add_menu_item() {
        add_submenu_page(
            'faire-woo-main',
            'Faire Manual Resolutions',
            'Faire Resolutions',
            'manage_woocommerce',
            'faire-resolutions',
            array($this, 'render_page')
        );
    }

    /**
     * Render the admin page.
     */
    public function render_page() {
        $pending = $this->handler->get_pending_resolutions();
        ?>
        <div class="wrap">
            <h1>Faire Manual Resolutions</h1>
            <?php
            if (empty($pending)) {
                echo '<div class="notice notice-success"><p>No pending resolutions!</p></div>';
                return;
            }
            ?>
            <div class="faire-resolutions">
                <?php foreach ($pending as $order_id => $queue) : ?>
                    <div class="faire-resolution-order">
                        <h2>
                            Order #<?php echo esc_html($order_id); ?>
                            <a href="<?php echo esc_url(get_edit_post_link($order_id)); ?>" class="page-title-action">
                                View Order
                            </a>
                        </h2>
                        <?php foreach ($queue as $item) : ?>
                            <div class="faire-resolution-item">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="faire_resolve_conflict">
                                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                                    <input type="hidden" name="field" value="<?php echo esc_attr($item['field']); ?>">
                                    <?php wp_nonce_field('faire_resolve_conflict'); ?>

                                    <h3>Field: <?php echo esc_html($item['field']); ?></h3>
                                    <p><strong>Reason for Review:</strong> <?php echo esc_html($item['reason']); ?></p>
                                    
                                    <div class="faire-resolution-values">
                                        <div class="faire-value">
                                            <h4>Faire Value:</h4>
                                            <pre><?php echo esc_html($this->format_value($item['faire_value'])); ?></pre>
                                            <label>
                                                <input type="radio" name="resolution" value="faire" required>
                                                Use Faire Value
                                            </label>
                                        </div>
                                        <div class="wc-value">
                                            <h4>WooCommerce Value:</h4>
                                            <pre><?php echo esc_html($this->format_value($item['wc_value'])); ?></pre>
                                            <label>
                                                <input type="radio" name="resolution" value="wc" required>
                                                Use WooCommerce Value
                                            </label>
                                        </div>
                                    </div>

                                    <div class="faire-resolution-notes">
                                        <label for="resolution_notes">Resolution Notes:</label>
                                        <textarea name="resolution_notes" id="resolution_notes" rows="3" required></textarea>
                                    </div>

                                    <div class="faire-resolution-submit">
                                        <button type="submit" class="button button-primary">Apply Resolution</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .faire-resolution-order {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin: 1em 0;
                padding: 1em;
            }
            .faire-resolution-item {
                border-top: 1px solid #eee;
                margin-top: 1em;
                padding-top: 1em;
            }
            .faire-resolution-values {
                display: flex;
                gap: 2em;
                margin: 1em 0;
            }
            .faire-value, .wc-value {
                flex: 1;
                background: #f9f9f9;
                padding: 1em;
                border: 1px solid #eee;
            }
            .faire-resolution-notes {
                margin: 1em 0;
            }
            .faire-resolution-notes textarea {
                width: 100%;
            }
            pre {
                white-space: pre-wrap;
                word-wrap: break-word;
                background: #fff;
                padding: 0.5em;
                border: 1px solid #eee;
                margin: 0.5em 0;
            }
        </style>
        <?php
    }

    /**
     * Handle form submission.
     */
    public function handle_resolution_submission() {
        check_admin_referer('faire_resolve_conflict');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $resolution = isset($_POST['resolution']) ? sanitize_text_field($_POST['resolution']) : '';
        $notes = isset($_POST['resolution_notes']) ? sanitize_textarea_field($_POST['resolution_notes']) : '';

        if (!$order_id || !$field || !$resolution || !$notes) {
            wp_die('Missing required fields');
        }

        $queue = get_post_meta($order_id, '_faire_manual_resolution_queue', true);
        $value = null;

        foreach ($queue as $item) {
            if ($item['field'] === $field) {
                $value = $resolution === 'faire' ? $item['faire_value'] : $item['wc_value'];
                break;
            }
        }

        if ($value === null) {
            wp_die('Invalid resolution data');
        }

        $success = $this->handler->apply_resolution($order_id, $field, $value, $notes);

        if ($success) {
            wp_redirect(add_query_arg(
                array('page' => 'faire-resolutions', 'success' => '1'),
                admin_url('admin.php')
            ));
        } else {
            wp_die('Failed to apply resolution');
        }
    }

    /**
     * Format a value for display.
     *
     * @param mixed $value Value to format.
     * @return string
     */
    private function format_value($value) {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }
        return (string) $value;
    }
} 