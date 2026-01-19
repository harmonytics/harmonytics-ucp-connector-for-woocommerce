<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * REST controller for category endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Category_Controller
 *
 * Handles category REST API endpoints for UCP.
 */
class UCP_WC_Category_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'categories';

	/**
	 * Category mapper instance.
	 *
	 * @var UCP_WC_Category_Mapper
	 */
	protected $category_mapper;

	/**
	 * Product mapper instance.
	 *
	 * @var UCP_WC_Product_Mapper
	 */
	protected $product_mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->category_mapper = new UCP_WC_Category_Mapper();
		$this->product_mapper  = new UCP_WC_Product_Mapper();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /categories - List all categories
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_categories' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => $this->get_list_categories_args(),
				),
			)
		);

		// GET /categories/{category_id} - Get single category
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<category_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_category' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(
						'category_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Category ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// GET /categories/{category_id}/products - Get products in a category
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<category_id>[\d]+)/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_category_products' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => $this->get_category_products_args(),
				),
			)
		);
	}

	/**
	 * Get arguments for list categories endpoint.
	 *
	 * @return array
	 */
	private function get_list_categories_args() {
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
				'default'           => 100,
				'minimum'           => 1,
				'maximum'           => 100,
				'description'       => __( 'Items per page.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'parent'     => array(
				'required'          => false,
				'type'              => 'integer',
				'description'       => __( 'Filter by parent category ID. Use 0 for top-level categories only.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'intval',
			),
			'hide_empty' => array(
				'required'    => false,
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Hide empty categories.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'orderby'    => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'name', 'id', 'slug', 'count', 'menu_order' ),
				'default'     => 'name',
				'description' => __( 'Sort collection by attribute.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'order'      => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'asc', 'desc' ),
				'default'     => 'asc',
				'description' => __( 'Order sort direction.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'hierarchy'  => array(
				'required'    => false,
				'type'        => 'boolean',
				'default'     => false,
				'description' => __( 'Return categories as a hierarchical tree.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get arguments for category products endpoint.
	 *
	 * @return array
	 */
	private function get_category_products_args() {
		return array(
			'category_id'     => array(
				'required'          => true,
				'type'              => 'integer',
				'description'       => __( 'Category ID.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'page'            => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'description'       => __( 'Page number.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'per_page'        => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'description'       => __( 'Items per page.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'include_children' => array(
				'required'    => false,
				'type'        => 'boolean',
				'default'     => true,
				'description' => __( 'Include products from child categories.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'orderby'         => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'date', 'id', 'title', 'price', 'popularity', 'rating', 'menu_order' ),
				'default'     => 'date',
				'description' => __( 'Sort collection by attribute.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'order'           => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'asc', 'desc' ),
				'default'     => 'desc',
				'description' => __( 'Order sort direction.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'in_stock'        => array(
				'required'    => false,
				'type'        => 'boolean',
				'description' => __( 'Filter by stock status.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
			'on_sale'         => array(
				'required'    => false,
				'type'        => 'boolean',
				'description' => __( 'Filter by on sale status.', 'harmonytics-ucp-connector-for-woocommerce' ),
			),
		);
	}

	/**
	 * List all categories.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_categories( $request ) {
		$this->log( 'Listing categories', array( 'params' => $request->get_params() ) );

		$page       = $request->get_param( 'page' ) ?: 1;
		$per_page   = $request->get_param( 'per_page' ) ?: 100;
		$parent     = $request->get_param( 'parent' );
		$hide_empty = $request->get_param( 'hide_empty' ) ?: false;
		$orderby    = $request->get_param( 'orderby' ) ?: 'name';
		$order      = strtoupper( $request->get_param( 'order' ) ?: 'ASC' );
		$hierarchy  = $request->get_param( 'hierarchy' ) ?: false;

		// Build query args
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => $hide_empty,
			'orderby'    => $this->map_orderby( $orderby ),
			'order'      => $order,
		);

		// Filter by parent if specified
		if ( ! is_null( $parent ) ) {
			$args['parent'] = $parent;
		}

		// Get total count first
		$count_args           = $args;
		$count_args['fields'] = 'count';
		$total                = (int) wp_count_terms( $count_args );

		// Add pagination
		$args['number'] = $per_page;
		$args['offset'] = ( $page - 1 ) * $per_page;

		// Get terms
		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return $this->error_response(
				'category_query_error',
				__( 'Failed to retrieve categories.', 'harmonytics-ucp-connector-for-woocommerce' ),
				500
			);
		}

		$total_pages = ceil( $total / $per_page );

		// Map categories
		$mapped_categories = array();
		foreach ( $terms as $term ) {
			$mapped_categories[] = $this->category_mapper->map_category_summary( $term );
		}

		// Build hierarchical tree if requested
		if ( $hierarchy && is_null( $parent ) ) {
			$mapped_categories = $this->category_mapper->build_hierarchy( $mapped_categories );
		}

		$result = array(
			'categories'  => $mapped_categories,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
		);

		$response = $this->success_response( $result );

		// Add pagination headers
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Get single category.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_category( $request ) {
		$category_id = $request->get_param( 'category_id' );

		$this->log( 'Getting category', array( 'category_id' => $category_id ) );

		$term = get_term( $category_id, 'product_cat' );

		if ( ! $term || is_wp_error( $term ) ) {
			return $this->error_response(
				'category_not_found',
				__( 'Category not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		$mapped_category = $this->category_mapper->map_category( $term, true );

		return $this->success_response( $mapped_category );
	}

	/**
	 * Get products in a category.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_category_products( $request ) {
		$category_id      = $request->get_param( 'category_id' );
		$page             = $request->get_param( 'page' ) ?: 1;
		$per_page         = $request->get_param( 'per_page' ) ?: 10;
		$include_children = $request->get_param( 'include_children' ) !== false;
		$orderby          = $request->get_param( 'orderby' ) ?: 'date';
		$order            = strtoupper( $request->get_param( 'order' ) ?: 'DESC' );
		$in_stock         = $request->get_param( 'in_stock' );
		$on_sale          = $request->get_param( 'on_sale' );

		$this->log(
			'Getting category products',
			array(
				'category_id' => $category_id,
				'params'      => $request->get_params(),
			)
		);

		// Verify category exists
		$term = get_term( $category_id, 'product_cat' );

		if ( ! $term || is_wp_error( $term ) ) {
			return $this->error_response(
				'category_not_found',
				__( 'Category not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				404
			);
		}

		// Build product query args
		$args = array(
			'status'     => 'publish',
			'limit'      => $per_page,
			'page'       => $page,
			'orderby'    => $this->map_product_orderby( $orderby ),
			'order'      => $order,
			'visibility' => 'visible',
		);

		// Set category filter
		if ( $include_children ) {
			// Include products from child categories
			$args['category'] = array( $term->slug );
		} else {
			// Only direct products (use tax_query for exact match).
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for filtering products by exact category without children.
			$args['tax_query'] = array(
				array(
					'taxonomy'         => 'product_cat',
					'field'            => 'term_id',
					'terms'            => $category_id,
					'include_children' => false,
				),
			);
		}

		// Stock filter
		if ( $in_stock === true ) {
			$args['stock_status'] = 'instock';
		} elseif ( $in_stock === false ) {
			$args['stock_status'] = 'outofstock';
		}

		// On sale filter
		if ( $on_sale === true ) {
			$args['include'] = wc_get_product_ids_on_sale();
		}

		$query    = new WC_Product_Query( $args );
		$products = $query->get_products();

		// Get total count for pagination
		$count_args           = $args;
		$count_args['return'] = 'ids';
		$count_args['limit']  = -1;
		$count_args['page']   = 1;
		unset( $count_args['offset'] );

		$count_query = new WC_Product_Query( $count_args );
		$total       = count( $count_query->get_products() );
		$total_pages = ceil( $total / $per_page );

		// Map products
		$mapped_products = array();
		foreach ( $products as $product ) {
			$mapped_products[] = $this->product_mapper->map_product_summary( $product );
		}

		$result = array(
			'category'    => $this->category_mapper->map_category_summary( $term ),
			'products'    => $mapped_products,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
		);

		$response = $this->success_response( $result );

		// Add pagination headers
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Map orderby parameter for categories.
	 *
	 * @param string $orderby Orderby parameter.
	 * @return string
	 */
	private function map_orderby( $orderby ) {
		$mapping = array(
			'name'       => 'name',
			'id'         => 'term_id',
			'slug'       => 'slug',
			'count'      => 'count',
			'menu_order' => 'menu_order',
		);

		return $mapping[ $orderby ] ?? 'name';
	}

	/**
	 * Map orderby parameter for products.
	 *
	 * @param string $orderby Orderby parameter.
	 * @return string
	 */
	private function map_product_orderby( $orderby ) {
		$mapping = array(
			'date'       => 'date',
			'id'         => 'ID',
			'title'      => 'title',
			'price'      => 'price',
			'popularity' => 'popularity',
			'rating'     => 'rating',
			'menu_order' => 'menu_order',
		);

		return $mapping[ $orderby ] ?? 'date';
	}
}
