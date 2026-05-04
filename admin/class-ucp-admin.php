<?php
/**
 * Admin functionality for Harmonytics UCP Connector.
 *
 * @package Harmonytics_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class HUCP_Admin
 *
 * Handles admin settings and pages.
 */
class HUCP_Admin {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'harmonytics-ucp-connector-for-woocommerce';

	/**
	 * Settings option group.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'hucp_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_hucp_rotate_key', array( $this, 'ajax_rotate_key' ) );
		add_action( 'wp_ajax_hucp_test_webhook', array( $this, 'ajax_test_webhook' ) );
		add_action( 'wp_ajax_hucp_retry_failed', array( $this, 'ajax_retry_failed' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'UCP Settings', 'harmonytics-ucp-connector-for-woocommerce' ),
			__( 'UCP', 'harmonytics-ucp-connector-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// General settings section.
		add_settings_section(
			'hucp_general',
			__( 'General Settings', 'harmonytics-ucp-connector-for-woocommerce' ),
			array( $this, 'render_general_section' ),
			self::PAGE_SLUG
		);

		// Enable/disable.
		register_setting(
			self::OPTION_GROUP,
			'hucp_enabled',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 'yes',
			)
		);
		add_settings_field(
			'hucp_enabled',
			__( 'Enable UCP', 'harmonytics-ucp-connector-for-woocommerce' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'hucp_general',
			array(
				'name'        => 'hucp_enabled',
				'label'       => __( 'Enable Universal Commerce Protocol integration', 'harmonytics-ucp-connector-for-woocommerce' ),
				'description' => __( 'When enabled, your store will be discoverable by AI agents.', 'harmonytics-ucp-connector-for-woocommerce' ),
			)
		);

		// Guest checkout.
		register_setting(
			self::OPTION_GROUP,
			'hucp_guest_checkout',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 'no',
			)
		);
		add_settings_field(
			'hucp_guest_checkout',
			__( 'Guest Checkout', 'harmonytics-ucp-connector-for-woocommerce' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'hucp_general',
			array(
				'name'        => 'hucp_guest_checkout',
				'label'       => __( 'Allow guest checkout via UCP', 'harmonytics-ucp-connector-for-woocommerce' ),
				'description' => __( 'Required for agent purchases without identity linking.', 'harmonytics-ucp-connector-for-woocommerce' ),
			)
		);

		// Webhook settings section.
		add_settings_section(
			'hucp_webhooks',
			__( 'Webhook Settings', 'harmonytics-ucp-connector-for-woocommerce' ),
			array( $this, 'render_webhooks_section' ),
			self::PAGE_SLUG
		);

		// Webhook URL.
		register_setting(
			self::OPTION_GROUP,
			'hucp_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_webhook_url' ),
				'default'           => '',
			)
		);
		add_settings_field(
			'hucp_webhook_url',
			__( 'Webhook URL', 'harmonytics-ucp-connector-for-woocommerce' ),
			array( $this, 'render_webhook_url_field' ),
			self::PAGE_SLUG,
			'hucp_webhooks'
		);

		// Debug settings section.
		add_settings_section(
			'hucp_debug',
			__( 'Debug Settings', 'harmonytics-ucp-connector-for-woocommerce' ),
			array( $this, 'render_debug_section' ),
			self::PAGE_SLUG
		);

		// Debug logging.
		register_setting(
			self::OPTION_GROUP,
			'hucp_debug_logging',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 'no',
			)
		);
		add_settings_field(
			'hucp_debug_logging',
			__( 'Debug Logging', 'harmonytics-ucp-connector-for-woocommerce' ),
			array( $this, 'render_checkbox_field' ),
			self::PAGE_SLUG,
			'hucp_debug',
			array(
				'name'        => 'hucp_debug_logging',
				'label'       => __( 'Enable debug logging', 'harmonytics-ucp-connector-for-woocommerce' ),
				'description' => __( 'Log UCP requests and responses to the error log.', 'harmonytics-ucp-connector-for-woocommerce' ),
			)
		);
	}

	/**
	 * Sanitize checkbox field value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public function sanitize_checkbox( $value ) {
		return ( 'yes' === $value ) ? 'yes' : 'no';
	}

	/**
	 * Sanitize webhook URL field value.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public function sanitize_webhook_url( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$url = esc_url_raw( $value );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			add_settings_error(
				'hucp_webhook_url',
				'invalid_url',
				__( 'Please enter a valid webhook URL.', 'harmonytics-ucp-connector-for-woocommerce' )
			);
			return get_option( 'hucp_webhook_url', '' );
		}
		$parsed = wp_parse_url( $url );
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			if ( empty( $parsed['scheme'] ) || 'https' !== $parsed['scheme'] ) {
				add_settings_error(
					'hucp_webhook_url',
					'not_https',
					__( 'Webhook URL must use HTTPS for secure payload delivery.', 'harmonytics-ucp-connector-for-woocommerce' )
				);
				return get_option( 'hucp_webhook_url', '' );
			}
		}
		return $url;
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'hucp-admin',
			HUCP_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			HUCP_VERSION
		);

		wp_enqueue_script(
			'hucp-admin',
			HUCP_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			HUCP_VERSION,
			true
		);

		wp_localize_script(
			'hucp-admin',
			'hucp_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'hucp_admin_nonce' ),
				'strings'  => array(
					'confirm_rotate' => __( 'Are you sure you want to rotate the signing key? This will invalidate any existing webhook integrations.', 'harmonytics-ucp-connector-for-woocommerce' ),
					'rotating'       => __( 'Rotating...', 'harmonytics-ucp-connector-for-woocommerce' ),
					'testing'        => __( 'Testing...', 'harmonytics-ucp-connector-for-woocommerce' ),
					'retrying'       => __( 'Retrying...', 'harmonytics-ucp-connector-for-woocommerce' ),
					'success'        => __( 'Success!', 'harmonytics-ucp-connector-for-woocommerce' ),
					'error'          => __( 'Error:', 'harmonytics-ucp-connector-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'harmonytics-ucp-connector-for-woocommerce' ) );
		}

		include HUCP_PLUGIN_DIR . 'admin/partials/settings-page.php';
	}

	/**
	 * Render general section description.
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure general UCP settings for your store.', 'harmonytics-ucp-connector-for-woocommerce' ) . '</p>';
	}

	/**
	 * Render webhooks section description.
	 */
	public function render_webhooks_section() {
		?>
		<div class="ucp-section-description">
			<p><?php esc_html_e( 'Webhooks allow your store to send real-time order event notifications to external platforms.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></p>
			<p><strong><?php esc_html_e( 'This is optional.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Leave blank if you only need the checkout and order REST APIs.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render debug section description.
	 */
	public function render_debug_section() {
		echo '<p>' . esc_html__( 'Debug and troubleshooting options.', 'harmonytics-ucp-connector-for-woocommerce' ) . '</p>';
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$value = get_option( $args['name'], 'no' );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $args['name'] ); ?>" value="yes" <?php checked( $value, 'yes' ); ?> />
			<?php echo esc_html( $args['label'] ); ?>
		</label>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$value = get_option( $args['name'], '' );
		$type  = $args['type'] ?? 'text';
		?>
		<input
			type="<?php echo esc_attr( $type ); ?>"
			name="<?php echo esc_attr( $args['name'] ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="<?php echo esc_attr( $args['placeholder'] ?? '' ); ?>"
		/>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render webhook URL field with detailed explanation.
	 */
	public function render_webhook_url_field() {
		$value = get_option( 'hucp_webhook_url', '' );
		?>
		<input
			type="url"
			name="hucp_webhook_url"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="https://your-platform.com/webhooks/ucp"
		/>
		<p class="description">
			<?php esc_html_e( 'The external URL where order events will be sent via HTTP POST.', 'harmonytics-ucp-connector-for-woocommerce' ); ?>
		</p>

		<div class="ucp-webhook-help">
			<h4><?php esc_html_e( 'Who provides this URL?', 'harmonytics-ucp-connector-for-woocommerce' ); ?></h4>
			<ul>
				<li><strong><?php esc_html_e( 'AI Agent Platform:', 'harmonytics-ucp-connector-for-woocommerce' ); ?></strong> <?php esc_html_e( 'The platform integrating with your store provides a callback URL to receive order updates.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></li>
				<li><strong><?php esc_html_e( 'Your Backend:', 'harmonytics-ucp-connector-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Create an endpoint on your own server to process order events.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></li>
				<li><strong><?php esc_html_e( 'Integration Services:', 'harmonytics-ucp-connector-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Use services like Zapier, Make, or n8n to receive webhooks.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></li>
				<li><strong><?php esc_html_e( 'Testing:', 'harmonytics-ucp-connector-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Use webhook.site or requestbin.com to inspect webhook payloads during development.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></li>
			</ul>

			<h4><?php esc_html_e( 'Events sent to this URL:', 'harmonytics-ucp-connector-for-woocommerce' ); ?></h4>
			<ul>
				<li><code>order.created</code> - <?php esc_html_e( 'When a new order is placed via UCP', 'harmonytics-ucp-connector-for-woocommerce' ); ?></li>
				<li><code>order.status_changed</code> - <?php esc_html_e( 'When order status changes (e.g., pending to processing)', 'harmonytics-ucp-connector-for-woocommerce' ); ?></li>
				<li><code>order.paid</code> - <?php esc_html_e( 'When payment is completed', 'harmonytics-ucp-connector-for-woocommerce' ); ?></li>
				<li><code>order.refunded</code> - <?php esc_html_e( 'When a refund is issued', 'harmonytics-ucp-connector-for-woocommerce' ); ?></li>
			</ul>

			<p><em><?php esc_html_e( 'All webhooks are signed with HMAC-SHA256 for security. The signing key is shown in the Status panel above.', 'harmonytics-ucp-connector-for-woocommerce' ); ?></em></p>
		</div>
		<?php
	}

	/**
	 * AJAX: Rotate signing key.
	 */
	public function ajax_rotate_key() {
		check_ajax_referer( 'hucp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'harmonytics-ucp-connector-for-woocommerce' ) ) );
		}

		$new_key = HUCP_Activator::rotate_signing_key();
		update_option( 'hucp_key_created_at', current_time( 'c' ) );

		wp_send_json_success(
			array(
				'message' => __( 'Signing key rotated successfully.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'key_id'  => substr( hash( 'sha256', $new_key ), 0, 16 ),
			)
		);
	}

	/**
	 * AJAX: Test webhook.
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'hucp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'harmonytics-ucp-connector-for-woocommerce' ) ) );
		}

		$webhook_url = get_option( 'hucp_webhook_url' );

		if ( empty( $webhook_url ) ) {
			wp_send_json_error( array( 'message' => __( 'No webhook URL configured.', 'harmonytics-ucp-connector-for-woocommerce' ) ) );
		}

		$sender = new HUCP_Webhook_Sender();
		$result = $sender->send(
			array(
				'event_type' => 'test',
				'timestamp'  => current_time( 'c' ),
				'data'       => array(
					'message' => 'This is a test webhook from Harmonytics UCP Connector.',
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Test webhook sent successfully.', 'harmonytics-ucp-connector-for-woocommerce' ) ) );
	}

	/**
	 * AJAX: Retry failed webhooks.
	 */
	public function ajax_retry_failed() {
		check_ajax_referer( 'hucp_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'harmonytics-ucp-connector-for-woocommerce' ) ) );
		}

		$sender  = new HUCP_Webhook_Sender();
		$results = $sender->retry_failed_webhooks();

		$success_count = count(
			array_filter(
				$results,
				function ( $r ) {
					return ! empty( $r['success'] );
				}
			)
		);
		$failed_count  = count( $results ) - $success_count;

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: success count, 2: failed count */
					__( 'Retry complete. %1$d succeeded, %2$d failed.', 'harmonytics-ucp-connector-for-woocommerce' ),
					$success_count,
					$failed_count
				),
				'results' => $results,
			)
		);
	}

	/**
	 * Get discovery URL.
	 *
	 * @return string
	 */
	public static function get_discovery_url() {
		return home_url( '/.well-known/ucp' );
	}

	/**
	 * Get key info.
	 *
	 * @return array
	 */
	public static function get_key_info() {
		$signing_key = HUCP_Activator::get_signing_key();

		if ( empty( $signing_key ) ) {
			return array(
				'exists'     => false,
				'key_id'     => null,
				'created_at' => null,
			);
		}

		return array(
			'exists'     => true,
			'key_id'     => substr( hash( 'sha256', $signing_key ), 0, 16 ),
			'created_at' => get_option( 'hucp_key_created_at', __( 'Unknown', 'harmonytics-ucp-connector-for-woocommerce' ) ),
		);
	}

	/**
	 * Get failed webhooks count.
	 *
	 * @return int
	 */
	public static function get_failed_webhooks_count() {
		$failed = get_option( 'hucp_failed_webhooks', array() );
		return count( $failed );
	}
}
