<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the Cart capability.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Cart
 *
 * Tests the cart functionality.
 */
class Test_UCP_Cart extends WC_Unit_Test_Case {

	/**
	 * Cart capability instance.
	 *
	 * @var UCP_WC_Cart
	 */
	protected $cart;

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
	 * Array of created cart IDs for cleanup.
	 *
	 * @var array
	 */
	protected $created_cart_ids = array();

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Load required classes.
		require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-line-item-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-address-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-shipping-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-product-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/capabilities/class-ucp-cart.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/capabilities/class-ucp-checkout.php';

		// Create tables.
		UCP_WC_Activator::activate();

		$this->cart = new UCP_WC_Cart();

		// Create test products.
		$this->product = WC_Helper_Product::create_simple_product();
		$this->product->set_sku( 'TEST-CART-SKU-001' );
		$this->product->set_price( 25.00 );
		$this->product->set_regular_price( 25.00 );
		$this->product->set_manage_stock( true );
		$this->product->set_stock_quantity( 10 );
		$this->product->set_stock_status( 'instock' );
		$this->product->save();

		$this->product2 = WC_Helper_Product::create_simple_product();
		$this->product2->set_sku( 'TEST-CART-SKU-002' );
		$this->product2->set_price( 50.00 );
		$this->product2->set_regular_price( 50.00 );
		$this->product2->set_manage_stock( true );
		$this->product2->set_stock_quantity( 5 );
		$this->product2->set_stock_status( 'instock' );
		$this->product2->save();

		// Enable UCP.
		update_option( 'ucp_wc_enabled', 'yes' );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		global $wpdb;

