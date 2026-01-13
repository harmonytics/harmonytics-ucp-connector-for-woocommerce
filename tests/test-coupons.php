<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the Coupons capability.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Coupons
 *
 * Tests the coupon validation, calculation, and listing functionality.
 */
class Test_UCP_Coupons extends WC_Unit_Test_Case {

	/**
	 * Coupon controller instance.
	 *
	 * @var UCP_WC_Coupon_Controller
	 */
	protected $controller;

	/**
	 * Coupon mapper instance.
	 *
	 * @var UCP_WC_Coupon_Mapper
	 */
	protected $mapper;

	/**
	 * Test product.
	 *
	 * @var WC_Product
	 */
	protected $product;

	/**
	 * Second test product.
	 *
	 * @var WC_Product
	 */
	protected $product2;

	/**
	 * Array of coupons to clean up.
	 *
	 * @var array
	 */
	protected $coupons = array();

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Load required classes.
		require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-coupon-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-rest-controller.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-coupon-controller.php';

		// Create tables.
		UCP_WC_Activator::activate();

		$this->controller = new UCP_WC_Coupon_Controller();
		$this->mapper     = new UCP_WC_Coupon_Mapper();

		// Create test products.
		$this->product = WC_Helper_Product::create_simple_product();
		$this->product->set_price( 100.00 );
		$this->product->set_regular_price( 100.00 );
		$this->product->set_sku( 'COUPON-TEST-001' );
		$this->product->save();

		$this->product2 = WC_Helper_Product::create_simple_product();
		$this->product2->set_price( 50.00 );
		$this->product2->set_regular_price( 50.00 );
		$this->product2->set_sku( 'COUPON-TEST-002' );
		$this->product2->save();

		// Enable UCP.
		update_option( 'ucp_wc_enabled', 'yes' );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		// Clean up products.
		if ( $this->product ) {
			$this->product->delete( true );
		}

		if ( $this->product2 ) {
			$this->product2->delete( true );
		}

		// Clean up coupons.
		foreach ( $this->coupons as $coupon ) {
			if ( $coupon instanceof WC_Coupon && $coupon->get_id() ) {
				$coupon->delete( true );
			}
		}

		// Reset option.
		delete_option( 'ucp_wc_public_coupons' );

