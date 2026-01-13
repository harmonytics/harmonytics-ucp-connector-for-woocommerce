<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Order mapper for UCP schema conversion.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Order_Mapper
 *
 * Maps WooCommerce orders to UCP order schema.
 */
class UCP_WC_Order_Mapper {

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
     * Map a WooCommerce order to UCP format.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    public function map_order( $order ) {
        return array(
            'id'               => $order->get_id(),
            'order_number'     => $order->get_order_number(),
            'order_key'        => $order->get_order_key(),
            'status'           => $this->map_status( $order->get_status() ),
            'wc_status'        => $order->get_status(),
            'currency'         => $order->get_currency(),
            'items'            => $this->line_item_mapper->map_order_items( $order ),
            'totals'           => $this->map_totals( $order ),
            'shipping_address' => $this->address_mapper->map_order_shipping( $order ),
            'billing_address'  => $this->address_mapper->map_order_billing( $order ),
            'shipping'         => $this->shipping_mapper->map_order_shipping( $order ),
            'tracking'         => $this->shipping_mapper->get_tracking_info( $order ),
            'payment'          => $this->map_payment( $order ),
            'customer'         => $this->map_customer( $order ),
            'meta'             => $this->map_meta( $order ),
            'dates'            => $this->map_dates( $order ),
            'links'            => $this->map_links( $order ),
        );
    }

    /**
     * Map order summary (for list views).
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    public function map_order_summary( $order ) {
        return array(
            'id'           => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status'       => $this->map_status( $order->get_status() ),
            'wc_status'    => $order->get_status(),
            'total'        => floatval( $order->get_total() ),
            'currency'     => $order->get_currency(),
            'items_count'  => $order->get_item_count(),
            'customer'     => array(
                'name'  => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
            ),
            'created_at'   => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : null,
        );
    }

    /**
     * Map WooCommerce status to UCP status.
     *
     * @param string $wc_status WooCommerce status.
     * @return string
     */
    public function map_status( $wc_status ) {
        $mapping = array(
            'pending'    => 'awaiting_payment',
            'on-hold'    => 'awaiting_payment',
            'processing' => 'preparing',
            'completed'  => 'delivered',
            'cancelled'  => 'cancelled',
            'failed'     => 'cancelled',
            'refunded'   => 'refunded',
        );

        return $mapping[ $wc_status ] ?? $wc_status;
    }

    /**
     * Map UCP status to WooCommerce status.
     *
     * @param string $ucp_status UCP status.
     * @return string
     */
    public function map_status_to_wc( $ucp_status ) {
        $mapping = array(
            'awaiting_payment' => 'pending',
            'preparing'        => 'processing',
            'shipped'          => 'processing',
            'delivered'        => 'completed',
            'cancelled'        => 'cancelled',
            'refunded'         => 'refunded',
        );

        return $mapping[ $ucp_status ] ?? $ucp_status;
    }

    /**
     * Map order totals.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function map_totals( $order ) {
        return array(
            'subtotal'       => floatval( $order->get_subtotal() ),
            'shipping'       => floatval( $order->get_shipping_total() ),
            'shipping_tax'   => floatval( $order->get_shipping_tax() ),
            'discount'       => floatval( $order->get_discount_total() ),
            'discount_tax'   => floatval( $order->get_discount_tax() ),
            'tax'            => floatval( $order->get_total_tax() ),
            'total'          => floatval( $order->get_total() ),
            'fees'           => $this->map_fees( $order ),
            'refunded'       => floatval( $order->get_total_refunded() ),
            'remaining'      => floatval( $order->get_remaining_refund_amount() ),
        );
    }

    /**
     * Map order fees.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function map_fees( $order ) {
        $fees = array();

        foreach ( $order->get_fees() as $fee ) {
            $fees[] = array(
                'id'    => $fee->get_id(),
                'name'  => $fee->get_name(),
                'total' => floatval( $fee->get_total() ),
                'tax'   => floatval( $fee->get_total_tax() ),
            );
        }

        return $fees;
    }

    /**
     * Map payment information.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function map_payment( $order ) {
        return array(
            'method'         => $order->get_payment_method(),
            'method_title'   => $order->get_payment_method_title(),
            'transaction_id' => $order->get_transaction_id(),
            'paid'           => $order->is_paid(),
            'date_paid'      => $order->get_date_paid() ? $order->get_date_paid()->format( 'c' ) : null,
        );
    }

    /**
     * Map customer information.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function map_customer( $order ) {
        return array(
            'id'         => $order->get_customer_id(),
            'email'      => $order->get_billing_email(),
            'first_name' => $order->get_billing_first_name(),
            'last_name'  => $order->get_billing_last_name(),
            'full_name'  => $order->get_formatted_billing_full_name(),
            'phone'      => $order->get_billing_phone(),
            'ip_address' => $order->get_customer_ip_address(),
            'user_agent' => $order->get_customer_user_agent(),
            'is_guest'   => $order->get_customer_id() === 0,
        );
    }

    /**
     * Map order meta (UCP-specific).
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function map_meta( $order ) {
        return array(
            'ucp_session_id' => $order->get_meta( '_ucp_session_id' ),
            'ucp_created_at' => $order->get_meta( '_ucp_created_at' ),
            'customer_note'  => $order->get_customer_note(),
            'coupons'        => $this->map_coupons( $order ),
        );
    }

    /**
     * Map applied coupons.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function map_coupons( $order ) {
        $coupons = array();

        foreach ( $order->get_coupon_codes() as $code ) {
            $coupon_item = null;
            foreach ( $order->get_items( 'coupon' ) as $item ) {
                if ( $item->get_code() === $code ) {
                    $coupon_item = $item;
                    break;
                }
            }

            $coupons[] = array(
                'code'     => $code,
                'discount' => $coupon_item ? floatval( $coupon_item->get_discount() ) : 0,
                'tax'      => $coupon_item ? floatval( $coupon_item->get_discount_tax() ) : 0,
            );
        }

        return $coupons;
    }

    /**
     * Map order dates.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function map_dates( $order ) {
        return array(
            'created'   => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : null,
            'modified'  => $order->get_date_modified() ? $order->get_date_modified()->format( 'c' ) : null,
            'paid'      => $order->get_date_paid() ? $order->get_date_paid()->format( 'c' ) : null,
            'completed' => $order->get_date_completed() ? $order->get_date_completed()->format( 'c' ) : null,
        );
    }

    /**
     * Map order links.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function map_links( $order ) {
        return array(
            'self'             => rest_url( 'ucp/v1/orders/' . $order->get_id() ),
            'events'           => rest_url( 'ucp/v1/orders/' . $order->get_id() . '/events' ),
            'checkout'         => $order->get_checkout_payment_url(),
            'order_received'   => $order->get_checkout_order_received_url(),
            'cancel'           => $order->get_cancel_order_url(),
            'view'             => $order->get_view_order_url(),
        );
    }
}
