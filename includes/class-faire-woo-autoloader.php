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
     * @param string $class Class name.
     */
    public function autoload($class) {
        // Convert namespace separators to directory separators
        $class = str_replace('\\', '/', $class);
        $class = strtolower($class);

        if (0 !== strpos($class, 'fairewoo/')) {
            return;
        }

        // Remove the namespace prefix
        $class = str_replace('fairewoo/', '', $class);
        
        // Get the file name
        $file = $this->get_file_name_from_class(basename($class));
        
        // Get the path without the filename
        $path = dirname($class);
        if ($path !== '.') {
            $path = $this->include_path . $path . '/' . $file;
        } else {
            $path = $this->include_path . $file;
        }

        if (!$this->load_file($path)) {
            // Try loading from subdirectories if not found in main directory
            $dirs = array('abstracts', 'interfaces', 'sync', 'api', 'admin');
            foreach ($dirs as $dir) {
                if ($this->load_file($this->include_path . $dir . '/' . $file)) {
                    return;
                }
            }
        }
    }
}

new Autoloader(); 