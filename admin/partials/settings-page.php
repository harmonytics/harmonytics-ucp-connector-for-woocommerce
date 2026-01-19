<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Admin settings page template.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

$discovery_url  = UCP_WC_Admin::get_discovery_url();
$key_info       = UCP_WC_Admin::get_key_info();
$failed_count   = UCP_WC_Admin::get_failed_webhooks_count();
?>

<div class="wrap ucp-settings">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <!-- Status Panel -->
    <div class="ucp-status-panel">
        <h2><?php esc_html_e( 'UCP Status', 'harmonytics-ucp-connector-woocommerce' ); ?></h2>

        <table class="widefat ucp-status-table">
            <tbody>
                <tr>
                    <th><?php esc_html_e( 'Plugin Version', 'harmonytics-ucp-connector-woocommerce' ); ?></th>
                    <td><?php echo esc_html( UCP_WC_VERSION ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Discovery URL', 'harmonytics-ucp-connector-woocommerce' ); ?></th>
                    <td>
                        <code><?php echo esc_html( $discovery_url ); ?></code>
                        <a href="<?php echo esc_url( $discovery_url ); ?>" target="_blank" class="button button-small">
                            <?php esc_html_e( 'View', 'harmonytics-ucp-connector-woocommerce' ); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Signing Key', 'harmonytics-ucp-connector-woocommerce' ); ?></th>
                    <td>
                        <?php if ( $key_info['exists'] ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                            <?php esc_html_e( 'Active', 'harmonytics-ucp-connector-woocommerce' ); ?>
                            <br>
                            <small>
                                <?php esc_html_e( 'Key ID:', 'harmonytics-ucp-connector-woocommerce' ); ?>
                                <code><?php echo esc_html( $key_info['key_id'] ); ?></code>
                            </small>
                            <br>
                            <button type="button" class="button button-small" id="ucp-rotate-key">
                                <?php esc_html_e( 'Rotate Key', 'harmonytics-ucp-connector-woocommerce' ); ?>
                            </button>
                        <?php else : ?>
                            <span class="dashicons dashicons-warning" style="color: orange;"></span>
                            <?php esc_html_e( 'Not configured', 'harmonytics-ucp-connector-woocommerce' ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Failed Webhooks', 'harmonytics-ucp-connector-woocommerce' ); ?></th>
                    <td>
                        <?php if ( $failed_count > 0 ) : ?>
                            <span class="dashicons dashicons-warning" style="color: orange;"></span>
                            <?php
                            printf(
                                /* translators: %d: number of failed webhooks */
                                esc_html( _n( '%d failed webhook', '%d failed webhooks', $failed_count, 'harmonytics-ucp-connector-woocommerce' ) ),
                                intval( $failed_count )
                            );
                            ?>
                            <button type="button" class="button button-small" id="ucp-retry-failed">
                                <?php esc_html_e( 'Retry All', 'harmonytics-ucp-connector-woocommerce' ); ?>
                            </button>
                        <?php else : ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                            <?php esc_html_e( 'No failed webhooks', 'harmonytics-ucp-connector-woocommerce' ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Settings Form -->
    <form method="post" action="options.php">
        <?php
        settings_fields( UCP_WC_Admin::OPTION_GROUP );
        do_settings_sections( UCP_WC_Admin::PAGE_SLUG );
        submit_button();
        ?>
    </form>

    <!-- Test Webhook -->
    <div class="ucp-test-section">
        <h2><?php esc_html_e( 'Test Webhook', 'harmonytics-ucp-connector-woocommerce' ); ?></h2>
        <p><?php esc_html_e( 'Send a test webhook to verify your integration.', 'harmonytics-ucp-connector-woocommerce' ); ?></p>
        <button type="button" class="button" id="ucp-test-webhook">
            <?php esc_html_e( 'Send Test Webhook', 'harmonytics-ucp-connector-woocommerce' ); ?>
        </button>
        <span id="ucp-test-result"></span>
    </div>

    <!-- Documentation -->
    <div class="ucp-docs-section">
        <h2><?php esc_html_e( 'Documentation', 'harmonytics-ucp-connector-woocommerce' ); ?></h2>
        <p>
            <?php esc_html_e( 'Learn more about the Universal Commerce Protocol:', 'harmonytics-ucp-connector-woocommerce' ); ?>
        </p>
        <ul>
            <li>
                <a href="https://ucp.dev/specification/overview/" target="_blank">
                    <?php esc_html_e( 'UCP Specification', 'harmonytics-ucp-connector-woocommerce' ); ?>
                </a>
            </li>
            <li>
                <a href="https://ucp.dev/specification/checkout-rest/" target="_blank">
                    <?php esc_html_e( 'Checkout REST Binding', 'harmonytics-ucp-connector-woocommerce' ); ?>
                </a>
            </li>
            <li>
                <a href="https://ucp.dev/specification/order/" target="_blank">
                    <?php esc_html_e( 'Order Capability', 'harmonytics-ucp-connector-woocommerce' ); ?>
                </a>
            </li>
        </ul>
    </div>
</div>