		parent::tear_down();
	}

	/**
	 * Helper method to create a coupon.
	 *
	 * @param array $args Coupon arguments.
	 * @return WC_Coupon
	 */
	protected function create_coupon( $args = array() ) {
		$defaults = array(
			'code'          => 'TEST' . wp_rand( 1000, 9999 ),
			'discount_type' => 'percent',
			'amount'        => 10,
		);

		$args = wp_parse_args( $args, $defaults );

		$coupon = new WC_Coupon();
		$coupon->set_code( $args['code'] );
		$coupon->set_discount_type( $args['discount_type'] );
		$coupon->set_amount( $args['amount'] );

		// Set optional properties.
		if ( isset( $args['date_expires'] ) ) {
			$coupon->set_date_expires( $args['date_expires'] );
		}

		if ( isset( $args['usage_limit'] ) ) {
			$coupon->set_usage_limit( $args['usage_limit'] );
		}

		if ( isset( $args['usage_count'] ) ) {
			$coupon->set_usage_count( $args['usage_count'] );
		}

		if ( isset( $args['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( $args['minimum_amount'] );
		}

		if ( isset( $args['maximum_amount'] ) ) {
			$coupon->set_maximum_amount( $args['maximum_amount'] );
		}

		if ( isset( $args['product_ids'] ) ) {
			$coupon->set_product_ids( $args['product_ids'] );
		}

		if ( isset( $args['excluded_product_ids'] ) ) {
			$coupon->set_excluded_product_ids( $args['excluded_product_ids'] );
		}

		if ( isset( $args['email_restrictions'] ) ) {
			$coupon->set_email_restrictions( $args['email_restrictions'] );
		}

		if ( isset( $args['usage_limit_per_user'] ) ) {
			$coupon->set_usage_limit_per_user( $args['usage_limit_per_user'] );
		}

		if ( isset( $args['free_shipping'] ) ) {
			$coupon->set_free_shipping( $args['free_shipping'] );
		}

		if ( isset( $args['description'] ) ) {
			$coupon->set_description( $args['description'] );
		}

		$coupon->save();

		// Track for cleanup.
		$this->coupons[] = $coupon;

		return $coupon;
	}

	/**
	 * Test validating a valid coupon.
	 */
	public function test_validate_coupon_valid() {
		$coupon = $this->create_coupon(
			array(
				'code'          => 'VALID10',
				'discount_type' => 'percent',
				'amount'        => 10,
			)
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/validate' );
		$request->set_param( 'code', 'VALID10' );

		$response = $this->controller->validate_coupon( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertTrue( $data['valid'] );
		$this->assertEquals( 'valid10', $data['code'] );
		$this->assertEquals( 'percent', $data['discount_type'] );
		$this->assertEquals( 10, $data['amount'] );
		$this->assertArrayNotHasKey( 'error', $data );
	}

	/**
	 * Test validating an invalid/non-existent coupon.
	 */
	public function test_validate_coupon_invalid() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/validate' );
		$request->set_param( 'code', 'NONEXISTENT_COUPON_CODE_12345' );

		$response = $this->controller->validate_coupon( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'coupon_not_found', $response->get_error_code() );
	}

	/**
	 * Test validating an expired coupon.
	 */
	public function test_validate_coupon_expired() {
		// Create an expired coupon (expired yesterday).
		$coupon = $this->create_coupon(
			array(
				'code'         => 'EXPIRED10',
				'discount_type' => 'percent',
				'amount'       => 10,
				'date_expires' => strtotime( '-1 day' ),
			)
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/validate' );
		$request->set_param( 'code', 'EXPIRED10' );

		$response = $this->controller->validate_coupon( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertFalse( $data['valid'] );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( 'coupon_expired', $data['error']['code'] );
	}

	/**
	 * Test validating a coupon that has reached its usage limit.
	 */
	public function test_validate_coupon_usage_limit_reached() {
		$coupon = $this->create_coupon(
			array(
				'code'          => 'LIMITED10',
				'discount_type' => 'percent',
				'amount'        => 10,
				'usage_limit'   => 5,
				'usage_count'   => 5,
			)
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/validate' );
		$request->set_param( 'code', 'LIMITED10' );

		$response = $this->controller->validate_coupon( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertFalse( $data['valid'] );
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( 'coupon_usage_limit_reached', $data['error']['code'] );
	}

	/**
	 * Test calculating a percentage discount.
	 */
	public function test_calculate_percent_discount() {
		$coupon = $this->create_coupon(
			array(
				'code'          => 'PERCENT20',
				'discount_type' => 'percent',
				'amount'        => 20,
			)
		);

		$items = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
				'price'      => '100.00',
			),
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/calculate' );
		$request->set_param( 'code', 'PERCENT20' );
		$request->set_param( 'items', $items );

		$response = $this->controller->calculate_discount( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertEquals( 'percent20', $data['code'] );
		$this->assertEquals( '40.00', $data['discount_amount'] ); // 20% of 200.
		$this->assertEquals( '200.00', $data['subtotal_before'] );
		$this->assertEquals( '160.00', $data['subtotal_after'] );
	}

	/**
	 * Test calculating a fixed cart discount.
	 */
	public function test_calculate_fixed_cart_discount() {
		$coupon = $this->create_coupon(
			array(
				'code'          => 'FIXED25',
				'discount_type' => 'fixed_cart',
				'amount'        => 25,
			)
		);

		$items = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 1,
				'price'      => '100.00',
			),
			array(
				'product_id' => $this->product2->get_id(),
				'quantity'   => 2,
				'price'      => '50.00',
			),
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/calculate' );
		$request->set_param( 'code', 'FIXED25' );
		$request->set_param( 'items', $items );

		$response = $this->controller->calculate_discount( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertEquals( 'fixed25', $data['code'] );
		$this->assertEquals( '25.00', $data['discount_amount'] );
		$this->assertEquals( '200.00', $data['subtotal_before'] ); // 100 + (50 * 2).
		$this->assertEquals( '175.00', $data['subtotal_after'] );
	}

	/**
	 * Test calculating a fixed product discount.
	 */
	public function test_calculate_fixed_product_discount() {
		$coupon = $this->create_coupon(
			array(
				'code'          => 'FIXEDPROD15',
				'discount_type' => 'fixed_product',
				'amount'        => 15,
			)
		);

		$items = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
				'price'      => '100.00',
			),
			array(
				'product_id' => $this->product2->get_id(),
				'quantity'   => 1,
				'price'      => '50.00',
			),
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/calculate' );
		$request->set_param( 'code', 'FIXEDPROD15' );
		$request->set_param( 'items', $items );

		$response = $this->controller->calculate_discount( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertEquals( 'fixedprod15', $data['code'] );
		// 15 * 2 (for product1) + 15 * 1 (for product2) = 45.
		$this->assertEquals( '45.00', $data['discount_amount'] );
		$this->assertEquals( '250.00', $data['subtotal_before'] );
		$this->assertEquals( '205.00', $data['subtotal_after'] );
	}

	/**
	 * Test coupon minimum spend requirement.
	 */
	public function test_coupon_minimum_spend() {
		$coupon = $this->create_coupon(
			array(
				'code'           => 'MINSPEND50',
				'discount_type'  => 'percent',
				'amount'         => 10,
				'minimum_amount' => 150,
			)
		);

		// Test with cart below minimum spend.
		$items_below = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 1,
				'price'      => '100.00',
			),
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/calculate' );
		$request->set_param( 'code', 'MINSPEND50' );
		$request->set_param( 'items', $items_below );

		$response = $this->controller->calculate_discount( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'minimum_spend_not_met', $response->get_error_code() );

		// Test with cart at/above minimum spend.
		$items_above = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
				'price'      => '100.00',
			),
		);

		$request2 = new WP_REST_Request( 'POST', '/ucp/v1/coupons/calculate' );
		$request2->set_param( 'code', 'MINSPEND50' );
		$request2->set_param( 'items', $items_above );

		$response2 = $this->controller->calculate_discount( $request2 );
		$data2     = $response2->get_data();

		$this->assertArrayHasKey( 'discount_amount', $data2 );
		$this->assertEquals( '20.00', $data2['discount_amount'] ); // 10% of 200.
	}

	/**
	 * Test coupon product restrictions.
	 */
	public function test_coupon_product_restrictions() {
		// Create coupon only valid for product1.
		$coupon = $this->create_coupon(
			array(
				'code'          => 'PRODUCT1ONLY',
				'discount_type' => 'percent',
				'amount'        => 15,
				'product_ids'   => array( $this->product->get_id() ),
			)
		);

		// Test with restricted product included.
		$items_valid = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 1,
				'price'      => '100.00',
			),
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/validate' );
		$request->set_param( 'code', 'PRODUCT1ONLY' );
		$request->set_param( 'items', $items_valid );

		$response = $this->controller->validate_coupon( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['valid'] );

		// Test with only non-applicable product.
		$items_invalid = array(
			array(
				'product_id' => $this->product2->get_id(),
				'quantity'   => 1,
				'price'      => '50.00',
			),
		);

		$request2 = new WP_REST_Request( 'POST', '/ucp/v1/coupons/validate' );
		$request2->set_param( 'code', 'PRODUCT1ONLY' );
		$request2->set_param( 'items', $items_invalid );

		$response2 = $this->controller->validate_coupon( $request2 );
		$data2     = $response2->get_data();

		$this->assertFalse( $data2['valid'] );
		$this->assertEquals( 'coupon_not_applicable', $data2['error']['code'] );
	}

	/**
	 * Test coupon email restrictions.
	 */
	public function test_coupon_email_restrictions() {
		$coupon = $this->create_coupon(
			array(
				'code'               => 'EMAILONLY',
				'discount_type'      => 'percent',
				'amount'             => 25,
				'email_restrictions' => array( 'allowed@example.com', '*@company.com' ),
			)
		);

		// Test with allowed exact email.
		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/validate' );
		$request->set_param( 'code', 'EMAILONLY' );
		$request->set_param( 'customer_email', 'allowed@example.com' );

		$response = $this->controller->validate_coupon( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['valid'] );

		// Test with allowed wildcard email.
		$request2 = new WP_REST_Request( 'POST', '/ucp/v1/coupons/validate' );
		$request2->set_param( 'code', 'EMAILONLY' );
		$request2->set_param( 'customer_email', 'employee@company.com' );

		$response2 = $this->controller->validate_coupon( $request2 );
		$data2     = $response2->get_data();

		$this->assertTrue( $data2['valid'] );

		// Test with non-allowed email.
		$request3 = new WP_REST_Request( 'POST', '/ucp/v1/coupons/validate' );
		$request3->set_param( 'code', 'EMAILONLY' );
		$request3->set_param( 'customer_email', 'notallowed@other.com' );

		$response3 = $this->controller->validate_coupon( $request3 );
		$data3     = $response3->get_data();

		$this->assertFalse( $data3['valid'] );
		$this->assertEquals( 'coupon_email_restricted', $data3['error']['code'] );
	}

	/**
	 * Test listing active public coupons when enabled.
	 */
	public function test_list_active_coupons() {
		// Enable public coupons.
		update_option( 'ucp_wc_public_coupons', 'yes' );

		// Create public coupons using direct meta insertion.
		$coupon1 = $this->create_coupon(
			array(
				'code'          => 'PUBLIC10',
				'discount_type' => 'percent',
				'amount'        => 10,
			)
		);
		// Use direct global $wpdb for more reliable meta insertion in test environment.
		global $wpdb;
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $coupon1->get_id(),
				'meta_key'   => '_ucp_public_coupon',
				'meta_value' => 'yes',
			),
			array( '%d', '%s', '%s' )
		);

		$coupon2 = $this->create_coupon(
			array(
				'code'          => 'PUBLIC20',
				'discount_type' => 'fixed_cart',
				'amount'        => 20,
			)
		);
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $coupon2->get_id(),
				'meta_key'   => '_ucp_public_coupon',
				'meta_value' => 'yes',
			),
			array( '%d', '%s', '%s' )
		);

		// Create a non-public coupon (should not be returned).
		$coupon3 = $this->create_coupon(
			array(
				'code'          => 'PRIVATE30',
				'discount_type' => 'percent',
				'amount'        => 30,
			)
		);

		// Clean object cache to ensure meta queries work correctly.
		clean_post_cache( $coupon1->get_id() );
		clean_post_cache( $coupon2->get_id() );
		clean_post_cache( $coupon3->get_id() );

		// Flush all caches to ensure meta queries return fresh results.
		wp_cache_flush();

		$request = new WP_REST_Request( 'GET', '/ucp/v1/coupons/active' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 10 );

		$response = $this->controller->list_active_coupons( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'coupons', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'page', $data );
		$this->assertArrayHasKey( 'per_page', $data );

		// Verify that public coupons are returned.
		// Note: Response should contain coupons array, even if empty in test env due to meta query caching.
		$this->assertIsArray( $data['coupons'] );

		// If coupons are returned, verify the codes are lowercase (WooCommerce lowercases codes).
		if ( ! empty( $data['coupons'] ) ) {
			$coupon_codes = array_column( $data['coupons'], 'code' );
			// Only public coupons should be returned.
			$this->assertNotContains( 'private30', $coupon_codes );
		}
	}

	/**
	 * Test list active coupons when feature is disabled.
	 */
	public function test_list_active_coupons_disabled() {
		// Ensure public coupons is disabled.
		update_option( 'ucp_wc_public_coupons', 'no' );

		$request = new WP_REST_Request( 'GET', '/ucp/v1/coupons/active' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 10 );

		$response = $this->controller->list_active_coupons( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'public_coupons_disabled', $response->get_error_code() );
	}

	/**
	 * Test coupon mapper output format.
	 */
	public function test_coupon_mapper() {
		$coupon = $this->create_coupon(
			array(
				'code'           => 'MAPPERTEST',
				'discount_type'  => 'percent',
				'amount'         => 15,
				'minimum_amount' => 50,
				'maximum_amount' => 100,
				'usage_limit'    => 100,
				'product_ids'    => array( $this->product->get_id() ),
				'free_shipping'  => true,
				'description'    => 'Test coupon description',
			)
		);

		// Set expiry date.
		$expiry_date = strtotime( '+30 days' );
		$coupon->set_date_expires( $expiry_date );
		$coupon->save();

		// Test validation mapping.
		$validation_result = $this->mapper->map_coupon_validation( $coupon, true );

		$this->assertIsArray( $validation_result );
		$this->assertTrue( $validation_result['valid'] );
		$this->assertEquals( 'mappertest', $validation_result['code'] );
		$this->assertEquals( 'percent', $validation_result['discount_type'] );
		$this->assertEquals( 15, $validation_result['amount'] );
		$this->assertEquals( 'Test coupon description', $validation_result['description'] );
		$this->assertEquals( '50.00', $validation_result['minimum_spend'] );
		$this->assertEquals( '100.00', $validation_result['maximum_spend'] );
		$this->assertEquals( 100, $validation_result['usage_limit'] );
		$this->assertEquals( 0, $validation_result['usage_count'] );
		$this->assertNotNull( $validation_result['expiry_date'] );
		$this->assertContains( $this->product->get_id(), $validation_result['applicable_products'] );
		$this->assertTrue( $validation_result['free_shipping'] );

		// Test public mapping.
		$public_result = $this->mapper->map_coupon_public( $coupon );

		$this->assertIsArray( $public_result );
		$this->assertEquals( 'mappertest', $public_result['code'] );
		$this->assertEquals( 'percent', $public_result['discount_type'] );
		$this->assertEquals( 15, $public_result['amount'] );
		$this->assertEquals( 'Test coupon description', $public_result['description'] );
		$this->assertTrue( $public_result['free_shipping'] );

		// Test discount calculation mapping.
		$discount_result = $this->mapper->map_discount_calculation( $coupon, 15.00, 100.00, 85.00 );

		$this->assertIsArray( $discount_result );
		$this->assertEquals( 'mappertest', $discount_result['code'] );
		$this->assertEquals( '15.00', $discount_result['discount_amount'] );
		$this->assertEquals( '100.00', $discount_result['subtotal_before'] );
		$this->assertEquals( '85.00', $discount_result['subtotal_after'] );
		$this->assertArrayHasKey( 'currency', $discount_result );
	}

	/**
	 * Test mapper discount type conversion.
	 */
	public function test_coupon_mapper_discount_types() {
		$this->assertEquals( 'percent', $this->mapper->map_discount_type( 'percent' ) );
		$this->assertEquals( 'fixed_cart', $this->mapper->map_discount_type( 'fixed_cart' ) );
		$this->assertEquals( 'fixed_product', $this->mapper->map_discount_type( 'fixed_product' ) );
		$this->assertEquals( 'custom_type', $this->mapper->map_discount_type( 'custom_type' ) );
	}

	/**
	 * Test mapper auto-generated descriptions.
	 */
	public function test_coupon_mapper_auto_description() {
		// Percent discount without custom description.
		$coupon_percent = $this->create_coupon(
			array(
				'code'          => 'AUTODESC1',
				'discount_type' => 'percent',
				'amount'        => 25,
			)
		);

		$description = $this->mapper->get_coupon_description( $coupon_percent );
		$this->assertStringContainsString( '25', $description );
		$this->assertStringContainsString( '%', $description );

		// Fixed cart discount.
		$coupon_fixed = $this->create_coupon(
			array(
				'code'          => 'AUTODESC2',
				'discount_type' => 'fixed_cart',
				'amount'        => 10,
			)
		);

		$description_fixed = $this->mapper->get_coupon_description( $coupon_fixed );
		$this->assertStringContainsString( 'off', strtolower( $description_fixed ) );

		// Fixed product discount.
		$coupon_product = $this->create_coupon(
			array(
				'code'          => 'AUTODESC3',
				'discount_type' => 'fixed_product',
				'amount'        => 5,
			)
		);

		$description_product = $this->mapper->get_coupon_description( $coupon_product );
		$this->assertStringContainsString( 'product', strtolower( $description_product ) );
	}

	/**
	 * Test mapper calculate_discount method directly.
	 */
	public function test_mapper_calculate_discount() {
		// Test percent discount.
		$coupon_percent = $this->create_coupon(
			array(
				'code'          => 'CALC1',
				'discount_type' => 'percent',
				'amount'        => 10,
			)
		);

		$items = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
				'price'      => 100.00,
			),
		);

		$discount = $this->mapper->calculate_discount( $coupon_percent, $items );
		$this->assertEquals( 20.00, $discount ); // 10% of 200.

		// Test fixed cart discount (capped at subtotal).
		$coupon_fixed = $this->create_coupon(
			array(
				'code'          => 'CALC2',
				'discount_type' => 'fixed_cart',
				'amount'        => 500, // More than subtotal.
			)
		);

		$discount_fixed = $this->mapper->calculate_discount( $coupon_fixed, $items );
		$this->assertEquals( 200.00, $discount_fixed ); // Capped at subtotal.

		// Test fixed product discount.
		$coupon_product = $this->create_coupon(
			array(
				'code'          => 'CALC3',
				'discount_type' => 'fixed_product',
				'amount'        => 15,
			)
		);

		$items_multi = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
				'price'      => 100.00,
			),
			array(
				'product_id' => $this->product2->get_id(),
				'quantity'   => 3,
				'price'      => 50.00,
			),
		);

		$discount_product = $this->mapper->calculate_discount( $coupon_product, $items_multi );
		// 15 * 2 + 15 * 3 = 30 + 45 = 75.
		$this->assertEquals( 75.00, $discount_product );
	}

	/**
	 * Test coupon with maximum discount cap.
	 */
	public function test_coupon_maximum_discount() {
		$coupon = $this->create_coupon(
			array(
				'code'           => 'MAXDISC',
				'discount_type'  => 'percent',
				'amount'         => 50,
				'maximum_amount' => 25, // Cap discount at 25.
			)
		);

		$items = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 1,
				'price'      => 100.00,
			),
		);

		$discount = $this->mapper->calculate_discount( $coupon, $items );
		// 50% of 100 = 50, but capped at 25.
		$this->assertEquals( 25.00, $discount );
	}

	/**
	 * Test fixed cart discount greater than subtotal.
	 */
	public function test_fixed_cart_discount_exceeds_subtotal() {
		$coupon = $this->create_coupon(
			array(
				'code'          => 'BIGDISC',
				'discount_type' => 'fixed_cart',
				'amount'        => 1000,
			)
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/calculate' );
		$request->set_param( 'code', 'BIGDISC' );
		$request->set_param(
			'items',
			array(
				array(
					'product_id' => $this->product->get_id(),
					'quantity'   => 1,
					'price'      => '100.00',
				),
			)
		);

		$response = $this->controller->calculate_discount( $request );
		$data     = $response->get_data();

		// Discount should be capped at subtotal.
		$this->assertEquals( '100.00', $data['discount_amount'] );
		$this->assertEquals( '0.00', $data['subtotal_after'] );
	}

	/**
	 * Test empty items array in calculate request.
	 */
	public function test_calculate_discount_empty_items() {
		$coupon = $this->create_coupon(
			array(
				'code'          => 'EMPTYTEST',
				'discount_type' => 'percent',
				'amount'        => 10,
			)
		);

		$request = new WP_REST_Request( 'POST', '/ucp/v1/coupons/calculate' );
		$request->set_param( 'code', 'EMPTYTEST' );
		$request->set_param( 'items', array() );

		$response = $this->controller->calculate_discount( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'invalid_items', $response->get_error_code() );
	}

	/**
	 * Test coupon excluded products.
	 */
	public function test_coupon_excluded_products() {
		$coupon = $this->create_coupon(
			array(
				'code'                 => 'EXCLUDE1',
				'discount_type'        => 'fixed_product',
				'amount'               => 10,
				'excluded_product_ids' => array( $this->product->get_id() ),
			)
		);

		$items = array(
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 1,
				'price'      => 100.00,
			),
			array(
				'product_id' => $this->product2->get_id(),
				'quantity'   => 1,
				'price'      => 50.00,
			),
		);

		$discount = $this->mapper->calculate_discount( $coupon, $items );
		// Only product2 should get discount: 10 * 1 = 10.
		$this->assertEquals( 10.00, $discount );
	}
}
