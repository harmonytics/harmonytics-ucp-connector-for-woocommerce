<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * REST controller for shipping endpoints.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Shipping_Controller
 *
 * Handles shipping-related REST API endpoints for UCP.
 */
class UCP_WC_Shipping_Controller extends UCP_WC_REST_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'shipping';

	/**
	 * Shipping mapper instance.
	 *
	 * @var UCP_WC_Shipping_Mapper
	 */
	protected $shipping_mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->shipping_mapper = new UCP_WC_Shipping_Mapper();
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// POST /shipping/rates - Calculate shipping rates.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/rates',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'calculate_rates' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
					'args'                => $this->get_calculate_rates_args(),
				),
			)
		);

		// GET /shipping/zones - List shipping zones.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/zones',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_zones' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
			)
		);

		// GET /shipping/methods - List all shipping methods.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/methods',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_methods' ),
					'permission_callback' => array( $this, 'check_read_permission' ),
				),
			)
		);
	}

	/**
	 * Get arguments for calculate rates endpoint.
	 *
	 * @return array
	 */
	private function get_calculate_rates_args() {
		return array(
			'items'       => array(
				'required'    => true,
				'type'        => 'array',
				'description' => __( 'Array of items to calculate shipping for.', 'harmonytics-ucp-connector-for-woocommerce' ),
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
						'variant_id' => array(
							'type'        => 'integer',
							'description' => __( 'Variation ID for variable products.', 'harmonytics-ucp-connector-for-woocommerce' ),
						),
					),
				),
			),
			'destination' => array(
				'required'    => true,
				'type'        => 'object',
				'description' => __( 'Shipping destination address.', 'harmonytics-ucp-connector-for-woocommerce' ),
				'properties'  => array(
					'country'  => array(
						'type'        => 'string',
						'description' => __( 'Country code (ISO 3166-1 alpha-2).', 'harmonytics-ucp-connector-for-woocommerce' ),
						'required'    => true,
					),
					'state'    => array(
						'type'        => 'string',
						'description' => __( 'State/province code.', 'harmonytics-ucp-connector-for-woocommerce' ),
					),
					'postcode' => array(
						'type'        => 'string',
						'description' => __( 'Postal/ZIP code.', 'harmonytics-ucp-connector-for-woocommerce' ),
					),
					'city'     => array(
						'type'        => 'string',
						'description' => __( 'City name.', 'harmonytics-ucp-connector-for-woocommerce' ),
					),
				),
			),
		);
	}

	/**
	 * Calculate shipping rates for given items and destination.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function calculate_rates( $request ) {
		$items       = $request->get_param( 'items' );
		$destination = $request->get_param( 'destination' );

		$this->log(
			'Calculating shipping rates',
			array(
				'items'       => $items,
				'destination' => $destination,
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

		// Validate destination.
		if ( empty( $destination ) || empty( $destination['country'] ) ) {
			return $this->error_response(
				'invalid_destination',
				__( 'Destination with country is required.', 'harmonytics-ucp-connector-for-woocommerce' ),
				400
			);
		}

		// Build package contents and calculate totals.
		$contents      = array();
		$contents_cost = 0;
		$total_weight  = 0;
		$line_number   = 0;

		foreach ( $items as $item ) {
			$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			$variant_id = isset( $item['variant_id'] ) ? absint( $item['variant_id'] ) : 0;
			$quantity   = isset( $item['quantity'] ) ? max( 1, absint( $item['quantity'] ) ) : 1;

			// Get the product.
			$product_to_use_id = $variant_id > 0 ? $variant_id : $product_id;
			$product           = wc_get_product( $product_to_use_id );

			if ( ! $product ) {
				return $this->error_response(
					'product_not_found',
					sprintf(
						/* translators: %d: Product ID */
						__( 'Product with ID %d not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
						$product_to_use_id
					),
					404
				);
			}

			// Check if product is purchasable and in stock.
			if ( ! $product->is_purchasable() ) {
				return $this->error_response(
					'product_not_purchasable',
					sprintf(
						/* translators: %s: Product name */
						__( 'Product "%s" is not purchasable.', 'harmonytics-ucp-connector-for-woocommerce' ),
						$product->get_name()
					),
					400
				);
			}

			// Skip virtual products for shipping calculation.
			if ( $product->is_virtual() ) {
				continue;
			}

			$line_total = $product->get_price() * $quantity;
			$contents_cost += $line_total;

			// Add product weight.
			$weight = floatval( $product->get_weight() );
			if ( $weight > 0 ) {
				$total_weight += $weight * $quantity;
			}

			$contents[ $line_number ] = array(
				'key'               => $line_number,
				'product_id'        => $variant_id > 0 ? $product->get_parent_id() : $product_id,
				'variation_id'      => $variant_id,
				'variation'         => array(),
				'quantity'          => $quantity,
				'data'              => $product,
				'data_hash'         => wc_get_cart_item_data_hash( $product ),
				'line_tax_data'     => array(),
				'line_subtotal'     => $line_total,
				'line_subtotal_tax' => 0,
				'line_total'        => $line_total,
				'line_tax'          => 0,
			);

			$line_number++;
		}

		// If all products are virtual, return empty rates.
		if ( empty( $contents ) ) {
			return $this->success_response(
				array(
					'rates'           => array(),
					'destination'     => $this->sanitize_destination( $destination ),
					'package_details' => array(
						'weight'      => '0',
						'weight_unit' => get_option( 'woocommerce_weight_unit', 'kg' ),
						'note'        => __( 'All products are virtual and do not require shipping.', 'harmonytics-ucp-connector-for-woocommerce' ),
					),
				)
			);
		}

		// Build shipping package.
		$package = array(
			'contents'        => $contents,
			'contents_cost'   => $contents_cost,
			'applied_coupons' => array(),
			'user'            => array(
				'ID' => 0,
			),
			'destination'     => array(
				'country'   => sanitize_text_field( $destination['country'] ),
				'state'     => isset( $destination['state'] ) ? sanitize_text_field( $destination['state'] ) : '',
				'postcode'  => isset( $destination['postcode'] ) ? sanitize_text_field( $destination['postcode'] ) : '',
				'city'      => isset( $destination['city'] ) ? sanitize_text_field( $destination['city'] ) : '',
				'address'   => '',
				'address_1' => '',
				'address_2' => '',
			),
			'cart_subtotal'   => $contents_cost,
		);

		// Get shipping zone for this package.
		$shipping_zone = WC_Shipping_Zones::get_zone_matching_package( $package );

		// Get available shipping methods for this zone.
		$shipping_methods = $shipping_zone->get_shipping_methods( true );

		// Calculate rates.
		$available_rates = array();

		foreach ( $shipping_methods as $method ) {
			if ( ! $method->is_enabled() ) {
				continue;
			}

			// Get rates from this method.
			$rates = $method->get_rates_for_package( $package );

			foreach ( $rates as $rate ) {
				$available_rates[] = $this->shipping_mapper->map_shipping_rate_for_api( $rate, $method );
			}
		}

		// Build response.
		$response = array(
			'rates'           => $available_rates,
			'destination'     => $this->sanitize_destination( $destination ),
			'package_details' => array(
				'weight'      => (string) $total_weight,
				'weight_unit' => get_option( 'woocommerce_weight_unit', 'kg' ),
			),
		);

		return $this->success_response( $response );
	}

	/**
	 * List all shipping zones.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_zones( $request ) {
		$this->log( 'Listing shipping zones' );

		$zones        = WC_Shipping_Zones::get_zones();
		$mapped_zones = array();

		// Add the "Rest of the World" zone.
		$rest_of_world_zone = new WC_Shipping_Zone( 0 );
		$mapped_zones[]     = $this->shipping_mapper->map_shipping_zone( $rest_of_world_zone );

		// Add all other zones.
		foreach ( $zones as $zone_data ) {
			$zone           = new WC_Shipping_Zone( $zone_data['id'] );
			$mapped_zones[] = $this->shipping_mapper->map_shipping_zone( $zone );
		}

		return $this->success_response(
			array(
				'zones' => $mapped_zones,
				'total' => count( $mapped_zones ),
			)
		);
	}

	/**
	 * List all available shipping methods.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_methods( $request ) {
		$this->log( 'Listing shipping methods' );

		$shipping        = WC()->shipping();
		$methods         = $shipping->get_shipping_methods();
		$mapped_methods  = array();

		foreach ( $methods as $method_id => $method ) {
			$mapped_methods[] = $this->shipping_mapper->map_shipping_method_info( $method );
		}

		return $this->success_response(
			array(
				'methods' => $mapped_methods,
				'total'   => count( $mapped_methods ),
			)
		);
	}

	/**
	 * Sanitize destination data for response.
	 *
	 * @param array $destination Destination data.
	 * @return array
	 */
	private function sanitize_destination( $destination ) {
		return array(
			'country'  => isset( $destination['country'] ) ? sanitize_text_field( $destination['country'] ) : '',
			'state'    => isset( $destination['state'] ) ? sanitize_text_field( $destination['state'] ) : '',
			'postcode' => isset( $destination['postcode'] ) ? sanitize_text_field( $destination['postcode'] ) : '',
			'city'     => isset( $destination['city'] ) ? sanitize_text_field( $destination['city'] ) : '',
		);
	}
}
