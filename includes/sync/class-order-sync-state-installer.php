<?php
/**
 * Order Sync State Installer Class
 *
 * @package FaireWoo
 * @since   1.0.0
 */

namespace FaireWoo\Sync;

defined('ABSPATH') || exit;

/**
 * OrderSyncStateInstaller
 *
 * Handles database table installation and upgrades for order state persistence.
 */
class OrderSyncStateInstaller {
    /**
     * Database version key
     */
    const DB_VERSION_KEY = 'faire_woo_order_states_db_version';

    /**
     * Current database version
     */
    const CURRENT_VERSION = '1.0.0';

    /**
     * Install or upgrade the database tables
     *
     * @return void
     */
    public static function install_or_upgrade() {
        global $wpdb;

        $installed_version = get_option(self::DB_VERSION_KEY);

        // If the installed version matches current, no need to run
        if ($installed_version === self::CURRENT_VERSION) {
            return;
        }

        // Ensure we have the dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Set the default character set and collation
        $charset_collate = $wpdb->get_charset_collate();

        // Create the main states table
        $table_name = $wpdb->prefix . OrderSyncStateManager::TABLE_NAME;
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id varchar(100) NOT NULL,
            faire_order_id varchar(100) NOT NULL,
            state varchar(50) NOT NULL,
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY faire_order_id (faire_order_id),
            KEY state (state),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);

        // Create the state history table
        $history_table_name = $wpdb->prefix . OrderSyncStateManager::TABLE_NAME . '_history';
        $sql = "CREATE TABLE {$history_table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id varchar(100) NOT NULL,
            faire_order_id varchar(100) NOT NULL,
            state varchar(50) NOT NULL,
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY faire_order_id (faire_order_id),
            KEY state (state),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);

        // Update the database version
        update_option(self::DB_VERSION_KEY, self::CURRENT_VERSION);
    }

    /**
     * Uninstall database tables and options
     *
     * @return void
     */
    public static function uninstall() {
        global $wpdb;

        // Drop the tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}" . OrderSyncStateManager::TABLE_NAME);
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}" . OrderSyncStateManager::TABLE_NAME . '_history');

        // Delete the version option
        delete_option(self::DB_VERSION_KEY);
    }
} 