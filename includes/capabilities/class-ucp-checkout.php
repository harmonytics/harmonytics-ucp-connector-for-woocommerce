<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Checkout capability handler.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Checkout
 *
 * Handles checkout session creation, management, and confirmation.
 */
class UCP_WC_Checkout {

    /**
     * Line item mapper.
     *
     * @var UCP_WC_Line_Item_Mapper
     */
    protected $line_item_mapper;

    /**
     * Address mapper.
     *
     * @var UCP_WC_Address_Mapper
     */
    protected $address_mapper;

    /**
     * Shipping mapper.
     *
     * @var UCP_WC_Shipping_Mapper
     */
    protected $shipping_mapper;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->line_item_mapper = new UCP_WC_Line_Item_Mapper();
        $this->address_mapper   = new UCP_WC_Address_Mapper();
        $this->shipping_mapper  = new UCP_WC_Shipping_Mapper();
    }

    /**
     * Create a new checkout session.
     *
     * @param array       $items            Array of items to add.
     * @param array|null  $shipping_address Shipping address.
     * @param array|null  $billing_address  Billing address.
     * @param string|null $coupon_code      Coupon code.
     * @param string|null $customer_note    Customer note.
     * @return array|WP_Error
     */
    public function create_session( $items, $shipping_address = null, $billing_address = null, $coupon_code = null, $customer_note = null ) {
        global $wpdb;

        // Validate items
        if ( empty( $items ) || ! is_array( $items ) ) {
            return new WP_Error(
                'invalid_items',
                __( 'Items array is required and cannot be empty.', 'ucp-for-woocommerce' ),
                array( 'status' => 400 )
            );
        }

        // Generate session ID
        $session_id = $this->generate_session_id();

        // Create WooCommerce order
        $order = wc_create_order( array( 'status' => 'pending' ) );

        if ( is_wp_error( $order ) ) {
            return $order;
        }

        // Add items to order
        $added_items = array();
        foreach ( $items as $item ) {
            $result = $this->add_item_to_order( $order, $item );
            if ( is_wp_error( $result ) ) {
                $order->delete( true );
                return $result;
            }
            $added_items[] = $result;
        }

        // Set addresses
        if ( $shipping_address ) {
            $this->set_order_shipping_address( $order, $shipping_address );
        }

        if ( $billing_address ) {
            $this->set_order_billing_address( $order, $billing_address );
        } elseif ( $shipping_address ) {
            // Use shipping address as billing if not provided
            $this->set_order_billing_address( $order, $shipping_address );
        }

        // Apply coupon if provided
        $coupon_result = null;
        if ( $coupon_code ) {
            $coupon_result = $order->apply_coupon( $coupon_code );
            if ( is_wp_error( $coupon_result ) ) {
                // Don't fail the session, just note the error
                $coupon_result = array(
                    'applied' => false,
                    'error'   => $coupon_result->get_error_message(),
                );
            } else {
                $coupon_result = array( 'applied' => true );
            }
        }

        // Set customer note
        if ( $customer_note ) {
            $order->set_customer_note( $customer_note );
        }

        // Calculate totals
        $order->calculate_totals();

        // Store UCP session metadata
        $order->update_meta_data( '_ucp_session_id', $session_id );
        $order->update_meta_data( '_ucp_created_at', current_time( 'mysql', true ) );
        $order->save();

        // Save session to database
        $session_data = array(
            'session_id'    => $session_id,
            'wc_order_id'   => $order->get_id(),
            'status'        => 'pending',
            'cart_data'     => wp_json_encode( $items ),
            'shipping_data' => $shipping_address ? wp_json_encode( $shipping_address ) : null,
            'customer_data' => $billing_address ? wp_json_encode( $billing_address ) : null,
            'next_action'   => $this->determine_next_action( $order, $shipping_address ),
            'created_at'    => current_time( 'mysql', true ),
            'expires_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+24 hours' ) ),
        );

        $table_name = UCP_WC_Activator::get_sessions_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for UCP sessions, no WP API available.
        $wpdb->insert( $table_name, $session_data );

        // Get available shipping methods
        $shipping_methods = $this->get_available_shipping_methods( $order );

        return array(
            'session_id'       => $session_id,
            'order_id'         => $order->get_id(),
            'status'           => 'pending',
            'items'            => $this->line_item_mapper->map_order_items( $order ),
            'totals'           => $this->get_order_totals( $order ),
            'shipping_options' => $shipping_methods,
            'coupon'           => $coupon_result,
            'next_action'      => $session_data['next_action'],
            'web_checkout_url' => $order->get_checkout_payment_url(),
            'expires_at'       => $session_data['expires_at'],
            'created_at'       => $session_data['created_at'],
        );
    }

    /**
     * Get checkout session details.
     *
     * @param string $session_id Session ID.
     * @return array|WP_Error
     */
    public function get_session( $session_id ) {
        global $wpdb;

        $table_name = UCP_WC_Activator::get_sessions_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table for UCP sessions, table name from trusted internal source.
        $session    = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted internal source.
                "SELECT * FROM {$table_name} WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        if ( ! $session ) {
            return new WP_Error(
                'session_not_found',
                __( 'Checkout session not found.', 'ucp-for-woocommerce' ),
                array( 'status' => 404 )
            );
        }

        // Check if session has expired
        if ( strtotime( $session['expires_at'] ) < time() ) {
            return new WP_Error(
                'session_expired',
                __( 'Checkout session has expired.', 'ucp-for-woocommerce' ),
                array( 'status' => 410 )
            );
        }

        // Get the order
        $order = wc_get_order( $session['wc_order_id'] );
        if ( ! $order ) {
            return new WP_Error(
                'order_not_found',
                __( 'Associated order not found.', 'ucp-for-woocommerce' ),
                array( 'status' => 404 )
            );
        }

        // Recalculate totals
        $order->calculate_totals();

        return array(
            'session_id'       => $session_id,
            'order_id'         => $order->get_id(),
            'status'           => $session['status'],
            'items'            => $this->line_item_mapper->map_order_items( $order ),
            'totals'           => $this->get_order_totals( $order ),
            'shipping_address' => $this->address_mapper->map_order_shipping( $order ),
            'billing_address'  => $this->address_mapper->map_order_billing( $order ),
            'shipping_options' => $this->get_available_shipping_methods( $order ),
            'selected_shipping' => $this->get_selected_shipping( $order ),
            'next_action'      => $session['next_action'],
            'web_checkout_url' => $order->get_checkout_payment_url(),
            'expires_at'       => $session['expires_at'],
            'created_at'       => $session['created_at'],
            'updated_at'       => $session['updated_at'],
        );
    }

    /**
     * Update checkout session.
     *
     * @param string $session_id Session ID.
     * @param array  $updates    Updates to apply.
     * @return array|WP_Error
     */
    public function update_session( $session_id, $updates ) {
        global $wpdb;

        $table_name = UCP_WC_Activator::get_sessions_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table for UCP sessions, table name from trusted internal source.
        $session    = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted internal source.
                "SELECT * FROM {$table_name} WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        if ( ! $session ) {
            return new WP_Error(
                'session_not_found',
                __( 'Checkout session not found.', 'ucp-for-woocommerce' ),
                array( 'status' => 404 )
            );
        }

        if ( $session['status'] === 'confirmed' ) {
            return new WP_Error(
                'session_already_confirmed',
                __( 'Cannot update a confirmed session.', 'ucp-for-woocommerce' ),
                array( 'status' => 400 )
            );
        }

        $order = wc_get_order( $session['wc_order_id'] );
        if ( ! $order ) {
            return new WP_Error(
                'order_not_found',
                __( 'Associated order not found.', 'ucp-for-woocommerce' ),
                array( 'status' => 404 )
            );
        }

        // Update shipping address
        if ( ! empty( $updates['shipping_address'] ) ) {
            $this->set_order_shipping_address( $order, $updates['shipping_address'] );
        }

        // Update billing address
        if ( ! empty( $updates['billing_address'] ) ) {
            $this->set_order_billing_address( $order, $updates['billing_address'] );
        }

        // Update shipping method
        if ( ! empty( $updates['shipping_method'] ) ) {
            $this->set_shipping_method( $order, $updates['shipping_method'] );
        }

        // Apply coupon
        if ( ! empty( $updates['coupon_code'] ) ) {
            $order->apply_coupon( $updates['coupon_code'] );
        }

        // Recalculate and save
        $order->calculate_totals();
        $order->save();

        // Determine shipping address for next action calculation
        $shipping_for_next_action = null;
        if ( isset( $updates['shipping_address'] ) ) {
            $shipping_for_next_action = $updates['shipping_address'];
        } elseif ( ! empty( $session['shipping_data'] ) ) {
            $shipping_for_next_action = json_decode( $session['shipping_data'], true );
        }

        // Update session in database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP sessions, update operation.
        $wpdb->update(
            $table_name,
            array(
                'shipping_data' => isset( $updates['shipping_address'] ) ? wp_json_encode( $updates['shipping_address'] ) : $session['shipping_data'],
                'customer_data' => isset( $updates['billing_address'] ) ? wp_json_encode( $updates['billing_address'] ) : $session['customer_data'],
                'next_action'   => $this->determine_next_action( $order, $shipping_for_next_action ),
            ),
            array( 'session_id' => $session_id )
        );

        return $this->get_session( $session_id );
    }

    /**
     * Confirm checkout session.
     *
     * @param string      $session_id      Session ID.
     * @param string|null $shipping_method Selected shipping method.
     * @param string|null $payment_method  Payment method.
     * @return array|WP_Error
     */
    public function confirm_session( $session_id, $shipping_method = null, $payment_method = null ) {
        global $wpdb;

        $table_name = UCP_WC_Activator::get_sessions_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table for UCP sessions, table name from trusted internal source.
        $session    = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted internal source.
                "SELECT * FROM {$table_name} WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        if ( ! $session ) {
            return new WP_Error(
                'session_not_found',
                __( 'Checkout session not found.', 'ucp-for-woocommerce' ),
                array( 'status' => 404 )
            );
        }

        if ( $session['status'] === 'confirmed' ) {
            return new WP_Error(
                'session_already_confirmed',
                __( 'Session has already been confirmed.', 'ucp-for-woocommerce' ),
                array( 'status' => 400 )
            );
        }

        $order = wc_get_order( $session['wc_order_id'] );
        if ( ! $order ) {
            return new WP_Error(
                'order_not_found',
                __( 'Associated order not found.', 'ucp-for-woocommerce' ),
                array( 'status' => 404 )
            );
        }

        // Set shipping method if provided
        if ( $shipping_method ) {
            $this->set_shipping_method( $order, $shipping_method );
        }

        // Calculate final totals
        $order->calculate_totals();
        $order->save();

        // Check if we need web checkout
        $needs_web_checkout = $this->needs_web_checkout( $order, $payment_method );

        if ( $needs_web_checkout ) {
            // Update session status to awaiting_payment
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP sessions, update operation.
            $wpdb->update(
                $table_name,
                array(
                    'status'      => 'awaiting_payment',
                    'next_action' => 'web_checkout',
                ),
                array( 'session_id' => $session_id )
            );

            return array(
                'session_id'       => $session_id,
                'order_id'         => $order->get_id(),
                'status'           => 'awaiting_payment',
                'next_action'      => 'web_checkout',
                'web_checkout_url' => $order->get_checkout_payment_url(),
                'totals'           => $this->get_order_totals( $order ),
                'message'          => __( 'Please complete payment at the checkout URL.', 'ucp-for-woocommerce' ),
            );
        }

        // For cases where payment is not required (e.g., free orders, COD)
        if ( floatval( $order->get_total() ) === 0.0 ) {
            $order->payment_complete();
        }

        // Update session status
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP sessions, update operation.
        $wpdb->update(
            $table_name,
            array(
                'status'      => 'confirmed',
                'next_action' => null,
            ),
            array( 'session_id' => $session_id )
        );

        $date_created = $order->get_date_created();

        return array(
            'session_id'  => $session_id,
            'order_id'    => $order->get_id(),
            'status'      => 'confirmed',
            'next_action' => null,
            'order'       => array(
                'id'         => $order->get_id(),
                'status'     => $order->get_status(),
                'total'      => $order->get_total(),
                'currency'   => $order->get_currency(),
                'created_at' => $date_created ? $date_created->format( 'c' ) : null,
            ),
        );
    }

    /**
     * Generate a unique session ID.
     *
     * @return string
     */
    private function generate_session_id() {
        return 'ucp_' . bin2hex( random_bytes( 16 ) );
    }

    /**
     * Add an item to the order.
     *
     * @param WC_Order $order Order object.
     * @param array    $item  Item data.
     * @return array|WP_Error
     */
    private function add_item_to_order( $order, $item ) {
        $product = null;

        // Find product by SKU, product_id, or variant_id
        if ( ! empty( $item['sku'] ) ) {
            $product_id = wc_get_product_id_by_sku( $item['sku'] );
            if ( $product_id ) {
                $product = wc_get_product( $product_id );
            }
        } elseif ( ! empty( $item['variant_id'] ) ) {
            $product = wc_get_product( $item['variant_id'] );
        } elseif ( ! empty( $item['product_id'] ) ) {
            $product = wc_get_product( $item['product_id'] );
        }

        if ( ! $product ) {
            return new WP_Error(
                'product_not_found',
                sprintf(
                    /* translators: %s: Product identifier (SKU, product ID, or variant ID) */
                    __( 'Product not found: %s', 'ucp-for-woocommerce' ),
                    $item['sku'] ?? $item['product_id'] ?? $item['variant_id'] ?? 'unknown'
                ),
                array( 'status' => 404 )
            );
        }

        if ( ! $product->is_purchasable() ) {
            return new WP_Error(
                'product_not_purchasable',
                /* translators: %s: Product name */
                sprintf( __( 'Product is not purchasable: %s', 'ucp-for-woocommerce' ), $product->get_name() ),
                array( 'status' => 400 )
            );
        }

        $quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;

        if ( ! $product->has_enough_stock( $quantity ) ) {
            return new WP_Error(
                'insufficient_stock',
                /* translators: %s: Product name */
                sprintf( __( 'Insufficient stock for: %s', 'ucp-for-woocommerce' ), $product->get_name() ),
                array( 'status' => 400 )
            );
        }

        $order->add_product( $product, $quantity );

        return array(
            'product_id' => $product->get_id(),
            'name'       => $product->get_name(),
            'quantity'   => $quantity,
            'price'      => $product->get_price(),
        );
    }

    /**
     * Set shipping address on order.
     *
     * @param WC_Order $order   Order object.
     * @param array    $address Address data.
     */
    private function set_order_shipping_address( $order, $address ) {
        $order->set_shipping_first_name( $address['first_name'] ?? '' );
        $order->set_shipping_last_name( $address['last_name'] ?? '' );
        $order->set_shipping_address_1( $address['address_1'] ?? '' );
        $order->set_shipping_address_2( $address['address_2'] ?? '' );
        $order->set_shipping_city( $address['city'] ?? '' );
        $order->set_shipping_state( $address['state'] ?? '' );
        $order->set_shipping_postcode( $address['postcode'] ?? '' );
        $order->set_shipping_country( $address['country'] ?? '' );
        $order->set_shipping_phone( $address['phone'] ?? '' );
    }

    /**
     * Set billing address on order.
     *
     * @param WC_Order $order   Order object.
     * @param array    $address Address data.
     */
    private function set_order_billing_address( $order, $address ) {
        $order->set_billing_first_name( $address['first_name'] ?? '' );
        $order->set_billing_last_name( $address['last_name'] ?? '' );
        $order->set_billing_address_1( $address['address_1'] ?? '' );
        $order->set_billing_address_2( $address['address_2'] ?? '' );
        $order->set_billing_city( $address['city'] ?? '' );
        $order->set_billing_state( $address['state'] ?? '' );
        $order->set_billing_postcode( $address['postcode'] ?? '' );
        $order->set_billing_country( $address['country'] ?? '' );
        $order->set_billing_phone( $address['phone'] ?? '' );
        $order->set_billing_email( $address['email'] ?? '' );
    }

    /**
     * Get available shipping methods for an order.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function get_available_shipping_methods( $order ) {
        $shipping_address = array(
            'country'  => $order->get_shipping_country(),
            'state'    => $order->get_shipping_state(),
            'postcode' => $order->get_shipping_postcode(),
            'city'     => $order->get_shipping_city(),
        );

        // Skip if no shipping address
        if ( empty( $shipping_address['country'] ) ) {
            return array();
        }

        // Get shipping packages
        $packages = array(
            array(
                'contents'        => array(),
                'contents_cost'   => $order->get_subtotal(),
                'applied_coupons' => array(),
                'destination'     => $shipping_address,
            ),
        );

        $shipping_zone = WC_Shipping_Zones::get_zone_matching_package( $packages[0] );
        $methods       = $shipping_zone->get_shipping_methods( true );

        $available_methods = array();
        foreach ( $methods as $method ) {
            if ( ! $method->is_enabled() ) {
                continue;
            }

            $available_methods[] = array(
                'id'          => $method->get_rate_id(),
                'method_id'   => $method->id,
                'title'       => $method->get_title(),
                'description' => $method->get_method_description(),
            );
        }

        return $available_methods;
    }

    /**
     * Set shipping method on order.
     *
     * @param WC_Order $order           Order object.
     * @param string   $shipping_method Shipping method ID.
     */
    private function set_shipping_method( $order, $shipping_method ) {
        // Remove existing shipping
        foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
            $order->remove_item( $item_id );
        }

        // Add new shipping
        $item = new WC_Order_Item_Shipping();
        $item->set_method_id( $shipping_method );
        $item->set_method_title( $shipping_method );
        $order->add_item( $item );
    }

    /**
     * Get selected shipping method.
     *
     * @param WC_Order $order Order object.
     * @return array|null
     */
    private function get_selected_shipping( $order ) {
        $shipping_items = $order->get_items( 'shipping' );
        if ( empty( $shipping_items ) ) {
            return null;
        }

        $item = reset( $shipping_items );
        return array(
            'method_id' => $item->get_method_id(),
            'title'     => $item->get_method_title(),
            'total'     => $item->get_total(),
        );
    }

    /**
     * Get order totals.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function get_order_totals( $order ) {
        return array(
            'subtotal'          => $order->get_subtotal(),
            'shipping_total'    => $order->get_shipping_total(),
            'tax_total'         => $order->get_total_tax(),
            'discount_total'    => $order->get_discount_total(),
            'total'             => $order->get_total(),
            'currency'          => $order->get_currency(),
            'prices_include_tax' => wc_prices_include_tax(),
        );
    }

    /**
     * Determine the next action for a session.
     *
     * @param WC_Order   $order            Order object.
     * @param array|null $shipping_address Shipping address.
     * @return string
     */
    private function determine_next_action( $order, $shipping_address ) {
        // No shipping address yet
        if ( empty( $shipping_address ) || empty( $shipping_address['country'] ) ) {
            return 'provide_shipping_address';
        }

        // No shipping method selected
        if ( empty( $order->get_items( 'shipping' ) ) ) {
            return 'select_shipping';
        }

        // Payment required
        if ( $order->get_total() > 0 ) {
            return 'web_checkout';
        }

        return 'confirm';
    }

    /**
     * Check if web checkout is needed.
     *
     * @param WC_Order    $order          Order object.
     * @param string|null $payment_method Payment method.
     * @return bool
     */
    private function needs_web_checkout( $order, $payment_method ) {
        // Free orders don't need web checkout
        if ( floatval( $order->get_total() ) === 0.0 ) {
            return false;
        }

        // Most payment methods require web checkout for security
        // Only skip for specific pre-authorized methods
        $agentic_methods = apply_filters( 'ucp_wc_agentic_payment_methods', array() );

        if ( $payment_method && in_array( $payment_method, $agentic_methods, true ) ) {
            return false;
        }

        return true;
    }
}
