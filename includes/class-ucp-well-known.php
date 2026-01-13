<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Handler for /.well-known/ucp discovery endpoint.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Well_Known
 *
 * Handles the /.well-known/ucp endpoint for UCP discovery.
 */
class UCP_WC_Well_Known {

    /**
     * Query variable for the well-known endpoint.
     *
     * @var string
     */
    const QUERY_VAR = 'ucp_well_known';

    /**
     * Register rewrite rules for /.well-known/ucp
     */
    public function register_rewrite_rules() {
        add_rewrite_rule(
            '^\.well-known/ucp/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    /**
     * Add query variables.
     *
     * @param array $vars Existing query variables.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * Handle the /.well-known/ucp request.
     *
     * @param WP $wp Current WordPress environment instance.
     */
    public function handle_request( $wp ) {
        if ( ! isset( $wp->query_vars[ self::QUERY_VAR ] ) ) {
            return;
        }

        // Check if UCP is enabled
        if ( get_option( 'ucp_wc_enabled', 'yes' ) !== 'yes' ) {
            status_header( 503 );
            header( 'Content-Type: application/json; charset=utf-8' );
            echo wp_json_encode(
                array(
                    'error'   => 'service_unavailable',
                    'message' => 'UCP is currently disabled for this store.',
                )
            );
            exit;
        }

        // Set proper headers
        status_header( 200 );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Cache-Control: public, max-age=3600' );
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type' );

        // Handle preflight requests
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
            exit;
        }

        echo wp_json_encode( $this->get_business_profile(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    /**
     * Get the business profile data.
     *
     * @return array
     */
    public function get_business_profile() {
        $site_url = home_url();
        $rest_url = rest_url( 'ucp/v1' );

        return array(
            'schema_version' => '1.0',
            'business'       => $this->get_business_info(),
            'capabilities'   => $this->get_capabilities( $rest_url ),
            'policies'       => $this->get_policies(),
            'signing_keys'   => $this->get_signing_keys(),
            'metadata'       => array(
                'platform'         => 'WooCommerce',
                'platform_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
                'plugin_version'   => UCP_WC_VERSION,
                'updated_at'       => gmdate( 'c' ),
            ),
        );
    }

    /**
     * Get business information.
     *
     * @return array
     */
    private function get_business_info() {
        $business = array(
            'name'        => get_bloginfo( 'name' ),
            'description' => get_bloginfo( 'description' ),
            'url'         => home_url(),
        );

        // Add logo if custom logo is set
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
            if ( $logo_url ) {
                $business['logo'] = $logo_url;
            }
        }

        // Add contact email if available
        $admin_email = get_option( 'admin_email' );
        if ( $admin_email ) {
            $business['contact_email'] = $admin_email;
        }

        // Add WooCommerce store details
        if ( function_exists( 'wc_get_base_location' ) ) {
            $location                  = wc_get_base_location();
            $business['country']       = $location['country'] ?? '';
            $business['currency']      = get_woocommerce_currency();
            $business['currency_symbol'] = get_woocommerce_currency_symbol();
        }

        return $business;
    }

    /**
     * Get capabilities and their REST endpoints.
     *
     * @param string $rest_url Base REST URL.
     * @return array
     */
    private function get_capabilities( $rest_url ) {
        $capabilities = array();

        // Checkout capability
        $capabilities['checkout'] = array(
            'enabled'  => true,
            'version'  => '1.0',
            'rest'     => array(
                'endpoint' => $rest_url . '/checkout',
                'methods'  => array(
                    array(
                        'action'   => 'create_session',
                        'method'   => 'POST',
                        'path'     => '/sessions',
                    ),
                    array(
                        'action'   => 'get_session',
                        'method'   => 'GET',
                        'path'     => '/sessions/{session_id}',
                    ),
                    array(
                        'action'   => 'confirm_session',
                        'method'   => 'POST',
                        'path'     => '/sessions/{session_id}/confirm',
                    ),
                ),
            ),
            'features' => array(
                'guest_checkout'    => get_option( 'ucp_wc_guest_checkout', 'yes' ) === 'yes',
                'shipping_options'  => true,
                'coupon_support'    => true,
                'tax_calculation'   => true,
                'web_checkout_fallback' => true,
            ),
        );

        // Order capability
        $capabilities['order'] = array(
            'enabled'  => true,
            'version'  => '1.0',
            'rest'     => array(
                'endpoint' => $rest_url . '/orders',
                'methods'  => array(
                    array(
                        'action'   => 'get_order',
                        'method'   => 'GET',
                        'path'     => '/{order_id}',
                    ),
                    array(
                        'action'   => 'list_orders',
                        'method'   => 'GET',
                        'path'     => '/',
                    ),
                ),
            ),
            'webhooks' => array(
                'events' => array(
                    'order.created',
                    'order.status_changed',
                    'order.paid',
                    'order.refunded',
                ),
            ),
        );

        return $capabilities;
    }

    /**
     * Get policy links.
     *
     * @return array
     */
    private function get_policies() {
        $policies = array();

        // Privacy policy
        $privacy_page_id = get_option( 'wp_page_for_privacy_policy' );
        if ( $privacy_page_id ) {
            $policies['privacy'] = get_permalink( $privacy_page_id );
        }

        // Terms and conditions (WooCommerce)
        $terms_page_id = wc_terms_and_conditions_page_id();
        if ( $terms_page_id ) {
            $policies['terms'] = get_permalink( $terms_page_id );
        }

        // Refund policy page (if using WooCommerce 4.0+)
        $refund_page_id = get_option( 'woocommerce_refund_returns_page_id' );
        if ( $refund_page_id ) {
            $policies['refund'] = get_permalink( $refund_page_id );
        }

        // Shipping policy
        $shipping_page_id = get_option( 'woocommerce_shipping_page_id' );
        if ( $shipping_page_id ) {
            $policies['shipping'] = get_permalink( $shipping_page_id );
        }

        return $policies;
    }

    /**
     * Get signing keys for webhook verification.
     *
     * @return array
     */
    private function get_signing_keys() {
        $signing_key = UCP_WC_Activator::get_signing_key();

        if ( empty( $signing_key ) ) {
            return array();
        }

        // Return the public key info for HMAC verification
        // For HMAC-SHA256, we expose a key ID and algorithm
        // The actual secret is NOT exposed - it's used for signing
        $key_id = hash( 'sha256', $signing_key );

        return array(
            array(
                'key_id'    => substr( $key_id, 0, 16 ),
                'algorithm' => 'HMAC-SHA256',
                'status'    => 'active',
                'created_at' => get_option( 'ucp_wc_key_created_at', gmdate( 'c' ) ),
            ),
        );
    }
}
