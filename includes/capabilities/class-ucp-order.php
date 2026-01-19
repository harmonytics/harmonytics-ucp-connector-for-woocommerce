<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Order capability handler.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Order
 *
 * Handles order retrieval and status mapping.
 */
class UCP_WC_Order {

    /**
     * Order mapper.
     *
     * @var UCP_WC_Order_Mapper
     */
    protected $order_mapper;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->order_mapper = new UCP_WC_Order_Mapper();
    }

    /**
     * List orders with filters.
     *
     * @param array $args Query arguments.
     * @return array|WP_Error
     */
    public function list_orders( $args = array() ) {
        $defaults = array(
            'page'           => 1,
            'per_page'       => 10,
            'status'         => 'any',
            'after'          => null,
            'before'         => null,
            'customer_email' => null,
        );

        $args = wp_parse_args( $args, $defaults );

        $query_args = array(
            'limit'   => $args['per_page'],
            'page'    => $args['page'],
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        );

        // Filter by status
        if ( $args['status'] && $args['status'] !== 'any' ) {
            $query_args['status'] = $args['status'];
        } else {
            $query_args['status'] = array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );
        }

        // Filter by date range
        if ( $args['after'] ) {
            $query_args['date_created'] = '>=' . strtotime( $args['after'] );
        }

        if ( $args['before'] ) {
            if ( isset( $query_args['date_created'] ) ) {
                $query_args['date_created'] .= '...' . strtotime( $args['before'] );
            } else {
                $query_args['date_created'] = '<=' . strtotime( $args['before'] );
            }
        }

        // Filter by customer email
        if ( $args['customer_email'] ) {
            $query_args['billing_email'] = $args['customer_email'];
        }

        // Only get orders created via UCP
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required to filter UCP-created orders, meta_key is indexed.
        $query_args['meta_key']   = '_ucp_session_id';
        $query_args['meta_compare'] = 'EXISTS';

        $orders = wc_get_orders( $query_args );

        // Get total count for pagination
        $count_args = $query_args;
        $count_args['return'] = 'ids';
        $count_args['limit']  = -1;
        $count_args['page']   = 1;
        $total = count( wc_get_orders( $count_args ) );

        $mapped_orders = array();
        foreach ( $orders as $order ) {
            $mapped_orders[] = $this->order_mapper->map_order_summary( $order );
        }

        return array(
            'orders'      => $mapped_orders,
            'total'       => $total,
            'page'        => $args['page'],
            'per_page'    => $args['per_page'],
            'total_pages' => ceil( $total / $args['per_page'] ),
        );
    }

    /**
     * Get order details.
     *
     * @param int $order_id Order ID.
     * @return array|WP_Error
     */
    public function get_order( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error(
                'order_not_found',
                __( 'Order not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
                array( 'status' => 404 )
            );
        }

        return $this->order_mapper->map_order( $order );
    }

    /**
     * Get order by session ID.
     *
     * @param string $session_id Session ID.
     * @return array|WP_Error
     */
    public function get_order_by_session( $session_id ) {
        global $wpdb;

        $table_name = UCP_WC_Activator::get_sessions_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table for UCP sessions, table name from trusted internal source.
        $session    = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted internal source.
                "SELECT wc_order_id FROM {$table_name} WHERE session_id = %s",
                $session_id
            ),
            ARRAY_A
        );

        if ( ! $session || empty( $session['wc_order_id'] ) ) {
            return new WP_Error(
                'order_not_found',
                __( 'No order found for this session.', 'harmonytics-ucp-connector-for-woocommerce' ),
                array( 'status' => 404 )
            );
        }

        return $this->get_order( $session['wc_order_id'] );
    }

    /**
     * Get order events/timeline.
     *
     * @param int $order_id Order ID.
     * @return array|WP_Error
     */
    public function get_order_events( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error(
                'order_not_found',
                __( 'Order not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
                array( 'status' => 404 )
            );
        }

        $events = array();

        // Add creation event
        $events[] = array(
            'event_type' => 'order.created',
            'timestamp'  => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : null,
            'data'       => array(
                'order_id' => $order->get_id(),
                'status'   => 'pending',
            ),
        );

        // Get order notes (status changes and other events)
        $notes = wc_get_order_notes(
            array(
                'order_id' => $order_id,
                'orderby'  => 'date_created',
                'order'    => 'ASC',
            )
        );

        foreach ( $notes as $note ) {
            // Skip notes without a valid date
            if ( ! isset( $note->date_created ) || ! $note->date_created ) {
                continue;
            }

            $event_type = 'order.note';
            $data       = array(
                'note_id' => $note->id,
                'content' => $note->content,
                'author'  => $note->added_by,
            );

            // Detect status change notes
            if ( strpos( $note->content, 'Order status changed' ) !== false ) {
                $event_type = 'order.status_changed';

                // Try to extract old and new status
                preg_match( '/from (.+) to (.+)\.?$/i', $note->content, $matches );
                if ( count( $matches ) === 3 ) {
                    $data['from_status'] = $this->map_wc_status_to_ucp( trim( $matches[1] ) );
                    $data['to_status']   = $this->map_wc_status_to_ucp( trim( $matches[2] ) );
                }
            }

            // Detect payment events
            if ( strpos( $note->content, 'Payment' ) !== false || strpos( $note->content, 'paid' ) !== false ) {
                $event_type = 'order.payment';
            }

            // Detect refund events
            if ( strpos( $note->content, 'Refund' ) !== false || strpos( $note->content, 'refunded' ) !== false ) {
                $event_type = 'order.refunded';
            }

            // Detect shipping events
            if ( strpos( $note->content, 'Tracking' ) !== false || strpos( $note->content, 'shipped' ) !== false ) {
                $event_type = 'order.shipped';
            }

            $events[] = array(
                'event_type' => $event_type,
                'timestamp'  => $note->date_created->format( 'c' ),
                'data'       => $data,
            );
        }

        // Add paid event if applicable
        if ( $order->get_date_paid() ) {
            $events[] = array(
                'event_type' => 'order.paid',
                'timestamp'  => $order->get_date_paid()->format( 'c' ),
                'data'       => array(
                    'order_id'       => $order->get_id(),
                    'payment_method' => $order->get_payment_method(),
                    'transaction_id' => $order->get_transaction_id(),
                ),
            );
        }

        // Add completed event if applicable
        if ( $order->get_date_completed() ) {
            $events[] = array(
                'event_type' => 'order.completed',
                'timestamp'  => $order->get_date_completed()->format( 'c' ),
                'data'       => array(
                    'order_id' => $order->get_id(),
                ),
            );
        }

        // Sort events by timestamp (handle null timestamps)
        usort(
            $events,
            function ( $a, $b ) {
                $time_a = ! empty( $a['timestamp'] ) ? strtotime( $a['timestamp'] ) : 0;
                $time_b = ! empty( $b['timestamp'] ) ? strtotime( $b['timestamp'] ) : 0;
                return $time_a - $time_b;
            }
        );

        return array(
            'order_id' => $order_id,
            'events'   => $events,
        );
    }

    /**
     * Map WooCommerce status to UCP status.
     *
     * @param string $wc_status WooCommerce status.
     * @return string
     */
    private function map_wc_status_to_ucp( $wc_status ) {
        // Remove 'wc-' prefix if present
        $status = str_replace( 'wc-', '', strtolower( $wc_status ) );

        $mapping = array(
            'pending'    => 'awaiting_payment',
            'on-hold'    => 'awaiting_payment',
            'processing' => 'preparing',
            'completed'  => 'delivered',
            'cancelled'  => 'cancelled',
            'failed'     => 'cancelled',
            'refunded'   => 'refunded',
        );

        return $mapping[ $status ] ?? $status;
    }
}
