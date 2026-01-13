<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the Auth capability.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Auth
 *
 * Tests the API key authentication functionality.
 */
class Test_UCP_Auth extends WC_Unit_Test_Case {

	/**
	 * Auth handler instance.
	 *
	 * @var UCP_WC_Auth
	 */
	protected $auth;

	/**
	 * Test API key data.
	 *
	 * @var array
	 */
	protected $test_key;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Load required classes.
		require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-auth.php';

		// Create tables.
		UCP_WC_Activator::activate();

		// Create auth instance.
		$this->auth = new UCP_WC_Auth();

		// Create admin user.
		$this->admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		// Enable UCP.
		update_option( 'ucp_wc_enabled', 'yes' );

		// Reset static properties for clean state.
		$this->reset_auth_state();
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		global $wpdb;

		// Clean up test API keys.
		$table_name = $wpdb->prefix . UCP_WC_Auth::TABLE_NAME;
		$wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Reset current user.
		wp_set_current_user( 0 );

		// Reset static auth state.
		$this->reset_auth_state();

		parent::tear_down();
	}

	/**
	 * Reset auth static state using reflection.
	 */
	private function reset_auth_state() {
		$reflection = new ReflectionClass( 'UCP_WC_Auth' );

		$current_api_key = $reflection->getProperty( 'current_api_key' );
		$current_api_key->setAccessible( true );
		$current_api_key->setValue( null, null );

		$auth_attempted = $reflection->getProperty( 'auth_attempted' );
		$auth_attempted->setAccessible( true );
		$auth_attempted->setValue( null, false );
	}

	/**
	 * Test generating a new API key.
	 */
	public function test_generate_api_key() {
		wp_set_current_user( $this->admin_id );

		$result = $this->auth->generate_api_key(
			array(
				'description' => 'Test Key',
				'permissions' => array( 'read', 'write' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'key_id', $result );
		$this->assertArrayHasKey( 'secret', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'permissions', $result );
		$this->assertArrayHasKey( 'created_at', $result );
		$this->assertArrayHasKey( 'status', $result );

		$this->assertEquals( 'Test Key', $result['description'] );
		$this->assertEquals( array( 'read', 'write' ), $result['permissions'] );
		$this->assertEquals( 'active', $result['status'] );
	}

	/**
	 * Test API key format validation.
	 */
	public function test_api_key_format() {
		wp_set_current_user( $this->admin_id );

		$result = $this->auth->generate_api_key(
			array(
				'description' => 'Format Test Key',
				'permissions' => array( 'read' ),
			)
		);

		$this->assertIsArray( $result );

		// Verify key_id format: starts with 'ucp_' followed by alphanumeric characters.
		$this->assertStringStartsWith( 'ucp_', $result['key_id'] );
		$this->assertMatchesRegularExpression( '/^ucp_[a-f0-9]+$/', $result['key_id'] );

		// Verify secret format: starts with 'ucp_secret_' followed by alphanumeric characters.
		$this->assertStringStartsWith( 'ucp_secret_', $result['secret'] );
		$this->assertMatchesRegularExpression( '/^ucp_secret_[a-f0-9]+$/', $result['secret'] );

		// Verify key_id length (ucp_ + 12 hex chars = ~16+ chars).
		$this->assertGreaterThanOrEqual( 16, strlen( $result['key_id'] ) );

		// Verify secret length (ucp_secret_ + 32 hex chars = ~43+ chars).
		$this->assertGreaterThanOrEqual( 43, strlen( $result['secret'] ) );
	}

	/**
	 * Test verifying a valid API key.
	 */
	public function test_verify_api_key_valid() {
		wp_set_current_user( $this->admin_id );

		// Generate a key first.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Valid Key Test',
				'permissions' => array( 'read', 'write' ),
			)
		);

		$this->assertIsArray( $generated );

		// Create the full API key string.
		$api_key = $generated['key_id'] . ':' . $generated['secret'];

		// Verify the key.
		$result = $this->auth->verify_api_key( $api_key );

		$this->assertIsArray( $result );
		$this->assertEquals( $generated['key_id'], $result['key_id'] );
		$this->assertContains( 'read', $result['permissions'] );
		$this->assertContains( 'write', $result['permissions'] );
		$this->assertEquals( 'active', $result['status'] );
	}

	/**
	 * Test error for invalid API key.
	 */
	public function test_verify_api_key_invalid() {
		// Test with completely invalid key.
		$result = $this->auth->verify_api_key( 'invalid_key' );
		$this->assertFalse( $result );

		// Test with invalid format (missing colon).
		$result = $this->auth->verify_api_key( 'ucp_abc123ucp_secret_xyz' );
		$this->assertFalse( $result );

		// Test with valid format but non-existent key_id.
		$result = $this->auth->verify_api_key( 'ucp_nonexistent:ucp_secret_invalid' );
		$this->assertFalse( $result );

		// Test with valid key_id but wrong secret.
		wp_set_current_user( $this->admin_id );
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Wrong Secret Test',
				'permissions' => array( 'read' ),
			)
		);

		$result = $this->auth->verify_api_key( $generated['key_id'] . ':wrong_secret' );
		$this->assertFalse( $result );
	}

	/**
	 * Test error for revoked API key.
	 */
	public function test_verify_api_key_revoked() {
		wp_set_current_user( $this->admin_id );

		// Generate a key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Revoked Key Test',
				'permissions' => array( 'read' ),
			)
		);

		$this->assertIsArray( $generated );
		$api_key = $generated['key_id'] . ':' . $generated['secret'];

		// Verify key works before revoking.
		$result = $this->auth->verify_api_key( $api_key );
		$this->assertIsArray( $result );

		// Revoke the key.
		$revoke_result = $this->auth->revoke_api_key( $generated['key_id'] );
		$this->assertTrue( $revoke_result );

		// Verify key no longer works.
		$result = $this->auth->verify_api_key( $api_key );
		$this->assertFalse( $result );
	}

	/**
	 * Test listing all API keys (admin only).
	 */
	public function test_list_api_keys() {
		wp_set_current_user( $this->admin_id );

		// Generate multiple keys.
		$key1 = $this->auth->generate_api_key(
			array(
				'description' => 'List Test Key 1',
				'permissions' => array( 'read' ),
			)
		);

		$key2 = $this->auth->generate_api_key(
			array(
				'description' => 'List Test Key 2',
				'permissions' => array( 'read', 'write' ),
			)
		);

		$key3 = $this->auth->generate_api_key(
			array(
				'description' => 'List Test Key 3',
				'permissions' => array( 'admin' ),
			)
		);

		// List keys.
		$result = $this->auth->list_api_keys();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'keys', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'page', $result );
		$this->assertArrayHasKey( 'per_page', $result );
		$this->assertArrayHasKey( 'total_pages', $result );

		$this->assertEquals( 3, $result['total'] );
		$this->assertCount( 3, $result['keys'] );

		// Verify keys do not contain secrets.
		foreach ( $result['keys'] as $key ) {
			$this->assertArrayNotHasKey( 'secret', $key );
			$this->assertArrayNotHasKey( 'secret_hash', $key );
			$this->assertArrayHasKey( 'key_id', $key );
			$this->assertArrayHasKey( 'description', $key );
			$this->assertArrayHasKey( 'permissions', $key );
			$this->assertArrayHasKey( 'status', $key );
		}
	}

	/**
	 * Test revoking an existing API key.
	 */
	public function test_revoke_api_key() {
		wp_set_current_user( $this->admin_id );

		// Generate a key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Revoke Test Key',
				'permissions' => array( 'read', 'write' ),
			)
		);

		$this->assertIsArray( $generated );

		// Verify key exists and is active.
		$key_data = $this->auth->get_api_key( $generated['key_id'] );
		$this->assertEquals( 'active', $key_data['status'] );

		// Revoke the key.
		$result = $this->auth->revoke_api_key( $generated['key_id'] );
		$this->assertTrue( $result );

		// Verify key status changed.
		$key_data = $this->auth->get_api_key( $generated['key_id'] );
		$this->assertEquals( 'revoked', $key_data['status'] );

		// Test revoking non-existent key.
		$result = $this->auth->revoke_api_key( 'ucp_nonexistent' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'key_not_found', $result->get_error_code() );
	}

	/**
	 * Test API key permissions (read/write/admin).
	 */
	public function test_api_key_permissions() {
		wp_set_current_user( $this->admin_id );

		// Test read-only key.
		$read_key = $this->auth->generate_api_key(
			array(
				'description' => 'Read Only Key',
				'permissions' => array( 'read' ),
			)
		);

		$this->assertIsArray( $read_key );
		$this->assertEquals( array( 'read' ), $read_key['permissions'] );

		// Test write key (includes read).
		$write_key = $this->auth->generate_api_key(
			array(
				'description' => 'Write Key',
				'permissions' => array( 'write' ),
			)
		);

		$this->assertIsArray( $write_key );
		$this->assertEquals( array( 'write' ), $write_key['permissions'] );

		// Test admin key (includes all).
		$admin_key = $this->auth->generate_api_key(
			array(
				'description' => 'Admin Key',
				'permissions' => array( 'admin' ),
			)
		);

		$this->assertIsArray( $admin_key );
		$this->assertEquals( array( 'admin' ), $admin_key['permissions'] );

		// Test combined permissions.
		$combined_key = $this->auth->generate_api_key(
			array(
				'description' => 'Combined Key',
				'permissions' => array( 'read', 'write', 'admin' ),
			)
		);

		$this->assertIsArray( $combined_key );
		$this->assertContains( 'read', $combined_key['permissions'] );
		$this->assertContains( 'write', $combined_key['permissions'] );
		$this->assertContains( 'admin', $combined_key['permissions'] );

		// Test invalid permissions are filtered.
		$invalid_key = $this->auth->generate_api_key(
			array(
				'description' => 'Invalid Permissions Key',
				'permissions' => array( 'read', 'invalid', 'superadmin' ),
			)
		);

		$this->assertIsArray( $invalid_key );
		$this->assertEquals( array( 'read' ), $invalid_key['permissions'] );

		// Test empty permissions returns error.
		$empty_key = $this->auth->generate_api_key(
			array(
				'description' => 'Empty Permissions Key',
				'permissions' => array( 'invalid' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $empty_key );
		$this->assertEquals( 'invalid_permissions', $empty_key->get_error_code() );
	}

	/**
	 * Test read permission check.
	 */
	public function test_check_permission_read() {
		wp_set_current_user( $this->admin_id );

		// Generate a read-only key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Read Permission Test',
				'permissions' => array( 'read' ),
			)
		);

		// Verify and set as current key.
		$api_key  = $generated['key_id'] . ':' . $generated['secret'];
		$key_data = $this->auth->verify_api_key( $api_key );
		$this->set_current_api_key( $key_data );

		// Reset current user to simulate API-only auth.
		wp_set_current_user( 0 );

		// Check permissions.
		$this->assertTrue( UCP_WC_Auth::check_permission( 'read' ) );
		$this->assertFalse( UCP_WC_Auth::check_permission( 'write' ) );
		$this->assertFalse( UCP_WC_Auth::check_permission( 'admin' ) );
	}

	/**
	 * Test write permission check.
	 */
	public function test_check_permission_write() {
		wp_set_current_user( $this->admin_id );

		// Generate a write key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Write Permission Test',
				'permissions' => array( 'write' ),
			)
		);

		// Verify and set as current key.
		$api_key  = $generated['key_id'] . ':' . $generated['secret'];
		$key_data = $this->auth->verify_api_key( $api_key );
		$this->set_current_api_key( $key_data );

		// Reset current user to simulate API-only auth.
		wp_set_current_user( 0 );

		// Write permission should include read.
		$this->assertTrue( UCP_WC_Auth::check_permission( 'read' ) );
		$this->assertTrue( UCP_WC_Auth::check_permission( 'write' ) );
		$this->assertFalse( UCP_WC_Auth::check_permission( 'admin' ) );
	}

	/**
	 * Test admin permission check.
	 */
	public function test_check_permission_admin() {
		wp_set_current_user( $this->admin_id );

		// Generate an admin key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Admin Permission Test',
				'permissions' => array( 'admin' ),
			)
		);

		// Verify and set as current key.
		$api_key  = $generated['key_id'] . ':' . $generated['secret'];
		$key_data = $this->auth->verify_api_key( $api_key );
		$this->set_current_api_key( $key_data );

		// Reset current user to simulate API-only auth.
		wp_set_current_user( 0 );

		// Admin permission should include all.
		$this->assertTrue( UCP_WC_Auth::check_permission( 'read' ) );
		$this->assertTrue( UCP_WC_Auth::check_permission( 'write' ) );
		$this->assertTrue( UCP_WC_Auth::check_permission( 'admin' ) );
	}

	/**
	 * Test authentication via X-UCP-API-Key header.
	 */
	public function test_authenticate_via_header() {
		wp_set_current_user( $this->admin_id );

		// Generate a key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Header Auth Test',
				'permissions' => array( 'read', 'write' ),
				'user_id'     => $this->admin_id,
			)
		);

		$api_key = $generated['key_id'] . ':' . $generated['secret'];

		// Simulate header authentication.
		$header_key = 'HTTP_' . str_replace( '-', '_', strtoupper( UCP_WC_Auth::HEADER_NAME ) );
		$_SERVER[ $header_key ] = $api_key;

		// Simulate REST request environment.
		$_SERVER['REQUEST_URI'] = '/wp-json/ucp/v1/test';

		// Reset auth state.
		$this->reset_auth_state();

		// Create a fresh auth instance and trigger authentication.
		$auth   = new UCP_WC_Auth();
		$result = $auth->authenticate( false );

		// Verify authentication succeeded.
		$this->assertEquals( $this->admin_id, $result );

		// Verify current API key is set.
		$current_key = UCP_WC_Auth::get_current_api_key();
		$this->assertNotNull( $current_key );
		$this->assertEquals( $generated['key_id'], $current_key['key_id'] );

		// Clean up.
		unset( $_SERVER[ $header_key ] );
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test authentication via query parameter.
	 */
	public function test_authenticate_via_query_param() {
		wp_set_current_user( $this->admin_id );

		// Generate a key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Query Param Auth Test',
				'permissions' => array( 'read' ),
				'user_id'     => $this->admin_id,
			)
		);

		$api_key = $generated['key_id'] . ':' . $generated['secret'];

		// Simulate query parameter authentication.
		$_GET[ UCP_WC_Auth::QUERY_PARAM ] = $api_key;

		// Simulate REST request environment.
		$_SERVER['REQUEST_URI'] = '/wp-json/ucp/v1/test';

		// Reset auth state.
		$this->reset_auth_state();

		// Create a fresh auth instance and trigger authentication.
		$auth   = new UCP_WC_Auth();
		$result = $auth->authenticate( false );

		// Verify authentication succeeded.
		$this->assertEquals( $this->admin_id, $result );

		// Verify current API key is set.
		$current_key = UCP_WC_Auth::get_current_api_key();
		$this->assertNotNull( $current_key );
		$this->assertEquals( $generated['key_id'], $current_key['key_id'] );

		// Clean up.
		unset( $_GET[ UCP_WC_Auth::QUERY_PARAM ] );
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test last_used_at timestamp updates.
	 */
	public function test_last_used_tracking() {
		global $wpdb;

		wp_set_current_user( $this->admin_id );

		// Generate a key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Last Used Test',
				'permissions' => array( 'read' ),
				'user_id'     => $this->admin_id,
			)
		);

		// Verify last_used is null initially.
		$key_data = $this->auth->get_api_key( $generated['key_id'] );
		$this->assertNull( $key_data['last_used'] );

		$api_key = $generated['key_id'] . ':' . $generated['secret'];

		// Simulate header authentication.
		$header_key = 'HTTP_' . str_replace( '-', '_', strtoupper( UCP_WC_Auth::HEADER_NAME ) );
		$_SERVER[ $header_key ] = $api_key;

		// Simulate REST request environment.
		$_SERVER['REQUEST_URI'] = '/wp-json/ucp/v1/test';

		// Reset auth state.
		$this->reset_auth_state();

		// Authenticate.
		$auth = new UCP_WC_Auth();
		$auth->authenticate( false );

		// Clean up.
		unset( $_SERVER[ $header_key ] );
		unset( $_SERVER['REQUEST_URI'] );

		// Verify last_used is now set.
		$key_data = $this->auth->get_api_key( $generated['key_id'] );
		$this->assertNotNull( $key_data['last_used'] );

		// Verify the timestamp is recent (within last minute).
		$last_used_time = strtotime( $key_data['last_used'] );
		$this->assertGreaterThan( time() - 60, $last_used_time );
	}

	/**
	 * Test non-admin cannot create API keys.
	 */
	public function test_create_key_unauthorized() {
		// Create a subscriber user.
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		// Verify subscriber cannot manage_woocommerce.
		$this->assertFalse( current_user_can( 'manage_woocommerce' ) );

		// The generate_api_key method itself does not check permissions.
		// Permission checks are done at the REST API controller level.
		// However, we can test the REST controller's permission check.

		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-rest-controller.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-auth-controller.php';

		$controller = new UCP_WC_Auth_Controller();
		$request    = new WP_REST_Request( 'POST', '/ucp/v1/auth/keys' );

		// Reset auth state to ensure no API key auth.
		$this->reset_auth_state();

		// Check permission.
		$result = $controller->check_admin_permission( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ucp_forbidden', $result->get_error_code() );

		// Also test with customer role.
		$customer_id = $this->factory->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $customer_id );

		$result = $controller->check_admin_permission( $request );
		$this->assertInstanceOf( WP_Error::class, $result );

		// Verify admin CAN create keys.
		wp_set_current_user( $this->admin_id );
		$result = $controller->check_admin_permission( $request );
		$this->assertTrue( $result );

		// Also verify shop_manager can create keys.
		$shop_manager_id = $this->factory->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $shop_manager_id );
		$result = $controller->check_admin_permission( $request );
		$this->assertTrue( $result );
	}

	/**
	 * Helper method to set current API key for testing.
	 *
	 * @param array $key_data Key data to set.
	 */
	private function set_current_api_key( $key_data ) {
		$reflection       = new ReflectionClass( 'UCP_WC_Auth' );
		$current_api_key  = $reflection->getProperty( 'current_api_key' );
		$current_api_key->setAccessible( true );
		$current_api_key->setValue( null, $key_data );
	}

	/**
	 * Test is_api_key_authenticated method.
	 */
	public function test_is_api_key_authenticated() {
		// Initially should be false.
		$this->assertFalse( UCP_WC_Auth::is_api_key_authenticated() );

		wp_set_current_user( $this->admin_id );

		// Generate and verify a key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Auth Check Test',
				'permissions' => array( 'read' ),
			)
		);

		$api_key  = $generated['key_id'] . ':' . $generated['secret'];
		$key_data = $this->auth->verify_api_key( $api_key );

		// Set as current key.
		$this->set_current_api_key( $key_data );

		// Now should be true.
		$this->assertTrue( UCP_WC_Auth::is_api_key_authenticated() );
	}

	/**
	 * Test get_current_api_key method.
	 */
	public function test_get_current_api_key() {
		// Initially should be null.
		$this->assertNull( UCP_WC_Auth::get_current_api_key() );

		wp_set_current_user( $this->admin_id );

		// Generate and verify a key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Get Current Key Test',
				'permissions' => array( 'read', 'write' ),
			)
		);

		$api_key  = $generated['key_id'] . ':' . $generated['secret'];
		$key_data = $this->auth->verify_api_key( $api_key );

		// Set as current key.
		$this->set_current_api_key( $key_data );

		// Verify we can retrieve it.
		$current = UCP_WC_Auth::get_current_api_key();
		$this->assertIsArray( $current );
		$this->assertEquals( $generated['key_id'], $current['key_id'] );
	}

	/**
	 * Test delete_api_key method.
	 */
	public function test_delete_api_key() {
		wp_set_current_user( $this->admin_id );

		// Generate a key.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'Delete Test Key',
				'permissions' => array( 'read' ),
			)
		);

		$this->assertIsArray( $generated );

		// Verify key exists.
		$key_data = $this->auth->get_api_key( $generated['key_id'] );
		$this->assertNotNull( $key_data );

		// Delete the key.
		$result = $this->auth->delete_api_key( $generated['key_id'] );
		$this->assertTrue( $result );

		// Verify key no longer exists.
		$key_data = $this->auth->get_api_key( $generated['key_id'] );
		$this->assertNull( $key_data );

		// Test deleting non-existent key.
		$result = $this->auth->delete_api_key( 'ucp_nonexistent' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'key_not_found', $result->get_error_code() );
	}

	/**
	 * Test list_api_keys with pagination.
	 */
	public function test_list_api_keys_pagination() {
		wp_set_current_user( $this->admin_id );

		// Generate 5 keys.
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->auth->generate_api_key(
				array(
					'description' => "Pagination Test Key {$i}",
					'permissions' => array( 'read' ),
				)
			);
		}

		// Test page 1 with 2 items per page.
		$result = $this->auth->list_api_keys(
			array(
				'page'     => 1,
				'per_page' => 2,
			)
		);

		$this->assertEquals( 5, $result['total'] );
		$this->assertEquals( 1, $result['page'] );
		$this->assertEquals( 2, $result['per_page'] );
		$this->assertEquals( 3, $result['total_pages'] );
		$this->assertCount( 2, $result['keys'] );

		// Test page 2.
		$result = $this->auth->list_api_keys(
			array(
				'page'     => 2,
				'per_page' => 2,
			)
		);

		$this->assertEquals( 2, $result['page'] );
		$this->assertCount( 2, $result['keys'] );

		// Test page 3 (only 1 item).
		$result = $this->auth->list_api_keys(
			array(
				'page'     => 3,
				'per_page' => 2,
			)
		);

		$this->assertEquals( 3, $result['page'] );
		$this->assertCount( 1, $result['keys'] );
	}

	/**
	 * Test list_api_keys filtering by status.
	 */
	public function test_list_api_keys_status_filter() {
		wp_set_current_user( $this->admin_id );

		// Generate keys.
		$active_key = $this->auth->generate_api_key(
			array(
				'description' => 'Active Key',
				'permissions' => array( 'read' ),
			)
		);

		$revoke_key = $this->auth->generate_api_key(
			array(
				'description' => 'Revoked Key',
				'permissions' => array( 'read' ),
			)
		);

		// Revoke one key.
		$this->auth->revoke_api_key( $revoke_key['key_id'] );

		// Test active only.
		$result = $this->auth->list_api_keys( array( 'status' => 'active' ) );
		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( 'active', $result['keys'][0]['status'] );

		// Test revoked only.
		$result = $this->auth->list_api_keys( array( 'status' => 'revoked' ) );
		$this->assertEquals( 1, $result['total'] );
		$this->assertEquals( 'revoked', $result['keys'][0]['status'] );

		// Test all.
		$result = $this->auth->list_api_keys( array( 'status' => 'all' ) );
		$this->assertEquals( 2, $result['total'] );
	}

	/**
	 * Test hash_secret method.
	 */
	public function test_hash_secret() {
		$secret = 'ucp_secret_test123456789';
		$hash   = $this->auth->hash_secret( $secret );

		// Hash should be different from original.
		$this->assertNotEquals( $secret, $hash );

		// Hash should be a valid password hash.
		$this->assertTrue( password_verify( $secret, $hash ) );

		// Different secrets should produce different hashes.
		$secret2 = 'ucp_secret_different';
		$hash2   = $this->auth->hash_secret( $secret2 );
		$this->assertNotEquals( $hash, $hash2 );
	}

	/**
	 * Test get_table_name static method.
	 */
	public function test_get_table_name() {
		global $wpdb;

		$expected = $wpdb->prefix . UCP_WC_Auth::TABLE_NAME;
		$actual   = UCP_WC_Auth::get_table_name();

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Test API key with associated user_id.
	 */
	public function test_api_key_with_user_id() {
		wp_set_current_user( $this->admin_id );

		// Create another user.
		$user_id = $this->factory->user->create( array( 'role' => 'customer' ) );

		// Generate key associated with user.
		$generated = $this->auth->generate_api_key(
			array(
				'description' => 'User Associated Key',
				'permissions' => array( 'read' ),
				'user_id'     => $user_id,
			)
		);

		$this->assertIsArray( $generated );
		$this->assertEquals( $user_id, $generated['user_id'] );

		// Verify the key.
		$api_key  = $generated['key_id'] . ':' . $generated['secret'];
		$key_data = $this->auth->verify_api_key( $api_key );

		$this->assertIsArray( $key_data );
		$this->assertEquals( $user_id, (int) $key_data['user_id'] );
	}
}
