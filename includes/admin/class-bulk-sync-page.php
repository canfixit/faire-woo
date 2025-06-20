<?php
/**
 * Bulk Sync Admin Page
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Admin;

use FaireWoo\Sync\BulkSyncManager;
use FaireWoo\Sync\OrderComparator;
use FaireWoo\Sync\ConflictResolver;
use FaireWoo\Sync\ErrorLogger;
use FaireWoo\Sync\OrderSyncStateMachine;
use FaireWoo\Sync\OrderSyncStateManager;

defined('ABSPATH') || exit;

/**
 * Bulk Sync Admin Page Class
 */
class BulkSyncPage {
    /**
     * Bulk sync manager instance.
     *
     * @var BulkSyncManager
     */
    private $sync_manager;

    /**
     * Constructor.
     *
     * @param BulkSyncManager $sync_manager Bulk sync manager instance.
     */
    public function __construct(BulkSyncManager $sync_manager) {
        $this->sync_manager = $sync_manager;
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_post_faire_start_bulk_sync', array($this, 'handle_start_sync'));
        add_action('admin_post_faire_cancel_sync', array($this, 'handle_cancel_sync'));
        add_action('wp_ajax_faire_get_sync_status', array($this, 'handle_get_status'));
    }

    /**
     * Add menu item.
     */
    public function add_menu_item() {
        add_submenu_page(
            'faire-woo-main',
            'Faire Bulk Sync',
            'Faire Bulk Sync',
            'manage_woocommerce',
            'faire-bulk-sync',
            array($this, 'render_page')
        );
    }

    /**
     * Render the admin page.
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1>Faire Bulk Synchronization</h1>

            <?php
            // Show success/error messages
            if (isset($_GET['sync_started'])) {
                echo '<div class="notice notice-success"><p>Sync job started successfully!</p></div>';
            } elseif (isset($_GET['sync_cancelled'])) {
                echo '<div class="notice notice-warning"><p>Sync job cancelled.</p></div>';
            } elseif (isset($_GET['error'])) {
                echo '<div class="notice notice-error"><p>' . esc_html(urldecode($_GET['error'])) . '</p></div>';
            }
            ?>

            <div class="faire-bulk-sync-container">
                <div class="faire-bulk-sync-form">
                    <h2>Start New Sync</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="faire_start_bulk_sync">
                        <?php wp_nonce_field('faire_start_bulk_sync'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="start_date">Start Date</label>
                                </th>
                                <td>
                                    <input type="date" 
                                           id="start_date" 
                                           name="start_date" 
                                           value="<?php echo esc_attr(date('Y-m-d', strtotime('-30 days'))); ?>"
                                           required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="end_date">End Date</label>
                                </th>
                                <td>
                                    <input type="date" 
                                           id="end_date" 
                                           name="end_date" 
                                           value="<?php echo esc_attr(date('Y-m-d')); ?>"
                                           required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="batch_size">Batch Size</label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="batch_size" 
                                           name="batch_size" 
                                           value="50"
                                           min="10"
                                           max="100"
                                           required>
                                    <p class="description">Number of orders to process per batch (10-100)</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Include Pending Orders</th>
                                <td>
                                    <label>
                                        <input type="checkbox" 
                                               name="include_pending" 
                                               value="1" 
                                               checked>
                                        Include pending orders in sync
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary">Start Sync</button>
                        </p>
                    </form>
                </div>

                <div class="faire-bulk-sync-status">
                    <h2>Active Sync Jobs</h2>
                    <div id="faire-sync-jobs"></div>
                </div>
            </div>

            <style>
                .faire-bulk-sync-container {
                    display: flex;
                    gap: 2em;
                    margin-top: 1em;
                }
                .faire-bulk-sync-form {
                    flex: 1;
                    max-width: 600px;
                }
                .faire-bulk-sync-status {
                    flex: 1;
                }
                .faire-sync-job {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    box-shadow: 0 1px 1px rgba(0,0,0,.04);
                    margin-bottom: 1em;
                    padding: 1em;
                }
                .faire-sync-progress {
                    margin: 1em 0;
                }
                .faire-sync-progress-bar {
                    background-color: #f0f0f1;
                    height: 20px;
                    border-radius: 3px;
                    overflow: hidden;
                }
                .faire-sync-progress-bar div {
                    background-color: #2271b1;
                    height: 100%;
                    width: 0;
                    transition: width 0.3s ease;
                }
                .faire-sync-stats {
                    display: flex;
                    gap: 1em;
                    margin-top: 0.5em;
                    font-size: 0.9em;
                    color: #666;
                }
                .faire-sync-failed {
                    color: #d63638;
                    margin-top: 0.5em;
                }
            </style>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                function updateSyncStatus() {
                    $.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'faire_get_sync_status'
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                var jobs = response.data;
                                var html = '';

                                if (Object.keys(jobs).length === 0) {
                                    html = '<p>No active sync jobs.</p>';
                                } else {
                                    for (var jobId in jobs) {
                                        var job = jobs[jobId];
                                        html += '<div class="faire-sync-job">';
                                        html += '<h3>Job ID: ' + jobId + '</h3>';
                                        html += '<div class="faire-sync-progress">';
                                        html += '<div class="faire-sync-progress-bar">';
                                        html += '<div style="width: ' + job.progress + '%"></div>';
                                        html += '</div>';
                                        html += '<div class="faire-sync-stats">';
                                        html += '<span>Progress: ' + job.progress + '%</span>';
                                        html += '<span>Processed: ' + job.processed_orders + '/' + job.total_orders + '</span>';
                                        html += '<span>Status: ' + job.status + '</span>';
                                        html += '</div>';
                                        html += '</div>';

                                        if (job.failed_orders.length > 0) {
                                            html += '<div class="faire-sync-failed">';
                                            html += '<strong>Failed Orders:</strong> ' + job.failed_orders.length;
                                            html += '</div>';
                                        }

                                        if (job.status === 'processing') {
                                            html += '<p><a href="' + 
                                                '<?php echo esc_url(admin_url("admin-post.php")); ?>?' +
                                                'action=faire_cancel_sync&job_id=' + jobId + 
                                                '&_wpnonce=<?php echo wp_create_nonce("faire_cancel_sync"); ?>' +
                                                '" class="button">Cancel Sync</a></p>';
                                        }

                                        html += '</div>';
                                    }
                                }

                                $('#faire-sync-jobs').html(html);

                                // Continue polling if there are active jobs
                                var hasActiveJobs = Object.values(jobs).some(function(job) {
                                    return job.status === 'processing';
                                });

                                if (hasActiveJobs) {
                                    setTimeout(updateSyncStatus, 5000);
                                }
                            }
                        }
                    });
                }

                updateSyncStatus();
            });
            </script>
        </div>
        <?php
    }

    /**
     * Handle start sync form submission.
     */
    public function handle_start_sync() {
        check_admin_referer('faire_start_bulk_sync');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }

