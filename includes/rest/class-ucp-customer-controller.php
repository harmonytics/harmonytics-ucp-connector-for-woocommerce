<?php
/**
 * REST controller for customer endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Customer_Controller
 *
 * Handles customer-related REST API endpoints.
 */
class UCP_WC_Customer_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'customers';

	/**
	 * Customer mapper.
	 *
	 * @var UCP_WC_Customer_Mapper
	 */
	protected $mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->mapper = new UCP_WC_Customer_Mapper();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /customers - Create/register a new customer.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_customer' ),
					'permission_callback' => array( $this, 'check_authenticated_write' ),
					'args'                => $this->get_create_customer_args(),
				),
			)
		);

		// GET /customers/{customer_id} - Get customer profile.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_customer' ),
					'permission_callback' => array( $this, 'check_customer_permission' ),
					'args'                => array(
						'customer_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Customer ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// PUT /customers/{customer_id} - Update customer profile.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_customer' ),
					'permission_callback' => array( $this, 'check_customer_permission' ),
					'args'                => $this->get_update_customer_args(),
				),
			)
		);

		// GET /customers/{customer_id}/addresses - Get saved addresses.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/addresses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_addresses' ),
					'permission_callback' => array( $this, 'check_customer_permission' ),
					'args'                => array(
						'customer_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Customer ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST /customers/{customer_id}/addresses - Add new address.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/addresses',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_address' ),
					'permission_callback' => array( $this, 'check_customer_permission' ),
					'args'                => $this->get_add_address_args(),
				),
			)
		);

		// GET /customers/{customer_id}/orders - Get customer order history.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<customer_id>[\d]+)/orders',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_orders' ),
					'permission_callback' => array( $this, 'check_customer_permission' ),
					'args'                => $this->get_orders_args(),
				),
			)
		);

		// POST /customers/lookup - Lookup customer by email.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/lookup',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'lookup_customer' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'email' => array(
							'required'          => true,
							'type'              => 'string',
							'format'            => 'email',
							'description'       => __( 'Customer email address.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'sanitize_email',
						),
					),
				),
			)
		);
	}

	/**
	 * Get arguments for create customer endpoint.
	 *
	 * @return array
	 */
	private function get_create_customer_args() {
		return array(
			'email'            => array(
				'required'          => true,
				'type'              => 'string',
				'format'            => 'email',
				'description'       => __( 'Customer email address.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_email',
			),
			'first_name'       => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Customer first name.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'        => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Customer last name.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'username'         => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Customer username.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_user',
			),
			'password'         => array(
				'required'    => false,
				'type'        => 'string',
				'description' => __( 'Customer password.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'billing_address'  => array(
				'required'    => false,
				'type'        => 'object',
				'description' => __( 'Customer billing address.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'shipping_address' => array(
				'required'    => false,
				'type'        => 'object',
				'description' => __( 'Customer shipping address.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get arguments for update customer endpoint.
	 *
	 * @return array
	 */
	private function get_update_customer_args() {
		return array(
			'customer_id'      => array(
				'required'          => true,
				'type'              => 'integer',
				'description'       => __( 'Customer ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'email'            => array(
				'required'          => false,
				'type'              => 'string',
				'format'            => 'email',
				'description'       => __( 'Customer email address.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_email',
			),
			'first_name'       => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Customer first name.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'last_name'        => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Customer last name.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'billing_address'  => array(
				'required'    => false,
				'type'        => 'object',
				'description' => __( 'Customer billing address.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'shipping_address' => array(
				'required'    => false,
				'type'        => 'object',
				'description' => __( 'Customer shipping address.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get arguments for add address endpoint.
	 *
	 * @return array
	 */
	private function get_add_address_args() {
		return array(
			'customer_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'description'       => __( 'Customer ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'type'        => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'billing', 'shipping' ),
				'default'     => 'shipping',
				'description' => __( 'Address type.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'label'       => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Address label (e.g., "Home", "Work").', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'set_default' => array(
				'required'    => false,
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Set as default address for this type.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'address'     => array(
				'required'    => true,
				'type'        => 'object',
				'description' => __( 'Address details.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get arguments for orders endpoint.
	 *
	 * @return array
	 */
	private function get_orders_args() {
		return array(
			'customer_id' => array(
				'required'          => true,
				'type'              => 'integer',
				'description'       => __( 'Customer ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'page'        => array(
				'required'    => false,
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
				'description' => __( 'Page number.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'per_page'    => array(
				'required'    => false,
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
				'description' => __( 'Items per page.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'status'      => array(
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
		);
	}

	/**
	 * Permission callback for customer-specific endpoints.
	 * Customers can only access their own data unless using admin API keys.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_customer_permission( $request ) {
		// First check if UCP is enabled.
		if ( ! $this->is_ucp_enabled() ) {
			return new WP_Error(
				'ucp_disabled',
				__( 'UCP is currently disabled for this store.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 503 )
			);
		}

		$customer_id = $request->get_param( 'customer_id' );

		// Check if user is admin or has manage_woocommerce capability.
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		// Check if authenticated via API key with admin or write permission.
		if ( $this->has_permission( 'write' ) ) {
			return true;
		}

		// Check if the authenticated user is the customer themselves.
		$current_user_id = get_current_user_id();
		if ( 0 === $current_user_id && ! $this->is_authenticated() ) {
			return new WP_Error(
				'ucp_unauthorized',
				__( 'Authentication is required to access customer data.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 401 )
			);
		}

		if ( $current_user_id !== (int) $customer_id ) {
			return new WP_Error(
				'ucp_forbidden',
				__( 'You do not have permission to access this customer data.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for admin-only endpoints (like lookup).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( $request ) {
		// First check if UCP is enabled.
		if ( ! $this->is_ucp_enabled() ) {
			return new WP_Error(
				'ucp_disabled',
				__( 'UCP is currently disabled for this store.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 503 )
			);
		}

		// Check for admin-level API key permission.
		if ( $this->has_permission( 'admin' ) ) {
			return true;
		}

		// Must have manage_woocommerce capability for lookup.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'ucp_forbidden',
				__( 'Admin or API key authentication is required for customer lookup.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Create/register a new customer.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_customer( $request ) {
		$email = $request->get_param( 'email' );

		$this->log( 'Creating customer', array( 'email' => $email ) );

		// Check if email already exists.
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			return $this->error_response(
				'ucp_customer_exists',
				__( 'A customer with this email address already exists.', 'harmonytics-ucp-connector-for-woocommerce' ),
				409
			);
		}

		// Prepare customer data.
		$data = $this->mapper->map_to_wc(
			array(
				'email'            => $email,
				'first_name'       => $request->get_param( 'first_name' ),
				'last_name'        => $request->get_param( 'last_name' ),
				'username'         => $request->get_param( 'username' ),
				'password'         => $request->get_param( 'password' ),
				'billing_address'  => $request->get_param( 'billing_address' ),
				'shipping_address' => $request->get_param( 'shipping_address' ),
			)
		);

		// Generate username if not provided.
		if ( empty( $data['username'] ) ) {
			$data['username'] = wc_create_new_customer_username( $email );
		}

		// Generate password if not provided.
		if ( empty( $data['password'] ) ) {
			$data['password'] = wp_generate_password( 12, true, true );
		}

		try {
			// Create the customer.
			$customer = new WC_Customer();
			$customer->set_email( $data['email'] );
			$customer->set_username( $data['username'] );
			$customer->set_password( $data['password'] );

			if ( ! empty( $data['first_name'] ) ) {
				$customer->set_first_name( $data['first_name'] );
			}
			if ( ! empty( $data['last_name'] ) ) {
				$customer->set_last_name( $data['last_name'] );
			}

			// Set billing address fields.
			$billing_fields = array(
				'billing_first_name',
				'billing_last_name',
				'billing_company',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_country',
				'billing_phone',
				'billing_email',
			);
			foreach ( $billing_fields as $field ) {
				if ( ! empty( $data[ $field ] ) ) {
					$method = 'set_' . $field;
					$customer->$method( $data[ $field ] );
				}
			}

			// Set shipping address fields.
			$shipping_fields = array(
				'shipping_first_name',
				'shipping_last_name',
				'shipping_company',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postcode',
				'shipping_country',
				'shipping_phone',
			);
			foreach ( $shipping_fields as $field ) {
				if ( ! empty( $data[ $field ] ) ) {
					$method = 'set_' . $field;
					$customer->$method( $data[ $field ] );
				}
			}

			$customer->save();

			$this->log( 'Customer created', array( 'customer_id' => $customer->get_id() ) );

			return $this->success_response(
				array(
					'customer' => $this->mapper->map_customer( $customer ),
					'message'  => __( 'Customer created successfully.', 'harmonytics-ucp-connector-for-woocommerce' ),
				),
				201
			);

		} catch ( Exception $e ) {
			$this->log( 'Customer creation failed', array( 'error' => $e->getMessage() ) );

			return $this->error_response(
				'ucp_customer_creation_failed',
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Get customer profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_customer( $request ) {
		$customer_id = $request->get_param( 'customer_id' );

		$this->log( 'Getting customer', array( 'customer_id' => $customer_id ) );

		try {
			$customer = new WC_Customer( $customer_id );

			if ( 0 === $customer->get_id() ) {
				return $this->error_response(
					'ucp_customer_not_found',
					__( 'Customer not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
					404
				);
			}

			return $this->success_response( $this->mapper->map_customer( $customer ) );

		} catch ( Exception $e ) {
			return $this->error_response(
				'ucp_customer_not_found',
				__( 'Customer not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}
	}

	/**
	 * Update customer profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_customer( $request ) {
		$customer_id = $request->get_param( 'customer_id' );

		$this->log( 'Updating customer', array( 'customer_id' => $customer_id ) );

		try {
			$customer = new WC_Customer( $customer_id );

			if ( 0 === $customer->get_id() ) {
				return $this->error_response(
					'ucp_customer_not_found',
					__( 'Customer not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
					404
				);
			}

			// Prepare update data.
			$data = $this->mapper->map_to_wc(
				array(
					'email'            => $request->get_param( 'email' ),
					'first_name'       => $request->get_param( 'first_name' ),
					'last_name'        => $request->get_param( 'last_name' ),
					'billing_address'  => $request->get_param( 'billing_address' ),
					'shipping_address' => $request->get_param( 'shipping_address' ),
				)
			);

			// Update basic fields.
			if ( ! empty( $data['email'] ) ) {
				// Check if email is already used by another user.
				$existing_user = get_user_by( 'email', $data['email'] );
				if ( $existing_user && $existing_user->ID !== $customer_id ) {
					return $this->error_response(
						'ucp_email_exists',
						__( 'This email address is already in use by another customer.', 'harmonytics-ucp-connector-for-woocommerce' ),
						409
					);
				}
				$customer->set_email( $data['email'] );
			}

			if ( isset( $data['first_name'] ) ) {
				$customer->set_first_name( $data['first_name'] );
			}
			if ( isset( $data['last_name'] ) ) {
				$customer->set_last_name( $data['last_name'] );
			}

			// Update billing address fields.
			$billing_fields = array(
				'billing_first_name',
				'billing_last_name',
				'billing_company',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_state',
				'billing_postcode',
				'billing_country',
				'billing_phone',
				'billing_email',
			);
			foreach ( $billing_fields as $field ) {
				if ( isset( $data[ $field ] ) ) {
					$method = 'set_' . $field;
					$customer->$method( $data[ $field ] );
				}
			}

			// Update shipping address fields.
			$shipping_fields = array(
				'shipping_first_name',
				'shipping_last_name',
				'shipping_company',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_state',
				'shipping_postcode',
				'shipping_country',
				'shipping_phone',
			);
			foreach ( $shipping_fields as $field ) {
				if ( isset( $data[ $field ] ) ) {
					$method = 'set_' . $field;
					$customer->$method( $data[ $field ] );
				}
			}

			$customer->save();

			$this->log( 'Customer updated', array( 'customer_id' => $customer_id ) );

			return $this->success_response(
				array(
					'customer' => $this->mapper->map_customer( $customer ),
					'message'  => __( 'Customer updated successfully.', 'harmonytics-ucp-connector-for-woocommerce' ),
				)
			);

		} catch ( Exception $e ) {
			$this->log( 'Customer update failed', array( 'error' => $e->getMessage() ) );

			return $this->error_response(
				'ucp_customer_update_failed',
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Get saved addresses for a customer.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_addresses( $request ) {
		$customer_id = $request->get_param( 'customer_id' );

		$this->log( 'Getting customer addresses', array( 'customer_id' => $customer_id ) );

		try {
			$customer = new WC_Customer( $customer_id );

			if ( 0 === $customer->get_id() ) {
				return $this->error_response(
					'ucp_customer_not_found',
					__( 'Customer not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
					404
				);
			}

			$addresses = $this->mapper->get_customer_addresses( $customer );

			return $this->success_response(
				array(
					'customer_id' => $customer_id,
					'addresses'   => $addresses,
				)
			);

		} catch ( Exception $e ) {
			return $this->error_response(
				'ucp_customer_not_found',
				__( 'Customer not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}
	}

	/**
	 * Add a new address for a customer.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_address( $request ) {
		$customer_id = $request->get_param( 'customer_id' );
		$type        = $request->get_param( 'type' );
		$label       = $request->get_param( 'label' );
		$set_default = $request->get_param( 'set_default' );
		$address     = $request->get_param( 'address' );

		$this->log(
			'Adding customer address',
			array(
				'customer_id' => $customer_id,
				'type'        => $type,
			)
		);

		try {
			$customer = new WC_Customer( $customer_id );

			if ( 0 === $customer->get_id() ) {
				return $this->error_response(
					'ucp_customer_not_found',
					__( 'Customer not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
					404
				);
			}

			// Validate address.
			$address_mapper = new UCP_WC_Address_Mapper();
			$errors         = $address_mapper->validate( $address );
			if ( ! empty( $errors ) ) {
				return $this->error_response(
					'ucp_invalid_address',
					implode( ' ', $errors ),
					400
				);
			}

			// Map address to WC format.
			$wc_address = $address_mapper->map_to_wc( $address );

			if ( $set_default ) {
				// Set as default billing or shipping address.
				if ( 'billing' === $type ) {
					$customer->set_billing_first_name( $wc_address['first_name'] );
					$customer->set_billing_last_name( $wc_address['last_name'] );
					$customer->set_billing_company( $wc_address['company'] );
					$customer->set_billing_address_1( $wc_address['address_1'] );
					$customer->set_billing_address_2( $wc_address['address_2'] );
					$customer->set_billing_city( $wc_address['city'] );
					$customer->set_billing_state( $wc_address['state'] );
					$customer->set_billing_postcode( $wc_address['postcode'] );
					$customer->set_billing_country( $wc_address['country'] );
					$customer->set_billing_phone( $wc_address['phone'] );
					if ( ! empty( $wc_address['email'] ) ) {
						$customer->set_billing_email( $wc_address['email'] );
					}
				} else {
					$customer->set_shipping_first_name( $wc_address['first_name'] );
					$customer->set_shipping_last_name( $wc_address['last_name'] );
					$customer->set_shipping_company( $wc_address['company'] );
					$customer->set_shipping_address_1( $wc_address['address_1'] );
					$customer->set_shipping_address_2( $wc_address['address_2'] );
					$customer->set_shipping_city( $wc_address['city'] );
					$customer->set_shipping_state( $wc_address['state'] );
					$customer->set_shipping_postcode( $wc_address['postcode'] );
					$customer->set_shipping_country( $wc_address['country'] );
					$customer->set_shipping_phone( $wc_address['phone'] );
				}
			} else {
				// Store as additional address in customer meta.
				$additional_addresses = $customer->get_meta( '_ucp_additional_addresses' );
				if ( ! is_array( $additional_addresses ) ) {
					$additional_addresses = array();
				}

				$wc_address['type']     = $type;
				$wc_address['label']    = $label;
				$additional_addresses[] = $wc_address;

				$customer->update_meta_data( '_ucp_additional_addresses', $additional_addresses );
			}

			$customer->save();

			$this->log(
				'Address added',
				array(
					'customer_id' => $customer_id,
					'type'        => $type,
				)
			);

			// Return updated addresses list.
			$addresses = $this->mapper->get_customer_addresses( $customer );

			return $this->success_response(
				array(
					'customer_id' => $customer_id,
					'addresses'   => $addresses,
					'message'     => __( 'Address added successfully.', 'harmonytics-ucp-connector-for-woocommerce' ),
				),
				201
			);

		} catch ( Exception $e ) {
			$this->log( 'Add address failed', array( 'error' => $e->getMessage() ) );

			return $this->error_response(
				'ucp_add_address_failed',
				$e->getMessage(),
				500
			);
		}
	}

	/**
	 * Get customer order history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_orders( $request ) {
		$customer_id = $request->get_param( 'customer_id' );
		$page        = $request->get_param( 'page' );
		$per_page    = $request->get_param( 'per_page' );
		$status      = $request->get_param( 'status' );

		$this->log( 'Getting customer orders', array( 'customer_id' => $customer_id ) );

		try {
			$customer = new WC_Customer( $customer_id );

			if ( 0 === $customer->get_id() ) {
				return $this->error_response(
					'ucp_customer_not_found',
					__( 'Customer not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
					404
				);
			}

			$result = $this->mapper->get_paginated_orders(
				$customer_id,
				array(
					'page'     => $page,
					'per_page' => $per_page,
					'status'   => $status,
				)
			);

			$response = $this->success_response( $result );

			// Add pagination headers.
			$response->header( 'X-WP-Total', $result['total'] );
			$response->header( 'X-WP-TotalPages', $result['total_pages'] );

			return $response;

		} catch ( Exception $e ) {
			return $this->error_response(
				'ucp_customer_not_found',
				__( 'Customer not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}
	}

	/**
	 * Lookup customer by email (admin/AI agent only).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function lookup_customer( $request ) {
		$email = $request->get_param( 'email' );

		$this->log( 'Looking up customer', array( 'email' => $email ) );

		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return $this->success_response(
				array(
					'found'    => false,
					'email'    => $email,
					'customer' => null,
					'message'  => __( 'No customer found with this email address.', 'harmonytics-ucp-connector-for-woocommerce' ),
				)
			);
		}

		try {
			$customer = new WC_Customer( $user->ID );

			if ( 0 === $customer->get_id() ) {
				return $this->success_response(
					array(
						'found'    => false,
						'email'    => $email,
						'customer' => null,
						'message'  => __( 'No customer found with this email address.', 'harmonytics-ucp-connector-for-woocommerce' ),
					)
				);
			}

			return $this->success_response(
				array(
					'found'    => true,
					'email'    => $email,
					'customer' => $this->mapper->map_customer( $customer ),
				)
			);

		} catch ( Exception $e ) {
			return $this->success_response(
				array(
					'found'    => false,
					'email'    => $email,
					'customer' => null,
					'message'  => __( 'No customer found with this email address.', 'harmonytics-ucp-connector-for-woocommerce' ),
				)
			);
		}
	}
}
