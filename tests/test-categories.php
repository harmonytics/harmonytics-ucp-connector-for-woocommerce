<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Tests for the Categories capability.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

/**
 * Class Test_UCP_Categories
 *
 * Tests the category listing and retrieval functionality.
 */
class Test_UCP_Categories extends WC_Unit_Test_Case {

	/**
	 * Category controller instance.
	 *
	 * @var UCP_WC_Category_Controller
	 */
	protected $controller;

	/**
	 * Category mapper instance.
	 *
	 * @var UCP_WC_Category_Mapper
	 */
	protected $category_mapper;

	/**
	 * Test categories.
	 *
	 * @var array
	 */
	protected $categories = array();

	/**
	 * Test products.
	 *
	 * @var array
	 */
	protected $products = array();

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		// Load required classes
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-category-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/mapping/class-ucp-product-mapper.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-rest-controller.php';
		require_once UCP_WC_PLUGIN_DIR . 'includes/rest/class-ucp-category-controller.php';

		$this->controller      = new UCP_WC_Category_Controller();
		$this->category_mapper = new UCP_WC_Category_Mapper();

		// Enable UCP
		update_option( 'ucp_wc_enabled', 'yes' );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down() {
		// Clean up products
		foreach ( $this->products as $product ) {
			if ( $product instanceof WC_Product ) {
				$product->delete( true );
			}
		}

		// Clean up categories
		foreach ( $this->categories as $category_id ) {
			wp_delete_term( $category_id, 'product_cat' );
		}

		$this->products   = array();
		$this->categories = array();

		parent::tear_down();
	}

	/**
	 * Helper to create a test category.
	 *
	 * @param string $name        Category name.
	 * @param int    $parent_id   Parent category ID.
	 * @param string $description Category description.
	 * @return int Term ID.
	 */
	protected function create_category( $name, $parent_id = 0, $description = '' ) {
		$result = wp_insert_term(
			$name,
			'product_cat',
			array(
				'parent'      => $parent_id,
				'description' => $description,
			)
		);

		if ( is_wp_error( $result ) ) {
			return 0;
		}

		$this->categories[] = $result['term_id'];

		return $result['term_id'];
	}

	/**
	 * Helper to create a test product in a category.
	 *
	 * @param int $category_id Category ID.
	 * @return WC_Product
	 */
	protected function create_product_in_category( $category_id ) {
		$product = WC_Helper_Product::create_simple_product();
		wp_set_object_terms( $product->get_id(), $category_id, 'product_cat' );
		$this->products[] = $product;

		return $product;
	}

	/**
	 * Test listing all categories.
	 */
	public function test_list_categories() {
		// Create test categories
		$cat1 = $this->create_category( 'Electronics', 0, 'Electronic devices' );
		$cat2 = $this->create_category( 'Clothing', 0, 'Apparel and accessories' );
		$cat3 = $this->create_category( 'Books', 0, 'Books and literature' );

		$this->assertGreaterThan( 0, $cat1 );
		$this->assertGreaterThan( 0, $cat2 );
		$this->assertGreaterThan( 0, $cat3 );

		// Create request
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories' );
		$request->set_param( 'per_page', 100 );

		$response = $this->controller->list_categories( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'categories', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'page', $data );
		$this->assertArrayHasKey( 'per_page', $data );
		$this->assertArrayHasKey( 'total_pages', $data );

		// Verify we have at least our 3 test categories
		$this->assertGreaterThanOrEqual( 3, count( $data['categories'] ) );

		// Find our test categories in the response
		$category_names = array_column( $data['categories'], 'name' );
		$this->assertContains( 'Electronics', $category_names );
		$this->assertContains( 'Clothing', $category_names );
		$this->assertContains( 'Books', $category_names );
	}

