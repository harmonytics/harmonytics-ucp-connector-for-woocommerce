<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the mapping classes.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Mapping
 *
 * Tests the schema mapping functionality.
 */
class Test_UCP_Mapping extends WC_Unit_Test_Case {

    /**
     * Address mapper instance.
     *
     * @var UCP_WC_Address_Mapper
     */
    protected $address_mapper;

    /**
     * Line item mapper instance.
     *
     * @var UCP_WC_Line_Item_Mapper
     */
    protected $line_item_mapper;

    /**
     * Order mapper instance.
     *
     * @var UCP_WC_Order_Mapper
     */
    protected $order_mapper;

    /**
     * Set up test fixtures.
     */
    public function set_up() {
        parent::set_up();

        require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-line-item-mapper.php';
        require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-address-mapper.php';
        require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-shipping-mapper.php';
        require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-order-mapper.php';

        $this->address_mapper   = new UCP_WC_Address_Mapper();
        $this->line_item_mapper = new UCP_WC_Line_Item_Mapper();
        $this->order_mapper     = new UCP_WC_Order_Mapper();
    }

    /**
     * Test address mapping.
     */
    public function test_address_mapping() {
        $address = array(
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'address_1'  => '123 Test St',
            'address_2'  => 'Apt 4',
            'city'       => 'Los Angeles',
            'state'      => 'CA',
            'postcode'   => '90210',
            'country'    => 'US',
            'phone'      => '555-1234',
            'email'      => 'john@example.com',
        );

        $result = $this->address_mapper->map_address( $address );

        $this->assertEquals( 'John', $result['first_name'] );
        $this->assertEquals( 'Doe', $result['last_name'] );
        $this->assertEquals( 'John Doe', $result['full_name'] );
        $this->assertEquals( '123 Test St', $result['address_line_1'] );
        $this->assertEquals( 'Apt 4', $result['address_line_2'] );
        $this->assertEquals( 'Los Angeles', $result['city'] );
        $this->assertEquals( 'CA', $result['state'] );
        $this->assertEquals( '90210', $result['postcode'] );
        $this->assertEquals( 'US', $result['country'] );
        $this->assertEquals( 'john@example.com', $result['email'] );
        $this->assertArrayHasKey( 'formatted', $result );
        $this->assertArrayHasKey( 'country_name', $result );
        $this->assertArrayHasKey( 'state_name', $result );
    }

    /**
     * Test address validation.
     */
    public function test_address_validation() {
        // Valid address
        $valid_address = array(
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'address_1'  => '123 Test St',
            'city'       => 'Los Angeles',
            'postcode'   => '90210',
            'country'    => 'US',
        );

        $errors = $this->address_mapper->validate( $valid_address );
        $this->assertEmpty( $errors );

        // Invalid address (missing required fields)
        $invalid_address = array(
            'first_name' => 'John',
        );

        $errors = $this->address_mapper->validate( $invalid_address );
        $this->assertNotEmpty( $errors );
    }

    /**
     * Test address conversion to WooCommerce format.
     */
    public function test_address_to_wc() {
        $ucp_address = array(
            'first_name'     => 'John',
            'last_name'      => 'Doe',
            'address_line_1' => '123 Test St',
            'address_line_2' => 'Apt 4',
            'city'           => 'Los Angeles',
            'state'          => 'CA',
            'postcode'       => '90210',
            'country'        => 'US',
        );

        $result = $this->address_mapper->map_to_wc( $ucp_address );

        $this->assertEquals( 'John', $result['first_name'] );
        $this->assertEquals( '123 Test St', $result['address_1'] );
        $this->assertEquals( 'Apt 4', $result['address_2'] );
    }

    /**
     * Test order status mapping.
     */
    public function test_order_status_mapping() {
        $this->assertEquals( 'awaiting_payment', $this->order_mapper->map_status( 'pending' ) );
        $this->assertEquals( 'awaiting_payment', $this->order_mapper->map_status( 'on-hold' ) );
        $this->assertEquals( 'preparing', $this->order_mapper->map_status( 'processing' ) );
        $this->assertEquals( 'delivered', $this->order_mapper->map_status( 'completed' ) );
        $this->assertEquals( 'cancelled', $this->order_mapper->map_status( 'cancelled' ) );
        $this->assertEquals( 'refunded', $this->order_mapper->map_status( 'refunded' ) );
    }

