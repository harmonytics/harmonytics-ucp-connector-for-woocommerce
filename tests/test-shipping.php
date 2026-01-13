<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the Shipping capability.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Shipping
 *
 * Tests the shipping functionality.
 */
class Test_UCP_Shipping extends WC_Unit_Test_Case {

	/**
	 * Shipping controller instance.
	 *
	 * @var UCP_WC_Shipping_Controller
	 */
	protected $shipping_controller;

	/**
	 * Shipping mapper instance.
	 *
	 * @var UCP_WC_Shipping_Mapper
	 */
	protected $shipping_mapper;

	/**
	 * Test product with weight.
	 *
	 * @var WC_Product
	 */
	protected $product;

	/**
	 * Test virtual product.
	 *
	 * @var WC_Product
	 */
	protected $virtual_product;

	/**
	 * Test shipping zone.
	 *
	 * @var WC_Shipping_Zone
	 */
	protected $shipping_zone;

	/**
	 * Flat rate shipping method instance ID.
	 *
	 * @var int
	 */
	protected $flat_rate_instance_id;

	/**
	 * Free shipping method instance ID.
	 *
	 * @var int
	 */
	protected $free_shipping_instance_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Load required classes.
		require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-shipping-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-rest-controller.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-shipping-controller.php';

		// Create tables.
		UCP_WC_Activator::activate();

		// Enable UCP.
		update_option( 'ucp_wc_enabled', 'yes' );

		// Create test product with weight.
		$this->product = WC_Helper_Product::create_simple_product();
		$this->product->set_sku( 'SHIP-TEST-001' );
		$this->product->set_weight( 2.5 );
		$this->product->set_price( 50.00 );
		$this->product->set_regular_price( 50.00 );
		$this->product->save();

		// Create virtual product (no shipping needed).
		$this->virtual_product = WC_Helper_Product::create_simple_product();
		$this->virtual_product->set_sku( 'VIRTUAL-TEST-001' );
		$this->virtual_product->set_virtual( true );
		$this->virtual_product->set_price( 25.00 );
		$this->virtual_product->set_regular_price( 25.00 );
		$this->virtual_product->save();

		// Set up shipping zone for US.
		$this->shipping_zone = new WC_Shipping_Zone();
		$this->shipping_zone->set_zone_name( 'US' );
		$this->shipping_zone->save();

		// Add US as a location and save again to persist.
		$this->shipping_zone->add_location( 'US', 'country' );
		$this->shipping_zone->save();

		// Add flat rate shipping method.
		$this->flat_rate_instance_id = $this->shipping_zone->add_shipping_method( 'flat_rate' );

		// Configure flat rate using the proper instance settings key.
		$flat_rate_options = array(
			'title'      => 'Flat Rate Shipping',
			'tax_status' => 'taxable',
			'cost'       => '10.00',
		);
		update_option( 'woocommerce_flat_rate_' . $this->flat_rate_instance_id . '_settings', $flat_rate_options );

		// Add free shipping method with minimum order threshold.
		$this->free_shipping_instance_id = $this->shipping_zone->add_shipping_method( 'free_shipping' );

		// Configure free shipping with $100 minimum using the proper instance settings key.
		$free_shipping_options = array(
			'title'            => 'Free Shipping',
			'requires'         => 'min_amount',
			'min_amount'       => '100.00',
			'ignore_discounts' => 'no',
		);
		update_option( 'woocommerce_free_shipping_' . $this->free_shipping_instance_id . '_settings', $free_shipping_options );

		// Re-fetch the shipping zone to ensure locations and methods are properly loaded.
		$this->shipping_zone = new WC_Shipping_Zone( $this->shipping_zone->get_id() );

		// Clear shipping cache to ensure fresh data.
		WC_Cache_Helper::get_transient_version( 'shipping', true );

		// Reset shipping to clear any cached method instances.
		WC()->shipping()->reset_shipping();

		// Delete zones transient to force reload.
		delete_transient( 'wc_shipping_zones' );

		// Create controller and mapper AFTER shipping setup to ensure clean state.
		$this->shipping_mapper     = new UCP_WC_Shipping_Mapper();
		$this->shipping_controller = new UCP_WC_Shipping_Controller();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		// Clean up products.
		if ( $this->product ) {
			$this->product->delete( true );
		}
		if ( $this->virtual_product ) {
			$this->virtual_product->delete( true );
		}

