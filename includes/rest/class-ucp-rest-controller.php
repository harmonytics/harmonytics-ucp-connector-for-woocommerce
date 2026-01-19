<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Base REST controller for UCP endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_REST_Controller
 *
 * Base controller class for UCP REST API endpoints.
 */
abstract class UCP_WC_REST_Controller extends WP_REST_Controller {

    /**
     * Namespace for REST routes.
     *
     * @var string
     */
    protected $namespace = 'ucp/v1';

    /**
     * Check if UCP is enabled.
     *
     * @return bool
     */
    protected function is_ucp_enabled() {
        return get_option( 'ucp_wc_enabled', 'yes' ) === 'yes';
    }

    /**
     * Permission callback for read endpoints.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_read_permission( $request ) {
        if ( ! $this->is_ucp_enabled() ) {
            return new WP_Error(
                'ucp_disabled',
                __( 'UCP is currently disabled for this store.', 'harmonytics-ucp-connector-woocommerce' ),
                array( 'status' => 503 )
            );
        }
        return true;
    }

    /**
     * Permission callback for write endpoints.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public function check_write_permission( $request ) {
        if ( ! $this->is_ucp_enabled() ) {
            return new WP_Error(
                'ucp_disabled',
                __( 'UCP is currently disabled for this store.', 'harmonytics-ucp-connector-woocommerce' ),
                array( 'status' => 503 )
            );
        }
        return true;
    }

    /**
     * Check if the current request is authenticated via API key or WordPress user.
     *
     * @return bool
     */
    protected function is_authenticated() {
        // Check if authenticated via API key.
        if ( class_exists( 'UCP_WC_Auth' ) && UCP_WC_Auth::is_api_key_authenticated() ) {
            return true;
        }

        // Check if authenticated as WordPress user.
        return is_user_logged_in();
    }

    /**
     * Check if the current request has the required permission level.
     *
     * @param string $permission Permission level to check (read, write, admin).
     * @return bool
     */
    protected function has_permission( $permission ) {
        if ( class_exists( 'UCP_WC_Auth' ) ) {
            return UCP_WC_Auth::check_permission( $permission );
        }

        // Fallback: check WordPress capabilities.
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Get the current authenticated API key info.
     *
     * @return array|null
     */
    protected function get_current_api_key() {
        if ( class_exists( 'UCP_WC_Auth' ) ) {
            return UCP_WC_Auth::get_current_api_key();
        }
        return null;
    }

    /**
     * Generate a unique session ID.
     *
     * @return string
     */
    protected function generate_session_id() {
        return 'ucp_' . bin2hex( random_bytes( 16 ) );
    }

    /**
     * Validate session ID format.
     *
     * @param string $session_id Session ID to validate.
     * @return bool
     */
    protected function is_valid_session_id( $session_id ) {
        return preg_match( '/^ucp_[a-f0-9]{32}$/', $session_id ) === 1;
    }

    /**
     * Create error response.
     *
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     * @return WP_Error
     */
    protected function error_response( $code, $message, $status = 400 ) {
        return new WP_Error( $code, $message, array( 'status' => $status ) );
    }

    /**
     * Create success response.
     *
     * @param array $data   Response data.
     * @param int   $status HTTP status code.
     * @return WP_REST_Response
     */
    protected function success_response( $data, $status = 200 ) {
        return new WP_REST_Response( $data, $status );
    }

    /**
     * Log debug message if debug logging is enabled.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context.
     */
    protected function log( $message, $context = array() ) {
        if ( get_option( 'ucp_wc_debug_logging', 'no' ) === 'yes' && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                $log_message = sprintf(
                    '[UCP] %s | Context: %s',
                    $message,
                    wp_json_encode( $context )
                );
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG_LOG is enabled.
                error_log( $log_message );
            }
        }
    }

    /**
     * Get IP address of the requester.
     *
     * @return string
     */
    protected function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $ip  = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return $ip;
    }
}