		// Clean up created carts.
		$table_name = UCP_WC_Cart::get_carts_table();
		foreach ( $this->created_cart_ids as $cart_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table_name, array( 'cart_id' => $cart_id ) );
		}

		// Clean up products.
		if ( $this->product ) {
			$this->product->delete( true );
		}
		if ( $this->product2 ) {
			$this->product2->delete( true );
		}

		parent::tear_down();
	}

	/**
	 * Helper to track created carts for cleanup.
	 *
	 * @param string $cart_id Cart ID.
	 */
	protected function track_cart( $cart_id ) {
		$this->created_cart_ids[] = $cart_id;
	}

	/**
	 * Test creating an empty cart.
	 */
	public function test_create_cart() {
		$result = $this->cart->create_cart();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'cart_id', $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'totals', $result );
		$this->assertArrayHasKey( 'item_count', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'created_at', $result );
		$this->assertArrayHasKey( 'expires_at', $result );

		// Verify cart_id format (cart_ prefix followed by 32 hex characters).
		$this->assertMatchesRegularExpression( '/^cart_[a-f0-9]{32}$/', $result['cart_id'] );

		// Verify empty cart state.
		$this->assertEmpty( $result['items'] );
		$this->assertEquals( 0, $result['item_count'] );
		$this->assertEquals( 'active', $result['status'] );
		$this->assertEquals( 0, $result['totals']['subtotal'] );

		$this->track_cart( $result['cart_id'] );
	}

	/**
	 * Test adding item to cart by product_id.
	 */
	public function test_add_item_to_cart() {
		// Create cart first.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Add item.
		$item = array(
			'product_id' => $this->product->get_id(),
			'quantity'   => 1,
		);

		$result = $this->cart->add_item( $cart_result['cart_id'], $item );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'items', $result );
		$this->assertCount( 1, $result['items'] );
		$this->assertEquals( 1, $result['item_count'] );

		// Verify item data.
		$added_item = $result['items'][0];
		$this->assertEquals( $this->product->get_id(), $added_item['product_id'] );
		$this->assertEquals( 1, $added_item['quantity'] );
		$this->assertEquals( $this->product->get_name(), $added_item['name'] );
		$this->assertEquals( 25.00, $added_item['price'] );
	}

	/**
	 * Test adding item to cart by SKU.
	 */
	public function test_add_item_by_sku() {
		// Create cart first.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Add item by SKU.
		$item = array(
			'sku'      => 'TEST-CART-SKU-001',
			'quantity' => 1,
		);

		$result = $this->cart->add_item( $cart_result['cart_id'], $item );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['items'] );

		// Verify correct product was added.
		$added_item = $result['items'][0];
		$this->assertEquals( $this->product->get_id(), $added_item['product_id'] );
		$this->assertEquals( 'TEST-CART-SKU-001', $added_item['sku'] );
	}

	/**
	 * Test adding item with specific quantity.
	 */
	public function test_add_item_with_quantity() {
		// Create cart first.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Add item with quantity 3.
		$item = array(
			'product_id' => $this->product->get_id(),
			'quantity'   => 3,
		);

		$result = $this->cart->add_item( $cart_result['cart_id'], $item );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['items'] );

		// Verify quantity.
		$added_item = $result['items'][0];
		$this->assertEquals( 3, $added_item['quantity'] );
		$this->assertEquals( 75.00, $added_item['line_total'] ); // 25.00 * 3
	}

	/**
	 * Test updating item quantity in cart.
	 */
	public function test_update_item_quantity() {
		// Create cart and add item.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		$item = array(
			'product_id' => $this->product->get_id(),
			'quantity'   => 1,
		);

		$add_result = $this->cart->add_item( $cart_result['cart_id'], $item );
		$this->assertIsArray( $add_result );

		// Get item_key.
		$item_key = $add_result['items'][0]['item_key'];

		// Update quantity.
		$result = $this->cart->update_item( $cart_result['cart_id'], $item_key, 5 );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['items'] );
		$this->assertEquals( 5, $result['items'][0]['quantity'] );
		$this->assertEquals( 125.00, $result['items'][0]['line_total'] ); // 25.00 * 5
	}

	/**
	 * Test removing specific item from cart.
	 */
	public function test_remove_item_from_cart() {
		// Create cart and add two items.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Add first item.
		$this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 1,
			)
		);

		// Add second item.
		$add_result = $this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product2->get_id(),
				'quantity'   => 1,
			)
		);

		$this->assertIsArray( $add_result );
		$this->assertCount( 2, $add_result['items'] );

		// Get item_key of first product.
		$item_key_to_remove = null;
		foreach ( $add_result['items'] as $item ) {
			if ( $item['product_id'] === $this->product->get_id() ) {
				$item_key_to_remove = $item['item_key'];
				break;
			}
		}

		// Remove first item.
		$result = $this->cart->remove_item( $cart_result['cart_id'], $item_key_to_remove );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['items'] );
		$this->assertEquals( $this->product2->get_id(), $result['items'][0]['product_id'] );
	}

	/**
	 * Test clearing all items from cart.
	 */
	public function test_clear_cart() {
		// Create cart and add items.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Add items.
		$this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
			)
		);

		$this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product2->get_id(),
				'quantity'   => 1,
			)
		);

		// Clear cart.
		$result = $this->cart->clear_cart( $cart_result['cart_id'] );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['items'] );
		$this->assertEquals( 0, $result['item_count'] );
		$this->assertEquals( 0, $result['totals']['subtotal'] );
		$this->assertEquals( 'active', $result['status'] );
	}

	/**
	 * Test deleting entire cart.
	 */
	public function test_delete_cart() {
		// Create cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );

		$cart_id = $cart_result['cart_id'];

		// Delete cart.
		$result = $this->cart->delete_cart( $cart_id );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertTrue( $result['deleted'] );
		$this->assertEquals( $cart_id, $result['cart_id'] );

		// Verify cart no longer exists.
		$get_result = $this->cart->get_cart( $cart_id );
		$this->assertInstanceOf( WP_Error::class, $get_result );
		$this->assertEquals( 'cart_not_found', $get_result->get_error_code() );
	}

	/**
	 * Test cart totals calculation.
	 */
	public function test_cart_totals_calculation() {
		// Create cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Add first product (25.00 * 2 = 50.00).
		$this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
			)
		);

		// Add second product (50.00 * 1 = 50.00).
		$result = $this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product2->get_id(),
				'quantity'   => 1,
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'totals', $result );

		// Verify subtotal (50.00 + 50.00 = 100.00).
		$this->assertEquals( 100.00, $result['totals']['subtotal'] );
		$this->assertArrayHasKey( 'currency', $result['totals'] );
		$this->assertArrayHasKey( 'currency_symbol', $result['totals'] );
	}

	/**
	 * Test converting cart to checkout session.
	 */
	public function test_convert_cart_to_checkout() {
		// Create cart and add items.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		$this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
			)
		);

		// Convert to checkout.
		$result = $this->cart->convert_to_checkout( $cart_result['cart_id'] );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'converted', $result );
		$this->assertTrue( $result['converted'] );
		$this->assertArrayHasKey( 'cart_id', $result );
		$this->assertEquals( $cart_result['cart_id'], $result['cart_id'] );
		$this->assertArrayHasKey( 'checkout_session', $result );
		$this->assertArrayHasKey( 'session_id', $result['checkout_session'] );
		$this->assertStringStartsWith( 'ucp_', $result['checkout_session']['session_id'] );

		// Verify cart is now marked as converted.
		$get_result = $this->cart->get_cart( $cart_result['cart_id'] );
		$this->assertInstanceOf( WP_Error::class, $get_result );
		$this->assertEquals( 'cart_converted', $get_result->get_error_code() );
	}

	/**
	 * Test expired cart handling.
	 */
	public function test_cart_expiration() {
		global $wpdb;

		// Create cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Manually set expiration to past.
		$table_name = UCP_WC_Cart::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table_name,
			array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 ) ),
			array( 'cart_id' => $cart_result['cart_id'] )
		);

		// Try to get expired cart.
		$result = $this->cart->get_cart( $cart_result['cart_id'] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'cart_expired', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 410, $error_data['status'] );
	}

	/**
	 * Test adding invalid (non-existent) product.
	 */
	public function test_add_item_invalid_product() {
		// Create cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Try to add non-existent product.
		$item = array(
			'product_id' => 999999,
			'quantity'   => 1,
		);

		$result = $this->cart->add_item( $cart_result['cart_id'], $item );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'product_not_found', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test adding item with insufficient stock.
	 */
	public function test_add_item_insufficient_stock() {
		// Create cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Try to add more than available stock (product has 10 in stock).
		$item = array(
			'product_id' => $this->product->get_id(),
			'quantity'   => 100,
		);

		$result = $this->cart->add_item( $cart_result['cart_id'], $item );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'insufficient_stock', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 400, $error_data['status'] );
	}

	/**
	 * Test maximum items limit (100 items).
	 */
	public function test_cart_max_items_limit() {
		// Create cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Create temporary products to fill cart.
		$temp_products = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$temp_product = WC_Helper_Product::create_simple_product();
			$temp_product->set_sku( 'TEMP-LIMIT-SKU-' . $i );
			$temp_product->save();
			$temp_products[] = $temp_product;

			// Add to cart.
			$this->cart->add_item(
				$cart_result['cart_id'],
				array(
					'product_id' => $temp_product->get_id(),
					'quantity'   => 1,
				)
			);
		}

		// Try to add one more item (should fail).
		$result = $this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 1,
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'cart_full', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 400, $error_data['status'] );

		// Clean up temporary products.
		foreach ( $temp_products as $temp_product ) {
			$temp_product->delete( true );
		}
	}

	/**
	 * Test getting non-existent cart returns 404.
	 */
	public function test_get_nonexistent_cart() {
		$result = $this->cart->get_cart( 'cart_nonexistent1234567890abcdef' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'cart_not_found', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test adding same product multiple times increases quantity.
	 */
	public function test_add_same_product_increases_quantity() {
		// Create cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Add item first time.
		$this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
			)
		);

		// Add same item again.
		$result = $this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 3,
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['items'] ); // Still one item, not two.
		$this->assertEquals( 5, $result['items'][0]['quantity'] ); // 2 + 3 = 5
	}

	/**
	 * Test updating item to zero quantity removes item.
	 */
	public function test_update_item_zero_removes_item() {
		// Create cart and add item.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		$add_result = $this->cart->add_item(
			$cart_result['cart_id'],
			array(
				'product_id' => $this->product->get_id(),
				'quantity'   => 2,
			)
		);

		$item_key = $add_result['items'][0]['item_key'];

		// Update to zero.
		$result = $this->cart->update_item( $cart_result['cart_id'], $item_key, 0 );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result['items'] );
		$this->assertEquals( 0, $result['item_count'] );
	}

	/**
	 * Test cart metadata is stored correctly.
	 */
	public function test_cart_with_metadata() {
		$metadata = array(
			'source'     => 'ai_agent',
			'agent_name' => 'Test Agent',
			'session_id' => 'test-session-123',
		);

		$result = $this->cart->create_cart( $metadata );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'metadata', $result );
		$this->assertEquals( $metadata, $result['metadata'] );

		$this->track_cart( $result['cart_id'] );

		// Verify metadata persists on get.
		$get_result = $this->cart->get_cart( $result['cart_id'] );
		$this->assertIsArray( $get_result );
		$this->assertEquals( $metadata, $get_result['metadata'] );
	}

	/**
	 * Test converting empty cart to checkout fails.
	 */
	public function test_convert_empty_cart_fails() {
		// Create empty cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Try to convert empty cart.
		$result = $this->cart->convert_to_checkout( $cart_result['cart_id'] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'cart_empty', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 400, $error_data['status'] );
	}

	/**
	 * Test update item on non-existent item fails.
	 */
	public function test_update_nonexistent_item() {
		// Create cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Try to update non-existent item.
		$result = $this->cart->update_item( $cart_result['cart_id'], 'item_nonexistent', 5 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'item_not_found', $result->get_error_code() );

		$error_data = $result->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test remove non-existent item fails.
	 */
	public function test_remove_nonexistent_item() {
		// Create cart.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );
		$this->track_cart( $cart_result['cart_id'] );

		// Try to remove non-existent item.
		$result = $this->cart->remove_item( $cart_result['cart_id'], 'item_nonexistent' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'item_not_found', $result->get_error_code() );
	}

	/**
	 * Test cleanup expired carts.
	 */
	public function test_cleanup_expired_carts() {
		global $wpdb;

		// Create cart and expire it.
		$cart_result = $this->cart->create_cart();
		$this->assertIsArray( $cart_result );

		$table_name = UCP_WC_Cart::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table_name,
			array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 3600 ) ),
			array( 'cart_id' => $cart_result['cart_id'] )
		);

		// Run cleanup.
		$deleted = UCP_WC_Cart::cleanup_expired_carts();

		$this->assertGreaterThanOrEqual( 1, $deleted );

		// Verify cart no longer exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cart = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table_name} WHERE cart_id = %s",
				$cart_result['cart_id']
			)
		);

		$this->assertNull( $cart );
	}
}