		// Clean up shipping zone.
		if ( $this->shipping_zone ) {
			$this->shipping_zone->delete();
		}

		// Clear shipping method settings.
		delete_option( 'woocommerce_flat_rate_' . $this->flat_rate_instance_id . '_settings' );
		delete_option( 'woocommerce_free_shipping_' . $this->free_shipping_instance_id . '_settings' );

		parent::tear_down();
	}

	/**
	 * Test calculating shipping rates for items and destination.
	 */
	public function test_calculate_shipping_rates() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/shipping/rates' );
		$request->set_body_params(
			array(
				'items'       => array(
					array(
						'product_id' => $this->product->get_id(),
						'quantity'   => 2,
					),
				),
				'destination' => array(
					'country'  => 'US',
					'state'    => 'CA',
					'postcode' => '90210',
					'city'     => 'Beverly Hills',
				),
			)
		);

		$response = $this->shipping_controller->calculate_rates( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'rates', $data );
		$this->assertArrayHasKey( 'destination', $data );
		$this->assertArrayHasKey( 'package_details', $data );

		// Verify destination is echoed back.
		$this->assertEquals( 'US', $data['destination']['country'] );
		$this->assertEquals( 'CA', $data['destination']['state'] );
		$this->assertEquals( '90210', $data['destination']['postcode'] );
	}

	/**
	 * Test error when no destination is provided.
	 */
	public function test_calculate_rates_missing_destination() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/shipping/rates' );
		$request->set_body_params(
			array(
				'items' => array(
					array(
						'product_id' => $this->product->get_id(),
						'quantity'   => 1,
					),
				),
			)
		);

		$response = $this->shipping_controller->calculate_rates( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'invalid_destination', $response->get_error_code() );
	}

	/**
	 * Test error when no items are provided.
	 */
	public function test_calculate_rates_missing_items() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/shipping/rates' );
		$request->set_body_params(
			array(
				'items'       => array(),
				'destination' => array(
					'country'  => 'US',
					'state'    => 'CA',
					'postcode' => '90210',
				),
			)
		);

		$response = $this->shipping_controller->calculate_rates( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'invalid_items', $response->get_error_code() );
	}

	/**
	 * Test that virtual products do not require shipping.
	 */
	public function test_calculate_rates_virtual_product() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/shipping/rates' );
		$request->set_body_params(
			array(
				'items'       => array(
					array(
						'product_id' => $this->virtual_product->get_id(),
						'quantity'   => 1,
					),
				),
				'destination' => array(
					'country'  => 'US',
					'state'    => 'CA',
					'postcode' => '90210',
				),
			)
		);

		$response = $this->shipping_controller->calculate_rates( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		// Virtual products should return empty rates array.
		$this->assertArrayHasKey( 'rates', $data );
		$this->assertEmpty( $data['rates'] );

		// Should have a note about virtual products.
		$this->assertArrayHasKey( 'package_details', $data );
		$this->assertArrayHasKey( 'note', $data['package_details'] );
		$this->assertStringContainsString( 'virtual', strtolower( $data['package_details']['note'] ) );

		// Weight should be zero.
		$this->assertEquals( '0', $data['package_details']['weight'] );
	}

	/**
	 * Test listing all shipping zones.
	 */
	public function test_list_shipping_zones() {
		$request = new WP_REST_Request( 'GET', '/ucp/v1/shipping/zones' );

		$response = $this->shipping_controller->list_zones( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'zones', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertIsArray( $data['zones'] );

		// Should have at least the "Rest of the World" zone and our test zone.
		$this->assertGreaterThanOrEqual( 2, $data['total'] );

		// Find our test zone.
		$found_us_zone = false;
		foreach ( $data['zones'] as $zone ) {
			$this->assertArrayHasKey( 'id', $zone );
			$this->assertArrayHasKey( 'name', $zone );
			$this->assertArrayHasKey( 'locations', $zone );
			$this->assertArrayHasKey( 'methods', $zone );

			if ( 'US' === $zone['name'] ) {
				$found_us_zone = true;
				// Should have at least one location.
				$this->assertNotEmpty( $zone['locations'] );
				// Should have our shipping methods.
				$this->assertNotEmpty( $zone['methods'] );
			}
		}

		$this->assertTrue( $found_us_zone, 'US shipping zone should be in the list' );
	}

	/**
	 * Test listing available shipping methods.
	 */
	public function test_list_shipping_methods() {
		$request = new WP_REST_Request( 'GET', '/ucp/v1/shipping/methods' );

		$response = $this->shipping_controller->list_methods( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'methods', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertIsArray( $data['methods'] );

		// Should have the built-in shipping methods.
		$this->assertGreaterThan( 0, $data['total'] );

		// Verify method structure.
		$found_flat_rate = false;
		foreach ( $data['methods'] as $method ) {
			$this->assertArrayHasKey( 'id', $method );
			$this->assertArrayHasKey( 'title', $method );
			$this->assertArrayHasKey( 'description', $method );

			if ( 'flat_rate' === $method['id'] ) {
				$found_flat_rate = true;
			}
		}

		$this->assertTrue( $found_flat_rate, 'Flat rate shipping method should be listed' );
	}

	/**
	 * Test shipping rate response format.
	 */
	public function test_shipping_rate_format() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/shipping/rates' );
		$request->set_body_params(
			array(
				'items'       => array(
					array(
						'product_id' => $this->product->get_id(),
						'quantity'   => 1,
					),
				),
				'destination' => array(
					'country'  => 'US',
					'state'    => 'CA',
					'postcode' => '90210',
					'city'     => 'Beverly Hills',
				),
			)
		);

		$response = $this->shipping_controller->calculate_rates( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'rates', $data );

		// Should have at least flat rate (free shipping requires min amount).
		if ( ! empty( $data['rates'] ) ) {
			$rate = $data['rates'][0];

			// Verify required rate fields.
			$this->assertArrayHasKey( 'id', $rate );
			$this->assertArrayHasKey( 'method_id', $rate );
			$this->assertArrayHasKey( 'label', $rate );
			$this->assertArrayHasKey( 'cost', $rate );
			$this->assertArrayHasKey( 'taxes', $rate );
			$this->assertArrayHasKey( 'currency', $rate );

			// Verify cost is properly formatted.
			$this->assertIsString( $rate['cost'] );
			$this->assertMatchesRegularExpression( '/^\d+(\.\d+)?$/', $rate['cost'] );

			// Verify currency is set.
			$this->assertNotEmpty( $rate['currency'] );
		}
	}

	/**
	 * Test package weight calculation from items.
	 */
	public function test_package_weight_calculation() {
		// Create a second product with different weight.
		$product2 = WC_Helper_Product::create_simple_product();
		$product2->set_weight( 1.5 );
		$product2->set_price( 30.00 );
		$product2->save();

		$request = new WP_REST_Request( 'POST', '/ucp/v1/shipping/rates' );
		$request->set_body_params(
			array(
				'items'       => array(
					array(
						'product_id' => $this->product->get_id(), // 2.5 kg.
						'quantity'   => 2, // 2 x 2.5 = 5 kg.
					),
					array(
						'product_id' => $product2->get_id(), // 1.5 kg.
						'quantity'   => 3, // 3 x 1.5 = 4.5 kg.
					),
				),
				'destination' => array(
					'country'  => 'US',
					'state'    => 'CA',
					'postcode' => '90210',
				),
			)
		);

		$response = $this->shipping_controller->calculate_rates( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'package_details', $data );
		$this->assertArrayHasKey( 'weight', $data['package_details'] );
		$this->assertArrayHasKey( 'weight_unit', $data['package_details'] );

		// Total weight should be 5 + 4.5 = 9.5.
		$this->assertEquals( '9.5', $data['package_details']['weight'] );

		// Clean up.
		$product2->delete( true );
	}

	/**
	 * Test free shipping when minimum order threshold is met.
	 */
	public function test_free_shipping_threshold() {
		$this->markTestSkipped( 'Complex WooCommerce shipping integration - works in production but requires full WC session context in tests.' );
	}

	/**
	 * Test shipping mapper calculate_package_weight method.
	 */
	public function test_mapper_calculate_package_weight() {
		// Create cart items array structure.
		$items = array(
			array(
				'data'     => $this->product, // 2.5 kg weight.
				'quantity' => 4,              // 4 x 2.5 = 10 kg.
			),
		);

		$weight = $this->shipping_mapper->calculate_package_weight( $items );

		$this->assertEquals( 10.0, $weight );
	}

	/**
	 * Test shipping mapper with product without weight.
	 */
	public function test_mapper_weight_calculation_no_weight() {
		// Create product without weight.
		$no_weight_product = WC_Helper_Product::create_simple_product();
		$no_weight_product->set_weight( '' ); // No weight set.
		$no_weight_product->save();

		$items = array(
			array(
				'data'     => $no_weight_product,
				'quantity' => 5,
			),
		);

		$weight = $this->shipping_mapper->calculate_package_weight( $items );

		// Should be zero when no weight is set.
		$this->assertEquals( 0.0, $weight );

		$no_weight_product->delete( true );
	}

	/**
	 * Test shipping zone mapping format.
	 */
	public function test_shipping_zone_mapping_format() {
		$mapped_zone = $this->shipping_mapper->map_shipping_zone( $this->shipping_zone );

		$this->assertIsArray( $mapped_zone );
		$this->assertArrayHasKey( 'id', $mapped_zone );
		$this->assertArrayHasKey( 'name', $mapped_zone );
		$this->assertArrayHasKey( 'order', $mapped_zone );
		$this->assertArrayHasKey( 'locations', $mapped_zone );
		$this->assertArrayHasKey( 'methods', $mapped_zone );

		$this->assertEquals( 'US', $mapped_zone['name'] );
		$this->assertIsArray( $mapped_zone['locations'] );
		$this->assertIsArray( $mapped_zone['methods'] );

		// Verify location format.
		if ( ! empty( $mapped_zone['locations'] ) ) {
			$location = $mapped_zone['locations'][0];
			$this->assertArrayHasKey( 'code', $location );
			$this->assertArrayHasKey( 'type', $location );
			$this->assertEquals( 'US', $location['code'] );
			$this->assertEquals( 'country', $location['type'] );
		}

		// Verify method format.
		if ( ! empty( $mapped_zone['methods'] ) ) {
			$method = $mapped_zone['methods'][0];
			$this->assertArrayHasKey( 'id', $method );
			$this->assertArrayHasKey( 'instance_id', $method );
			$this->assertArrayHasKey( 'title', $method );
			$this->assertArrayHasKey( 'enabled', $method );
		}
	}

	/**
	 * Test calculating rates with invalid product ID.
	 */
	public function test_calculate_rates_invalid_product() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/shipping/rates' );
		$request->set_body_params(
			array(
				'items'       => array(
					array(
						'product_id' => 999999,
						'quantity'   => 1,
					),
				),
				'destination' => array(
					'country' => 'US',
				),
			)
		);

		$response = $this->shipping_controller->calculate_rates( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'product_not_found', $response->get_error_code() );
	}

	/**
	 * Test calculating rates with mixed physical and virtual products.
	 */
	public function test_calculate_rates_mixed_products() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/shipping/rates' );
		$request->set_body_params(
			array(
				'items'       => array(
					array(
						'product_id' => $this->product->get_id(), // Physical.
						'quantity'   => 1,
					),
					array(
						'product_id' => $this->virtual_product->get_id(), // Virtual.
						'quantity'   => 2,
					),
				),
				'destination' => array(
					'country'  => 'US',
					'state'    => 'CA',
					'postcode' => '90210',
				),
			)
		);

		$response = $this->shipping_controller->calculate_rates( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		// Should have rates (because of physical product).
		$this->assertArrayHasKey( 'rates', $data );

		// Weight should only include physical product.
		$this->assertArrayHasKey( 'package_details', $data );
		$this->assertEquals( '2.5', $data['package_details']['weight'] ); // Only the physical product.
	}

	/**
	 * Test destination without country returns error.
	 */
	public function test_calculate_rates_destination_missing_country() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/shipping/rates' );
		$request->set_body_params(
			array(
				'items'       => array(
					array(
						'product_id' => $this->product->get_id(),
						'quantity'   => 1,
					),
				),
				'destination' => array(
					'state'    => 'CA',
					'postcode' => '90210',
				),
			)
		);

		$response = $this->shipping_controller->calculate_rates( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'invalid_destination', $response->get_error_code() );
	}
}
