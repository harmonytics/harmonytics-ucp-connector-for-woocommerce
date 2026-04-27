<?php
/**
 * Admin settings page template.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

$hucp_discovery_url = HUCP_Admin::get_discovery_url();
$hucp_key_info      = HUCP_Admin::get_key_info();
$hucp_failed_count  = HUCP_Admin::get_failed_webhooks_count();
?>

<div class="wrap ucp-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Status Panel -->
	<div class="ucp-status-panel">
		<h2><?php esc_html_e( 'UCP Status', 'harmonytics-ucp-connector-for-woocommerce' ); ?></h2>

		<table class="widefat ucp-status-table">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Plugin Version', 'harmonytics-ucp-connector-for-woocommerce' ); ?></th>
					<td><?php echo esc_html( HUCP_VERSION ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Discovery URL', 'harmonytics-ucp-connector-for-woocommerce' ); ?></th>
					<td>
						<code><?php echo esc_html( $hucp_discovery_url ); ?></code>
						<a href="<?php echo esc_url( $hucp_discovery_url ); ?>" target="_blank" class="button button-small">
							<?php esc_html_e( 'View', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
						</a>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Signing Key', 'harmonytics-ucp-connector-for-woocommerce' ); ?></th>
					<td>
						<?php if ( $hucp_key_info['exists'] ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
							<?php esc_html_e( 'Active', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
							<br>
							<small>
								<?php esc_html_e( 'Key ID:', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
								<code><?php echo esc_html( $hucp_key_info['key_id'] ); ?></code>
							</small>
							<br>
							<button type="button" class="button button-small" id="ucp-rotate-key">
								<?php esc_html_e( 'Rotate Key', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
							</button>
						<?php else : ?>
							<span class="dashicons dashicons-warning" style="color: orange;"></span>
							<?php esc_html_e( 'Not configured', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Failed Webhooks', 'harmonytics-ucp-connector-for-woocommerce' ); ?></th>
					<td>
						<?php if ( $hucp_failed_count > 0 ) : ?>
							<span class="dashicons dashicons-warning" style="color: orange;"></span>
							<?php
							printf(
								/* translators: %d: number of failed webhooks */
								esc_html( _n( '%d failed webhook', '%d failed webhooks', $hucp_failed_count, 'harmonytics-ucp-connector-for-woocommerce' ) ),
								intval( $hucp_failed_count )
							);
							?>
							<button type="button" class="button button-small" id="ucp-retry-failed">
								<?php esc_html_e( 'Retry All', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
							</button>
						<?php else : ?>
							<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
							<?php esc_html_e( 'No failed webhooks', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<!-- Settings Form -->
	<form method="post" action="options.php">
		<?php
		settings_fields( HUCP_Admin::OPTION_GROUP );
		do_settings_sections( HUCP_Admin::PAGE_SLUG );
		submit_button();
		?>
	</form>

	<!-- Test Webhook -->
	<div class="ucp-test-section">
		<h2><?php esc_html_e( 'Test Webhook', 'harmonytics-ucp-connector-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Send a test webhook to verify your integration.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></p>
		<button type="button" class="button" id="ucp-test-webhook">
			<?php esc_html_e( 'Send Test Webhook', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
		</button>
		<span id="ucp-test-result"></span>
	</div>

	<!-- Documentation -->
	<div class="ucp-docs-section">
		<h2><?php esc_html_e( 'Documentation', 'harmonytics-ucp-connector-for-woocommerce' ); ?></h2>
		<p>
			<?php esc_html_e( 'Learn more about the Universal Commerce Protocol:', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
		</p>
		<ul>
			<li>
				<a href="https://ucp.dev/specification/overview/" target="_blank">
					<?php esc_html_e( 'UCP Specification', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
				</a>
			</li>
			<li>
				<a href="https://ucp.dev/specification/checkout-rest/" target="_blank">
					<?php esc_html_e( 'Checkout REST Binding', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
				</a>
			</li>
			<li>
				<a href="https://ucp.dev/specification/order/" target="_blank">
					<?php esc_html_e( 'Order Capability', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
				</a>
			</li>
		</ul>
	</div>
</div>
