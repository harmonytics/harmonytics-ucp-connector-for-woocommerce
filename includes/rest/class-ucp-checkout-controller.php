<?php
/**
 * REST controller for checkout endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Checkout_Controller
 *
 * Handles checkout-related REST API endpoints.
 */
class UCP_WC_Checkout_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'checkout';

	/**
	 * Checkout capability handler.
	 *
	 * @var UCP_WC_Checkout
	 */
	protected $checkout;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->checkout = new UCP_WC_Checkout();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /checkout/sessions - Create a new checkout session.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sessions',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_session' ),
					'permission_callback' => array( $this, 'check_authenticated_write' ),
					'args'                => $this->get_create_session_args(),
				),
			)
		);

		// GET /checkout/sessions/{session_id} - Get session details.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sessions/(?P<session_id>ucp_[a-f0-9]{32})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_session' ),
					'permission_callback' => array( $this, 'check_authenticated_read' ),
					'args'                => array(
						'session_id' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'Unique session identifier.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// POST /checkout/sessions/{session_id}/confirm - Confirm checkout session.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sessions/(?P<session_id>ucp_[a-f0-9]{32})/confirm',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'confirm_session' ),
					'permission_callback' => array( $this, 'check_authenticated_write' ),
					'args'                => $this->get_confirm_session_args(),
				),
			)
		);

		// PATCH /checkout/sessions/{session_id} - Update session.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sessions/(?P<session_id>ucp_[a-f0-9]{32})',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_session' ),
					'permission_callback' => array( $this, 'check_authenticated_write' ),
					'args'                => $this->get_update_session_args(),
				),
			)
		);
	}

	/**
	 * Get arguments for create session endpoint.
	 *
	 * @return array
	 */
	private function get_create_session_args() {
		return array(
			'items'            => array(
				'required'    => true,
				'type'        => 'array',
				'description' => __( 'Array of items to add to the checkout.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'sku'        => array(
							'type'        => 'string',
							'description' => __( 'Product SKU.', 'harmonytics-ucp-connector-for-woocommerce' ),
						),
						'product_id' => array(
							'type'        => 'integer',
							'description' => __( 'Product ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
						),
						'variant_id' => array(
							'type'        => 'integer',
							'description' => __( 'Variant/variation ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
						),
						'quantity'   => array(
							'type'        => 'integer',
							'description' => __( 'Quantity.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'default'     => 1,
							'minimum'     => 1,
						),
					),
				),
			),
			'shipping_address' => array(
				'required'    => false,
				'type'        => 'object',
				'description' => __( 'Shipping address.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'properties'  => array(
					'first_name' => array( 'type' => 'string' ),
					'last_name'  => array( 'type' => 'string' ),
					'address_1'  => array( 'type' => 'string' ),
					'address_2'  => array( 'type' => 'string' ),
					'city'       => array( 'type' => 'string' ),
					'state'      => array( 'type' => 'string' ),
					'postcode'   => array( 'type' => 'string' ),
					'country'    => array( 'type' => 'string' ),
					'phone'      => array( 'type' => 'string' ),
					'email'      => array(
						'type'   => 'string',
						'format' => 'email',
					),
				),
			),
			'billing_address'  => array(
				'required'    => false,
				'type'        => 'object',
				'description' => __( 'Billing address.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'coupon_code'      => array(
				'required'    => false,
				'type'        => 'string',
				'description' => __( 'Coupon code to apply.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'customer_note'    => array(
				'required'    => false,
				'type'        => 'string',
				'description' => __( 'Customer note for the order.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get arguments for confirm session endpoint.
	 *
	 * @return array
	 */
	private function get_confirm_session_args() {
		return array(
			'session_id'      => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Session ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'shipping_method' => array(
				'required'    => false,
				'type'        => 'string',
				'description' => __( 'Selected shipping method ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'payment_method'  => array(
				'required'    => false,
				'type'        => 'string',
				'description' => __( 'Payment method ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get arguments for update session endpoint.
	 *
	 * @return array
	 */
	private function get_update_session_args() {
		return array(
			'session_id'       => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'shipping_address' => array(
				'required' => false,
				'type'     => 'object',
			),
			'billing_address'  => array(
				'required' => false,
				'type'     => 'object',
			),
			'shipping_method'  => array(
				'required' => false,
				'type'     => 'string',
			),
			'coupon_code'      => array(
				'required' => false,
				'type'     => 'string',
			),
		);
	}

	/**
	 * Create a new checkout session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_session( $request ) {
		$this->log( 'Creating checkout session', array( 'items' => $request->get_param( 'items' ) ) );

		$result = $this->checkout->create_session(
			$request->get_param( 'items' ),
			$request->get_param( 'shipping_address' ),
			$request->get_param( 'billing_address' ),
			$request->get_param( 'coupon_code' ),
			$request->get_param( 'customer_note' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result, 201 );
	}

	/**
	 * Get checkout session details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_session( $request ) {
		$session_id = $request->get_param( 'session_id' );

		$this->log( 'Getting checkout session', array( 'session_id' => $session_id ) );

		$result = $this->checkout->get_session( $session_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	/**
	 * Confirm checkout session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirm_session( $request ) {
		$session_id = $request->get_param( 'session_id' );

		$this->log( 'Confirming checkout session', array( 'session_id' => $session_id ) );

		$result = $this->checkout->confirm_session(
			$session_id,
			$request->get_param( 'shipping_method' ),
			$request->get_param( 'payment_method' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	/**
	 * Update checkout session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_session( $request ) {
		$session_id = $request->get_param( 'session_id' );

		$this->log( 'Updating checkout session', array( 'session_id' => $session_id ) );

		$result = $this->checkout->update_session(
			$session_id,
			array(
				'shipping_address' => $request->get_param( 'shipping_address' ),
				'billing_address'  => $request->get_param( 'billing_address' ),
				'shipping_method'  => $request->get_param( 'shipping_method' ),
				'coupon_code'      => $request->get_param( 'coupon_code' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}
}
