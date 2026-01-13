<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Plugin activation and deactivation handler.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Activator
 *
 * Handles plugin activation and deactivation tasks.
 */
class UCP_WC_Activator {

    /**
     * Database table name for UCP sessions.
     *
     * @var string
     */
    const SESSIONS_TABLE = 'ucp_sessions';

    /**
     * Database table name for UCP carts.
     *
     * @var string
     */
    const CARTS_TABLE = 'ucp_carts';

    /**
     * Database table name for UCP API keys.
     *
     * @var string
     */
    const API_KEYS_TABLE = 'ucp_api_keys';

    /**
     * Option name for signing key.
     *
     * @var string
     */
    const SIGNING_KEY_OPTION = 'ucp_wc_signing_key';

    /**
     * Option name for database version.
     *
     * @var string
     */
    const DB_VERSION_OPTION = 'ucp_wc_db_version';

    /**
     * Current database version.
     *
     * @var string
     */
    const DB_VERSION = '1.2.0';

    /**
     * Activate the plugin.
     */
    public static function activate() {
        self::create_tables();
        self::generate_signing_key();
        self::set_default_options();
        self::flush_rewrite_rules();
    }

    /**
     * Deactivate the plugin.
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Sessions table.
        $sessions_table = $wpdb->prefix . self::SESSIONS_TABLE;
        $sessions_sql   = "CREATE TABLE {$sessions_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) UNIQUE NOT NULL,
            wc_order_id BIGINT UNSIGNED NULL,
            status VARCHAR(32) DEFAULT 'pending',
            cart_data LONGTEXT,
            shipping_data LONGTEXT,
            customer_data LONGTEXT,
            next_action VARCHAR(64),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME,
            INDEX idx_status (status),
            INDEX idx_wc_order_id (wc_order_id),
            INDEX idx_expires_at (expires_at)
        ) {$charset_collate};";

        // Carts table.
        $carts_table = $wpdb->prefix . self::CARTS_TABLE;
        $carts_sql   = "CREATE TABLE {$carts_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cart_id VARCHAR(64) UNIQUE NOT NULL,
            items LONGTEXT,
            metadata LONGTEXT,
            status VARCHAR(32) DEFAULT 'active',
            checkout_session_id VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME,
            INDEX idx_status (status),
            INDEX idx_checkout_session_id (checkout_session_id),
            INDEX idx_expires_at (expires_at)
        ) {$charset_collate};";

        // API keys table.
        $api_keys_table = $wpdb->prefix . self::API_KEYS_TABLE;
        $api_keys_sql   = "CREATE TABLE {$api_keys_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            key_id VARCHAR(50) UNIQUE NOT NULL,
            secret_hash VARCHAR(255) NOT NULL,
            description VARCHAR(255),
            permissions TEXT,
            user_id BIGINT UNSIGNED DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            status VARCHAR(20) DEFAULT 'active',
            INDEX idx_key_id (key_id),
            INDEX idx_status (status),
            INDEX idx_user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sessions_sql );
        dbDelta( $carts_sql );
        dbDelta( $api_keys_sql );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Generate a signing key for webhook signatures.
     */
    private static function generate_signing_key() {
        $existing_key = get_option( self::SIGNING_KEY_OPTION );

        if ( empty( $existing_key ) ) {
            $signing_key = wp_generate_password( 64, false, false );
            update_option( self::SIGNING_KEY_OPTION, $signing_key );
        }
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $defaults = array(
            'ucp_wc_enabled'        => 'yes',
            'ucp_wc_webhook_url'    => '',
            'ucp_wc_debug_logging'  => 'no',
            'ucp_wc_guest_checkout' => 'yes',
        );

        foreach ( $defaults as $option => $value ) {
            if ( get_option( $option ) === false ) {
                add_option( $option, $value );
            }
        }
    }

    /**
     * Flush rewrite rules to register /.well-known/ucp endpoint.
     */
    private static function flush_rewrite_rules() {
        // Register the rewrite rules first
        require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-well-known.php';
        $well_known = new UCP_WC_Well_Known();
        $well_known->register_rewrite_rules();

        flush_rewrite_rules();
    }

    /**
     * Get the signing key.
     *
     * @return string
     */
    public static function get_signing_key() {
        return get_option( self::SIGNING_KEY_OPTION, '' );
    }

    /**
     * Rotate the signing key.
     *
     * @return string The new signing key.
     */
    public static function rotate_signing_key() {
        $new_key = wp_generate_password( 64, false, false );
        update_option( self::SIGNING_KEY_OPTION, $new_key );
        return $new_key;
    }

    /**
     * Get the sessions table name.
     *
     * @return string
     */
    public static function get_sessions_table() {
        global $wpdb;
        return $wpdb->prefix . self::SESSIONS_TABLE;
    }

    /**
     * Get the carts table name.
     *
     * @return string
     */
    public static function get_carts_table() {
        global $wpdb;
        return $wpdb->prefix . self::CARTS_TABLE;
    }

    /**
     * Get the API keys table name.
     *
     * @return string
     */
    public static function get_api_keys_table() {
        global $wpdb;
        return $wpdb->prefix . self::API_KEYS_TABLE;
    }

    /**
     * Check if tables need upgrade.
     *
     * @return bool
     */
    public static function needs_upgrade() {
        $current_version = get_option( self::DB_VERSION_OPTION, '0' );
        return version_compare( $current_version, self::DB_VERSION, '<' );
    }

    /**
     * Run database upgrade if needed.
     */
    public static function maybe_upgrade() {
        if ( self::needs_upgrade() ) {
            self::create_tables();
        }
    }
}
