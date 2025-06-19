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
        // Project-specific namespace prefix
        $prefix = 'FaireWoo\\';

        // Does the class use the namespace prefix?
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            // No, move to the next registered autoloader
            return;
        }

        // Get the relative class name
        $relative_class = substr($class, $len);

        // Replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file = 'class-' . str_replace('_', '-', strtolower(basename(str_replace('\\', '/', $class)))) . '.php';
        
        // Prepare the path
        $path_parts = explode('\\', strtolower($relative_class));
        array_pop($path_parts); // Remove class name
        $path = '';
        if (!empty($path_parts)) {
            $path = implode('/', $path_parts) . '/';
        }
        
        $file_path = $this->include_path . $path . $file;
        
        // if the file exists, require it
        if (file_exists($file_path)) {
            require $file_path;
        }
    }
}

new Autoloader(); 