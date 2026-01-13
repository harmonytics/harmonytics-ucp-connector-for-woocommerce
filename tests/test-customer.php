<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the Customer capability.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Customer
 *
 * Tests the customer functionality.
 */
class Test_UCP_Customer extends WC_Unit_Test_Case {

	/**
	 * Customer controller instance.
	 *
	 * @var UCP_WC_Customer_Controller
	 */
	protected $controller;

	/**
	 * Customer mapper instance.
	 *
	 * @var UCP_WC_Customer_Mapper
	 */
	protected $mapper;

	/**
	 * Test customer.
	 *
	 * @var WC_Customer
	 */
	protected $test_customer;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected $admin_user_id;

	/**
	 * Regular user ID.
	 *
	 * @var int
	 */
	protected $regular_user_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Load required classes.
		require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-address-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-line-item-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-shipping-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-order-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-customer-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-rest-controller.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-customer-controller.php';

		// Create tables.
		UCP_WC_Activator::activate();

		$this->controller = new UCP_WC_Customer_Controller();
		$this->mapper     = new UCP_WC_Customer_Mapper();

		// Create an admin user.
		$this->admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		// Grant WooCommerce capabilities to admin.
		$admin_user = new WP_User( $this->admin_user_id );
		$admin_user->add_cap( 'manage_woocommerce' );

		// Create a regular customer user.
		$this->regular_user_id = $this->factory->user->create(
			array(
				'role'       => 'customer',
				'user_email' => 'regular@example.com',
			)
		);

		// Create a test WooCommerce customer.
		$this->test_customer = new WC_Customer();
		$this->test_customer->set_email( 'test@example.com' );
		$this->test_customer->set_first_name( 'John' );
		$this->test_customer->set_last_name( 'Doe' );
		$this->test_customer->set_billing_first_name( 'John' );
		$this->test_customer->set_billing_last_name( 'Doe' );
		$this->test_customer->set_billing_address_1( '123 Main St' );
		$this->test_customer->set_billing_city( 'New York' );
		$this->test_customer->set_billing_state( 'NY' );
		$this->test_customer->set_billing_postcode( '10001' );
		$this->test_customer->set_billing_country( 'US' );
		$this->test_customer->set_billing_phone( '555-1234' );
		$this->test_customer->set_billing_email( 'test@example.com' );
		$this->test_customer->set_shipping_first_name( 'John' );
		$this->test_customer->set_shipping_last_name( 'Doe' );
		$this->test_customer->set_shipping_address_1( '456 Oak Ave' );
		$this->test_customer->set_shipping_city( 'Los Angeles' );
		$this->test_customer->set_shipping_state( 'CA' );
		$this->test_customer->set_shipping_postcode( '90001' );
		$this->test_customer->set_shipping_country( 'US' );
		$this->test_customer->save();

		// Enable UCP.
		update_option( 'ucp_wc_enabled', 'yes' );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		// Reset current user.
		wp_set_current_user( 0 );

		// Clean up test customer.
		if ( $this->test_customer && $this->test_customer->get_id() ) {
			$this->test_customer->delete( true );
		}

		// Clean up users.
		if ( $this->admin_user_id ) {
			wp_delete_user( $this->admin_user_id );
		}

		if ( $this->regular_user_id ) {
			wp_delete_user( $this->regular_user_id );
		}

