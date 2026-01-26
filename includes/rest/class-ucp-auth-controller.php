<?php
/**
 * REST controller for authentication endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Auth_Controller
 *
 * Handles API key management REST endpoints.
 */
class UCP_WC_Auth_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'auth';

	/**
	 * Auth handler instance.
	 *
	 * @var UCP_WC_Auth
	 */
	protected $auth;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->auth = new UCP_WC_Auth();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /auth/keys - Create new API key (admin only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/keys',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_api_key' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_create_key_args(),
				),
			)
		);

		// GET /auth/keys - List API keys (admin only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/keys',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_api_keys' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_list_keys_args(),
				),
			)
		);

		// DELETE /auth/keys/{key_id} - Revoke API key (admin only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/keys/(?P<key_id>[a-zA-Z0-9_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revoke_api_key' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'key_id' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'The API key ID to revoke.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// POST /auth/verify - Verify an API key and get permissions.
		// This endpoint is intentionally public (check_public_read_permission) because:
		// 1. It allows clients to test if their credentials are valid (similar to OAuth2 token introspection)
		// 2. The API key to verify is provided in the request body or Authorization header
		// 3. No sensitive data is exposed without providing valid credentials.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/verify',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'verify_api_key' ),
					'permission_callback' => array( $this, 'check_public_read_permission' ),
					'args'                => array(
						'api_key' => array(
							'required'          => false,
							'type'              => 'string',
							'description'       => __( 'The API key to verify (key_id:secret format). If not provided, uses the key from the request header.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Get arguments for create key endpoint.
	 *
	 * @return array
	 */
	private function get_create_key_args() {
		return array(
			'description' => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'description'       => __( 'Description for the API key (e.g., "AI Agent - Claude").', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'permissions' => array(
				'required'    => false,
				'type'        => 'array',
				'default'     => array( 'read' ),
				'description' => __( 'Array of permissions: read, write, admin.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'read', 'write', 'admin' ),
				),
			),
			'user_id'     => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 0,
				'description'       => __( 'WordPress user ID to associate with this key.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Get arguments for list keys endpoint.
	 *
	 * @return array
	 */
	private function get_list_keys_args() {
		return array(
			'status'   => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => 'active',
				'enum'              => array( 'active', 'revoked', 'all' ),
				'description'       => __( 'Filter by key status.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'page'     => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'description'       => __( 'Page number.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'description'       => __( 'Items per page.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Permission callback for admin-only endpoints.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( $request ) {
		// First check if UCP is enabled.
		if ( ! $this->is_ucp_enabled() ) {
			return new WP_Error(
				'ucp_disabled',
				__( 'UCP is currently disabled for this store.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 503 )
			);
		}

		// Check for manage_woocommerce capability (admin users).
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		// Check for admin-level API key.
		if ( UCP_WC_Auth::check_permission( 'admin' ) ) {
			return true;
		}

		return new WP_Error(
			'ucp_forbidden',
			__( 'Admin permission is required to manage API keys.', 'harmonytics-ucp-connector-for-woocommerce' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Create a new API key.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_api_key( $request ) {
		$description = $request->get_param( 'description' );
		$permissions = $request->get_param( 'permissions' );
		$user_id     = $request->get_param( 'user_id' );

		$this->log(
			'Creating API key',
			array(
				'description' => $description,
				'permissions' => $permissions,
			)
		);

		// Validate user_id if provided.
		if ( $user_id > 0 ) {
			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return $this->error_response(
					'invalid_user',
					__( 'The specified user does not exist.', 'harmonytics-ucp-connector-for-woocommerce' ),
					400
				);
			}
		}

		$result = $this->auth->generate_api_key(
			array(
				'description' => $description,
				'permissions' => $permissions,
				'user_id'     => $user_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->log( 'API key creation failed', array( 'error' => $result->get_error_message() ) );
			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				400
			);
		}

		$this->log( 'API key created', array( 'key_id' => $result['key_id'] ) );

		// Format response.
		$response = array(
			'key_id'      => $result['key_id'],
			'description' => $result['description'],
			'permissions' => $result['permissions'],
			'created_at'  => $result['created_at'],
			'last_used'   => $result['last_used'],
			'secret'      => $result['secret'], // Only shown once at creation!
			'message'     => __( 'API key created successfully. Save the secret now - it will not be shown again.', 'harmonytics-ucp-connector-for-woocommerce' ),
		);

		return $this->success_response( $response, 201 );
	}

	/**
	 * List all API keys.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_api_keys( $request ) {
		$status   = $request->get_param( 'status' );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$this->log( 'Listing API keys', array( 'status' => $status ) );

		$result = $this->auth->list_api_keys(
			array(
				'status'   => $status,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		$response = $this->success_response( $result );

		// Add pagination headers.
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', $result['total_pages'] );

		return $response;
	}

	/**
	 * Revoke an API key.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revoke_api_key( $request ) {
		$key_id = $request->get_param( 'key_id' );

		$this->log( 'Revoking API key', array( 'key_id' => $key_id ) );

		// Get key info before revoking.
		$key_info = $this->auth->get_api_key( $key_id );
		if ( ! $key_info ) {
			return $this->error_response(
				'key_not_found',
				__( 'API key not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		// Prevent revoking already revoked keys.
		if ( 'revoked' === $key_info['status'] ) {
			return $this->error_response(
				'already_revoked',
				__( 'API key is already revoked.', 'harmonytics-ucp-connector-for-woocommerce' ),
				400
			);
		}

		$result = $this->auth->revoke_api_key( $key_id );

		if ( is_wp_error( $result ) ) {
			$this->log( 'API key revocation failed', array( 'error' => $result->get_error_message() ) );
			return $this->error_response(
				$result->get_error_code(),
				$result->get_error_message(),
				500
			);
		}

		$this->log( 'API key revoked', array( 'key_id' => $key_id ) );

		return $this->success_response(
			array(
				'key_id'  => $key_id,
				'status'  => 'revoked',
				'message' => __( 'API key revoked successfully.', 'harmonytics-ucp-connector-for-woocommerce' ),
			)
		);
	}

	/**
	 * Verify an API key and return its permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify_api_key( $request ) {
		$api_key = $request->get_param( 'api_key' );

		// If no key provided in body, check if authenticated via header.
		if ( empty( $api_key ) ) {
			$current_key = UCP_WC_Auth::get_current_api_key();

			if ( ! $current_key ) {
				return $this->error_response(
					'no_api_key',
					__( 'No API key provided or found in request headers.', 'harmonytics-ucp-connector-for-woocommerce' ),
					400
				);
			}

			return $this->success_response(
				array(
					'valid'       => true,
					'key_id'      => $current_key['key_id'],
					'description' => $current_key['description'],
					'permissions' => $current_key['permissions'],
					'created_at'  => $current_key['created_at'] ? gmdate( 'c', strtotime( $current_key['created_at'] ) ) : null,
					'last_used'   => $current_key['last_used_at'] ? gmdate( 'c', strtotime( $current_key['last_used_at'] ) ) : null,
					'status'      => $current_key['status'],
				)
			);
		}

		$this->log( 'Verifying API key' );

		// Verify the provided key.
		$key_data = $this->auth->verify_api_key( $api_key );

		if ( ! $key_data ) {
			return $this->success_response(
				array(
					'valid'   => false,
					'message' => __( 'Invalid or expired API key.', 'harmonytics-ucp-connector-for-woocommerce' ),
				)
			);
		}

		return $this->success_response(
			array(
				'valid'       => true,
				'key_id'      => $key_data['key_id'],
				'description' => $key_data['description'],
				'permissions' => $key_data['permissions'],
				'created_at'  => $key_data['created_at'] ? gmdate( 'c', strtotime( $key_data['created_at'] ) ) : null,
				'last_used'   => $key_data['last_used_at'] ? gmdate( 'c', strtotime( $key_data['last_used_at'] ) ) : null,
				'status'      => $key_data['status'],
			)
		);
	}
}
