<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the Reviews capability.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Reviews
 *
 * Tests the product review functionality including listing, filtering, and creation.
 */
class Test_UCP_Reviews extends WC_Unit_Test_Case {

	/**
	 * Review controller instance.
	 *
	 * @var UCP_WC_Review_Controller
	 */
	protected $controller;

	/**
	 * Review mapper instance.
	 *
	 * @var UCP_WC_Review_Mapper
	 */
	protected $mapper;

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
	 * Array of comment IDs to clean up.
	 *
	 * @var array
	 */
	protected $comment_ids = array();

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Load required classes.
		require_once UCP_WC_PLUGIN_DIR . 'includes/class-ucp-activator.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-review-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-rest-controller.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-review-controller.php';

		// Create tables.
		UCP_WC_Activator::activate();

		$this->controller = new UCP_WC_Review_Controller();
		$this->mapper     = new UCP_WC_Review_Mapper();

		// Create test products.
		$this->product = WC_Helper_Product::create_simple_product();
		$this->product->set_price( 100.00 );
		$this->product->set_regular_price( 100.00 );
		$this->product->set_sku( 'REVIEW-TEST-001' );
		$this->product->set_reviews_allowed( true );
		$this->product->save();

		$this->product2 = WC_Helper_Product::create_simple_product();
		$this->product2->set_price( 50.00 );
		$this->product2->set_regular_price( 50.00 );
		$this->product2->set_sku( 'REVIEW-TEST-002' );
		$this->product2->set_reviews_allowed( true );
		$this->product2->save();

		// Enable UCP.
		update_option( 'ucp_wc_enabled', 'yes' );

		// Allow duplicate reviews for testing.
		update_option( 'woocommerce_review_rating_verification_required', 'no' );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		// Clean up comments.
		foreach ( $this->comment_ids as $comment_id ) {
			wp_delete_comment( $comment_id, true );
		}

		// Clean up products.
		if ( $this->product ) {
			$this->product->delete( true );
		}

		if ( $this->product2 ) {
			$this->product2->delete( true );
		}

		// Reset options.
		delete_option( 'woocommerce_review_rating_verification_required' );

