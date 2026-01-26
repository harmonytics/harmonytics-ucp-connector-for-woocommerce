<?php
/**
 * REST controller for order endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Order_Controller
 *
 * Handles order-related REST API endpoints.
 */
class UCP_WC_Order_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'orders';

	/**
	 * Order capability handler.
	 *
	 * @var UCP_WC_Order
	 */
	protected $order;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->order = new UCP_WC_Order();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /orders - List orders (with optional filters).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_orders' ),
					'permission_callback' => array( $this, 'check_authenticated_read' ),
					'args'                => $this->get_list_orders_args(),
				),
			)
		);

		// GET /orders/{order_id} - Get order details.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<order_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_order' ),
					'permission_callback' => array( $this, 'check_authenticated_read' ),
					'args'                => array(
						'order_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Order ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// GET /orders/{order_id}/events - Get order timeline/events.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<order_id>[\d]+)/events',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_order_events' ),
					'permission_callback' => array( $this, 'check_authenticated_read' ),
					'args'                => array(
						'order_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// GET /orders/by-session/{session_id} - Get order by session ID.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/by-session/(?P<session_id>ucp_[a-f0-9]{32})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_order_by_session' ),
					'permission_callback' => array( $this, 'check_authenticated_read' ),
					'args'                => array(
						'session_id' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Get arguments for list orders endpoint.
	 *
	 * @return array
	 */
	private function get_list_orders_args() {
		return array(
			'page'           => array(
				'required'    => false,
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
				'description' => __( 'Page number.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'per_page'       => array(
				'required'    => false,
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
				'description' => __( 'Items per page.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'status'         => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array(
					'pending',
					'processing',
					'on-hold',
					'completed',
					'cancelled',
					'refunded',
					'failed',
					'any',
				),
				'default'     => 'any',
				'description' => __( 'Filter by order status.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'after'          => array(
				'required'    => false,
				'type'        => 'string',
				'format'      => 'date-time',
				'description' => __( 'Orders created after this date.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'before'         => array(
				'required'    => false,
				'type'        => 'string',
				'format'      => 'date-time',
				'description' => __( 'Orders created before this date.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'customer_email' => array(
				'required'    => false,
				'type'        => 'string',
				'format'      => 'email',
				'description' => __( 'Filter by customer email.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
		);
	}

	/**
	 * List orders.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_orders( $request ) {
		$this->log( 'Listing orders', array( 'params' => $request->get_params() ) );

		$result = $this->order->list_orders(
			array(
				'page'           => $request->get_param( 'page' ),
				'per_page'       => $request->get_param( 'per_page' ),
				'status'         => $request->get_param( 'status' ),
				'after'          => $request->get_param( 'after' ),
				'before'         => $request->get_param( 'before' ),
				'customer_email' => $request->get_param( 'customer_email' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = $this->success_response( $result );

		// Add pagination headers.
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', $result['total_pages'] );

		return $response;
	}

	/**
	 * Get order details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_order( $request ) {
		$order_id = $request->get_param( 'order_id' );

		$this->log( 'Getting order', array( 'order_id' => $order_id ) );

		$result = $this->order->get_order( $order_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	/**
	 * Get order events/timeline.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_order_events( $request ) {
		$order_id = $request->get_param( 'order_id' );

		$this->log( 'Getting order events', array( 'order_id' => $order_id ) );

		$result = $this->order->get_order_events( $order_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	/**
	 * Get order by session ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_order_by_session( $request ) {
		$session_id = $request->get_param( 'session_id' );

		$this->log( 'Getting order by session', array( 'session_id' => $session_id ) );

		$result = $this->order->get_order_by_session( $session_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}
}