        $args = array(
            'start_date' => isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '',
            'end_date' => isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '',
            'batch_size' => isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50,
            'include_pending' => isset($_POST['include_pending']),
        );

        $result = $this->sync_manager->start_sync($args);

        if (is_wp_error($result)) {
            wp_redirect(add_query_arg(
                array(
                    'page' => 'faire-bulk-sync',
                    'error' => urlencode($result->get_error_message()),
                ),
                admin_url('admin.php')
            ));
            exit;
        }

        wp_redirect(add_query_arg(
            array(
                'page' => 'faire-bulk-sync',
                'sync_started' => '1',
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Handle cancel sync request.
     */
    public function handle_cancel_sync() {
        check_admin_referer('faire_cancel_sync');

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }

        $job_id = isset($_GET['job_id']) ? sanitize_text_field($_GET['job_id']) : '';
        if (!$job_id) {
            wp_die('Invalid job ID');
        }

        $this->sync_manager->cancel_job($job_id);

        wp_redirect(add_query_arg(
            array(
                'page' => 'faire-bulk-sync',
                'sync_cancelled' => '1',
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Handle get status AJAX request.
     */
    public function handle_get_status() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $jobs = $wpdb->get_results(
            "SELECT option_name, option_value 
            FROM {$wpdb->options} 
            WHERE option_name LIKE 'faire_woo_sync_job_%'
            ORDER BY option_id DESC 
            LIMIT 10",
            ARRAY_A
        );

        $active_jobs = array();
        foreach ($jobs as $job) {
            $job_data = maybe_unserialize($job['option_value']);
            if ($job_data['status'] === 'processing' || 
                (isset($job_data['end_time']) && strtotime($job_data['end_time']) > strtotime('-1 hour'))) {
                $job_id = str_replace('faire_woo_sync_job_', '', $job['option_name']);
                $active_jobs[$job_id] = $this->sync_manager->get_job_status($job_id);
            }
        }

        wp_send_json_success($active_jobs);
    }
} 