<?php
/**
 * FaireWoo setup
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo;

defined('ABSPATH') || exit;

use FaireWoo\Admin\ManualResolutionPage;
use FaireWoo\Admin\BulkSyncPage;

/**
 * Main FaireWoo Class.
 *
 * @class FaireWoo
 */
final class FaireWoo {
    /**
     * Single instance of the FaireWoo class.
     *
     * @var FaireWoo
     */
    protected static $instance = null;

    /**
     * Order sync manager instance.
     *
     * @var Sync\OrderSyncManager
     */
    public $order_sync = null;

    /**
     * Main FaireWoo Instance.
     *
     * Ensures only one instance of FaireWoo is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return FaireWoo - Main instance.
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * FaireWoo Constructor.
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define FaireWoo Constants.
     */
    private function define_constants() {
        $this->define('FAIRE_WOO_ABSPATH', dirname(FAIRE_WOO_PLUGIN_FILE) . '/');
        $this->define('FAIRE_WOO_VERSION', '1.0.0');
    }

    /**
     * Define constant if not already set.
     *
     * @param string      $name  Constant name.
     * @param string|bool $value Constant value.
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Include required core files used in admin and on the frontend.
     */
    private function includes() {
        // Interfaces
        include_once FAIRE_WOO_ABSPATH . 'includes/interfaces/interface-order-comparator.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/interfaces/interface-conflict-resolver.php';

        // Abstract classes
        include_once FAIRE_WOO_ABSPATH . 'includes/abstracts/abstract-faire-woo-sync.php';

        // Core classes
        include_once FAIRE_WOO_ABSPATH . 'includes/sync/class-order-sync-manager.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/sync/class-order-comparator.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/sync/class-conflict-resolver.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/sync/class-conflict-resolution-config.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/sync/class-error-logger.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/sync/class-order-sync-state-machine.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/sync/class-order-sync-state-manager.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/sync/class-manual-resolution-handler.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/admin/class-manual-resolution-page.php';
        include_once FAIRE_WOO_ABSPATH . 'includes/admin/class-bulk-sync-page.php';
    }

    /**
     * Hook into actions and filters.
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'), 0);
        add_action('woocommerce_init', array($this, 'woocommerce_init'));
    }

    /**
     * Init FaireWoo when WordPress Initializes.
     */
    public function init() {
        // Initialize components
        $order_comparator = new Sync\OrderComparator();
        $conflict_resolver = new Sync\ConflictResolver();
        $error_logger = new Sync\ErrorLogger();
        $state_machine = new Sync\OrderSyncStateMachine();
        $state_manager = new Sync\OrderSyncStateManager($error_logger, $state_machine);
        $this->order_sync = new Sync\OrderSyncManager(
            $order_comparator,
            $conflict_resolver,
            $error_logger,
            $state_manager
        );
        $this->init_components();
    }

    /**
     * Init FaireWoo when WooCommerce initializes.
     */
    public function woocommerce_init() {
        // Additional WooCommerce-specific initialization
    }

    /**
     * Initialize plugin components.
     */
    private function init_components() {
        // Initialize admin components
        if (is_admin()) {
            new ManualResolutionPage();
            new BulkSyncPage();
        }

        // Initialize other components
        // ... existing component initialization code ...
    }
} 