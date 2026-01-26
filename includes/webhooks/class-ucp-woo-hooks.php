<?php
/**
 * WooCommerce hooks integration for UCP events.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Woo_Hooks
 *
 * Handles WooCommerce hooks and triggers UCP events.
 */
class UCP_WC_Woo_Hooks {

	/**
	 * Webhook sender instance.
	 *
	 * @var UCP_WC_Webhook_Sender
	 */
	protected $webhook_sender;

	/**
	 * Order mapper instance.
	 *
	 * @var UCP_WC_Order_Mapper
	 */
	protected $order_mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->webhook_sender = new UCP_WC_Webhook_Sender();
		$this->order_mapper   = new UCP_WC_Order_Mapper();
	}

	/**
	 * Handle new order creation.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 */
	public function on_order_created( $order_id, $order = null ) {
		if ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		// Only trigger for UCP-created orders.
		if ( ! $order->get_meta( '_ucp_session_id' ) ) {
			return;
		}

		$this->log( 'Order created', array( 'order_id' => $order_id ) );

		$event = array(
			'event_type' => 'order.created',
			'timestamp'  => current_time( 'c' ),
			'order_id'   => $order_id,
			'session_id' => $order->get_meta( '_ucp_session_id' ),
			'data'       => $this->order_mapper->map_order_summary( $order ),
		);

		$this->webhook_sender->send( $event );
	}

	/**
	 * Handle order status change.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Old status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order      Order object.
	 */
	public function on_order_status_changed( $order_id, $old_status, $new_status, $order ) {
		// Only trigger for UCP-created orders.
		if ( ! $order->get_meta( '_ucp_session_id' ) ) {
			return;
		}

		$this->log(
			'Order status changed',
			array(
				'order_id'   => $order_id,
				'old_status' => $old_status,
				'new_status' => $new_status,
			)
		);

		// Update session status in database.
		$this->update_session_status( $order, $new_status );

		$event = array(
			'event_type' => 'order.status_changed',
			'timestamp'  => current_time( 'c' ),
			'order_id'   => $order_id,
			'session_id' => $order->get_meta( '_ucp_session_id' ),
			'data'       => array(
				'from_status'     => $old_status,
				'to_status'       => $new_status,
				'from_ucp_status' => $this->order_mapper->map_status( $old_status ),
				'to_ucp_status'   => $this->order_mapper->map_status( $new_status ),
				'order'           => $this->order_mapper->map_order_summary( $order ),
			),
		);

		$this->webhook_sender->send( $event );
	}

	/**
	 * Handle payment complete.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_payment_complete( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Only trigger for UCP-created orders.
		if ( ! $order->get_meta( '_ucp_session_id' ) ) {
			return;
		}

		$this->log( 'Payment complete', array( 'order_id' => $order_id ) );

		$event = array(
			'event_type' => 'order.paid',
			'timestamp'  => current_time( 'c' ),
			'order_id'   => $order_id,
			'session_id' => $order->get_meta( '_ucp_session_id' ),
			'data'       => array(
				'payment_method' => $order->get_payment_method(),
				'transaction_id' => $order->get_transaction_id(),
				'total'          => floatval( $order->get_total() ),
				'currency'       => $order->get_currency(),
				'order'          => $this->order_mapper->map_order_summary( $order ),
			),
		);

		$this->webhook_sender->send( $event );
	}

	/**
	 * Handle order refund.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public function on_order_refunded( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Only trigger for UCP-created orders.
		if ( ! $order->get_meta( '_ucp_session_id' ) ) {
			return;
		}

		$refund = wc_get_order( $refund_id );

		$this->log(
			'Order refunded',
			array(
				'order_id'  => $order_id,
				'refund_id' => $refund_id,
			)
		);

		// Get refund details safely.
		$refund_amount = 0;
		$refund_reason = '';
		if ( $refund && is_a( $refund, 'WC_Order_Refund' ) ) {
			$refund_amount = floatval( $refund->get_amount() );
			$refund_reason = $refund->get_reason();
		}

		$event = array(
			'event_type' => 'order.refunded',
			'timestamp'  => current_time( 'c' ),
			'order_id'   => $order_id,
			'session_id' => $order->get_meta( '_ucp_session_id' ),
			'data'       => array(
				'refund_id'      => $refund_id,
				'refund_amount'  => $refund_amount,
				'refund_reason'  => $refund_reason,
				'total_refunded' => floatval( $order->get_total_refunded() ),
				'remaining'      => floatval( $order->get_remaining_refund_amount() ),
				'is_full_refund' => $order->get_remaining_refund_amount() <= 0,
				'order'          => $this->order_mapper->map_order_summary( $order ),
			),
		);

		$this->webhook_sender->send( $event );
	}

	/**
	 * Update session status in database.
	 *
	 * @param WC_Order $order      Order object.
	 * @param string   $new_status New WooCommerce status.
	 */
	private function update_session_status( $order, $new_status ) {
		global $wpdb;

		$session_id = $order->get_meta( '_ucp_session_id' );
		if ( ! $session_id ) {
			return;
		}

		$table_name = UCP_WC_Activator::get_sessions_table();

		// Map WC status to session status.
		$session_status = 'pending';
		switch ( $new_status ) {
			case 'processing':
			case 'completed':
				$session_status = 'confirmed';
				break;
			case 'cancelled':
			case 'failed':
				$session_status = 'cancelled';
				break;
			case 'refunded':
				$session_status = 'refunded';
				break;
			case 'pending':
			case 'on-hold':
			default:
				$session_status = 'awaiting_payment';
				break;
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP sessions, update operation.
		$wpdb->update(
			$table_name,
			array(
				'status'      => $session_status,
				'next_action' => 'confirmed' === $session_status ? null : 'web_checkout',
			),
			array( 'session_id' => $session_id )
		);
	}

	/**
	 * Log debug message.
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
						'[UCP Hooks] %s | %s',
						$message,
						wp_json_encode( $context )
					)
				);
			}
		}
	}
}