    /**
     * Test reverse status mapping.
     */
    public function test_reverse_status_mapping() {
        $this->assertEquals( 'pending', $this->order_mapper->map_status_to_wc( 'awaiting_payment' ) );
        $this->assertEquals( 'processing', $this->order_mapper->map_status_to_wc( 'preparing' ) );
        $this->assertEquals( 'processing', $this->order_mapper->map_status_to_wc( 'shipped' ) );
        $this->assertEquals( 'completed', $this->order_mapper->map_status_to_wc( 'delivered' ) );
        $this->assertEquals( 'cancelled', $this->order_mapper->map_status_to_wc( 'cancelled' ) );
        $this->assertEquals( 'refunded', $this->order_mapper->map_status_to_wc( 'refunded' ) );
    }

    /**
     * Test unknown status passthrough.
     */
    public function test_unknown_status_passthrough() {
        $this->assertEquals( 'custom-status', $this->order_mapper->map_status( 'custom-status' ) );
        $this->assertEquals( 'unknown', $this->order_mapper->map_status_to_wc( 'unknown' ) );
    }

    /**
     * Test order items mapping.
     */
    public function test_order_items_mapping() {
        $order   = WC_Helper_Order::create_order();
        $result  = $this->line_item_mapper->map_order_items( $order );

        $this->assertIsArray( $result );
        $this->assertNotEmpty( $result );

        $first_item = $result[0];
        $this->assertArrayHasKey( 'id', $first_item );
        $this->assertArrayHasKey( 'product_id', $first_item );
        $this->assertArrayHasKey( 'name', $first_item );
        $this->assertArrayHasKey( 'quantity', $first_item );
        $this->assertArrayHasKey( 'unit_price', $first_item );
        $this->assertArrayHasKey( 'subtotal', $first_item );
        $this->assertArrayHasKey( 'total', $first_item );

        $order->delete( true );
    }

    /**
     * Test full order mapping.
     */
    public function test_full_order_mapping() {
        $order  = WC_Helper_Order::create_order();
        $result = $this->order_mapper->map_order( $order );

        $this->assertArrayHasKey( 'id', $result );
        $this->assertArrayHasKey( 'order_number', $result );
        $this->assertArrayHasKey( 'status', $result );
        $this->assertArrayHasKey( 'wc_status', $result );
        $this->assertArrayHasKey( 'currency', $result );
        $this->assertArrayHasKey( 'items', $result );
        $this->assertArrayHasKey( 'totals', $result );
        $this->assertArrayHasKey( 'shipping_address', $result );
        $this->assertArrayHasKey( 'billing_address', $result );
        $this->assertArrayHasKey( 'shipping', $result );
        $this->assertArrayHasKey( 'payment', $result );
        $this->assertArrayHasKey( 'customer', $result );
        $this->assertArrayHasKey( 'meta', $result );
        $this->assertArrayHasKey( 'dates', $result );
        $this->assertArrayHasKey( 'links', $result );

        $order->delete( true );
    }

    /**
     * Test order totals mapping.
     */
    public function test_order_totals_mapping() {
        $order  = WC_Helper_Order::create_order();
        $result = $this->order_mapper->map_order( $order );

        $totals = $result['totals'];
        $this->assertArrayHasKey( 'subtotal', $totals );
        $this->assertArrayHasKey( 'shipping', $totals );
        $this->assertArrayHasKey( 'tax', $totals );
        $this->assertArrayHasKey( 'discount', $totals );
        $this->assertArrayHasKey( 'total', $totals );
        $this->assertArrayHasKey( 'refunded', $totals );
        $this->assertArrayHasKey( 'remaining', $totals );

        $order->delete( true );
    }
}
