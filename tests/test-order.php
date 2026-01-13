<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the Order capability.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Order
 *
 * Tests the order functionality.
 */
class Test_UCP_Order extends WC_Unit_Test_Case {

    /**
     * Order capability instance.
     *
     * @var UCP_WC_Order
     */
    protected $order_capability;

    /**
     * Test order.
     *
     * @var WC_Order
     */
    protected $test_order;

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
        require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-order-mapper.php';
        require_once UCP_WC_PLUGIN_DIR . 'includes/capabilities/class-ucp-order.php';

        // Create tables
        UCP_WC_Activator::activate();

        $this->order_capability = new UCP_WC_Order();

        // Create a test order with UCP session ID
        $this->test_order = WC_Helper_Order::create_order();
        $this->test_order->update_meta_data( '_ucp_session_id', 'ucp_' . bin2hex( random_bytes( 16 ) ) );
        $this->test_order->save();
    }

    /**
     * Tear down test fixtures.
     */
    public function tear_down() {
        if ( $this->test_order ) {
            $this->test_order->delete( true );
        }

        parent::tear_down();
    }

    /**
     * Test getting an order.
     */
    public function test_get_order() {
        $result = $this->order_capability->get_order( $this->test_order->get_id() );

        $this->assertIsArray( $result );
        $this->assertEquals( $this->test_order->get_id(), $result['id'] );
        $this->assertArrayHasKey( 'status', $result );
        $this->assertArrayHasKey( 'items', $result );
        $this->assertArrayHasKey( 'totals', $result );
        $this->assertArrayHasKey( 'shipping_address', $result );
        $this->assertArrayHasKey( 'billing_address', $result );
        $this->assertArrayHasKey( 'dates', $result );
    }

    /**
     * Test getting non-existent order.
     */
    public function test_get_order_not_found() {
        $result = $this->order_capability->get_order( 999999 );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertEquals( 'order_not_found', $result->get_error_code() );
    }

    /**
     * Test listing orders.
     */
    public function test_list_orders() {
        $result = $this->order_capability->list_orders();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'orders', $result );
        $this->assertArrayHasKey( 'total', $result );
        $this->assertArrayHasKey( 'page', $result );
        $this->assertArrayHasKey( 'per_page', $result );
        $this->assertArrayHasKey( 'total_pages', $result );
    }

    /**
     * Test listing orders with pagination.
     */
    public function test_list_orders_pagination() {
        $result = $this->order_capability->list_orders(
            array(
                'page'     => 1,
                'per_page' => 5,
            )
        );

        $this->assertEquals( 1, $result['page'] );
        $this->assertEquals( 5, $result['per_page'] );
    }

    /**
     * Test getting order events.
     */
    public function test_get_order_events() {
        $result = $this->order_capability->get_order_events( $this->test_order->get_id() );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'order_id', $result );
        $this->assertArrayHasKey( 'events', $result );
        $this->assertEquals( $this->test_order->get_id(), $result['order_id'] );

        // Should have at least the creation event
        $this->assertNotEmpty( $result['events'] );

        $first_event = $result['events'][0];
        $this->assertArrayHasKey( 'event_type', $first_event );
        $this->assertArrayHasKey( 'timestamp', $first_event );
        $this->assertArrayHasKey( 'data', $first_event );
    }

    /**
     * Test getting order by session ID.
     */
    public function test_get_order_by_session() {
        global $wpdb;

        // Insert session record
        $session_id = $this->test_order->get_meta( '_ucp_session_id' );
        $table_name = UCP_WC_Activator::get_sessions_table();

        $wpdb->insert(
            $table_name,
            array(
                'session_id'  => $session_id,
                'wc_order_id' => $this->test_order->get_id(),
                'status'      => 'confirmed',
            )
        );

        $result = $this->order_capability->get_order_by_session( $session_id );

        $this->assertIsArray( $result );
        $this->assertEquals( $this->test_order->get_id(), $result['id'] );
    }

    /**
     * Test status mapping.
     */
    public function test_status_mapping() {
        $mapper = new UCP_WC_Order_Mapper();

        $this->assertEquals( 'awaiting_payment', $mapper->map_status( 'pending' ) );
        $this->assertEquals( 'awaiting_payment', $mapper->map_status( 'on-hold' ) );
        $this->assertEquals( 'preparing', $mapper->map_status( 'processing' ) );
        $this->assertEquals( 'delivered', $mapper->map_status( 'completed' ) );
        $this->assertEquals( 'cancelled', $mapper->map_status( 'cancelled' ) );
        $this->assertEquals( 'cancelled', $mapper->map_status( 'failed' ) );
        $this->assertEquals( 'refunded', $mapper->map_status( 'refunded' ) );
    }

    /**
     * Test order summary structure.
     */
    public function test_order_summary() {
        $result = $this->order_capability->list_orders();

        if ( ! empty( $result['orders'] ) ) {
            $order_summary = $result['orders'][0];

            $this->assertArrayHasKey( 'id', $order_summary );
            $this->assertArrayHasKey( 'order_number', $order_summary );
            $this->assertArrayHasKey( 'status', $order_summary );
            $this->assertArrayHasKey( 'total', $order_summary );
            $this->assertArrayHasKey( 'currency', $order_summary );
            $this->assertArrayHasKey( 'customer', $order_summary );
            $this->assertArrayHasKey( 'created_at', $order_summary );
        }
    }

    /**
     * Test order links are included.
     */
    public function test_order_links() {
        $result = $this->order_capability->get_order( $this->test_order->get_id() );

        $this->assertArrayHasKey( 'links', $result );
        $this->assertArrayHasKey( 'self', $result['links'] );
        $this->assertArrayHasKey( 'events', $result['links'] );
        $this->assertArrayHasKey( 'checkout', $result['links'] );
    }
}
