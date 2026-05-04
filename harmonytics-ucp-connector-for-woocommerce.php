<?php
/**
 * Plugin Name: Harmonytics UCP Connector for WooCommerce
 * Plugin URI: https://github.com/harmonytics/harmonytics-ucp-connector-for-woocommerce
 * Description: Adds Universal Commerce Protocol (UCP) capabilities to WooCommerce, enabling AI agents to discover, browse, and complete purchases.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Harmonytics
 * Author URI: https://harmonytics.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: harmonytics-ucp-connector-for-woocommerce
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 10.4.3
 *
 * @package Harmonytics_UCP
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

// Plugin constants.
define( 'HUCP_VERSION', '1.0.0' );
define( 'HUCP_PLUGIN_FILE', __FILE__ );
define( 'HUCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HUCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HUCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function hucp_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function hucp_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Harmonytics UCP Connector requires WooCommerce to be installed and active.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></p>
	</div>
	<?php
}

/**
 * Initialize the plugin
 */
function hucp_init() {
	// Check WooCommerce dependency.
	if ( ! hucp_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'hucp_woocommerce_missing_notice' );
		return;
	}

	// Load Composer autoloader if exists.
	$autoloader = HUCP_PLUGIN_DIR . 'vendor/autoload.php';
	if ( file_exists( $autoloader ) ) {
		require_once $autoloader;
	}

	// Load plugin classes.
	require_once HUCP_PLUGIN_DIR . 'includes/class-ucp-loader.php';
	require_once HUCP_PLUGIN_DIR . 'includes/class-ucp-activator.php';
	require_once HUCP_PLUGIN_DIR . 'includes/class-ucp-well-known.php';
	require_once HUCP_PLUGIN_DIR . 'includes/class-ucp-auth.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-rest-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-auth-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-checkout-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-order-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-product-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-category-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-cart-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-shipping-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-coupon-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-customer-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/rest/class-ucp-review-controller.php';
	require_once HUCP_PLUGIN_DIR . 'includes/capabilities/class-ucp-checkout.php';
	require_once HUCP_PLUGIN_DIR . 'includes/capabilities/class-ucp-order.php';
	require_once HUCP_PLUGIN_DIR . 'includes/capabilities/class-ucp-cart.php';
	require_once HUCP_PLUGIN_DIR . 'includes/mapping/class-ucp-line-item-mapper.php';
	require_once HUCP_PLUGIN_DIR . 'includes/mapping/class-ucp-address-mapper.php';
	require_once HUCP_PLUGIN_DIR . 'includes/mapping/class-ucp-shipping-mapper.php';
	require_once HUCP_PLUGIN_DIR . 'includes/mapping/class-ucp-order-mapper.php';
	require_once HUCP_PLUGIN_DIR . 'includes/mapping/class-ucp-product-mapper.php';
	require_once HUCP_PLUGIN_DIR . 'includes/mapping/class-ucp-category-mapper.php';
	require_once HUCP_PLUGIN_DIR . 'includes/mapping/class-ucp-coupon-mapper.php';
	require_once HUCP_PLUGIN_DIR . 'includes/mapping/class-ucp-customer-mapper.php';
	require_once HUCP_PLUGIN_DIR . 'includes/mapping/class-ucp-review-mapper.php';
	require_once HUCP_PLUGIN_DIR . 'includes/webhooks/class-ucp-woo-hooks.php';
	require_once HUCP_PLUGIN_DIR . 'includes/webhooks/class-ucp-webhook-sender.php';

	// Initialize the loader.
	$loader = new HUCP_Loader();
	$loader->run();
}
add_action( 'plugins_loaded', 'hucp_init' );

/**
 * Declare WooCommerce feature compatibility.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', __FILE__, true );
	}
} );

/**
 * Plugin activation hook
 */
function hucp_activate() {
	require_once HUCP_PLUGIN_DIR . 'includes/class-ucp-activator.php';
	HUCP_Activator::activate();
}
register_activation_hook( __FILE__, 'hucp_activate' );

/**
 * Plugin deactivation hook
 */
function hucp_deactivate() {
	require_once HUCP_PLUGIN_DIR . 'includes/class-ucp-activator.php';
	HUCP_Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'hucp_deactivate' );

/**
 * Load admin functionality
 */
function hucp_admin_init() {
	if ( is_admin() ) {
		require_once HUCP_PLUGIN_DIR . 'admin/class-ucp-admin.php';
		new HUCP_Admin();
	}
}
add_action( 'plugins_loaded', 'hucp_admin_init', 20 );
