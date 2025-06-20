<?php
/**
 * Admin Settings Page
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Admin;

defined('ABSPATH') || exit;

/**
 * Settings Page Class
 */
class SettingsPage {
    /**
     * Option group for settings.
     */
    const OPTION_GROUP = 'faire_woo_settings';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_item'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add menu item.
     */
    public function add_menu_item() {
        add_submenu_page(
            'faire-woo-main', // This will be created by a new main menu entry
            'FaireWoo Settings',
            'Settings',
            'manage_woocommerce',
            'faire-woo-settings',
            array($this, 'render_page')
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(self::OPTION_GROUP, 'faire_woo_api_key', 'sanitize_text_field');
    }

    /**
     * Render the admin page.
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1>FaireWoo Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::OPTION_GROUP);
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Faire API Key</th>
                        <td>
                            <input type="text" 
                                   name="faire_woo_api_key" 
                                   value="<?php echo esc_attr(get_option('faire_woo_api_key')); ?>"
                                   class="regular-text"/>
                            <p class="description">Enter your API key from your Faire account.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
} 