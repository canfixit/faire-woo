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
        return 'class-' . str_replace('_', '-', strtolower($class)) . '.php';
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
        $class = strtolower($class);

        if (0 !== strpos($class, 'fairewoo\\')) {
            return;
        }

        $class = str_replace('fairewoo\\', '', $class);
        $class = str_replace('\\', '/', $class);
        $file  = $this->get_file_name_from_class($class);
        $path  = $this->include_path . $file;

        if (!$this->load_file($path)) {
            // Try loading from subdirectories
            $dirs = array('abstracts', 'interfaces', 'sync', 'api');
            foreach ($dirs as $dir) {
                if ($this->load_file($this->include_path . $dir . '/' . $file)) {
                    return;
                }
            }
        }
    }
}

new Autoloader(); 