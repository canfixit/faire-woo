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
     * @var Sync\OrderSyncManager
     */
    public $order_sync = null;

    /**
     * @var Sync\BulkSyncManager
     */
    public $bulk_sync = null;
    
    /**
     * @var Sync\ManualResolutionHandler
     */
    public $resolution_handler = null;

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
        // The autoloader handles all class loading.
        // Manual includes are no longer necessary.
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
        $error_logger       = new Sync\ErrorLogger();
        $state_machine      = new Sync\OrderSyncStateMachine();
        $state_manager      = new Sync\OrderSyncStateManager($error_logger, $state_machine);
        $order_comparator   = new Sync\OrderComparator();
        $conflict_resolver  = new Sync\ConflictResolver();
        
        $this->order_sync = new Sync\OrderSyncManager(
            $order_comparator,
            $conflict_resolver,
            $error_logger,
            $state_manager
        );

        $this->bulk_sync = new Sync\BulkSyncManager($this->order_sync);
        $this->resolution_handler = new Sync\ManualResolutionHandler();

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
            new ManualResolutionPage($this->resolution_handler);
            new BulkSyncPage($this->bulk_sync);
        }

        // Initialize other components
        // ... existing component initialization code ...
    }
} 