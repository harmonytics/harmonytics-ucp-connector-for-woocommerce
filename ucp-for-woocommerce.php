<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Plugin Name: UCP for WooCommerce
 * Plugin URI: https://github.com/harmonytics/ucp-for-woocommerce
 * Description: Adds Universal Commerce Protocol (UCP) capabilities to WooCommerce, enabling AI agents to discover, browse, and complete purchases.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Harmonytics
 * Author URI: https://harmonytics.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ucp-for-woocommerce
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 10.4.3
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'UCP_WC_VERSION', '1.0.0' );
define( 'UCP_WC_PLUGIN_FILE', __FILE__ );
define( 'UCP_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UCP_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UCP_WC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function ucp_wc_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function ucp_wc_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'WooCommerce UCP requires WooCommerce to be installed and active.', 'ucp-for-woocommerce' ); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function ucp_wc_init() {
    // Check WooCommerce dependency
    if ( ! ucp_wc_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'ucp_wc_woocommerce_missing_notice' );
        return;
    }

    // Load Composer autoloader if exists
    $autoloader = UCP_WC_PLUGIN_DIR . 'vendor/autoload.php';
    if ( file_exists( $autoloader ) ) {
        require_once $autoloader;
    }

    // Load plugin classes
    require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-loader.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-well-known.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-auth.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-rest-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-auth-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-checkout-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-order-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-product-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-category-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-cart-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-shipping-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-coupon-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-customer-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-review-controller.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/capabilities/class-ucp-checkout.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/capabilities/class-ucp-order.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/capabilities/class-ucp-cart.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-line-item-mapper.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-address-mapper.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-shipping-mapper.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-order-mapper.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-product-mapper.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-category-mapper.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-coupon-mapper.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-customer-mapper.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-review-mapper.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/webhooks/class-ucp-woo-hooks.php';
    require_once UCP_WC_PLUGIN_DIR . 'includes/webhooks/class-ucp-webhook-sender.php';

    // Initialize the loader
    $loader = new UCP_WC_Loader();
    $loader->run();
}
add_action( 'plugins_loaded', 'ucp_wc_init' );

/**
 * Plugin activation hook
 */
function ucp_wc_activate() {
    require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
    UCP_WC_Activator::activate();
}
register_activation_hook( __FILE__, 'ucp_wc_activate' );

/**
 * Plugin deactivation hook
 */
function ucp_wc_deactivate() {
    require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
    UCP_WC_Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'ucp_wc_deactivate' );

/**
 * Load admin functionality
 */
function ucp_wc_admin_init() {
    if ( is_admin() ) {
        require_once UCP_WC_PLUGIN_DIR . 'admin/class-ucp-admin.php';
        new UCP_WC_Admin();
    }
}
add_action( 'plugins_loaded', 'ucp_wc_admin_init', 20 );
