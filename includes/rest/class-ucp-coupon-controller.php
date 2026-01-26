<?php
/**
 * REST controller for coupon endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Coupon_Controller
 *
 * Handles coupon-related REST API endpoints for UCP.
 */
class UCP_WC_Coupon_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'coupons';

	/**
	 * Coupon mapper instance.
	 *
	 * @var UCP_WC_Coupon_Mapper
	 */
	protected $coupon_mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->coupon_mapper = new UCP_WC_Coupon_Mapper();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /coupons/validate - Validate a coupon code.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/validate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'validate_coupon' ),
					'permission_callback' => array( $this, 'check_public_read_permission' ),
					'args'                => $this->get_validate_args(),
				),
			)
		);

		// POST /coupons/calculate - Calculate discount for items.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/calculate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'calculate_discount' ),
					'permission_callback' => array( $this, 'check_public_read_permission' ),
					'args'                => $this->get_calculate_args(),
				),
			)
		);

		// GET /coupons/active - List active/available public coupons.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/active',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_active_coupons' ),
					'permission_callback' => array( $this, 'check_public_read_permission' ),
					'args'                => $this->get_list_active_args(),
				),
			)
		);
	}

	/**
	 * Get arguments for validate endpoint.
	 *
	 * @return array
	 */
	private function get_validate_args() {
		return array(
			'code'           => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Coupon code to validate.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'items'          => array(
				'required'    => false,
				'type'        => 'array',
				'description' => __( 'Array of items to validate coupon against.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => __( 'Product ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'required'    => true,
						),
						'quantity'   => array(
							'type'        => 'integer',
							'description' => __( 'Quantity.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'default'     => 1,
							'minimum'     => 1,
						),
					),
				),
			),
			'customer_email' => array(
				'required'          => false,
				'type'              => 'string',
				'format'            => 'email',
				'description'       => __( 'Customer email for validation.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_email',
			),
		);
	}

	/**
	 * Get arguments for calculate endpoint.
	 *
	 * @return array
	 */
	private function get_calculate_args() {
		return array(
			'code'  => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Coupon code.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'items' => array(
				'required'    => true,
				'type'        => 'array',
				'description' => __( 'Array of items to calculate discount for.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => __( 'Product ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'required'    => true,
						),
						'quantity'   => array(
							'type'        => 'integer',
							'description' => __( 'Quantity.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'default'     => 1,
							'minimum'     => 1,
						),
						'price'      => array(
							'type'        => 'string',
							'description' => __( 'Item price.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'required'    => true,
						),
					),
				),
			),
		);
	}

	/**
	 * Get arguments for list active endpoint.
	 *
	 * @return array
	 */
	private function get_list_active_args() {
		return array(
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
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 50,
				'description'       => __( 'Items per page.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Validate a coupon code.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function validate_coupon( $request ) {
		$code           = $request->get_param( 'code' );
		$items          = $request->get_param( 'items' );
		$customer_email = $request->get_param( 'customer_email' );

		$this->log(
			'Validating coupon',
			array(
				'code'           => $code,
				'items'          => $items,
				'customer_email' => $customer_email,
			)
		);

		// Get the coupon.
		$coupon = new WC_Coupon( $code );

		// Check if coupon exists.
		if ( ! $coupon->get_id() ) {
			return $this->error_response(
				'coupon_not_found',
				__( 'Coupon code not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		// Perform validation checks.
		$validation_result = $this->perform_coupon_validation( $coupon, $items, $customer_email );

		if ( is_wp_error( $validation_result ) ) {
			// Return coupon data with valid=false and error details.
			$response          = $this->coupon_mapper->map_coupon_validation( $coupon, false );
			$response['error'] = array(
				'code'    => $validation_result->get_error_code(),
				'message' => $validation_result->get_error_message(),
			);
			return $this->success_response( $response );
		}

		// Coupon is valid.
		$response = $this->coupon_mapper->map_coupon_validation( $coupon, true );
		return $this->success_response( $response );
	}

	/**
	 * Calculate discount for items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function calculate_discount( $request ) {
		$code  = $request->get_param( 'code' );
		$items = $request->get_param( 'items' );

		$this->log(
			'Calculating discount',
			array(
				'code'  => $code,
				'items' => $items,
			)
		);

		// Validate items.
		if ( empty( $items ) || ! is_array( $items ) ) {
			return $this->error_response(
				'invalid_items',
				__( 'Items array is required and must not be empty.', 'harmonytics-ucp-connector-for-woocommerce' ),
				400
			);
		}

		// Get the coupon.
		$coupon = new WC_Coupon( $code );

		// Check if coupon exists.
		if ( ! $coupon->get_id() ) {
			return $this->error_response(
				'coupon_not_found',
				__( 'Coupon code not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		// Validate the coupon is usable.
		$validation_result = $this->perform_coupon_validation( $coupon, $items, '' );

		if ( is_wp_error( $validation_result ) ) {
			return $this->error_response(
				$validation_result->get_error_code(),
				$validation_result->get_error_message(),
				400
			);
		}

		// Calculate subtotal before discount.
		$subtotal_before = 0;
		foreach ( $items as $item ) {
			$price            = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
			$quantity         = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
			$subtotal_before += $price * $quantity;
		}

		// Check minimum spend requirement.
		$minimum_amount = floatval( $coupon->get_minimum_amount() );
		if ( $minimum_amount > 0 && $subtotal_before < $minimum_amount ) {
			return $this->error_response(
				'minimum_spend_not_met',
				sprintf(
					/* translators: %s: minimum spend amount */
					__( 'This coupon requires a minimum spend of %s.', 'harmonytics-ucp-connector-for-woocommerce' ),
					wc_price( $minimum_amount )
				),
				400
			);
		}

		// Calculate discount.
		$discount_amount = $this->coupon_mapper->calculate_discount( $coupon, $items );
		$subtotal_after  = max( 0, $subtotal_before - $discount_amount );

		$response = $this->coupon_mapper->map_discount_calculation(
			$coupon,
			$discount_amount,
			$subtotal_before,
			$subtotal_after
		);

		return $this->success_response( $response );
	}

	/**
	 * List active public coupons.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_active_coupons( $request ) {
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$this->log(
			'Listing active coupons',
			array(
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		// Check if public coupons feature is enabled.
		if ( get_option( 'ucp_wc_public_coupons', 'no' ) !== 'yes' ) {
			return $this->error_response(
				'public_coupons_disabled',
				__( 'Public coupon listing is not enabled for this store.', 'harmonytics-ucp-connector-for-woocommerce' ),
				403
			);
		}

		// Query for active coupons with UCP public flag.
		$args = array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for filtering public coupons with valid expiry dates.
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_ucp_public_coupon',
					'value'   => 'yes',
					'compare' => '=',
				),
				array(
					'relation' => 'OR',
					// No expiry date set.
					array(
						'key'     => 'date_expires',
						'compare' => 'NOT EXISTS',
					),
					// Empty expiry date.
					array(
						'key'     => 'date_expires',
						'value'   => '',
						'compare' => '=',
					),
					// Expiry date in the future.
					array(
						'key'     => 'date_expires',
						'value'   => time(),
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query   = new WP_Query( $args );
		$coupons = array();

		foreach ( $query->posts as $post ) {
			$coupon = new WC_Coupon( $post->ID );

			// Skip if coupon has reached usage limit.
			$usage_limit = $coupon->get_usage_limit();
			$usage_count = $coupon->get_usage_count();

			if ( $usage_limit && $usage_count >= $usage_limit ) {
				continue;
			}

			$coupons[] = $this->coupon_mapper->map_coupon_public( $coupon );
		}

		$total       = $query->found_posts;
		$total_pages = $query->max_num_pages;

		$response = $this->success_response(
			array(
				'coupons'     => $coupons,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => $total_pages,
			)
		);

		// Add pagination headers.
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Perform comprehensive coupon validation.
	 *
	 * @param WC_Coupon   $coupon         Coupon object.
	 * @param array|null  $items          Array of items.
	 * @param string|null $customer_email Customer email.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function perform_coupon_validation( $coupon, $items = null, $customer_email = null ) {
		// Check if coupon is enabled.
		if ( 'publish' !== get_post_status( $coupon->get_id() ) ) {
			return new WP_Error(
				'coupon_disabled',
				__( 'This coupon is not currently active.', 'harmonytics-ucp-connector-for-woocommerce' )
			);
		}

		// Check expiry date.
		$expiry_date = $coupon->get_date_expires();
		if ( $expiry_date && time() > $expiry_date->getTimestamp() ) {
			return new WP_Error(
				'coupon_expired',
				__( 'This coupon has expired.', 'harmonytics-ucp-connector-for-woocommerce' )
			);
		}

		// Check usage limit.
		$usage_limit = $coupon->get_usage_limit();
		$usage_count = $coupon->get_usage_count();

		if ( $usage_limit && $usage_count >= $usage_limit ) {
			return new WP_Error(
				'coupon_usage_limit_reached',
				__( 'This coupon has reached its usage limit.', 'harmonytics-ucp-connector-for-woocommerce' )
			);
		}

		// Check usage limit per user if customer email provided.
		if ( ! empty( $customer_email ) ) {
			$usage_limit_per_user = $coupon->get_usage_limit_per_user();

			if ( $usage_limit_per_user > 0 ) {
				$customer_usage = $this->get_coupon_usage_by_email( $coupon, $customer_email );

				if ( $customer_usage >= $usage_limit_per_user ) {
					return new WP_Error(
						'coupon_usage_limit_per_user_reached',
						__( 'You have already used this coupon the maximum number of times.', 'harmonytics-ucp-connector-for-woocommerce' )
					);
				}
			}

			// Check email restrictions.
			$email_restrictions = $coupon->get_email_restrictions();
			if ( ! empty( $email_restrictions ) ) {
				$email_allowed = false;

				foreach ( $email_restrictions as $restriction ) {
					// Check for wildcard patterns.
					if ( strpos( $restriction, '*' ) !== false ) {
						$pattern = '/^' . str_replace( '\*', '.*', preg_quote( $restriction, '/' ) ) . '$/i';
						if ( preg_match( $pattern, $customer_email ) ) {
							$email_allowed = true;
							break;
						}
					} elseif ( strtolower( $restriction ) === strtolower( $customer_email ) ) {
						$email_allowed = true;
						break;
					}
				}

				if ( ! $email_allowed ) {
					return new WP_Error(
						'coupon_email_restricted',
						__( 'This coupon is not valid for your email address.', 'harmonytics-ucp-connector-for-woocommerce' )
					);
				}
			}
		}

		// Validate product restrictions if items provided.
		if ( ! empty( $items ) && is_array( $items ) ) {
			$product_ids          = $coupon->get_product_ids();
			$excluded_product_ids = $coupon->get_excluded_product_ids();
			$product_categories   = $coupon->get_product_categories();
			$excluded_categories  = $coupon->get_excluded_product_categories();

			$has_restrictions = ! empty( $product_ids ) || ! empty( $product_categories );
			$valid_item_found = false;

			foreach ( $items as $item ) {
				$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;

				if ( ! $product_id ) {
					continue;
				}

				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				// Check exclusions.
				if ( ! empty( $excluded_product_ids ) && in_array( $product_id, $excluded_product_ids, true ) ) {
					continue;
				}

				$item_categories = $product->get_category_ids();
				if ( ! empty( $excluded_categories ) && array_intersect( $item_categories, $excluded_categories ) ) {
					continue;
				}

				// Check inclusions.
				if ( $has_restrictions ) {
					if ( ! empty( $product_ids ) && in_array( $product_id, $product_ids, true ) ) {
						$valid_item_found = true;
						break;
					}

					if ( ! empty( $product_categories ) && array_intersect( $item_categories, $product_categories ) ) {
						$valid_item_found = true;
						break;
					}
				} else {
					// No restrictions, any item is valid.
					$valid_item_found = true;
					break;
				}
			}

			if ( $has_restrictions && ! $valid_item_found ) {
				return new WP_Error(
					'coupon_not_applicable',
					__( 'This coupon is not applicable to the selected products.', 'harmonytics-ucp-connector-for-woocommerce' )
				);
			}
		}

		return true;
	}

	/**
	 * Get coupon usage count for a specific email.
	 *
	 * @param WC_Coupon $coupon Coupon object.
	 * @param string    $email  Customer email.
	 * @return int
	 */
	private function get_coupon_usage_by_email( $coupon, $email ) {
		global $wpdb;

		$coupon_id = $coupon->get_id();

		// Check used_by meta.
		$used_by = $coupon->get_used_by();

		if ( ! empty( $used_by ) ) {
			return count(
				array_filter(
					$used_by,
					function ( $user ) use ( $email ) {
						// Could be user ID or email.
						if ( is_numeric( $user ) ) {
							$user_data = get_userdata( $user );
							return $user_data && strtolower( $user_data->user_email ) === strtolower( $email );
						}
						return strtolower( $user ) === strtolower( $email );
					}
				)
			);
		}

		return 0;
	}
}
