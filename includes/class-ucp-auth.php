<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Authentication handler for UCP API keys.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Auth
 *
 * Handles API key-based authentication for AI agents.
 */
class UCP_WC_Auth {

	/**
	 * Database table name for API keys.
	 *
	 * @var string
	 */
	const TABLE_NAME = 'ucp_api_keys';

	/**
	 * Header name for API key authentication.
	 *
	 * @var string
	 */
	const HEADER_NAME = 'X-UCP-API-Key';

	/**
	 * Query parameter name for API key authentication.
	 *
	 * @var string
	 */
	const QUERY_PARAM = 'ucp_api_key';

	/**
	 * Valid permission levels.
	 *
	 * @var array
	 */
	const PERMISSIONS = array( 'read', 'write', 'admin' );

	/**
	 * Current authenticated API key data.
	 *
	 * @var array|null
	 */
	private static $current_api_key = null;

	/**
	 * Whether authentication has been attempted.
	 *
	 * @var bool
	 */
	private static $auth_attempted = false;

	/**
	 * Initialize the auth handler.
	 */
	public function __construct() {
		// Hook into WordPress authentication.
		add_filter( 'determine_current_user', array( $this, 'authenticate' ), 20 );
		add_filter( 'rest_authentication_errors', array( $this, 'check_authentication_error' ), 15 );
	}

