<?php
/**
 * FaireWoo Autoloader.
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo;

defined('ABSPATH') || exit;

/**
 * Autoloader class.
 */
class Autoloader {
    /**
     * Path to the includes directory.
     *
     * @var string
     */
    private $include_path = '';

    /**
     * The Constructor.
     */
    public function __construct() {
        spl_autoload_register(array($this, 'autoload'));
        $this->include_path = FAIRE_WOO_PLUGIN_DIR . 'includes/';
    }

    /**
     * Auto-load FaireWoo classes on demand.
     *
     * @param string $class The class name.
     */
    public function autoload($class) {
        $prefix = 'FaireWoo\\';
        $len = strlen($prefix);

        // Not a class from this plugin.
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        // Get the relative class name. E.g., "Sync\OrderSyncManager"
        $relative_class = substr($class, $len);

        $parts = explode('\\', $relative_class);
        $class_name = array_pop($parts);

        // Convert the class name from CamelCase to kebab-case for the filename.
        // E.g., "OrderSyncManager" -> "order-sync-manager"
        $file_name_kebab = strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $class_name));
        $file_name = 'class-' . $file_name_kebab . '.php';
        
        // Convert namespace parts to a directory path.
        $path = '';
        if (!empty($parts)) {
            $path = strtolower(implode('/', $parts)) . '/';
        }

        $file_path = $this->include_path . $path . $file_name;
        
        if (file_exists($file_path)) {
            require $file_path;
        }
    }
}

new Autoloader(); 