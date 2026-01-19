<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * REST controller for product review endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Review_Controller
 *
 * Handles product review REST API endpoints for UCP.
 */
class UCP_WC_Review_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'reviews';

	/**
	 * Review mapper instance.
	 *
	 * @var UCP_WC_Review_Mapper
	 */
	protected $review_mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->review_mapper = new UCP_WC_Review_Mapper();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /reviews - List all reviews.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_reviews' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => $this->get_list_reviews_args(),
				),
			)
		);

		// GET /reviews/{review_id} - Get single review.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<review_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_review' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(
						'review_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Review ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST /reviews - Create a new review.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_review' ),
					'permission_callback' => array( $this, 'check_write_permission' ),
					'args'                => $this->get_create_review_args(),
				),
			)
		);

		// GET /products/{product_id}/reviews - Get reviews for a product.
		register_rest_route(
			$this->namespace,
			'/products/(?P<product_id>[\d]+)/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product_reviews' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => $this->get_product_reviews_args(),
				),
			)
		);

		// GET /products/{product_id}/reviews/summary - Get review summary for product.
		register_rest_route(
			$this->namespace,
			'/products/(?P<product_id>[\d]+)/' . $this->rest_base . '/summary',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product_review_summary' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(
						'product_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Product ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Get arguments for list reviews endpoint.
	 *
	 * @return array
	 */
	private function get_list_reviews_args() {
		return array(
			'page'       => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'description'       => __( 'Page number.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'per_page'   => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'description'       => __( 'Items per page.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'product_id' => array(
				'required'          => false,
				'type'              => 'integer',
				'description'       => __( 'Filter by product ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'rating'     => array(
				'required'          => false,
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => 5,
				'description'       => __( 'Filter by rating (1-5).', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'verified'   => array(
				'required'    => false,
				'type'        => 'boolean',
				'description' => __( 'Filter verified purchases only.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'status'     => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'approved', 'pending', 'spam', 'any' ),
				'default'     => 'approved',
				'description' => __( 'Filter by review status.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'orderby'    => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'date', 'rating', 'id' ),
				'default'     => 'date',
				'description' => __( 'Sort collection by attribute.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'order'      => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'asc', 'desc' ),
				'default'     => 'desc',
				'description' => __( 'Order sort direction.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get arguments for product reviews endpoint.
	 *
	 * @return array
	 */
	private function get_product_reviews_args() {
		$args               = $this->get_list_reviews_args();
		$args['product_id'] = array(
			'required'          => true,
			'type'              => 'integer',
			'description'       => __( 'Product ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
			'sanitize_callback' => 'absint',
		);
		return $args;
	}

	/**
	 * Get arguments for create review endpoint.
	 *
	 * @return array
	 */
	private function get_create_review_args() {
		return array(
			'product_id'     => array(
				'required'          => true,
				'type'              => 'integer',
				'description'       => __( 'Product ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'reviewer'       => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Reviewer name.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'reviewer_email' => array(
				'required'          => true,
				'type'              => 'string',
				'format'            => 'email',
				'description'       => __( 'Reviewer email address.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_email',
			),
			'review'         => array(
				'required'          => true,
				'type'              => 'string',
				'description'       => __( 'Review content.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'rating'         => array(
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => 5,
				'description'       => __( 'Rating (1-5).', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * List all reviews.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_reviews( $request ) {
		$this->log( 'Listing reviews', array( 'params' => $request->get_params() ) );

		$args = $this->build_comment_query_args( $request );

		$query    = new WP_Comment_Query();
		$comments = $query->query( $args );

		// Get total count for pagination.
		$count_args          = $args;
		$count_args['count'] = true;
		unset( $count_args['number'], $count_args['offset'] );

		$count_query = new WP_Comment_Query();
		$total       = $count_query->query( $count_args );

		$per_page    = $request->get_param( 'per_page' ) ?: 10;
		$total_pages = ceil( $total / $per_page );

		// Map reviews.
		$mapped_reviews = array();
		foreach ( $comments as $comment ) {
			$mapped_reviews[] = $this->review_mapper->map_review_summary( $comment );
		}

		// Apply post-query filters.
		$mapped_reviews = $this->apply_post_filters( $mapped_reviews, $request );

		$result = array(
			'reviews'     => $mapped_reviews,
			'total'       => $total,
			'page'        => $request->get_param( 'page' ) ?: 1,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
		);

		$response = $this->success_response( $result );

		// Add pagination headers.
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Get single review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_review( $request ) {
		$review_id = $request->get_param( 'review_id' );

		$this->log( 'Getting review', array( 'review_id' => $review_id ) );

		$comment = get_comment( $review_id );

		if ( ! $comment ) {
			return $this->error_response(
				'review_not_found',
				__( 'Review not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		// Verify this is a product review.
		if ( 'review' !== $comment->comment_type && 'product' !== get_post_type( $comment->comment_post_ID ) ) {
			return $this->error_response(
				'not_product_review',
				__( 'The requested comment is not a product review.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		// Check if review is accessible (approved or user has permission).
		if ( '1' !== $comment->comment_approved && 'approve' !== $comment->comment_approved ) {
			if ( ! current_user_can( 'moderate_comments' ) ) {
				return $this->error_response(
					'review_not_accessible',
					__( 'Review is not accessible.', 'harmonytics-ucp-connector-for-woocommerce' ),
					404
				);
			}
		}

		$mapped_review = $this->review_mapper->map_review( $comment );

		return $this->success_response( $mapped_review );
	}

	/**
	 * Create a new review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_review( $request ) {
		$product_id = $request->get_param( 'product_id' );

		$this->log( 'Creating review', array( 'product_id' => $product_id, 'params' => $request->get_params() ) );

		// Verify product exists.
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return $this->error_response(
				'product_not_found',
				__( 'Product not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		// Check if reviews are enabled for this product.
		if ( ! $product->get_reviews_allowed() ) {
			return $this->error_response(
				'reviews_disabled',
				__( 'Reviews are not enabled for this product.', 'harmonytics-ucp-connector-for-woocommerce' ),
				403
			);
		}

		// Validate rating.
		$rating = $request->get_param( 'rating' );
		if ( $rating < 1 || $rating > 5 ) {
			return $this->error_response(
				'invalid_rating',
				__( 'Rating must be between 1 and 5.', 'harmonytics-ucp-connector-for-woocommerce' ),
				400
			);
		}

		// Prepare review data.
		$review_data = array(
			'reviewer'       => $request->get_param( 'reviewer' ),
			'reviewer_email' => $request->get_param( 'reviewer_email' ),
			'review'         => $request->get_param( 'review' ),
		);

		// Add user ID if logged in.
		if ( is_user_logged_in() ) {
			$review_data['user_id'] = get_current_user_id();
		}

		$comment_data = $this->review_mapper->prepare_review_for_insert( $review_data, $product_id );

		// Check for duplicate reviews.
		$existing = get_comments(
			array(
				'post_id'      => $product_id,
				'author_email' => $comment_data['comment_author_email'],
				'type'         => 'review',
				'count'        => true,
			)
		);

		if ( $existing > 0 ) {
			// Check WooCommerce setting for duplicate reviews.
			$allow_duplicates = get_option( 'woocommerce_review_rating_verification_required', 'no' ) !== 'yes';
			if ( ! $allow_duplicates ) {
				return $this->error_response(
					'duplicate_review',
					__( 'You have already reviewed this product.', 'harmonytics-ucp-connector-for-woocommerce' ),
					409
				);
			}
		}

		// Insert the comment.
		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			return $this->error_response(
				'review_creation_failed',
				__( 'Failed to create review.', 'harmonytics-ucp-connector-for-woocommerce' ),
				500
			);
		}

		// Add rating meta.
		add_comment_meta( $comment_id, 'rating', $rating, true );

		// Check if this is a verified purchase.
		$user_id        = $review_data['user_id'] ?? 0;
		$reviewer_email = $review_data['reviewer_email'];
		$verified       = wc_customer_bought_product( $reviewer_email, $user_id, $product_id );
		add_comment_meta( $comment_id, 'verified', $verified ? 1 : 0, true );

		// Clear product transients so rating counts update.
		wc_delete_product_transients( $product_id );

		// Get the created review.
		$comment        = get_comment( $comment_id );
		$mapped_review  = $this->review_mapper->map_review( $comment );

		// Add moderation notice if applicable.
		if ( '0' === $comment->comment_approved || 'hold' === $comment->comment_approved ) {
			$mapped_review['_notice'] = __( 'Your review is pending moderation.', 'harmonytics-ucp-connector-for-woocommerce' );
		}

		return $this->success_response( $mapped_review, 201 );
	}

	/**
	 * Get reviews for a specific product.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_product_reviews( $request ) {
		$product_id = $request->get_param( 'product_id' );

		$this->log( 'Getting product reviews', array( 'product_id' => $product_id, 'params' => $request->get_params() ) );

		// Verify product exists.
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return $this->error_response(
				'product_not_found',
				__( 'Product not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		// Force product_id filter.
		$request->set_param( 'product_id', $product_id );

		return $this->list_reviews( $request );
	}

	/**
	 * Get review summary for a product.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_product_review_summary( $request ) {
		$product_id = $request->get_param( 'product_id' );

		$this->log( 'Getting product review summary', array( 'product_id' => $product_id ) );

		// Verify product exists.
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return $this->error_response(
				'product_not_found',
				__( 'Product not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		$summary = $this->review_mapper->get_product_review_summary( $product_id );

		return $this->success_response( $summary );
	}

	/**
	 * Build WP_Comment_Query arguments from request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	private function build_comment_query_args( $request ) {
		$page     = $request->get_param( 'page' ) ?: 1;
		$per_page = $request->get_param( 'per_page' ) ?: 10;

		$args = array(
			'type'       => 'review',
			'post_type'  => 'product',
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => $this->map_orderby( $request->get_param( 'orderby' ) ?: 'date' ),
			'order'      => strtoupper( $request->get_param( 'order' ) ?: 'DESC' ),
		);

		// Status filter.
		$status = $request->get_param( 'status' );
		if ( ! empty( $status ) && 'any' !== $status ) {
			$args['status'] = $this->review_mapper->map_ucp_status_to_wp( $status );
		} else {
			// Default to approved reviews for public access.
			if ( ! current_user_can( 'moderate_comments' ) ) {
				$args['status'] = 'approve';
			}
		}

		// Product filter.
		$product_id = $request->get_param( 'product_id' );
		if ( ! empty( $product_id ) ) {
			$args['post_id'] = $product_id;
		}

		// Rating filter (handled via meta query).
		$rating = $request->get_param( 'rating' );
		if ( ! empty( $rating ) && $rating >= 1 && $rating <= 5 ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for filtering reviews by rating value.
			$args['meta_query'] = array(
				array(
					'key'     => 'rating',
					'value'   => $rating,
					'compare' => '=',
					'type'    => 'NUMERIC',
				),
			);
		}

		return $args;
	}

	/**
	 * Apply post-query filters that can't be done in WP_Comment_Query.
	 *
	 * @param array           $reviews Mapped reviews array.
	 * @param WP_REST_Request $request Request object.
	 * @return array Filtered reviews.
	 */
	private function apply_post_filters( $reviews, $request ) {
		$verified = $request->get_param( 'verified' );

		// Filter by verified purchases if requested.
		if ( true === $verified ) {
			$reviews = array_filter(
				$reviews,
				function ( $review ) {
					return true === $review['verified'];
				}
			);
			$reviews = array_values( $reviews ); // Re-index array.
		}

		return $reviews;
	}

	/**
	 * Map orderby parameter to WP_Comment_Query format.
	 *
	 * @param string $orderby Orderby parameter.
	 * @return string
	 */
	private function map_orderby( $orderby ) {
		$mapping = array(
			'date'   => 'comment_date_gmt',
			'rating' => 'meta_value_num',
			'id'     => 'comment_ID',
		);

		return $mapping[ $orderby ] ?? 'comment_date_gmt';
	}
}
