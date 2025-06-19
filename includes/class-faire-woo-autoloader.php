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
        if (function_exists('__autoload')) {
            spl_autoload_register('__autoload');
        }

        spl_autoload_register(array($this, 'autoload'));

        $this->include_path = untrailingslashit(plugin_dir_path(FAIRE_WOO_PLUGIN_FILE)) . '/includes/';
    }

    /**
     * Take a class name and turn it into a file name.
     *
     * @param  string $class Class name.
     * @return string
     */
    private function get_file_name_from_class($class) {
        // Convert class name to lowercase and replace underscores with hyphens
        $file = strtolower($class);
        $file = str_replace('_', '-', $file);
        
        // If this is the main class, handle it specially
        if ($file === 'fairewoo') {
            return 'class-faire-woo.php';
        }
        
        return 'class-' . $file . '.php';
    }

    /**
     * Include a class file.
     *
     * @param  string $path File path.
     * @return bool Successful or not.
     */
    private function load_file($path) {
        if ($path && is_readable($path)) {
            include_once $path;
            return true;
        }
        return false;
    }

    /**
     * Auto-load FaireWoo classes on demand.
     *
     * @param string $class The class name.
     */
    public function autoload($class) {
        // Only autoload classes from this plugin's namespace.
        if (0 !== strpos($class, 'FaireWoo\\')) {
            return;
        }

        // Remove the namespace prefix.
        $relative_class = substr($class, strlen('FaireWoo\\'));

        // Prepare the file name.
        $file = 'class-' . str_replace('_', '-', strtolower(basename(str_replace('\\', '/', $relative_class)))) . '.php';
        
        // Prepare the path.
        $path_parts = explode('\\', strtolower($relative_class));
        // Remove the class name part, leaving only the directory structure.
        array_pop($path_parts); 
        $path = '';
        if (!empty($path_parts)) {
            $path = implode('/', $path_parts) . '/';
        }

        // Load the file.
        $this->load_file($this->include_path . $path . $file);
    }
}

new Autoloader(); 