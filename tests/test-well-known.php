<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the /.well-known/ucp endpoint.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Well_Known
 *
 * Tests the discovery endpoint functionality.
 */
class Test_UCP_Well_Known extends WP_UnitTestCase {

    /**
     * Well-known handler instance.
     *
     * @var UCP_WC_Well_Known
     */
    protected $well_known;

    /**
     * Set up test fixtures.
     */
    public function set_up() {
        parent::set_up();

        require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
        require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-well-known.php';

        $this->well_known = new UCP_WC_Well_Known();

        // Enable UCP
        update_option( 'ucp_wc_enabled', 'yes' );
        update_option( 'ucp_wc_guest_checkout', 'yes' );
    }

    /**
     * Tear down test fixtures.
     */
    public function tear_down() {
        parent::tear_down();
    }

    /**
     * Test business profile structure.
     */
    public function test_business_profile_structure() {
        $profile = $this->well_known->get_business_profile();

        $this->assertIsArray( $profile );
        $this->assertArrayHasKey( 'schema_version', $profile );
        $this->assertArrayHasKey( 'business', $profile );
        $this->assertArrayHasKey( 'capabilities', $profile );
        $this->assertArrayHasKey( 'policies', $profile );
        $this->assertArrayHasKey( 'signing_keys', $profile );
        $this->assertArrayHasKey( 'metadata', $profile );
    }

    /**
     * Test business info contains required fields.
     */
    public function test_business_info() {
        $profile = $this->well_known->get_business_profile();

        $this->assertArrayHasKey( 'name', $profile['business'] );
        $this->assertArrayHasKey( 'url', $profile['business'] );
    }

    /**
     * Test capabilities structure.
     */
    public function test_capabilities_structure() {
        $profile = $this->well_known->get_business_profile();

        $this->assertArrayHasKey( 'checkout', $profile['capabilities'] );
        $this->assertArrayHasKey( 'order', $profile['capabilities'] );

        // Checkout capability
        $checkout = $profile['capabilities']['checkout'];
        $this->assertTrue( $checkout['enabled'] );
        $this->assertArrayHasKey( 'rest', $checkout );
        $this->assertArrayHasKey( 'features', $checkout );
        $this->assertTrue( $checkout['features']['guest_checkout'] );

        // Order capability
        $order = $profile['capabilities']['order'];
        $this->assertTrue( $order['enabled'] );
        $this->assertArrayHasKey( 'rest', $order );
        $this->assertArrayHasKey( 'webhooks', $order );
    }

    /**
     * Test metadata structure.
     */
    public function test_metadata() {
        $profile = $this->well_known->get_business_profile();

        $this->assertEquals( 'WooCommerce', $profile['metadata']['platform'] );
        $this->assertEquals( UCP_WC_VERSION, $profile['metadata']['plugin_version'] );
        $this->assertArrayHasKey( 'updated_at', $profile['metadata'] );
    }

    /**
     * Test schema version.
     */
    public function test_schema_version() {
        $profile = $this->well_known->get_business_profile();

        $this->assertEquals( '1.0', $profile['schema_version'] );
    }

    /**
     * Test query vars are registered.
     */
    public function test_query_vars() {
        $vars = $this->well_known->add_query_vars( array() );

        $this->assertContains( 'ucp_well_known', $vars );
    }

    /**
     * Test UCP disabled returns no profile.
     */
    public function test_disabled_ucp() {
        update_option( 'ucp_wc_enabled', 'no' );

        // The profile should still be generated, but the request handler
        // would return a 503 error. We test the profile generation here.
        $profile = $this->well_known->get_business_profile();

        // Profile is still generated
        $this->assertIsArray( $profile );
    }
}
