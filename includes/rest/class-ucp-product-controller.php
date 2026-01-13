<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * REST controller for product catalog endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Product_Controller
 *
 * Handles product catalog REST API endpoints for UCP.
 */
class UCP_WC_Product_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'products';

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
		$this->product_mapper = new UCP_WC_Product_Mapper();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /products - List products
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_products' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => $this->get_list_products_args(),
				),
			)
		);

		// GET /products/search - Search products
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/search',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_products' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => $this->get_search_products_args(),
				),
			)
		);

		// GET /products/{product_id} - Get single product
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<product_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => array(
						'product_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Product ID.', 'ucp-for-woocommerce' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Get arguments for list products endpoint.
	 *
	 * @return array
	 */
	private function get_list_products_args() {
		return array(
			'page'         => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'description'       => __( 'Page number.', 'ucp-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'per_page'     => array(
				'required'          => false,
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'description'       => __( 'Items per page.', 'ucp-for-woocommerce' ),
				'sanitize_callback' => 'absint',
			),
			'category'     => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Filter by category slug or ID.', 'ucp-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'tag'          => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Filter by tag slug or ID.', 'ucp-for-woocommerce' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'       => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'publish', 'draft', 'pending', 'any' ),
				'default'     => 'publish',
				'description' => __( 'Filter by product status.', 'ucp-for-woocommerce' ),
			),
			'type'         => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'simple', 'variable', 'grouped', 'external', 'any' ),
				'default'     => 'any',
				'description' => __( 'Filter by product type.', 'ucp-for-woocommerce' ),
			),
			'featured'     => array(
				'required'    => false,
				'type'        => 'boolean',
				'description' => __( 'Filter by featured status.', 'ucp-for-woocommerce' ),
			),
			'on_sale'      => array(
				'required'    => false,
				'type'        => 'boolean',
				'description' => __( 'Filter by on sale status.', 'ucp-for-woocommerce' ),
			),
			'in_stock'     => array(
				'required'    => false,
				'type'        => 'boolean',
				'description' => __( 'Filter by stock status.', 'ucp-for-woocommerce' ),
			),
			'min_price'    => array(
				'required'          => false,
				'type'              => 'number',
				'description'       => __( 'Minimum price filter.', 'ucp-for-woocommerce' ),
				'sanitize_callback' => 'floatval',
			),
			'max_price'    => array(
				'required'          => false,
				'type'              => 'number',
				'description'       => __( 'Maximum price filter.', 'ucp-for-woocommerce' ),
				'sanitize_callback' => 'floatval',
			),
			'orderby'      => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'date', 'id', 'title', 'price', 'popularity', 'rating', 'menu_order' ),
				'default'     => 'date',
				'description' => __( 'Sort collection by attribute.', 'ucp-for-woocommerce' ),
			),
			'order'        => array(
				'required'    => false,
				'type'        => 'string',
				'enum'        => array( 'asc', 'desc' ),
				'default'     => 'desc',
				'description' => __( 'Order sort direction.', 'ucp-for-woocommerce' ),
			),
		);
	}

	/**
	 * Get arguments for search products endpoint.
	 *
	 * @return array
	 */
	private function get_search_products_args() {
		$args = $this->get_list_products_args();

		// Add search-specific parameter
		$args['q'] = array(
			'required'          => true,
			'type'              => 'string',
			'description'       => __( 'Search query string.', 'ucp-for-woocommerce' ),
			'sanitize_callback' => 'sanitize_text_field',
		);

		return $args;
	}

	/**
	 * List products.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_products( $request ) {
		$this->log( 'Listing products', array( 'params' => $request->get_params() ) );

		$args = $this->build_query_args( $request );

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

		$per_page    = $request->get_param( 'per_page' ) ?: 10;
		$total_pages = ceil( $total / $per_page );

		// Map products
		$mapped_products = array();
		foreach ( $products as $product ) {
			$mapped_products[] = $this->product_mapper->map_product_summary( $product );
		}

		$result = array(
			'products'    => $mapped_products,
			'total'       => $total,
			'page'        => $request->get_param( 'page' ) ?: 1,
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
	 * Search products.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function search_products( $request ) {
		$search_query = $request->get_param( 'q' );

		$this->log( 'Searching products', array( 'query' => $search_query, 'params' => $request->get_params() ) );

		if ( empty( $search_query ) ) {
			return $this->error_response(
				'invalid_search_query',
				__( 'Search query is required.', 'ucp-for-woocommerce' ),
				400
			);
		}

		// Build query args with search
		$args     = $this->build_query_args( $request );
		$args['s'] = $search_query;

		// Use WP_Query for search as WC_Product_Query has limited search support
		$wp_args = array(
			'post_type'      => 'product',
			'post_status'    => $args['status'] ?? 'publish',
			's'              => $search_query,
			'posts_per_page' => $args['limit'] ?? 10,
			'paged'          => $request->get_param( 'page' ) ?: 1,
			'orderby'        => $this->map_orderby( $request->get_param( 'orderby' ) ?: 'relevance' ),
			'order'          => strtoupper( $request->get_param( 'order' ) ?: 'DESC' ),
		);

		// Add tax query for categories/tags
		$tax_query = array();

		$category = $request->get_param( 'category' );
		if ( ! empty( $category ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
				'terms'    => $category,
			);
		}

		$tag = $request->get_param( 'tag' );
		if ( ! empty( $tag ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_tag',
				'field'    => is_numeric( $tag ) ? 'term_id' : 'slug',
				'terms'    => $tag,
			);
		}

		// Add product visibility
		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => array( 'exclude-from-search', 'exclude-from-catalog' ),
			'operator' => 'NOT IN',
		);

		if ( ! empty( $tax_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for filtering products by category and visibility.
			$wp_args['tax_query'] = $tax_query;
		}

		// Add meta query for price filtering
		$meta_query = array();

		$min_price = $request->get_param( 'min_price' );
		if ( ! is_null( $min_price ) ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => $min_price,
				'compare' => '>=',
				'type'    => 'DECIMAL',
			);
		}

		$max_price = $request->get_param( 'max_price' );
		if ( ! is_null( $max_price ) ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => $max_price,
				'compare' => '<=',
				'type'    => 'DECIMAL',
			);
		}

		// Add stock filter
		$in_stock = $request->get_param( 'in_stock' );
		if ( $in_stock === true ) {
			$meta_query[] = array(
				'key'     => '_stock_status',
				'value'   => 'instock',
				'compare' => '=',
			);
		}

		if ( ! empty( $meta_query ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for filtering products by price and stock status.
			$wp_args['meta_query'] = $meta_query;
		}

		$query       = new WP_Query( $wp_args );
		$total       = $query->found_posts;
		$per_page    = $wp_args['posts_per_page'];
		$total_pages = $query->max_num_pages;

		// Map products
		$mapped_products = array();
		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$mapped_products[] = $this->product_mapper->map_product_summary( $product );
			}
		}

		$result = array(
			'query'       => $search_query,
			'products'    => $mapped_products,
			'total'       => $total,
			'page'        => $request->get_param( 'page' ) ?: 1,
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
	 * Get single product.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_product( $request ) {
		$product_id = $request->get_param( 'product_id' );

		$this->log( 'Getting product', array( 'product_id' => $product_id ) );

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return $this->error_response(
				'product_not_found',
				__( 'Product not found.', 'ucp-for-woocommerce' ),
				404
			);
		}

		// Check if product is published (or accessible)
		if ( 'publish' !== $product->get_status() ) {
			return $this->error_response(
				'product_not_accessible',
				__( 'Product is not accessible.', 'ucp-for-woocommerce' ),
				404
			);
		}

		$mapped_product = $this->product_mapper->map_product( $product );

		return $this->success_response( $mapped_product );
	}

	/**
	 * Build WC_Product_Query arguments from request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array
	 */
	private function build_query_args( $request ) {
		$page     = $request->get_param( 'page' ) ?: 1;
		$per_page = $request->get_param( 'per_page' ) ?: 10;

		$args = array(
			'status'  => $request->get_param( 'status' ) ?: 'publish',
			'limit'   => $per_page,
			'page'    => $page,
			'orderby' => $this->map_orderby( $request->get_param( 'orderby' ) ?: 'date' ),
			'order'   => strtoupper( $request->get_param( 'order' ) ?: 'DESC' ),
		);

		// Product type filter
		$type = $request->get_param( 'type' );
		if ( ! empty( $type ) && 'any' !== $type ) {
			$args['type'] = $type;
		}

		// Category filter
		$category = $request->get_param( 'category' );
		if ( ! empty( $category ) ) {
			$args['category'] = is_numeric( $category ) ? array( $category ) : array( $category );
		}

		// Tag filter
		$tag = $request->get_param( 'tag' );
		if ( ! empty( $tag ) ) {
			$args['tag'] = is_numeric( $tag ) ? array( $tag ) : array( $tag );
		}

		// Featured filter
		$featured = $request->get_param( 'featured' );
		if ( $featured === true ) {
			$args['featured'] = true;
		}

		// On sale filter
		$on_sale = $request->get_param( 'on_sale' );
		if ( $on_sale === true ) {
			$args['include'] = wc_get_product_ids_on_sale();
		}

		// Stock filter
		$in_stock = $request->get_param( 'in_stock' );
		if ( $in_stock === true ) {
			$args['stock_status'] = 'instock';
		} elseif ( $in_stock === false ) {
			$args['stock_status'] = 'outofstock';
		}

		// Price range filters
		$min_price = $request->get_param( 'min_price' );
		if ( ! is_null( $min_price ) ) {
			$args['min_price'] = $min_price;
		}

		$max_price = $request->get_param( 'max_price' );
		if ( ! is_null( $max_price ) ) {
			$args['max_price'] = $max_price;
		}

		// Only show visible products in catalog
		$args['visibility'] = 'visible';

		return $args;
	}

	/**
	 * Map orderby parameter to WC_Product_Query format.
	 *
	 * @param string $orderby Orderby parameter.
	 * @return string
	 */
	private function map_orderby( $orderby ) {
		$mapping = array(
			'date'       => 'date',
			'id'         => 'ID',
			'title'      => 'title',
			'price'      => 'price',
			'popularity' => 'popularity',
			'rating'     => 'rating',
			'menu_order' => 'menu_order',
			'relevance'  => 'relevance',
		);

		return $mapping[ $orderby ] ?? 'date';
	}
}
