<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Webhook sender for UCP events.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Webhook_Sender
 *
 * Sends webhook notifications to configured endpoints.
 */
class UCP_WC_Webhook_Sender {

    /**
     * Maximum retry attempts.
     *
     * @var int
     */
    const MAX_RETRIES = 3;

    /**
     * Retry delay in seconds.
     *
     * @var int
     */
    const RETRY_DELAY = 5;

    /**
     * Request timeout in seconds.
     *
     * @var int
     */
    const TIMEOUT = 30;

    /**
     * Send a webhook event.
     *
     * @param array $event Event data.
     * @return bool|WP_Error
     */
    public function send( $event ) {
        $webhook_url = get_option( 'ucp_wc_webhook_url' );

        if ( empty( $webhook_url ) ) {
            $this->log( 'No webhook URL configured, skipping', array( 'event_type' => $event['event_type'] ) );
            return true; // Not an error, just not configured
        }

        // Validate URL
        if ( ! filter_var( $webhook_url, FILTER_VALIDATE_URL ) ) {
            $this->log( 'Invalid webhook URL', array( 'url' => $webhook_url ) );
            return new WP_Error( 'invalid_url', __( 'Invalid webhook URL configured.', 'harmonytics-ucp-connector-for-woocommerce' ) );
        }

        // Prepare payload
        $payload = $this->prepare_payload( $event );

        // Generate signature
        $signature = $this->generate_signature( $payload );

        // Send with retries
        return $this->send_with_retry( $webhook_url, $payload, $signature );
    }

    /**
     * Prepare the webhook payload.
     *
     * @param array $event Event data.
     * @return array
     */
    private function prepare_payload( $event ) {
        return array(
            'id'           => wp_generate_uuid4(),
            'event_type'   => $event['event_type'],
            'timestamp'    => $event['timestamp'] ?? current_time( 'c' ),
            'api_version'  => '1.0',
            'source'       => array(
                'platform'     => 'WooCommerce',
                'plugin'       => 'harmonytics-ucp-connector-for-woocommerce',
                'version'      => UCP_WC_VERSION,
                'site_url'     => home_url(),
            ),
            'data'         => $event['data'] ?? array(),
            'meta'         => array(
                'order_id'   => $event['order_id'] ?? null,
                'session_id' => $event['session_id'] ?? null,
            ),
        );
    }

    /**
     * Generate HMAC-SHA256 signature.
     *
     * @param array $payload Payload data.
     * @return string
     */
    private function generate_signature( $payload ) {
        $signing_key = UCP_WC_Activator::get_signing_key();

        if ( empty( $signing_key ) ) {
            return '';
        }

        $payload_json = wp_json_encode( $payload );
        $timestamp    = time();

        // Create signature message: timestamp.payload
        $message = $timestamp . '.' . $payload_json;

        // Generate HMAC-SHA256
        $signature = hash_hmac( 'sha256', $message, $signing_key );

        // Return in format: t=timestamp,v1=signature
        return sprintf( 't=%d,v1=%s', $timestamp, $signature );
    }

    /**
     * Send webhook with retry logic.
     *
     * @param string $url       Webhook URL.
     * @param array  $payload   Payload data.
     * @param string $signature Signature header.
     * @return bool|WP_Error
     */
    private function send_with_retry( $url, $payload, $signature ) {
        $attempt = 0;
        $last_error = null;

        while ( $attempt < self::MAX_RETRIES ) {
            $attempt++;

            $result = $this->send_request( $url, $payload, $signature );

            if ( $result === true ) {
                $this->log(
                    'Webhook sent successfully',
                    array(
                        'url'         => $url,
                        'event_type'  => $payload['event_type'],
                        'attempt'     => $attempt,
                    )
                );
                return true;
            }

            $last_error = $result;

            $this->log(
                'Webhook failed, will retry',
                array(
                    'url'     => $url,
                    'attempt' => $attempt,
                    'error'   => is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error',
                )
            );

            // Wait before retry (except on last attempt)
            if ( $attempt < self::MAX_RETRIES ) {
                sleep( self::RETRY_DELAY * $attempt );
            }
        }

        $this->log(
            'Webhook failed after all retries',
            array(
                'url'       => $url,
                'attempts'  => $attempt,
                'error'     => is_wp_error( $last_error ) ? $last_error->get_error_message() : 'Unknown error',
            )
        );

        // Store failed webhook for later processing
        $this->store_failed_webhook( $url, $payload, $signature, $last_error );

        return $last_error;
    }