	/**
	 * Test hierarchical category structure.
	 */
	public function test_list_categories_with_hierarchy() {
		// Create parent category
		$parent_id = $this->create_category( 'Parent Category', 0, 'Parent description' );
		$this->assertGreaterThan( 0, $parent_id );

		// Create child categories
		$child1_id = $this->create_category( 'Child One', $parent_id, 'First child' );
		$child2_id = $this->create_category( 'Child Two', $parent_id, 'Second child' );

		$this->assertGreaterThan( 0, $child1_id );
		$this->assertGreaterThan( 0, $child2_id );

		// Create grandchild
		$grandchild_id = $this->create_category( 'Grandchild', $child1_id, 'Grandchild category' );
		$this->assertGreaterThan( 0, $grandchild_id );

		// Test with hierarchy=true
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories' );
		$request->set_param( 'hierarchy', true );
		$request->set_param( 'per_page', 100 );

		$response = $this->controller->list_categories( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'categories', $data );

		// Test parent filtering
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories' );
		$request->set_param( 'parent', $parent_id );

		$response = $this->controller->list_categories( $request );
		$data     = $response->get_data();

		// Should return direct children only
		$child_names = array_column( $data['categories'], 'name' );
		$this->assertContains( 'Child One', $child_names );
		$this->assertContains( 'Child Two', $child_names );

		// Test top-level only
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories' );
		$request->set_param( 'parent', 0 );

		$response    = $this->controller->list_categories( $request );
		$data        = $response->get_data();
		$parent_ids  = array_column( $data['categories'], 'parent_id' );

		// All returned categories should have null parent_id
		foreach ( $parent_ids as $pid ) {
			$this->assertNull( $pid );
		}
	}

	/**
	 * Test getting a single category.
	 */
	public function test_get_single_category() {
		// Create a test category
		$category_id = $this->create_category( 'Test Single Category', 0, 'A test category for single retrieval' );
		$this->assertGreaterThan( 0, $category_id );

		// Create request
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/' . $category_id );
		$request->set_param( 'category_id', $category_id );

		$response = $this->controller->get_category( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'slug', $data );
		$this->assertArrayHasKey( 'description', $data );
		$this->assertArrayHasKey( 'parent_id', $data );
		$this->assertArrayHasKey( 'product_count', $data );
		$this->assertArrayHasKey( 'image', $data );
		$this->assertArrayHasKey( 'url', $data );
		$this->assertArrayHasKey( 'links', $data );
		$this->assertArrayHasKey( 'children', $data );

		$this->assertEquals( $category_id, $data['id'] );
		$this->assertEquals( 'Test Single Category', $data['name'] );
		$this->assertEquals( 'A test category for single retrieval', $data['description'] );
		$this->assertNull( $data['parent_id'] );

		// Verify links structure
		$this->assertArrayHasKey( 'self', $data['links'] );
		$this->assertArrayHasKey( 'products', $data['links'] );
		$this->assertArrayHasKey( 'permalink', $data['links'] );
	}

