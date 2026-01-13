<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the Checkout capability.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Checkout
 *
 * Tests the checkout session functionality.
 */
class Test_UCP_Checkout extends WC_Unit_Test_Case {

    /**
     * Checkout capability instance.
     *
     * @var UCP_WC_Checkout
     */
    protected $checkout;

    /**
     * Test product.
     *
     * @var WC_Product
     */
    protected $product;

    /**
     * Set up test fixtures.
     */
    public function set_up() {
        parent::set_up();

        // Load required classes
        require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
        require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-line-item-mapper.php';
        require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-address-mapper.php';
        require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-shipping-mapper.php';
        require_once UCP_WC_PLUGIN_DIR . 'includes/capabilities/class-ucp-checkout.php';

        // Create tables
        UCP_WC_Activator::activate();

        $this->checkout = new UCP_WC_Checkout();

        // Create a test product
        $this->product = WC_Helper_Product::create_simple_product();
        $this->product->set_sku( 'TEST-SKU-001' );
        $this->product->save();

        // Enable UCP
        update_option( 'ucp_wc_enabled', 'yes' );
    }

    /**
     * Tear down test fixtures.
     */
    public function tear_down() {
        // Clean up
        if ( $this->product ) {
            $this->product->delete( true );
        }

        parent::tear_down();
    }

    /**
     * Test creating a checkout session with valid items.
     */
    public function test_create_session_with_product_id() {
        $items = array(
            array(
                'product_id' => $this->product->get_id(),
                'quantity'   => 2,
            ),
        );

        $result = $this->checkout->create_session( $items );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'session_id', $result );
        $this->assertArrayHasKey( 'order_id', $result );
        $this->assertArrayHasKey( 'status', $result );
        $this->assertArrayHasKey( 'items', $result );
        $this->assertArrayHasKey( 'totals', $result );

        $this->assertEquals( 'pending', $result['status'] );
        $this->assertStringStartsWith( 'ucp_', $result['session_id'] );
        $this->assertCount( 1, $result['items'] );
    }

    /**
     * Test creating a checkout session with SKU.
     */
    public function test_create_session_with_sku() {
        $items = array(
            array(
                'sku'      => 'TEST-SKU-001',
                'quantity' => 1,
            ),
        );

        $result = $this->checkout->create_session( $items );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'session_id', $result );
        $this->assertCount( 1, $result['items'] );
    }

    /**
     * Test creating session with shipping address.
     */
    public function test_create_session_with_shipping() {
        $items = array(
            array(
                'product_id' => $this->product->get_id(),
                'quantity'   => 1,
            ),
        );

        $shipping = array(
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'address_1'  => '123 Test St',
            'city'       => 'Test City',
            'state'      => 'CA',
            'postcode'   => '90210',
            'country'    => 'US',
            'email'      => 'john@example.com',
        );

        $result = $this->checkout->create_session( $items, $shipping );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'shipping_options', $result );
    }

    /**
     * Test creating session with invalid product.
     */
    public function test_create_session_invalid_product() {
        $items = array(
            array(
                'product_id' => 999999,
                'quantity'   => 1,
            ),
        );

        $result = $this->checkout->create_session( $items );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'product_not_found', $result->get_error_code() );
    }

    /**
     * Test creating session with empty items.
     */
    public function test_create_session_empty_items() {
        $result = $this->checkout->create_session( array() );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'invalid_items', $result->get_error_code() );
    }

    /**
     * Test getting a session.
     */
    public function test_get_session() {
        // Create a session first
        $items = array(
            array(
                'product_id' => $this->product->get_id(),
                'quantity'   => 1,
            ),
        );

        $created = $this->checkout->create_session( $items );
        $this->assertIsArray( $created );

        // Now get it
        $result = $this->checkout->get_session( $created['session_id'] );

        $this->assertIsArray( $result );
        $this->assertEquals( $created['session_id'], $result['session_id'] );
        $this->assertEquals( $created['order_id'], $result['order_id'] );
    }

    /**
     * Test getting non-existent session.
     */
    public function test_get_session_not_found() {
        $result = $this->checkout->get_session( 'ucp_nonexistent12345678901234567890' );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'session_not_found', $result->get_error_code() );
    }

    /**
     * Test totals calculation.
     */
    public function test_totals_calculation() {
        // Set regular price (not just active price) to ensure proper persistence.
        $this->product->set_regular_price( 25.00 );
        $this->product->save();

        // Reload product from database to ensure fresh data.
        $product = wc_get_product( $this->product->get_id() );

        $items = array(
            array(
                'product_id' => $product->get_id(),
                'quantity'   => 2,
            ),
        );

        $result = $this->checkout->create_session( $items );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'totals', $result );
        $this->assertEquals( 50.00, $result['totals']['subtotal'] );
    }

    /**
     * Test next action determination.
     */
    public function test_next_action_no_shipping() {
        $items = array(
            array(
                'product_id' => $this->product->get_id(),
                'quantity'   => 1,
            ),
        );

        $result = $this->checkout->create_session( $items );

        $this->assertEquals( 'provide_shipping_address', $result['next_action'] );
    }

    /**
     * Test web checkout URL is provided.
     */
    public function test_web_checkout_url_provided() {
        $items = array(
            array(
                'product_id' => $this->product->get_id(),
                'quantity'   => 1,
            ),
        );

        $result = $this->checkout->create_session( $items );

        $this->assertArrayHasKey( 'web_checkout_url', $result );
        $this->assertNotEmpty( $result['web_checkout_url'] );
    }
}