	/**
	 * Authenticate the request using API key.
	 *
	 * @param int|false $user_id Current user ID or false.
	 * @return int|false User ID or false.
	 */
	public function authenticate( $user_id ) {
		// Skip if already authenticated with a user.
		if ( ! empty( $user_id ) ) {
			return $user_id;
		}

		// Only authenticate REST requests.
		if ( ! $this->is_rest_request() ) {
			return $user_id;
		}

		self::$auth_attempted = true;

		$api_key = $this->get_api_key_from_request();

		if ( empty( $api_key ) ) {
			return $user_id;
		}

		$key_data = $this->verify_api_key( $api_key );

		if ( ! $key_data ) {
			return $user_id;
		}

		// Store the authenticated key data.
		self::$current_api_key = $key_data;

		// Update last used timestamp.
		$this->update_last_used( $key_data['id'] );

		// Return the user ID associated with the key, or a fallback admin user.
		if ( ! empty( $key_data['user_id'] ) ) {
			return $key_data['user_id'];
		}

		// For admin-level keys without user association, grant manage_woocommerce capability.
		if ( in_array( 'admin', $key_data['permissions'], true ) ) {
			// Find an admin user to impersonate for capability checks.
			$admins = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
				)
			);
			if ( ! empty( $admins ) ) {
				return $admins[0]->ID;
			}
		}

		return $user_id;
	}

	/**
	 * Check for authentication errors.
	 *
	 * @param WP_Error|null|true $error Authentication error.
	 * @return WP_Error|null|true
	 */
	public function check_authentication_error( $error ) {
		// Pass through existing errors.
		if ( ! empty( $error ) ) {
			return $error;
		}

		// If we attempted auth but failed and no API key was provided, allow the request.
		// Protected endpoints will handle their own permission checks.
		return $error;
	}

	/**
	 * Check if the current request is a REST API request.
	 *
	 * @return bool
	 */
	private function is_rest_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// Check if the request URI contains the REST API base.
		$rest_prefix = rest_get_url_prefix();
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		return strpos( $request_uri, '/' . $rest_prefix . '/' ) !== false;
	}

	/**
	 * Get API key from request (header or query param).
	 *
	 * @return string|null
	 */
	private function get_api_key_from_request() {
		// Check header first (preferred method).
		$header_key = 'HTTP_' . str_replace( '-', '_', strtoupper( self::HEADER_NAME ) );
		if ( ! empty( $_SERVER[ $header_key ] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER[ $header_key ] ) );
		}

		// Fall back to query parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- API key authentication, not form submission.
		if ( ! empty( $_GET[ self::QUERY_PARAM ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( $_GET[ self::QUERY_PARAM ] ) );
		}

		return null;
	}

	/**
	 * Verify an API key and return key data.
	 *
	 * @param string $api_key The API key in format "key_id:secret".
	 * @return array|false Key data array or false if invalid.
	 */
	public function verify_api_key( $api_key ) {
		global $wpdb;

		// Parse key_id:secret format.
		$parts = explode( ':', $api_key, 2 );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		list( $key_id, $secret ) = $parts;

		if ( empty( $key_id ) || empty( $secret ) ) {
			return false;
		}

		// Check cache first.
		$cache_key = 'ucp_api_key_' . md5( $key_id );
		$key_data  = wp_cache_get( $cache_key, 'ucp_api_keys' );

		if ( false === $key_data ) {
			$table_name = $wpdb->prefix . self::TABLE_NAME;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for UCP API keys, no WP API available.
			$key_data = $wpdb->get_row(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses constant prefix.
					"SELECT * FROM {$table_name} WHERE key_id = %s AND status = 'active'",
					$key_id
				),
				ARRAY_A
			);

			// Cache for 5 minutes even if not found to prevent repeated lookups.
			wp_cache_set( $cache_key, $key_data ? $key_data : 'not_found', 'ucp_api_keys', 300 );
		} elseif ( 'not_found' === $key_data ) {
			$key_data = null;
		}

		if ( ! $key_data ) {
			return false;
		}

		// Verify secret.
		if ( ! $this->verify_secret( $secret, $key_data['secret_hash'] ) ) {
			return false;
		}

		// Parse permissions.
		$key_data['permissions'] = json_decode( $key_data['permissions'], true );
		if ( ! is_array( $key_data['permissions'] ) ) {
			$key_data['permissions'] = array();
		}

		return $key_data;
	}

	/**
	 * Get the current authenticated API key info.
	 *
	 * @return array|null Key data or null if not authenticated via API key.
	 */
	public static function get_current_api_key() {
		return self::$current_api_key;
	}

	/**
	 * Check if the current request is authenticated with an API key.
	 *
	 * @return bool
	 */
	public static function is_api_key_authenticated() {
		return self::$current_api_key !== null;
	}

	/**
	 * Check if the current API key has a specific permission.
	 *
	 * @param string $permission Permission to check (read, write, admin).
	 * @return bool
	 */
	public static function check_permission( $permission ) {
		// Allow if authenticated as WordPress user with manage_woocommerce capability.
		if ( current_user_can( 'manage_woocommerce' ) && ! self::is_api_key_authenticated() ) {
			return true;
		}

		// Check API key permissions.
		$key_data = self::get_current_api_key();

		if ( ! $key_data ) {
			return false;
		}

		// Admin permission includes all others.
		if ( in_array( 'admin', $key_data['permissions'], true ) ) {
			return true;
		}

		// Write permission includes read.
		if ( 'read' === $permission && in_array( 'write', $key_data['permissions'], true ) ) {
			return true;
		}

		return in_array( $permission, $key_data['permissions'], true );
	}

	/**
	 * Generate a new API key pair.
	 *
	 * @param array $args {
	 *     Key generation arguments.
	 *
	 *     @type string $description Key description.
	 *     @type array  $permissions Array of permissions (read, write, admin).
	 *     @type int    $user_id     Optional user ID to associate with key.
	 * }
	 * @return array|WP_Error Key data including secret (only shown once) or error.
	 */
	public function generate_api_key( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'description' => '',
			'permissions' => array( 'read' ),
			'user_id'     => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate permissions.
		$permissions = array_intersect( $args['permissions'], self::PERMISSIONS );
		if ( empty( $permissions ) ) {
			return new WP_Error(
				'invalid_permissions',
				__( 'At least one valid permission (read, write, admin) is required.', 'ucp-for-woocommerce' )
			);
		}

		// Generate key_id and secret.
		$key_id = 'ucp_' . $this->generate_random_string( 12 );
		$secret = 'ucp_secret_' . $this->generate_random_string( 32 );

		// Hash the secret for storage.
		$secret_hash = $this->hash_secret( $secret );

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Insert the key.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for UCP API keys, no WP API available.
		$result = $wpdb->insert(
			$table_name,
			array(
				'key_id'       => $key_id,
				'secret_hash'  => $secret_hash,
				'description'  => sanitize_text_field( $args['description'] ),
				'permissions'  => wp_json_encode( array_values( $permissions ) ),
				'user_id'      => absint( $args['user_id'] ),
				'created_at'   => current_time( 'mysql' ),
				'last_used_at' => null,
				'status'       => 'active',
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_error',
				__( 'Failed to create API key.', 'ucp-for-woocommerce' )
			);
		}

		return array(
			'key_id'      => $key_id,
			'secret'      => $secret,
			'description' => $args['description'],
			'permissions' => array_values( $permissions ),
			'user_id'     => absint( $args['user_id'] ),
			'created_at'  => gmdate( 'c' ),
			'last_used'   => null,
			'status'      => 'active',
		);
	}

	/**
	 * List all API keys.
	 *
	 * @param array $args Query arguments.
	 * @return array Array of key data (without secrets).
	 */
	public function list_api_keys( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => 'active',
			'page'     => 1,
			'per_page' => 20,
		);

		$args = wp_parse_args( $args, $defaults );

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$offset     = ( absint( $args['page'] ) - 1 ) * absint( $args['per_page'] );

		// Build query based on status filter.
		if ( 'all' !== $args['status'] ) {
			$status = sanitize_key( $args['status'] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, table name uses constant prefix.
			$keys = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, key_id, description, permissions, user_id, created_at, last_used_at, status
					FROM {$table_name}
					WHERE status = %s
					ORDER BY created_at DESC
					LIMIT %d OFFSET %d",
					$status,
					absint( $args['per_page'] ),
					$offset
				),
				ARRAY_A
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, table name uses constant prefix.
			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
					$status
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, table name uses constant prefix.
			$keys = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, key_id, description, permissions, user_id, created_at, last_used_at, status
					FROM {$table_name}
					ORDER BY created_at DESC
					LIMIT %d OFFSET %d",
					absint( $args['per_page'] ),
					$offset
				),
				ARRAY_A
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, table name uses constant prefix.
			$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		}

		$formatted_keys = array();
		foreach ( $keys as $key ) {
			$formatted_keys[] = array(
				'id'          => (int) $key['id'],
				'key_id'      => $key['key_id'],
				'description' => $key['description'],
				'permissions' => json_decode( $key['permissions'], true ),
				'user_id'     => (int) $key['user_id'],
				'created_at'  => $key['created_at'] ? gmdate( 'c', strtotime( $key['created_at'] ) ) : null,
				'last_used'   => $key['last_used_at'] ? gmdate( 'c', strtotime( $key['last_used_at'] ) ) : null,
				'status'      => $key['status'],
			);
		}

		return array(
			'keys'        => $formatted_keys,
			'total'       => (int) $total,
			'page'        => (int) $args['page'],
			'per_page'    => (int) $args['per_page'],
			'total_pages' => (int) ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Get a single API key by key_id.
	 *
	 * @param string $key_id The key_id to look up.
	 * @return array|null Key data or null if not found.
	 */
	public function get_api_key( $key_id ) {
		global $wpdb;

		// Check cache first.
		$cache_key = 'ucp_api_key_info_' . md5( $key_id );
		$key       = wp_cache_get( $cache_key, 'ucp_api_keys' );

		if ( false === $key ) {
			$table_name = $wpdb->prefix . self::TABLE_NAME;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table for UCP API keys, table name uses constant prefix.
			$key = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, key_id, description, permissions, user_id, created_at, last_used_at, status
					FROM {$table_name}
					WHERE key_id = %s",
					$key_id
				),
				ARRAY_A
			);

			// Cache for 5 minutes.
			wp_cache_set( $cache_key, $key ? $key : 'not_found', 'ucp_api_keys', 300 );
		} elseif ( 'not_found' === $key ) {
			$key = null;
		}

		if ( ! $key ) {
			return null;
		}

		return array(
			'id'          => (int) $key['id'],
			'key_id'      => $key['key_id'],
			'description' => $key['description'],
			'permissions' => json_decode( $key['permissions'], true ),
			'user_id'     => (int) $key['user_id'],
			'created_at'  => $key['created_at'] ? gmdate( 'c', strtotime( $key['created_at'] ) ) : null,
			'last_used'   => $key['last_used_at'] ? gmdate( 'c', strtotime( $key['last_used_at'] ) ) : null,
			'status'      => $key['status'],
		);
	}

	/**
	 * Revoke an API key.
	 *
	 * @param string $key_id The key_id to revoke.
	 * @return bool|WP_Error True on success or error.
	 */
	public function revoke_api_key( $key_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Check if key exists.
		$existing = $this->get_api_key( $key_id );
		if ( ! $existing ) {
			return new WP_Error(
				'key_not_found',
				__( 'API key not found.', 'ucp-for-woocommerce' )
			);
		}

		// Update status to revoked.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, write operation invalidates cache below.
		$result = $wpdb->update(
			$table_name,
			array( 'status' => 'revoked' ),
			array( 'key_id' => $key_id ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_error',
				__( 'Failed to revoke API key.', 'ucp-for-woocommerce' )
			);
		}

		// Invalidate cache.
		$this->invalidate_key_cache( $key_id );

		return true;
	}

	/**
	 * Delete an API key permanently.
	 *
	 * @param string $key_id The key_id to delete.
	 * @return bool|WP_Error True on success or error.
	 */
	public function delete_api_key( $key_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Check if key exists.
		$existing = $this->get_api_key( $key_id );
		if ( ! $existing ) {
			return new WP_Error(
				'key_not_found',
				__( 'API key not found.', 'ucp-for-woocommerce' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, write operation invalidates cache below.
		$result = $wpdb->delete(
			$table_name,
			array( 'key_id' => $key_id ),
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'db_error',
				__( 'Failed to delete API key.', 'ucp-for-woocommerce' )
			);
		}

		// Invalidate cache.
		$this->invalidate_key_cache( $key_id );

		return true;
	}

	/**
	 * Hash the API key secret for storage.
	 *
	 * @param string $secret The secret to hash.
	 * @return string Hashed secret.
	 */
	public function hash_secret( $secret ) {
		return password_hash( $secret, PASSWORD_DEFAULT );
	}

	/**
	 * Verify a secret against its hash.
	 *
	 * @param string $secret The secret to verify.
	 * @param string $hash   The stored hash.
	 * @return bool True if valid.
	 */
	private function verify_secret( $secret, $hash ) {
		return password_verify( $secret, $hash );
	}

	/**
	 * Update the last used timestamp for a key.
	 *
	 * @param int $key_db_id Database ID of the key.
	 */
	private function update_last_used( $key_db_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, timestamp update doesn't require cache invalidation.
		$wpdb->update(
			$table_name,
			array( 'last_used_at' => current_time( 'mysql' ) ),
			array( 'id' => $key_db_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Invalidate cache for a specific API key.
	 *
	 * @param string $key_id The key_id to invalidate cache for.
	 */
	private function invalidate_key_cache( $key_id ) {
		wp_cache_delete( 'ucp_api_key_' . md5( $key_id ), 'ucp_api_keys' );
		wp_cache_delete( 'ucp_api_key_info_' . md5( $key_id ), 'ucp_api_keys' );
	}

	/**
	 * Generate a random string.
	 *
	 * @param int $length Length of the string.
	 * @return string Random string.
	 */
	private function generate_random_string( $length = 16 ) {
		return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) );
	}

	/**
	 * Get the API keys table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}
}