		parent::tear_down();
	}

	/**
	 * Helper method to create a review.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $args       Review arguments.
	 * @return int Comment ID.
	 */
	protected function create_review( $product_id, $args = array() ) {
		$defaults = array(
			'author'   => 'John Doe',
			'email'    => 'john@example.com',
			'content'  => 'Great product!',
			'rating'   => 5,
			'approved' => 1,
			'verified' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $product_id,
				'comment_author'       => $args['author'],
				'comment_author_email' => $args['email'],
				'comment_content'      => $args['content'],
				'comment_type'         => 'review',
				'comment_approved'     => $args['approved'],
			)
		);

		update_comment_meta( $comment_id, 'rating', $args['rating'] );
		update_comment_meta( $comment_id, 'verified', $args['verified'] );

		// Track for cleanup.
		$this->comment_ids[] = $comment_id;

		return $comment_id;
	}

	/**
	 * Test listing all reviews.
	 */
	public function test_list_reviews() {
		// Create test reviews.
		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Alice',
				'email'   => 'alice@example.com',
				'content' => 'Excellent product!',
				'rating'  => 5,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Bob',
				'email'   => 'bob@example.com',
				'content' => 'Good quality.',
				'rating'  => 4,
			)
		);

		$this->create_review(
			$this->product2->get_id(),
			array(
				'author'  => 'Charlie',
				'email'   => 'charlie@example.com',
				'content' => 'Decent product.',
				'rating'  => 3,
			)
		);

		$request = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 10 );

		$response = $this->controller->list_reviews( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'reviews', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'page', $data );
		$this->assertArrayHasKey( 'per_page', $data );
		$this->assertArrayHasKey( 'total_pages', $data );

		$this->assertCount( 3, $data['reviews'] );
		$this->assertEquals( 3, $data['total'] );
		$this->assertEquals( 1, $data['page'] );
	}

	/**
	 * Test listing reviews filtered by product_id.
	 */
	public function test_list_reviews_filter_by_product() {
		// Create reviews for different products.
		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Alice',
				'email'   => 'alice@example.com',
				'content' => 'Product 1 review.',
				'rating'  => 5,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Bob',
				'email'   => 'bob@example.com',
				'content' => 'Another product 1 review.',
				'rating'  => 4,
			)
		);

		$this->create_review(
			$this->product2->get_id(),
			array(
				'author'  => 'Charlie',
				'email'   => 'charlie@example.com',
				'content' => 'Product 2 review.',
				'rating'  => 3,
			)
		);

		$request = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request->set_param( 'product_id', $this->product->get_id() );

		$response = $this->controller->list_reviews( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertCount( 2, $data['reviews'] );

		// Verify all returned reviews are for the correct product.
		foreach ( $data['reviews'] as $review ) {
			$this->assertEquals( $this->product->get_id(), $review['product_id'] );
		}
	}

	/**
	 * Test listing reviews filtered by rating.
	 */
	public function test_list_reviews_filter_by_rating() {
		// Create reviews with different ratings.
		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Alice',
				'email'   => 'alice@example.com',
				'content' => 'Perfect!',
				'rating'  => 5,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Bob',
				'email'   => 'bob@example.com',
				'content' => 'Also perfect!',
				'rating'  => 5,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Charlie',
				'email'   => 'charlie@example.com',
				'content' => 'Good but not great.',
				'rating'  => 4,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Diana',
				'email'   => 'diana@example.com',
				'content' => 'Average product.',
				'rating'  => 3,
			)
		);

		$request = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request->set_param( 'rating', 5 );

		$response = $this->controller->list_reviews( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertCount( 2, $data['reviews'] );

		// Verify all returned reviews have rating 5.
		foreach ( $data['reviews'] as $review ) {
			$this->assertEquals( 5, $review['rating'] );
		}
	}

	/**
	 * Test listing reviews filtered to verified purchases only.
	 */
	public function test_list_reviews_filter_verified() {
		// Create verified and non-verified reviews.
		$this->create_review(
			$this->product->get_id(),
			array(
				'author'   => 'Alice',
				'email'    => 'alice@example.com',
				'content'  => 'Verified purchase review.',
				'rating'   => 5,
				'verified' => 1,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'   => 'Bob',
				'email'    => 'bob@example.com',
				'content'  => 'Non-verified review.',
				'rating'   => 4,
				'verified' => 0,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'   => 'Charlie',
				'email'    => 'charlie@example.com',
				'content'  => 'Another verified review.',
				'rating'   => 5,
				'verified' => 1,
			)
		);

		$request = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request->set_param( 'verified', true );

		$response = $this->controller->list_reviews( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertCount( 2, $data['reviews'] );

		// Verify all returned reviews are verified.
		foreach ( $data['reviews'] as $review ) {
			$this->assertTrue( $review['verified'] );
		}
	}

	/**
	 * Test getting a single review.
	 */
	public function test_get_single_review() {
		$comment_id = $this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'John Doe',
				'email'   => 'john@example.com',
				'content' => 'This is a detailed review of the product.',
				'rating'  => 5,
			)
		);

		$request = new WP_REST_Request( 'GET', '/ucp/v1/reviews/' . $comment_id );
		$request->set_param( 'review_id', $comment_id );

		$response = $this->controller->get_review( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertEquals( $comment_id, $data['id'] );
		$this->assertEquals( $this->product->get_id(), $data['product_id'] );
		$this->assertEquals( 'John Doe', $data['reviewer'] );
		$this->assertEquals( 'john@example.com', $data['reviewer_email'] );
		$this->assertEquals( 5, $data['rating'] );
		$this->assertEquals( 'This is a detailed review of the product.', $data['review'] );
		$this->assertEquals( 'approved', $data['status'] );
		$this->assertArrayHasKey( 'date_created', $data );
	}

	/**
	 * Test getting a non-existent review returns 404.
	 */
	public function test_get_review_not_found() {
		$request = new WP_REST_Request( 'GET', '/ucp/v1/reviews/999999' );
		$request->set_param( 'review_id', 999999 );

		$response = $this->controller->get_review( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'review_not_found', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}

	/**
	 * Test creating a new review.
	 */
	public function test_create_review() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/reviews' );
		$request->set_param( 'product_id', $this->product->get_id() );
		$request->set_param( 'reviewer', 'Jane Smith' );
		$request->set_param( 'reviewer_email', 'jane@example.com' );
		$request->set_param( 'review', 'This is my review of the product. It is excellent!' );
		$request->set_param( 'rating', 5 );

		$response = $this->controller->create_review( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertEquals( $this->product->get_id(), $data['product_id'] );
		$this->assertEquals( 'Jane Smith', $data['reviewer'] );
		$this->assertEquals( 5, $data['rating'] );

		// Track for cleanup.
		$this->comment_ids[] = $data['id'];
	}

	/**
	 * Test creating a review with invalid rating outside 1-5 range.
	 */
	public function test_create_review_invalid_rating() {
		// Test rating below minimum.
		$request = new WP_REST_Request( 'POST', '/ucp/v1/reviews' );
		$request->set_param( 'product_id', $this->product->get_id() );
		$request->set_param( 'reviewer', 'Jane Smith' );
		$request->set_param( 'reviewer_email', 'jane@example.com' );
		$request->set_param( 'review', 'Testing invalid rating.' );
		$request->set_param( 'rating', 0 );

		$response = $this->controller->create_review( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'invalid_rating', $response->get_error_code() );
		$this->assertEquals( 400, $response->get_error_data()['status'] );

		// Test rating above maximum.
		$request2 = new WP_REST_Request( 'POST', '/ucp/v1/reviews' );
		$request2->set_param( 'product_id', $this->product->get_id() );
		$request2->set_param( 'reviewer', 'Jane Smith' );
		$request2->set_param( 'reviewer_email', 'jane@example.com' );
		$request2->set_param( 'review', 'Testing invalid rating.' );
		$request2->set_param( 'rating', 6 );

		$response2 = $this->controller->create_review( $request2 );

		$this->assertInstanceOf( WP_Error::class, $response2 );
		$this->assertEquals( 'invalid_rating', $response2->get_error_code() );
		$this->assertEquals( 400, $response2->get_error_data()['status'] );
	}

	/**
	 * Test creating a duplicate review returns error when verification is required.
	 */
	public function test_create_review_duplicate() {
		// Enable verification required (prevents duplicate reviews).
		update_option( 'woocommerce_review_rating_verification_required', 'yes' );

		// Create initial review.
		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Jane Smith',
				'email'   => 'jane@example.com',
				'content' => 'First review.',
				'rating'  => 5,
			)
		);

		// Attempt to create duplicate review.
		$request = new WP_REST_Request( 'POST', '/ucp/v1/reviews' );
		$request->set_param( 'product_id', $this->product->get_id() );
		$request->set_param( 'reviewer', 'Jane Smith' );
		$request->set_param( 'reviewer_email', 'jane@example.com' );
		$request->set_param( 'review', 'Second review attempt.' );
		$request->set_param( 'rating', 4 );

		$response = $this->controller->create_review( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'duplicate_review', $response->get_error_code() );
		$this->assertEquals( 409, $response->get_error_data()['status'] );
	}

	/**
	 * Test getting reviews for a specific product.
	 */
	public function test_get_product_reviews() {
		// Create reviews for the product.
		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Alice',
				'email'   => 'alice@example.com',
				'content' => 'Great product!',
				'rating'  => 5,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Bob',
				'email'   => 'bob@example.com',
				'content' => 'Good product.',
				'rating'  => 4,
			)
		);

		// Create a review for another product.
		$this->create_review(
			$this->product2->get_id(),
			array(
				'author'  => 'Charlie',
				'email'   => 'charlie@example.com',
				'content' => 'Different product review.',
				'rating'  => 3,
			)
		);

		$request = new WP_REST_Request( 'GET', '/ucp/v1/products/' . $this->product->get_id() . '/reviews' );
		$request->set_param( 'product_id', $this->product->get_id() );

		$response = $this->controller->get_product_reviews( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'reviews', $data );
		$this->assertCount( 2, $data['reviews'] );

		// Verify all returned reviews are for the correct product.
		foreach ( $data['reviews'] as $review ) {
			$this->assertEquals( $this->product->get_id(), $review['product_id'] );
		}
	}

	/**
	 * Test getting product review summary with average rating and distribution.
	 */
	public function test_get_product_review_summary() {
		// Create reviews with different ratings to test distribution.
		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Alice',
				'email'   => 'alice@example.com',
				'content' => 'Perfect!',
				'rating'  => 5,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Bob',
				'email'   => 'bob@example.com',
				'content' => 'Also perfect!',
				'rating'  => 5,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Charlie',
				'email'   => 'charlie@example.com',
				'content' => 'Very good.',
				'rating'  => 4,
			)
		);

		$this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Diana',
				'email'   => 'diana@example.com',
				'content' => 'Average.',
				'rating'  => 3,
			)
		);

		// Clear product transients to ensure fresh data.
		wc_delete_product_transients( $this->product->get_id() );

		$request = new WP_REST_Request( 'GET', '/ucp/v1/products/' . $this->product->get_id() . '/reviews/summary' );
		$request->set_param( 'product_id', $this->product->get_id() );

		$response = $this->controller->get_product_review_summary( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertEquals( $this->product->get_id(), $data['product_id'] );
		$this->assertArrayHasKey( 'average_rating', $data );
		$this->assertArrayHasKey( 'review_count', $data );
		$this->assertArrayHasKey( 'rating_distribution', $data );

		// Verify rating distribution structure.
		$distribution = $data['rating_distribution'];
		$this->assertArrayHasKey( '5', $distribution );
		$this->assertArrayHasKey( '4', $distribution );
		$this->assertArrayHasKey( '3', $distribution );
		$this->assertArrayHasKey( '2', $distribution );
		$this->assertArrayHasKey( '1', $distribution );
	}

	/**
	 * Test review mapper output format.
	 */
	public function test_review_mapper() {
		$comment_id = $this->create_review(
			$this->product->get_id(),
			array(
				'author'   => 'Test User',
				'email'    => 'test@example.com',
				'content'  => 'This is a test review with some detailed content about the product.',
				'rating'   => 4,
				'verified' => 1,
			)
		);

		$comment = get_comment( $comment_id );

		// Test full review mapping.
		$mapped_review = $this->mapper->map_review( $comment );

		$this->assertIsArray( $mapped_review );
		$this->assertEquals( $comment_id, $mapped_review['id'] );
		$this->assertEquals( $this->product->get_id(), $mapped_review['product_id'] );
		$this->assertEquals( 'Test User', $mapped_review['reviewer'] );
		$this->assertEquals( 'test@example.com', $mapped_review['reviewer_email'] );
		$this->assertEquals( 4, $mapped_review['rating'] );
		$this->assertEquals( 'This is a test review with some detailed content about the product.', $mapped_review['review'] );
		$this->assertTrue( $mapped_review['verified'] );
		$this->assertEquals( 'approved', $mapped_review['status'] );
		$this->assertArrayHasKey( 'date_created', $mapped_review );

		// Test summary review mapping.
		$mapped_summary = $this->mapper->map_review_summary( $comment );

		$this->assertIsArray( $mapped_summary );
		$this->assertEquals( $comment_id, $mapped_summary['id'] );
		$this->assertEquals( $this->product->get_id(), $mapped_summary['product_id'] );
		$this->assertEquals( 'Test User', $mapped_summary['reviewer'] );
		$this->assertEquals( 4, $mapped_summary['rating'] );
		$this->assertTrue( $mapped_summary['verified'] );
		$this->assertEquals( 'approved', $mapped_summary['status'] );
		$this->assertArrayHasKey( 'date_created', $mapped_summary );
		// Summary should not include reviewer_email.
		$this->assertArrayNotHasKey( 'reviewer_email', $mapped_summary );
	}

	/**
	 * Test review pagination.
	 */
	public function test_review_pagination() {
		// Create 15 reviews.
		for ( $i = 1; $i <= 15; $i++ ) {
			$this->create_review(
				$this->product->get_id(),
				array(
					'author'  => 'User ' . $i,
					'email'   => 'user' . $i . '@example.com',
					'content' => 'Review number ' . $i,
					'rating'  => ( $i % 5 ) + 1,
				)
			);
		}

		// Test first page.
		$request = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 5 );

		$response = $this->controller->list_reviews( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertEquals( 15, $data['total'] );
		$this->assertEquals( 1, $data['page'] );
		$this->assertEquals( 5, $data['per_page'] );
		$this->assertEquals( 3, $data['total_pages'] );
		$this->assertCount( 5, $data['reviews'] );

		// Test second page.
		$request2 = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request2->set_param( 'page', 2 );
		$request2->set_param( 'per_page', 5 );

		$response2 = $this->controller->list_reviews( $request2 );

		$this->assertInstanceOf( WP_REST_Response::class, $response2 );

		$data2 = $response2->get_data();

		$this->assertEquals( 2, $data2['page'] );
		$this->assertCount( 5, $data2['reviews'] );

		// Test last page.
		$request3 = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request3->set_param( 'page', 3 );
		$request3->set_param( 'per_page', 5 );

		$response3 = $this->controller->list_reviews( $request3 );

		$this->assertInstanceOf( WP_REST_Response::class, $response3 );

		$data3 = $response3->get_data();

		$this->assertEquals( 3, $data3['page'] );
		$this->assertCount( 5, $data3['reviews'] );
	}

	/**
	 * Test review ordering by different attributes.
	 */
	public function test_review_ordering() {
		// Create reviews with different dates and ratings.
		// Older review.
		$review1 = $this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'First User',
				'email'   => 'first@example.com',
				'content' => 'First review.',
				'rating'  => 3,
			)
		);

		// Slightly newer review.
		sleep( 1 ); // Ensure different timestamps.
		$review2 = $this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Second User',
				'email'   => 'second@example.com',
				'content' => 'Second review.',
				'rating'  => 5,
			)
		);

		// Newest review.
		sleep( 1 );
		$review3 = $this->create_review(
			$this->product->get_id(),
			array(
				'author'  => 'Third User',
				'email'   => 'third@example.com',
				'content' => 'Third review.',
				'rating'  => 4,
			)
		);

		// Test order by date descending (default).
		$request = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request->set_param( 'orderby', 'date' );
		$request->set_param( 'order', 'desc' );

		$response = $this->controller->list_reviews( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );

		$data = $response->get_data();

		$this->assertEquals( $review3, $data['reviews'][0]['id'] );
		$this->assertEquals( $review2, $data['reviews'][1]['id'] );
		$this->assertEquals( $review1, $data['reviews'][2]['id'] );

		// Test order by date ascending.
		$request2 = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request2->set_param( 'orderby', 'date' );
		$request2->set_param( 'order', 'asc' );

		$response2 = $this->controller->list_reviews( $request2 );

		$this->assertInstanceOf( WP_REST_Response::class, $response2 );

		$data2 = $response2->get_data();

		$this->assertEquals( $review1, $data2['reviews'][0]['id'] );
		$this->assertEquals( $review2, $data2['reviews'][1]['id'] );
		$this->assertEquals( $review3, $data2['reviews'][2]['id'] );

		// Test order by ID descending.
		$request3 = new WP_REST_Request( 'GET', '/ucp/v1/reviews' );
		$request3->set_param( 'orderby', 'id' );
		$request3->set_param( 'order', 'desc' );

		$response3 = $this->controller->list_reviews( $request3 );

		$this->assertInstanceOf( WP_REST_Response::class, $response3 );

		$data3 = $response3->get_data();

		// Highest ID first.
		$this->assertGreaterThan( $data3['reviews'][1]['id'], $data3['reviews'][0]['id'] );
		$this->assertGreaterThan( $data3['reviews'][2]['id'], $data3['reviews'][1]['id'] );
	}

	/**
	 * Test getting product reviews for non-existent product.
	 */
	public function test_get_product_reviews_not_found() {
		$request = new WP_REST_Request( 'GET', '/ucp/v1/products/999999/reviews' );
		$request->set_param( 'product_id', 999999 );

		$response = $this->controller->get_product_reviews( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'product_not_found', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}

	/**
	 * Test creating review for non-existent product.
	 */
	public function test_create_review_product_not_found() {
		$request = new WP_REST_Request( 'POST', '/ucp/v1/reviews' );
		$request->set_param( 'product_id', 999999 );
		$request->set_param( 'reviewer', 'Jane Smith' );
		$request->set_param( 'reviewer_email', 'jane@example.com' );
		$request->set_param( 'review', 'Test review.' );
		$request->set_param( 'rating', 5 );

		$response = $this->controller->create_review( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'product_not_found', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}

	/**
	 * Test creating review when reviews are disabled for product.
	 */
	public function test_create_review_reviews_disabled() {
		// Disable reviews for the product.
		$this->product->set_reviews_allowed( false );
		$this->product->save();

		$request = new WP_REST_Request( 'POST', '/ucp/v1/reviews' );
		$request->set_param( 'product_id', $this->product->get_id() );
		$request->set_param( 'reviewer', 'Jane Smith' );
		$request->set_param( 'reviewer_email', 'jane@example.com' );
		$request->set_param( 'review', 'Test review.' );
		$request->set_param( 'rating', 5 );

		$response = $this->controller->create_review( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'reviews_disabled', $response->get_error_code() );
		$this->assertEquals( 403, $response->get_error_data()['status'] );
	}

	/**
	 * Test review status mapping.
	 */
	public function test_review_status_mapping() {
		// Test approved review.
		$approved_id = $this->create_review(
			$this->product->get_id(),
			array(
				'author'   => 'Approved User',
				'email'    => 'approved@example.com',
				'content'  => 'Approved review.',
				'rating'   => 5,
				'approved' => 1,
			)
		);

		$approved_comment = get_comment( $approved_id );
		$approved_mapped  = $this->mapper->map_review( $approved_comment );
		$this->assertEquals( 'approved', $approved_mapped['status'] );

		// Test pending review.
		$pending_id = $this->create_review(
			$this->product->get_id(),
			array(
				'author'   => 'Pending User',
				'email'    => 'pending@example.com',
				'content'  => 'Pending review.',
				'rating'   => 4,
				'approved' => 0,
			)
		);

		$pending_comment = get_comment( $pending_id );
		$pending_mapped  = $this->mapper->map_review( $pending_comment );
		$this->assertEquals( 'pending', $pending_mapped['status'] );
	}

	/**
	 * Test UCP status to WP status mapping.
	 */
	public function test_ucp_status_to_wp_mapping() {
		$this->assertEquals( 'approve', $this->mapper->map_ucp_status_to_wp( 'approved' ) );
		$this->assertEquals( 'hold', $this->mapper->map_ucp_status_to_wp( 'pending' ) );
		$this->assertEquals( 'spam', $this->mapper->map_ucp_status_to_wp( 'spam' ) );
		$this->assertEquals( 'trash', $this->mapper->map_ucp_status_to_wp( 'trash' ) );
		$this->assertEquals( 'hold', $this->mapper->map_ucp_status_to_wp( 'unknown' ) );
	}

	/**
	 * Test getting review summary for non-existent product.
	 */
	public function test_get_review_summary_product_not_found() {
		$request = new WP_REST_Request( 'GET', '/ucp/v1/products/999999/reviews/summary' );
		$request->set_param( 'product_id', 999999 );

		$response = $this->controller->get_product_review_summary( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'product_not_found', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}

	/**
	 * Test mapper prepare_review_for_insert method.
	 */
	public function test_mapper_prepare_review_for_insert() {
		$review_data = array(
			'reviewer'       => 'Test Reviewer',
			'reviewer_email' => 'reviewer@example.com',
			'review'         => 'This is my test review content.',
		);

		$prepared = $this->mapper->prepare_review_for_insert( $review_data, $this->product->get_id() );

		$this->assertIsArray( $prepared );
		$this->assertEquals( $this->product->get_id(), $prepared['comment_post_ID'] );
		$this->assertEquals( 'Test Reviewer', $prepared['comment_author'] );
		$this->assertEquals( 'reviewer@example.com', $prepared['comment_author_email'] );
		$this->assertEquals( 'This is my test review content.', $prepared['comment_content'] );
		$this->assertEquals( 'review', $prepared['comment_type'] );
		$this->assertEquals( 0, $prepared['comment_parent'] );
	}

	/**
	 * Test review with empty rating returns 0.
	 */
	public function test_review_with_no_rating() {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $this->product->get_id(),
				'comment_author'       => 'No Rating User',
				'comment_author_email' => 'norating@example.com',
				'comment_content'      => 'Review without rating.',
				'comment_type'         => 'review',
				'comment_approved'     => 1,
			)
		);

		$this->comment_ids[] = $comment_id;

		$comment = get_comment( $comment_id );
		$mapped  = $this->mapper->map_review( $comment );

		$this->assertEquals( 0, $mapped['rating'] );
	}
}