	/**
	 * Test 404 for non-existent category.
	 */
	public function test_get_category_not_found() {
		// Create request for non-existent category
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/999999' );
		$request->set_param( 'category_id', 999999 );

		$response = $this->controller->get_category( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'category_not_found', $response->get_error_code() );
		$error_data = $response->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test getting products in a category.
	 */
	public function test_get_category_products() {
		// Create a test category
		$category_id = $this->create_category( 'Products Category', 0, 'Category with products' );
		$this->assertGreaterThan( 0, $category_id );

		// Create products in the category
		$product1 = $this->create_product_in_category( $category_id );
		$product2 = $this->create_product_in_category( $category_id );

		// Create request
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/' . $category_id . '/products' );
		$request->set_param( 'category_id', $category_id );

		$response = $this->controller->get_category_products( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'category', $data );
		$this->assertArrayHasKey( 'products', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertArrayHasKey( 'page', $data );
		$this->assertArrayHasKey( 'per_page', $data );
		$this->assertArrayHasKey( 'total_pages', $data );

		// Should have at least 2 products
		$this->assertGreaterThanOrEqual( 2, count( $data['products'] ) );

		// Verify product structure
		if ( ! empty( $data['products'] ) ) {
			$first_product = $data['products'][0];
			$this->assertArrayHasKey( 'id', $first_product );
			$this->assertArrayHasKey( 'name', $first_product );
		}
	}

	/**
	 * Test category product count.
	 */
	public function test_category_product_count() {
		// Create a test category
		$category_id = $this->create_category( 'Count Category', 0, 'Category for counting' );
		$this->assertGreaterThan( 0, $category_id );

		// Initially should have 0 products
		$term = get_term( $category_id, 'product_cat' );
		$this->assertEquals( 0, $term->count );

		// Create 3 products in the category
		$this->create_product_in_category( $category_id );
		$this->create_product_in_category( $category_id );
		$this->create_product_in_category( $category_id );

		// Recount terms
		wp_update_term_count_now( array( $category_id ), 'product_cat' );

		// Verify count via API
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/' . $category_id );
		$request->set_param( 'category_id', $category_id );

		$response = $this->controller->get_category( $request );
		$data     = $response->get_data();

		$this->assertEquals( 3, $data['product_count'] );
	}

	/**
	 * Test category with thumbnail image.
	 */
	public function test_category_with_image() {
		// Create a test category
		$category_id = $this->create_category( 'Image Category', 0, 'Category with image' );
		$this->assertGreaterThan( 0, $category_id );

		// Create a mock attachment ID for the thumbnail
		$attachment_id = $this->factory->attachment->create_upload_object(
			dirname( __FILE__ ) . '/assets/test-image.jpg',
			0
		);

		// If no test image file exists, create a mock attachment
		if ( ! $attachment_id ) {
			$attachment_id = wp_insert_attachment(
				array(
					'post_mime_type' => 'image/jpeg',
					'post_title'     => 'Test Category Image',
					'post_status'    => 'inherit',
				)
			);
		}

		if ( $attachment_id ) {
			// Set the thumbnail
			update_term_meta( $category_id, 'thumbnail_id', $attachment_id );

			// Verify via mapper
			$term   = get_term( $category_id, 'product_cat' );
			$mapped = $this->category_mapper->map_category( $term );

			// Image should be present (may be null if attachment URL not available in test env)
			$this->assertArrayHasKey( 'image', $mapped );

			// Clean up attachment
			wp_delete_attachment( $attachment_id, true );
		}

		// Test category without image
		$no_image_category_id = $this->create_category( 'No Image Category', 0, 'Category without image' );
		$term                 = get_term( $no_image_category_id, 'product_cat' );
		$mapped               = $this->category_mapper->map_category( $term );

		$this->assertNull( $mapped['image'] );
	}

	/**
	 * Test pagination for category products.
	 */
	public function test_category_pagination() {
		// Create a test category
		$category_id = $this->create_category( 'Pagination Category', 0, 'Category for pagination test' );
		$this->assertGreaterThan( 0, $category_id );

		// Create 15 products in the category
		for ( $i = 0; $i < 15; $i++ ) {
			$this->create_product_in_category( $category_id );
		}

		// Recount terms
		wp_update_term_count_now( array( $category_id ), 'product_cat' );

		// Test first page with 5 per page
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/' . $category_id . '/products' );
		$request->set_param( 'category_id', $category_id );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 5 );

		$response = $this->controller->get_category_products( $request );
		$data     = $response->get_data();

		$this->assertEquals( 1, $data['page'] );
		$this->assertEquals( 5, $data['per_page'] );
		$this->assertEquals( 15, $data['total'] );
		$this->assertEquals( 3, $data['total_pages'] );
		$this->assertCount( 5, $data['products'] );

		// Test second page
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/' . $category_id . '/products' );
		$request->set_param( 'category_id', $category_id );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 5 );

		$response = $this->controller->get_category_products( $request );
		$data     = $response->get_data();

		$this->assertEquals( 2, $data['page'] );
		$this->assertCount( 5, $data['products'] );

		// Test third (last) page
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/' . $category_id . '/products' );
		$request->set_param( 'category_id', $category_id );
		$request->set_param( 'page', 3 );
		$request->set_param( 'per_page', 5 );

		$response = $this->controller->get_category_products( $request );
		$data     = $response->get_data();

		$this->assertEquals( 3, $data['page'] );
		$this->assertCount( 5, $data['products'] );

		// Verify pagination headers
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );
		$this->assertEquals( 15, $headers['X-WP-Total'] );
		$this->assertEquals( 3, $headers['X-WP-TotalPages'] );
	}

	/**
	 * Test the category mapper class directly.
	 */
	public function test_category_mapper() {
		// Create parent category
		$parent_id = $this->create_category( 'Mapper Parent', 0, 'Parent for mapper test' );
		$this->assertGreaterThan( 0, $parent_id );

		// Create child categories
		$child1_id = $this->create_category( 'Mapper Child 1', $parent_id, 'First child for mapper' );
		$child2_id = $this->create_category( 'Mapper Child 2', $parent_id, 'Second child for mapper' );

		$this->assertGreaterThan( 0, $child1_id );
		$this->assertGreaterThan( 0, $child2_id );

		// Test map_category with children
		$parent_term = get_term( $parent_id, 'product_cat' );
		$mapped      = $this->category_mapper->map_category( $parent_term, true );

		$this->assertArrayHasKey( 'id', $mapped );
		$this->assertArrayHasKey( 'name', $mapped );
		$this->assertArrayHasKey( 'slug', $mapped );
		$this->assertArrayHasKey( 'description', $mapped );
		$this->assertArrayHasKey( 'parent_id', $mapped );
		$this->assertArrayHasKey( 'product_count', $mapped );
		$this->assertArrayHasKey( 'image', $mapped );
		$this->assertArrayHasKey( 'url', $mapped );
		$this->assertArrayHasKey( 'links', $mapped );
		$this->assertArrayHasKey( 'children', $mapped );

		$this->assertEquals( 'Mapper Parent', $mapped['name'] );
		$this->assertEquals( 'Parent for mapper test', $mapped['description'] );
		$this->assertNull( $mapped['parent_id'] );

		// Verify children are included
		$this->assertIsArray( $mapped['children'] );
		$this->assertCount( 2, $mapped['children'] );

		$child_names = array_column( $mapped['children'], 'name' );
		$this->assertContains( 'Mapper Child 1', $child_names );
		$this->assertContains( 'Mapper Child 2', $child_names );

		// Test map_category_summary (no children)
		$summary = $this->category_mapper->map_category_summary( $parent_term );

		$this->assertArrayHasKey( 'id', $summary );
		$this->assertArrayHasKey( 'name', $summary );
		$this->assertArrayNotHasKey( 'children', $summary );
		$this->assertArrayNotHasKey( 'links', $summary );

		// Test child category parent_id
		$child_term   = get_term( $child1_id, 'product_cat' );
		$child_mapped = $this->category_mapper->map_category( $child_term );

		$this->assertEquals( $parent_id, $child_mapped['parent_id'] );

		// Verify links include parent reference
		$this->assertArrayHasKey( 'parent', $child_mapped['links'] );

		// Test build_hierarchy
		$flat_categories = array(
			array(
				'id'        => 1,
				'name'      => 'Root 1',
				'parent_id' => null,
			),
			array(
				'id'        => 2,
				'name'      => 'Root 2',
				'parent_id' => null,
			),
			array(
				'id'        => 3,
				'name'      => 'Child of 1',
				'parent_id' => 1,
			),
			array(
				'id'        => 4,
				'name'      => 'Child of 3',
				'parent_id' => 3,
			),
		);

		$hierarchy = $this->category_mapper->build_hierarchy( $flat_categories );

		$this->assertCount( 2, $hierarchy );

		// Find Root 1 and verify its children
		$root1 = null;
		foreach ( $hierarchy as $cat ) {
			if ( $cat['name'] === 'Root 1' ) {
				$root1 = $cat;
				break;
			}
		}

		$this->assertNotNull( $root1 );
		$this->assertArrayHasKey( 'children', $root1 );
		$this->assertCount( 1, $root1['children'] );
		$this->assertEquals( 'Child of 1', $root1['children'][0]['name'] );

		// Verify nested child
		$this->assertArrayHasKey( 'children', $root1['children'][0] );
		$this->assertCount( 1, $root1['children'][0]['children'] );
		$this->assertEquals( 'Child of 3', $root1['children'][0]['children'][0]['name'] );
	}

	/**
	 * Test category products with include_children parameter.
	 */
	public function test_category_products_include_children() {
		// Create parent category
		$parent_id = $this->create_category( 'Parent With Children', 0, 'Parent category' );
		$this->assertGreaterThan( 0, $parent_id );

		// Create child category
		$child_id = $this->create_category( 'Child Category', $parent_id, 'Child category' );
		$this->assertGreaterThan( 0, $child_id );

		// Create product in parent
		$parent_product = $this->create_product_in_category( $parent_id );

		// Create product in child
		$child_product = $this->create_product_in_category( $child_id );

		// Recount terms
		wp_update_term_count_now( array( $parent_id, $child_id ), 'product_cat' );

		// Test with include_children=true (default)
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/' . $parent_id . '/products' );
		$request->set_param( 'category_id', $parent_id );
		$request->set_param( 'include_children', true );

		$response = $this->controller->get_category_products( $request );
		$data     = $response->get_data();

		// Should include products from both parent and child
		$this->assertGreaterThanOrEqual( 2, $data['total'] );

		// Test with include_children=false
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/' . $parent_id . '/products' );
		$request->set_param( 'category_id', $parent_id );
		$request->set_param( 'include_children', false );

		$response = $this->controller->get_category_products( $request );
		$data     = $response->get_data();

		// Should only include products directly in parent
		$this->assertEquals( 1, $data['total'] );
	}

	/**
	 * Test category listing with hide_empty parameter.
	 */
	public function test_category_hide_empty() {
		// Create category with products
		$populated_id = $this->create_category( 'Populated Category', 0, 'Has products' );
		$this->create_product_in_category( $populated_id );

		// Create empty category
		$empty_id = $this->create_category( 'Empty Category', 0, 'No products' );

		// Recount terms
		wp_update_term_count_now( array( $populated_id, $empty_id ), 'product_cat' );

		// Test with hide_empty=false (default)
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories' );
		$request->set_param( 'hide_empty', false );

		$response        = $this->controller->list_categories( $request );
		$data            = $response->get_data();
		$category_names  = array_column( $data['categories'], 'name' );

		$this->assertContains( 'Populated Category', $category_names );
		$this->assertContains( 'Empty Category', $category_names );

		// Test with hide_empty=true
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories' );
		$request->set_param( 'hide_empty', true );

		$response       = $this->controller->list_categories( $request );
		$data           = $response->get_data();
		$category_names = array_column( $data['categories'], 'name' );

		$this->assertContains( 'Populated Category', $category_names );
		$this->assertNotContains( 'Empty Category', $category_names );
	}

	/**
	 * Test category products 404 for non-existent category.
	 */
	public function test_category_products_not_found() {
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories/999999/products' );
		$request->set_param( 'category_id', 999999 );

		$response = $this->controller->get_category_products( $request );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertEquals( 'category_not_found', $response->get_error_code() );
		$error_data = $response->get_error_data();
		$this->assertEquals( 404, $error_data['status'] );
	}

	/**
	 * Test category ordering.
	 */
	public function test_category_ordering() {
		// Create categories with different names
		$this->create_category( 'Zebra Category', 0, '' );
		$this->create_category( 'Alpha Category', 0, '' );
		$this->create_category( 'Middle Category', 0, '' );

		// Test orderby name ASC
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'asc' );

		$response = $this->controller->list_categories( $request );
		$data     = $response->get_data();

		$names = array_column( $data['categories'], 'name' );

		// Find positions of our test categories
		$alpha_pos  = array_search( 'Alpha Category', $names );
		$middle_pos = array_search( 'Middle Category', $names );
		$zebra_pos  = array_search( 'Zebra Category', $names );

		$this->assertLessThan( $middle_pos, $alpha_pos );
		$this->assertLessThan( $zebra_pos, $middle_pos );

		// Test orderby name DESC
		$request = new WP_REST_Request( 'GET', '/ucp/v1/categories' );
		$request->set_param( 'orderby', 'name' );
		$request->set_param( 'order', 'desc' );

		$response = $this->controller->list_categories( $request );
		$data     = $response->get_data();

		$names = array_column( $data['categories'], 'name' );

		$alpha_pos  = array_search( 'Alpha Category', $names );
		$middle_pos = array_search( 'Middle Category', $names );
		$zebra_pos  = array_search( 'Zebra Category', $names );

		$this->assertGreaterThan( $middle_pos, $alpha_pos );
		$this->assertGreaterThan( $zebra_pos, $middle_pos );
	}
}
