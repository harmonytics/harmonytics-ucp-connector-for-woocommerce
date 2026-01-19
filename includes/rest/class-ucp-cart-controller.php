<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * REST controller for cart endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Cart_Controller
 *
 * Handles cart-related REST API endpoints.
 */
class UCP_WC_Cart_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'cart';

	/**
	 * Cart capability handler.
	 *
	 * @var UCP_WC_Cart
	 */
	protected $cart;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cart = new UCP_WC_Cart();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /cart - Create a new cart.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_cart' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_create_cart_args(),
				),
			)
		);

		// GET /cart/{cart_id} - Get cart contents.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<cart_id>cart_[a-f0-9]{32})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cart' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(
						'cart_id' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'Unique cart identifier.', 'harmonytics-ucp-connector-woocommerce' ),
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_cart_id' ),
						),
					),
				),
			)
		);

		// DELETE /cart/{cart_id} - Clear/delete cart.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<cart_id>cart_[a-f0-9]{32})',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_cart' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(
						'cart_id' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'Unique cart identifier.', 'harmonytics-ucp-connector-woocommerce' ),
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_cart_id' ),
						),
					),
				),
			)
		);

		// POST /cart/{cart_id}/items - Add item to cart.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<cart_id>cart_[a-f0-9]{32})/items',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_item' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_add_item_args(),
				),
			)
		);

		// PUT /cart/{cart_id}/items/{item_key} - Update item quantity.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<cart_id>cart_[a-f0-9]{32})/items/(?P<item_key>item_[a-zA-Z0-9_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_update_item_args(),
				),
			)
		);

		// DELETE /cart/{cart_id}/items/{item_key} - Remove item from cart.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<cart_id>cart_[a-f0-9]{32})/items/(?P<item_key>item_[a-zA-Z0-9_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_item' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => array(
						'cart_id'  => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'Unique cart identifier.', 'harmonytics-ucp-connector-woocommerce' ),
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_cart_id' ),
						),
						'item_key' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'Unique item key within the cart.', 'harmonytics-ucp-connector-woocommerce' ),
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => array( $this, 'validate_item_key' ),
						),
					),
				),
			)
		);

		// POST /cart/{cart_id}/checkout - Convert cart to checkout session.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<cart_id>cart_[a-f0-9]{32})/checkout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'checkout' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_checkout_args(),
				),
			)
		);
	}

	/**
	 * Get arguments for create cart endpoint.
	 *
	 * @return array
	 */
	private function get_create_cart_args() {
		return array(
			'metadata' => array(
				'required'    => false,
				'type'        => 'object',
				'description' => __( 'Optional metadata for the cart (e.g., agent_id, session reference).', 'harmonytics-ucp-connector-woocommerce' ),
			),
		);
	}

	/**
	 * Get arguments for add item endpoint.
	 *
	 * @return array
	 */
	private function get_add_item_args() {
		return array(
			'cart_id'    => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Unique cart identifier.', 'harmonytics-ucp-connector-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_cart_id' ),
			),
			'sku'        => array(
				'required'    => false,
				'type'        => 'string',
				'description' => __( 'Product SKU.', 'harmonytics-ucp-connector-woocommerce' ),
			),
			'product_id' => array(
				'required'    => false,
				'type'        => 'integer',
				'description' => __( 'Product ID.', 'harmonytics-ucp-connector-woocommerce' ),
			),
			'variant_id' => array(
				'required'    => false,
				'type'        => 'integer',
				'description' => __( 'Variant/variation ID.', 'harmonytics-ucp-connector-woocommerce' ),
			),
			'quantity'   => array(
				'required'    => false,
				'type'        => 'integer',
				'description' => __( 'Quantity to add.', 'harmonytics-ucp-connector-woocommerce' ),
				'default'     => 1,
				'minimum'     => 1,
			),
		);
	}

	/**
	 * Get arguments for update item endpoint.
	 *
	 * @return array
	 */
	private function get_update_item_args() {
		return array(
			'cart_id'  => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Unique cart identifier.', 'harmonytics-ucp-connector-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_cart_id' ),
			),
			'item_key' => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Unique item key within the cart.', 'harmonytics-ucp-connector-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_item_key' ),
			),
			'quantity' => array(
				'required'    => true,
				'type'        => 'integer',
				'description' => __( 'New quantity (0 to remove item).', 'harmonytics-ucp-connector-woocommerce' ),
				'minimum'     => 0,
			),
		);
	}

	/**
	 * Get arguments for checkout endpoint.
	 *
	 * @return array
	 */
	private function get_checkout_args() {
		return array(
			'cart_id'          => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Unique cart identifier.', 'harmonytics-ucp-connector-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_cart_id' ),
			),
			'shipping_address' => array(
				'required'    => false,
				'type'        => 'object',
				'description' => __( 'Shipping address.', 'harmonytics-ucp-connector-woocommerce' ),
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
				'description' => __( 'Billing address.', 'harmonytics-ucp-connector-woocommerce' ),
			),
			'coupon_code'      => array(
				'required'    => false,
				'type'        => 'string',
				'description' => __( 'Coupon code to apply.', 'harmonytics-ucp-connector-woocommerce' ),
			),
			'customer_note'    => array(
				'required'    => false,
				'type'        => 'string',
				'description' => __( 'Customer note for the order.', 'harmonytics-ucp-connector-woocommerce' ),
			),
		);
	}

	/**
	 * Validate cart ID format.
	 *
	 * @param string          $cart_id Cart ID.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return bool|WP_Error
	 */
	public function validate_cart_id( $cart_id, $request, $param ) {
		if ( ! preg_match( '/^cart_[a-f0-9]{32}$/', $cart_id ) ) {
			return new WP_Error(
				'invalid_cart_id',
				__( 'Invalid cart ID format.', 'harmonytics-ucp-connector-woocommerce' ),
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * Validate item key format.
	 *
	 * @param string          $item_key Item key.
	 * @param WP_REST_Request $request  Request object.
	 * @param string          $param    Parameter name.
	 * @return bool|WP_Error
	 */
	public function validate_item_key( $item_key, $request, $param ) {
		if ( ! preg_match( '/^item_[a-zA-Z0-9_]+$/', $item_key ) ) {
			return new WP_Error(
				'invalid_item_key',
				__( 'Invalid item key format.', 'harmonytics-ucp-connector-woocommerce' ),
				array( 'status' => 400 )
			);
		}
		return true;
	}

	/**
	 * Create a new cart.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_cart( $request ) {
		$this->log( 'Creating new cart', array( 'metadata' => $request->get_param( 'metadata' ) ) );

		$result = $this->cart->create_cart( $request->get_param( 'metadata' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result, 201 );
	}

	/**
	 * Get cart contents.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_cart( $request ) {
		$cart_id = $request->get_param( 'cart_id' );

		$this->log( 'Getting cart', array( 'cart_id' => $cart_id ) );

		$result = $this->cart->get_cart( $cart_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	/**
	 * Delete/clear cart.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_cart( $request ) {
		$cart_id = $request->get_param( 'cart_id' );

		$this->log( 'Deleting cart', array( 'cart_id' => $cart_id ) );

		$result = $this->cart->delete_cart( $cart_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	/**
	 * Add item to cart.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_item( $request ) {
		$cart_id = $request->get_param( 'cart_id' );

		$item = array(
			'sku'        => $request->get_param( 'sku' ),
			'product_id' => $request->get_param( 'product_id' ),
			'variant_id' => $request->get_param( 'variant_id' ),
			'quantity'   => $request->get_param( 'quantity' ),
		);

		// Validate that at least one product identifier is provided.
		if ( empty( $item['sku'] ) && empty( $item['product_id'] ) && empty( $item['variant_id'] ) ) {
			return $this->error_response(
				'missing_product_identifier',
				__( 'At least one of sku, product_id, or variant_id is required.', 'harmonytics-ucp-connector-woocommerce' ),
				400
			);
		}

		$this->log( 'Adding item to cart', array( 'cart_id' => $cart_id, 'item' => $item ) );

		$result = $this->cart->add_item( $cart_id, $item );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	/**
	 * Update item quantity in cart.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$cart_id  = $request->get_param( 'cart_id' );
		$item_key = $request->get_param( 'item_key' );
		$quantity = $request->get_param( 'quantity' );

		$this->log(
			'Updating cart item',
			array(
				'cart_id'  => $cart_id,
				'item_key' => $item_key,
				'quantity' => $quantity,
			)
		);

		$result = $this->cart->update_item( $cart_id, $item_key, $quantity );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	/**
	 * Remove item from cart.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_item( $request ) {
		$cart_id  = $request->get_param( 'cart_id' );
		$item_key = $request->get_param( 'item_key' );

		$this->log(
			'Removing item from cart',
			array(
				'cart_id'  => $cart_id,
				'item_key' => $item_key,
			)
		);

		$result = $this->cart->remove_item( $cart_id, $item_key );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success_response( $result );
	}

	/**
	 * Convert cart to checkout session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function checkout( $request ) {
		$cart_id = $request->get_param( 'cart_id' );

		$this->log(
			'Converting cart to checkout',
			array(
				'cart_id'          => $cart_id,
				'shipping_address' => $request->get_param( 'shipping_address' ),
			)
		);

		$result = $this->cart->convert_to_checkout(
			$cart_id,
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
}