    /**
     * Send HTTP request.
     *
     * @param string $url       URL.
     * @param array  $payload   Payload.
     * @param string $signature Signature.
     * @return bool|WP_Error
     */
    private function send_request( $url, $payload, $signature ) {
        $args = array(
            'method'      => 'POST',
            'timeout'     => self::TIMEOUT,
            'redirection' => 0,
            'httpversion' => '1.1',
            'headers'     => array(
                'Content-Type'       => 'application/json',
                'User-Agent'         => 'WooCommerce-UCP/' . UCP_WC_VERSION,
                'X-UCP-Signature'    => $signature,
                'X-UCP-Event-Type'   => $payload['event_type'],
                'X-UCP-Delivery-ID'  => $payload['id'],
            ),
            'body'        => wp_json_encode( $payload ),
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        // Success: 2xx status codes
        if ( $status_code >= 200 && $status_code < 300 ) {
            return true;
        }

        // Client error: 4xx - don't retry
        if ( $status_code >= 400 && $status_code < 500 ) {
            return new WP_Error(
                'client_error',
                /* translators: %d: HTTP status code */
                sprintf( __( 'Webhook returned %d status code', 'harmonytics-ucp-connector-for-woocommerce' ), $status_code ),
                array( 'status' => $status_code )
            );
        }

        // Server error: 5xx - retry
        return new WP_Error(
            'server_error',
            /* translators: %d: HTTP status code */
            sprintf( __( 'Webhook returned %d status code', 'harmonytics-ucp-connector-for-woocommerce' ), $status_code ),
            array( 'status' => $status_code )
        );
    }

    /**
     * Store failed webhook for later retry.
     *
     * @param string   $url       URL.
     * @param array    $payload   Payload.
     * @param string   $signature Signature.
     * @param WP_Error $error     Error object.
     */
    private function store_failed_webhook( $url, $payload, $signature, $error ) {
        $failed_webhooks = get_option( 'ucp_wc_failed_webhooks', array() );

        // Limit stored failed webhooks to 100
        if ( count( $failed_webhooks ) >= 100 ) {
            array_shift( $failed_webhooks );
        }

        $failed_webhooks[] = array(
            'url'        => $url,
            'payload'    => $payload,
            'signature'  => $signature,
            'error'      => is_wp_error( $error ) ? $error->get_error_message() : 'Unknown error',
            'failed_at'  => current_time( 'mysql', true ),
        );

        update_option( 'ucp_wc_failed_webhooks', $failed_webhooks );
    }

    /**
     * Retry failed webhooks.
     *
     * Called by WP-Cron or manually.
     *
     * @return array Results of retry attempts.
     */
    public function retry_failed_webhooks() {
        $failed_webhooks = get_option( 'ucp_wc_failed_webhooks', array() );

        if ( empty( $failed_webhooks ) ) {
            return array();
        }

        $results = array();
        $remaining = array();

        foreach ( $failed_webhooks as $webhook ) {
            $result = $this->send_request(
                $webhook['url'],
                $webhook['payload'],
                $webhook['signature']
            );

            if ( $result === true ) {
                $results[] = array(
                    'success'    => true,
                    'event_type' => $webhook['payload']['event_type'],
                );
            } else {
                // Keep for future retry if not too old (max 24 hours)
                $failed_at = strtotime( $webhook['failed_at'] );
                if ( time() - $failed_at < 86400 ) {
                    $remaining[] = $webhook;
                }

                $results[] = array(
                    'success'    => false,
                    'event_type' => $webhook['payload']['event_type'],
                    'error'      => is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error',
                );
            }
        }

        update_option( 'ucp_wc_failed_webhooks', $remaining );

        return $results;
    }

    /**
     * Verify incoming webhook signature.
     *
     * @param string $payload   Raw payload body.
     * @param string $signature Signature header value.
     * @return bool
     */
    public static function verify_signature( $payload, $signature ) {
        $signing_key = UCP_WC_Activator::get_signing_key();

        if ( empty( $signing_key ) || empty( $signature ) ) {
            return false;
        }

        // Parse signature: t=timestamp,v1=signature
        if ( ! preg_match( '/t=(\d+),v1=([a-f0-9]+)/', $signature, $matches ) ) {
            return false;
        }

        $timestamp          = (int) $matches[1];
        $received_signature = $matches[2];

        // Check timestamp tolerance (5 minutes)
        if ( abs( time() - $timestamp ) > 300 ) {
            return false;
        }

        // Reconstruct message
        $message = $timestamp . '.' . $payload;

        // Calculate expected signature
        $expected_signature = hash_hmac( 'sha256', $message, $signing_key );

        // Timing-safe comparison
        return hash_equals( $expected_signature, $received_signature );
    }

    /**
     * Log message.
     *
     * @param string $message Message.
     * @param array  $context Context.
     */
    private function log( $message, $context = array() ) {
        if ( get_option( 'ucp_wc_debug_logging', 'no' ) === 'yes' && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG_LOG is enabled.
                error_log(
                    sprintf(
                        '[UCP Webhook] %s | %s',
                        $message,
                        wp_json_encode( $context )
                    )
                );
            }
        }
    }
}