		parent::tear_down();
	}

	/**
	 * Test creating a new customer.
	 */
	public function test_create_customer() {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/ucp/v1/customers' );
		$request->set_param( 'email', 'newcustomer@example.com' );
		$request->set_param( 'first_name', 'Jane' );
		$request->set_param( 'last_name', 'Smith' );

		$response = $this->controller->create_customer( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'customer', $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertEquals( 'newcustomer@example.com', $data['customer']['email'] );
		$this->assertEquals( 'Jane', $data['customer']['first_name'] );
		$this->assertEquals( 'Smith', $data['customer']['last_name'] );

		// Clean up the created customer.
		$new_customer = new WC_Customer( $data['customer']['id'] );
		$new_customer->delete( true );
	}

	/**
	 * Test creating a customer with duplicate email returns error.
	 */
	public function test_create_customer_duplicate_email() {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/ucp/v1/customers' );
		$request->set_param( 'email', 'test@example.com' ); // Existing email.
		$request->set_param( 'first_name', 'Duplicate' );
		$request->set_param( 'last_name', 'User' );

		$response = $this->controller->create_customer( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'ucp_customer_exists', $response->get_error_code() );

		$error_data = $response->get_error_data();
		$this->assertEquals( 409, $error_data['status'] );
	}

	/**
	 * Test getting customer profile.
	 */
	public function test_get_customer_profile() {
		wp_set_current_user( $this->test_customer->get_id() );

		$request = new WP_REST_Request( 'GET', '/ucp/v1/customers/' . $this->test_customer->get_id() );
		$request->set_param( 'customer_id', $this->test_customer->get_id() );

		$response = $this->controller->get_customer( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( $this->test_customer->get_id(), $data['id'] );
		$this->assertEquals( 'test@example.com', $data['email'] );
		$this->assertEquals( 'John', $data['first_name'] );
		$this->assertEquals( 'Doe', $data['last_name'] );
		$this->assertArrayHasKey( 'billing_address', $data );
		$this->assertArrayHasKey( 'shipping_address', $data );
	}

	/**
	 * Test getting customer without authentication returns 401.
	 */
	public function test_get_customer_unauthorized() {
		// Ensure no user is logged in.
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/ucp/v1/customers/' . $this->test_customer->get_id() );
		$request->set_param( 'customer_id', $this->test_customer->get_id() );

		$result = $this->controller->check_customer_permission( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ucp_unauthorized', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 401, $error_data['status'] );
	}

	/**
	 * Test accessing another customer's data returns 403.
	 */
	public function test_get_customer_forbidden() {
		// Log in as regular user.
		wp_set_current_user( $this->regular_user_id );

		$request = new WP_REST_Request( 'GET', '/ucp/v1/customers/' . $this->test_customer->get_id() );
		$request->set_param( 'customer_id', $this->test_customer->get_id() );

		$result = $this->controller->check_customer_permission( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ucp_forbidden', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 403, $error_data['status'] );
	}

	/**
	 * Test updating customer profile.
	 */
	public function test_update_customer_profile() {
		wp_set_current_user( $this->test_customer->get_id() );

		$request = new WP_REST_Request( 'PUT', '/ucp/v1/customers/' . $this->test_customer->get_id() );
		$request->set_param( 'customer_id', $this->test_customer->get_id() );
		$request->set_param( 'first_name', 'Johnny' );
		$request->set_param( 'last_name', 'Updated' );

		$response = $this->controller->update_customer( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'customer', $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertEquals( 'Johnny', $data['customer']['first_name'] );
		$this->assertEquals( 'Updated', $data['customer']['last_name'] );
	}

	/**
	 * Test getting customer saved addresses.
	 */
	public function test_get_customer_addresses() {
		wp_set_current_user( $this->test_customer->get_id() );

		$request = new WP_REST_Request( 'GET', '/ucp/v1/customers/' . $this->test_customer->get_id() . '/addresses' );
		$request->set_param( 'customer_id', $this->test_customer->get_id() );

		$response = $this->controller->get_addresses( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'customer_id', $data );
		$this->assertArrayHasKey( 'addresses', $data );
		$this->assertEquals( $this->test_customer->get_id(), $data['customer_id'] );
		$this->assertIsArray( $data['addresses'] );

		// Should have at least billing and shipping addresses.
		$this->assertGreaterThanOrEqual( 2, count( $data['addresses'] ) );

		// Verify address structure.
		$billing_found  = false;
		$shipping_found = false;
		foreach ( $data['addresses'] as $address ) {
			$this->assertArrayHasKey( 'id', $address );
			$this->assertArrayHasKey( 'type', $address );
			$this->assertArrayHasKey( 'default', $address );
			$this->assertArrayHasKey( 'address', $address );

			if ( 'billing' === $address['type'] ) {
				$billing_found = true;
			}
			if ( 'shipping' === $address['type'] ) {
				$shipping_found = true;
			}
		}

		$this->assertTrue( $billing_found, 'Billing address should be present' );
		$this->assertTrue( $shipping_found, 'Shipping address should be present' );
	}

	/**
	 * Test adding a new customer address.
	 */
	public function test_add_customer_address() {
		wp_set_current_user( $this->test_customer->get_id() );

		$request = new WP_REST_Request( 'POST', '/ucp/v1/customers/' . $this->test_customer->get_id() . '/addresses' );
		$request->set_param( 'customer_id', $this->test_customer->get_id() );
		$request->set_param( 'type', 'shipping' );
		$request->set_param( 'label', 'Work' );
		$request->set_param( 'set_default', false );
		$request->set_param(
			'address',
			array(
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'address_1'  => '789 Work Blvd',
				'city'       => 'Chicago',
				'state'      => 'IL',
				'postcode'   => '60601',
				'country'    => 'US',
				'phone'      => '555-9876',
			)
		);

		$response = $this->controller->add_address( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'customer_id', $data );
		$this->assertArrayHasKey( 'addresses', $data );
		$this->assertArrayHasKey( 'message', $data );

		// Verify the new address was added.
		$additional_found = false;
		foreach ( $data['addresses'] as $address ) {
			if ( strpos( $address['id'], 'additional_' ) === 0 ) {
				$additional_found = true;
				$this->assertEquals( 'Work', $address['label'] );
				$this->assertFalse( $address['default'] );
			}
		}
		$this->assertTrue( $additional_found, 'Additional address should be present' );
	}

	/**
	 * Test getting customer order history.
	 */
	public function test_get_customer_orders() {
		wp_set_current_user( $this->test_customer->get_id() );

		// Create an order for the test customer.
		$order = WC_Helper_Order::create_order( $this->test_customer->get_id() );
		$order->save();

		$request = new WP_REST_Request( 'GET', '/ucp/v1/customers/' . $this->test_customer->get_id() . '/orders' );
		$request->set_param( 'customer_id', $this->test_customer->get_id() );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 10 );
		$request->set_param( 'status', 'any' );

		$response = $this->controller->get_orders( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'orders', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'total_pages', $data );
		$this->assertArrayHasKey( 'page', $data );
		$this->assertArrayHasKey( 'per_page', $data );

		$this->assertIsArray( $data['orders'] );
		$this->assertGreaterThanOrEqual( 1, $data['total'] );

		// Clean up order.
		$order->delete( true );
	}

	/**
	 * Test admin lookup of customer by email.
	 */
	public function test_customer_lookup_by_email() {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/ucp/v1/customers/lookup' );
		$request->set_param( 'email', 'test@example.com' );

		$response = $this->controller->lookup_customer( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'found', $data );
		$this->assertArrayHasKey( 'email', $data );
		$this->assertArrayHasKey( 'customer', $data );
		$this->assertTrue( $data['found'] );
		$this->assertEquals( 'test@example.com', $data['email'] );
		$this->assertEquals( $this->test_customer->get_id(), $data['customer']['id'] );
	}

	/**
	 * Test non-admin cannot lookup customers.
	 */
	public function test_customer_lookup_unauthorized() {
		wp_set_current_user( $this->regular_user_id );

		$request = new WP_REST_Request( 'POST', '/ucp/v1/customers/lookup' );
		$request->set_param( 'email', 'test@example.com' );

		$result = $this->controller->check_admin_permission( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ucp_forbidden', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 403, $error_data['status'] );
	}

	/**
	 * Test customer mapper output format.
	 */
	public function test_customer_mapper() {
		$result = $this->mapper->map_customer( $this->test_customer );

		$this->assertIsArray( $result );

		// Verify required fields are present.
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertArrayHasKey( 'first_name', $result );
		$this->assertArrayHasKey( 'last_name', $result );
		$this->assertArrayHasKey( 'display_name', $result );
		$this->assertArrayHasKey( 'billing_address', $result );
		$this->assertArrayHasKey( 'shipping_address', $result );
		$this->assertArrayHasKey( 'is_paying_customer', $result );
		$this->assertArrayHasKey( 'order_count', $result );
		$this->assertArrayHasKey( 'total_spent', $result );
		$this->assertArrayHasKey( 'currency', $result );
		$this->assertArrayHasKey( 'created_at', $result );

		// Verify values.
		$this->assertEquals( $this->test_customer->get_id(), $result['id'] );
		$this->assertEquals( 'test@example.com', $result['email'] );
		$this->assertEquals( 'John', $result['first_name'] );
		$this->assertEquals( 'Doe', $result['last_name'] );
		$this->assertEquals( get_woocommerce_currency(), $result['currency'] );

		// Verify billing address structure.
		$this->assertIsArray( $result['billing_address'] );
		$this->assertArrayHasKey( 'first_name', $result['billing_address'] );
		$this->assertArrayHasKey( 'last_name', $result['billing_address'] );

		// Verify shipping address structure.
		$this->assertIsArray( $result['shipping_address'] );
		$this->assertArrayHasKey( 'first_name', $result['shipping_address'] );
		$this->assertArrayHasKey( 'last_name', $result['shipping_address'] );
	}

	/**
	 * Test customer order count is correct.
	 */
	public function test_customer_order_count() {
		// Create multiple orders for the test customer.
		$order1 = WC_Helper_Order::create_order( $this->test_customer->get_id() );
		$order1->set_status( 'completed' );
		$order1->save();

		$order2 = WC_Helper_Order::create_order( $this->test_customer->get_id() );
		$order2->set_status( 'completed' );
		$order2->save();

		$order3 = WC_Helper_Order::create_order( $this->test_customer->get_id() );
		$order3->set_status( 'processing' );
		$order3->save();

		// Refresh the customer to get updated stats.
		$customer = new WC_Customer( $this->test_customer->get_id() );

		$result = $this->mapper->map_customer( $customer );

		$this->assertArrayHasKey( 'order_count', $result );
		$this->assertGreaterThanOrEqual( 3, $result['order_count'] );

		// Clean up orders.
		$order1->delete( true );
		$order2->delete( true );
		$order3->delete( true );
	}

	/**
	 * Test customer total spent calculation.
	 */
	public function test_customer_total_spent() {
		// Create orders with known totals.
		$order1 = WC_Helper_Order::create_order( $this->test_customer->get_id() );
		$order1->set_total( 50.00 );
		$order1->set_status( 'completed' );
		$order1->save();

		$order2 = WC_Helper_Order::create_order( $this->test_customer->get_id() );
		$order2->set_total( 75.00 );
		$order2->set_status( 'completed' );
		$order2->save();

		// Refresh the customer to get updated stats.
		$customer = new WC_Customer( $this->test_customer->get_id() );

		$result = $this->mapper->map_customer( $customer );

		$this->assertArrayHasKey( 'total_spent', $result );
		// Total should be at least 125.00 from our two orders.
		$this->assertGreaterThanOrEqual( 125.00, floatval( $result['total_spent'] ) );
		$this->assertArrayHasKey( 'currency', $result );

		// Clean up orders.
		$order1->delete( true );
		$order2->delete( true );
	}

	/**
	 * Test customer summary mapper.
	 */
	public function test_customer_summary_mapper() {
		$result = $this->mapper->map_customer_summary( $this->test_customer );

		$this->assertIsArray( $result );

		// Summary should have fewer fields than full customer.
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'email', $result );
		$this->assertArrayHasKey( 'first_name', $result );
		$this->assertArrayHasKey( 'last_name', $result );
		$this->assertArrayHasKey( 'display_name', $result );
		$this->assertArrayHasKey( 'is_paying_customer', $result );
		$this->assertArrayHasKey( 'order_count', $result );
		$this->assertArrayHasKey( 'created_at', $result );

		// Summary should NOT have addresses.
		$this->assertArrayNotHasKey( 'billing_address', $result );
		$this->assertArrayNotHasKey( 'shipping_address', $result );
		$this->assertArrayNotHasKey( 'total_spent', $result );
	}

	/**
	 * Test lookup returns not found for non-existent email.
	 */
	public function test_customer_lookup_not_found() {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/ucp/v1/customers/lookup' );
		$request->set_param( 'email', 'nonexistent@example.com' );

		$response = $this->controller->lookup_customer( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'found', $data );
		$this->assertFalse( $data['found'] );
		$this->assertNull( $data['customer'] );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test getting non-existent customer returns error.
	 */
	public function test_get_customer_not_found() {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'GET', '/ucp/v1/customers/999999' );
		$request->set_param( 'customer_id', 999999 );

		$response = $this->controller->get_customer( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'ucp_customer_not_found', $response->get_error_code() );

		$error_data = $response->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test admin can access any customer's data.
	 */
	public function test_admin_can_access_any_customer() {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'GET', '/ucp/v1/customers/' . $this->test_customer->get_id() );
		$request->set_param( 'customer_id', $this->test_customer->get_id() );

		$result = $this->controller->check_customer_permission( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Test map_to_wc converts input data correctly.
	 */
	public function test_mapper_map_to_wc() {
		$input = array(
			'email'           => 'mapped@example.com',
			'first_name'      => 'Mapped',
			'last_name'       => 'User',
			'username'        => 'mappeduser',
			'password'        => 'securepassword',
			'billing_address' => array(
				'first_name' => 'Mapped',
				'last_name'  => 'Billing',
				'address_1'  => '100 Billing St',
				'city'       => 'Billing City',
				'state'      => 'BC',
				'postcode'   => '12345',
				'country'    => 'US',
			),
		);

		$result = $this->mapper->map_to_wc( $input );

		$this->assertIsArray( $result );
		$this->assertEquals( 'mapped@example.com', $result['email'] );
		$this->assertEquals( 'Mapped', $result['first_name'] );
		$this->assertEquals( 'User', $result['last_name'] );
		$this->assertEquals( 'mappeduser', $result['username'] );
		$this->assertEquals( 'securepassword', $result['password'] );

		// Check billing address fields are prefixed correctly.
		$this->assertArrayHasKey( 'billing_first_name', $result );
		$this->assertArrayHasKey( 'billing_last_name', $result );
		$this->assertArrayHasKey( 'billing_address_1', $result );
		$this->assertArrayHasKey( 'billing_city', $result );
	}
}
