<?php
/**
 * Cart capability handler.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Cart
 *
 * Handles cart creation, management, and conversion to checkout sessions.
 */
class UCP_WC_Cart {

	/**
	 * Database table name for UCP carts.
	 *
	 * @var string
	 */
	const CARTS_TABLE = 'ucp_carts';

	/**
	 * Default cart expiration in seconds (7 days).
	 *
	 * @var int
	 */
	const DEFAULT_EXPIRATION = 604800;

	/**
	 * Maximum items allowed per cart.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 100;

	/**
	 * Line item mapper.
	 *
	 * @var UCP_WC_Line_Item_Mapper
	 */
	protected $line_item_mapper;

	/**
	 * Product mapper.
	 *
	 * @var UCP_WC_Product_Mapper
	 */
	protected $product_mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->line_item_mapper = new UCP_WC_Line_Item_Mapper();
		$this->product_mapper   = new UCP_WC_Product_Mapper();
	}

	/**
	 * Create a new cart.
	 *
	 * @param array|null $metadata Optional metadata for the cart.
	 * @return array|WP_Error
	 */
	public function create_cart( $metadata = null ) {
		global $wpdb;

		$cart_id    = $this->generate_cart_id();
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::DEFAULT_EXPIRATION );

		$cart_data = array(
			'cart_id'    => $cart_id,
			'items'      => wp_json_encode( array() ),
			'metadata'   => $metadata ? wp_json_encode( $metadata ) : null,
			'status'     => 'active',
			'created_at' => current_time( 'mysql', true ),
			'updated_at' => current_time( 'mysql', true ),
			'expires_at' => $expires_at,
		);

		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for UCP carts, no WP API available.
		$result = $wpdb->insert( $table_name, $cart_data );

		if ( false === $result ) {
			return new WP_Error(
				'cart_creation_failed',
				__( 'Failed to create cart.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'cart_id'    => $cart_id,
			'items'      => array(),
			'totals'     => $this->calculate_empty_totals(),
			'item_count' => 0,
			'status'     => 'active',
			'metadata'   => $metadata,
			'created_at' => $cart_data['created_at'],
			'updated_at' => $cart_data['updated_at'],
			'expires_at' => $expires_at,
		);
	}

	/**
	 * Get cart by ID.
	 *
	 * @param string $cart_id Cart ID.
	 * @return array|WP_Error
	 */
	public function get_cart( $cart_id ) {
		global $wpdb;

		// Table name from trusted internal source (prefix + constant).
		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table for UCP carts, table name from trusted internal source.
		$cart = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted internal source.
				"SELECT * FROM {$table_name} WHERE cart_id = %s",
				$cart_id
			),
			ARRAY_A
		);

		if ( ! $cart ) {
			return new WP_Error(
				'cart_not_found',
				__( 'Cart not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 404 )
			);
		}

		// Check if cart has expired.
		if ( strtotime( $cart['expires_at'] ) < time() ) {
			return new WP_Error(
				'cart_expired',
				__( 'Cart has expired.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 410 )
			);
		}

		// Check if cart was converted to checkout.
		if ( 'converted' === $cart['status'] ) {
			return new WP_Error(
				'cart_converted',
				__( 'Cart has been converted to a checkout session.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array(
					'status'     => 410,
					'session_id' => $cart['checkout_session_id'],
				)
			);
		}

		$items     = json_decode( $cart['items'], true ) ? json_decode( $cart['items'], true ) : array();
		$item_data = $this->get_items_with_product_data( $items );
		$totals    = $this->calculate_totals( $item_data );
		$metadata  = $cart['metadata'] ? json_decode( $cart['metadata'], true ) : null;

		return array(
			'cart_id'             => $cart_id,
			'items'               => $item_data,
			'totals'              => $totals,
			'item_count'          => count( $items ),
			'status'              => $cart['status'],
			'metadata'            => $metadata,
			'checkout_session_id' => $cart['checkout_session_id'],
			'created_at'          => $cart['created_at'],
			'updated_at'          => $cart['updated_at'],
			'expires_at'          => $cart['expires_at'],
		);
	}

	/**
	 * Add item to cart.
	 *
	 * @param string $cart_id Cart ID.
	 * @param array  $item    Item data (product_id, variant_id, sku, quantity).
	 * @return array|WP_Error
	 */
	public function add_item( $cart_id, $item ) {
		global $wpdb;

		// Get cart.
		$cart_result = $this->get_cart_raw( $cart_id );
		if ( is_wp_error( $cart_result ) ) {
			return $cart_result;
		}

		$cart  = $cart_result;
		$items = json_decode( $cart['items'], true ) ? json_decode( $cart['items'], true ) : array();

		// Check max items.
		if ( count( $items ) >= self::MAX_ITEMS ) {
			return new WP_Error(
				'cart_full',
				sprintf(
					/* translators: %d: Maximum number of items allowed in cart */
					__( 'Cart cannot contain more than %d items.', 'harmonytics-ucp-connector-for-woocommerce' ),
					self::MAX_ITEMS
				),
				array( 'status' => 400 )
			);
		}

		// Find product.
		$product = $this->find_product( $item );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		// Validate product.
		$validation = $this->validate_product_for_cart( $product, $item );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
		$item_key = $this->generate_item_key( $product );

		// Check if item already exists in cart.
		$existing_index = $this->find_item_index( $items, $item_key );
		if ( false !== $existing_index ) {
			// Update existing item quantity.
			$new_quantity = $items[ $existing_index ]['quantity'] + $quantity;

			// Check stock for combined quantity.
			if ( ! $product->has_enough_stock( $new_quantity ) ) {
				return new WP_Error(
					'insufficient_stock',
					/* translators: %s: Product name */
					sprintf( __( 'Insufficient stock for: %s', 'harmonytics-ucp-connector-for-woocommerce' ), $product->get_name() ),
					array( 'status' => 400 )
				);
			}

			$items[ $existing_index ]['quantity'] = $new_quantity;
		} else {
			// Add new item.
			$items[] = array(
				'item_key'   => $item_key,
				'product_id' => $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(),
				'variant_id' => $product->get_parent_id() ? $product->get_id() : null,
				'sku'        => $product->get_sku(),
				'quantity'   => $quantity,
				'added_at'   => current_time( 'mysql', true ),
			);
		}

		// Update cart.
		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP carts, update operation.
		$wpdb->update(
			$table_name,
			array(
				'items'      => wp_json_encode( $items ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'cart_id' => $cart_id )
		);

		return $this->get_cart( $cart_id );
	}

	/**
	 * Update item quantity in cart.
	 *
	 * @param string $cart_id  Cart ID.
	 * @param string $item_key Item key.
	 * @param int    $quantity New quantity.
	 * @return array|WP_Error
	 */
	public function update_item( $cart_id, $item_key, $quantity ) {
		global $wpdb;

		// Get cart.
		$cart_result = $this->get_cart_raw( $cart_id );
		if ( is_wp_error( $cart_result ) ) {
			return $cart_result;
		}

		$cart  = $cart_result;
		$items = json_decode( $cart['items'], true ) ? json_decode( $cart['items'], true ) : array();

		// Find item.
		$item_index = $this->find_item_index( $items, $item_key );
		if ( false === $item_index ) {
			return new WP_Error(
				'item_not_found',
				__( 'Item not found in cart.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 404 )
			);
		}

		$quantity = absint( $quantity );

		// If quantity is 0, remove item.
		if ( 0 === $quantity ) {
			return $this->remove_item( $cart_id, $item_key );
		}

		// Get product to check stock.
		$product_id = $items[ $item_index ]['variant_id'] ? $items[ $item_index ]['variant_id'] : $items[ $item_index ]['product_id'];
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product no longer exists.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 404 )
			);
		}

		// Check stock.
		if ( ! $product->has_enough_stock( $quantity ) ) {
			return new WP_Error(
				'insufficient_stock',
				/* translators: %s: Product name */
				sprintf( __( 'Insufficient stock for: %s', 'harmonytics-ucp-connector-for-woocommerce' ), $product->get_name() ),
				array( 'status' => 400 )
			);
		}

		// Update quantity.
		$items[ $item_index ]['quantity'] = $quantity;

		// Update cart.
		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP carts, update operation.
		$wpdb->update(
			$table_name,
			array(
				'items'      => wp_json_encode( $items ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'cart_id' => $cart_id )
		);

		return $this->get_cart( $cart_id );
	}

	/**
	 * Remove item from cart.
	 *
	 * @param string $cart_id  Cart ID.
	 * @param string $item_key Item key.
	 * @return array|WP_Error
	 */
	public function remove_item( $cart_id, $item_key ) {
		global $wpdb;

		// Get cart.
		$cart_result = $this->get_cart_raw( $cart_id );
		if ( is_wp_error( $cart_result ) ) {
			return $cart_result;
		}

		$cart  = $cart_result;
		$items = json_decode( $cart['items'], true ) ? json_decode( $cart['items'], true ) : array();

		// Find and remove item.
		$item_index = $this->find_item_index( $items, $item_key );
		if ( false === $item_index ) {
			return new WP_Error(
				'item_not_found',
				__( 'Item not found in cart.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 404 )
			);
		}

		array_splice( $items, $item_index, 1 );

		// Update cart.
		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP carts, update operation.
		$wpdb->update(
			$table_name,
			array(
				'items'      => wp_json_encode( $items ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'cart_id' => $cart_id )
		);

		return $this->get_cart( $cart_id );
	}

	/**
	 * Clear all items from cart.
	 *
	 * @param string $cart_id Cart ID.
	 * @return array|WP_Error
	 */
	public function clear_cart( $cart_id ) {
		global $wpdb;

		// Get cart to verify it exists.
		$cart_result = $this->get_cart_raw( $cart_id );
		if ( is_wp_error( $cart_result ) ) {
			return $cart_result;
		}

		// Clear items.
		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP carts, update operation.
		$wpdb->update(
			$table_name,
			array(
				'items'      => wp_json_encode( array() ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'cart_id' => $cart_id )
		);

		return $this->get_cart( $cart_id );
	}

	/**
	 * Delete cart entirely.
	 *
	 * @param string $cart_id Cart ID.
	 * @return array|WP_Error
	 */
	public function delete_cart( $cart_id ) {
		global $wpdb;

		// Get cart to verify it exists.
		$cart_result = $this->get_cart_raw( $cart_id );
		if ( is_wp_error( $cart_result ) ) {
			return $cart_result;
		}

		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP carts, delete operation.
		$result = $wpdb->delete(
			$table_name,
			array( 'cart_id' => $cart_id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'cart_delete_failed',
				__( 'Failed to delete cart.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 500 )
			);
		}

		return array(
			'cart_id' => $cart_id,
			'deleted' => true,
			'message' => __( 'Cart has been deleted.', 'harmonytics-ucp-connector-for-woocommerce' ),
		);
	}

	/**
	 * Convert cart to checkout session.
	 *
	 * @param string      $cart_id          Cart ID.
	 * @param array|null  $shipping_address Shipping address.
	 * @param array|null  $billing_address  Billing address.
	 * @param string|null $coupon_code     Coupon code.
	 * @param string|null $customer_note   Customer note.
	 * @return array|WP_Error
	 */
	public function convert_to_checkout( $cart_id, $shipping_address = null, $billing_address = null, $coupon_code = null, $customer_note = null ) {
		global $wpdb;

		// Get cart.
		$cart_result = $this->get_cart_raw( $cart_id );
		if ( is_wp_error( $cart_result ) ) {
			return $cart_result;
		}

		$cart  = $cart_result;
		$items = json_decode( $cart['items'], true ) ? json_decode( $cart['items'], true ) : array();

		// Check if cart has items.
		if ( empty( $items ) ) {
			return new WP_Error(
				'cart_empty',
				__( 'Cannot checkout with an empty cart.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		// Convert cart items to checkout format.
		$checkout_items = array();
		foreach ( $items as $item ) {
			$checkout_item = array(
				'quantity' => $item['quantity'],
			);

			if ( ! empty( $item['variant_id'] ) ) {
				$checkout_item['variant_id'] = $item['variant_id'];
			} elseif ( ! empty( $item['sku'] ) ) {
				$checkout_item['sku'] = $item['sku'];
			} else {
				$checkout_item['product_id'] = $item['product_id'];
			}

			$checkout_items[] = $checkout_item;
		}

		// Create checkout session using the checkout capability.
		$checkout = new UCP_WC_Checkout();
		$result   = $checkout->create_session(
			$checkout_items,
			$shipping_address,
			$billing_address,
			$coupon_code,
			$customer_note
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Mark cart as converted.
		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for UCP carts, update operation.
		$wpdb->update(
			$table_name,
			array(
				'status'              => 'converted',
				'checkout_session_id' => $result['session_id'],
				'updated_at'          => current_time( 'mysql', true ),
			),
			array( 'cart_id' => $cart_id )
		);

		return array(
			'cart_id'          => $cart_id,
			'converted'        => true,
			'checkout_session' => $result,
		);
	}

	/**
	 * Get cart raw data from database.
	 *
	 * @param string $cart_id Cart ID.
	 * @return array|WP_Error
	 */
	private function get_cart_raw( $cart_id ) {
		global $wpdb;

		// Table name from trusted internal source (prefix + constant).
		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table for UCP carts, table name from trusted internal source.
		$cart = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted internal source.
				"SELECT * FROM {$table_name} WHERE cart_id = %s",
				$cart_id
			),
			ARRAY_A
		);

		if ( ! $cart ) {
			return new WP_Error(
				'cart_not_found',
				__( 'Cart not found.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 404 )
			);
		}

		// Check if cart has expired.
		if ( strtotime( $cart['expires_at'] ) < time() ) {
			return new WP_Error(
				'cart_expired',
				__( 'Cart has expired.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array( 'status' => 410 )
			);
		}

		// Check if cart was converted to checkout.
		if ( 'converted' === $cart['status'] ) {
			return new WP_Error(
				'cart_converted',
				__( 'Cart has been converted to a checkout session.', 'harmonytics-ucp-connector-for-woocommerce' ),
				array(
					'status'     => 410,
					'session_id' => $cart['checkout_session_id'],
				)
			);
		}

		return $cart;
	}

	/**
	 * Generate a unique cart ID.
	 *
	 * @return string
	 */
	private function generate_cart_id() {
		return 'cart_' . bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Generate item key from product.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	private function generate_item_key( $product ) {
		$product_id = $product->get_id();
		$parent_id  = $product->get_parent_id();

		if ( $parent_id ) {
			return 'item_' . $parent_id . '_' . $product_id;
		}

		return 'item_' . $product_id;
	}

	/**
	 * Find item index in items array.
	 *
	 * @param array  $items    Items array.
	 * @param string $item_key Item key to find.
	 * @return int|false
	 */
	private function find_item_index( $items, $item_key ) {
		foreach ( $items as $index => $item ) {
			if ( $item['item_key'] === $item_key ) {
				return $index;
			}
		}
		return false;
	}

	/**
	 * Find product by item data.
	 *
	 * @param array $item Item data.
	 * @return WC_Product|WP_Error
	 */
	private function find_product( $item ) {
		$product = null;

		// Find product by SKU, product_id, or variant_id.
		if ( ! empty( $item['sku'] ) ) {
			$product_id = wc_get_product_id_by_sku( $item['sku'] );
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
			}
		} elseif ( ! empty( $item['variant_id'] ) ) {
			$product = wc_get_product( $item['variant_id'] );
		} elseif ( ! empty( $item['product_id'] ) ) {
			$product = wc_get_product( $item['product_id'] );
		}

		if ( ! $product ) {
			return new WP_Error(
				'product_not_found',
				sprintf(
					/* translators: %s: Product identifier (SKU, product ID, or variant ID) */
					__( 'Product not found: %s', 'harmonytics-ucp-connector-for-woocommerce' ),
					$item['sku'] ?? $item['product_id'] ?? $item['variant_id'] ?? 'unknown'
				),
				array( 'status' => 404 )
			);
		}

		return $product;
	}

	/**
	 * Validate product can be added to cart.
	 *
	 * @param WC_Product $product Product object.
	 * @param array      $item    Item data.
	 * @return true|WP_Error
	 */
	private function validate_product_for_cart( $product, $item ) {
		if ( ! $product->is_purchasable() ) {
			return new WP_Error(
				'product_not_purchasable',
				/* translators: %s: Product name */
				sprintf( __( 'Product is not purchasable: %s', 'harmonytics-ucp-connector-for-woocommerce' ), $product->get_name() ),
				array( 'status' => 400 )
			);
		}

		$quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;

		if ( ! $product->is_in_stock() ) {
			return new WP_Error(
				'product_out_of_stock',
				/* translators: %s: Product name */
				sprintf( __( 'Product is out of stock: %s', 'harmonytics-ucp-connector-for-woocommerce' ), $product->get_name() ),
				array( 'status' => 400 )
			);
		}

		if ( ! $product->has_enough_stock( $quantity ) ) {
			return new WP_Error(
				'insufficient_stock',
				/* translators: %s: Product name */
				sprintf( __( 'Insufficient stock for: %s', 'harmonytics-ucp-connector-for-woocommerce' ), $product->get_name() ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Get items with full product data.
	 *
	 * @param array $items Raw items array.
	 * @return array
	 */
	private function get_items_with_product_data( $items ) {
		$result = array();

		foreach ( $items as $item ) {
			$product_id = ! empty( $item['variant_id'] ) ? $item['variant_id'] : $item['product_id'];
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$item_data = array(
				'item_key'   => $item['item_key'],
				'product_id' => $item['product_id'],
				'variant_id' => $item['variant_id'],
				'sku'        => $product->get_sku(),
				'name'       => $product->get_name(),
				'quantity'   => $item['quantity'],
				'price'      => (float) $product->get_price(),
				'line_total' => (float) $product->get_price() * $item['quantity'],
				'image'      => $this->get_product_image( $product ),
				'in_stock'   => $product->is_in_stock(),
				'added_at'   => $item['added_at'],
			);

			// Add variation attributes if applicable.
			if ( $product->is_type( 'variation' ) ) {
				$item_data['attributes'] = $product->get_variation_attributes();
			}

			$result[] = $item_data;
		}

		return $result;
	}

	/**
	 * Get product image URL.
	 *
	 * @param WC_Product $product Product object.
	 * @return string|null
	 */
	private function get_product_image( $product ) {
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			return wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
		}
		return null;
	}

	/**
	 * Calculate totals for cart items.
	 *
	 * @param array $items Items with product data.
	 * @return array
	 */
	private function calculate_totals( $items ) {
		$subtotal = 0;

		foreach ( $items as $item ) {
			$subtotal += $item['line_total'];
		}

		return array(
			'subtotal'           => $subtotal,
			'currency'           => get_woocommerce_currency(),
			'currency_symbol'    => get_woocommerce_currency_symbol(),
			'prices_include_tax' => wc_prices_include_tax(),
			'note'               => __( 'Tax and shipping calculated at checkout.', 'harmonytics-ucp-connector-for-woocommerce' ),
		);
	}

	/**
	 * Calculate empty totals.
	 *
	 * @return array
	 */
	private function calculate_empty_totals() {
		return array(
			'subtotal'           => 0,
			'currency'           => get_woocommerce_currency(),
			'currency_symbol'    => get_woocommerce_currency_symbol(),
			'prices_include_tax' => wc_prices_include_tax(),
			'note'               => __( 'Tax and shipping calculated at checkout.', 'harmonytics-ucp-connector-for-woocommerce' ),
		);
	}

	/**
	 * Get the carts table name.
	 *
	 * @return string
	 */
	public static function get_carts_table() {
		global $wpdb;
		return $wpdb->prefix . self::CARTS_TABLE;
	}

	/**
	 * Clean up expired carts.
	 *
	 * @return int Number of deleted carts.
	 */
	public static function cleanup_expired_carts() {
		global $wpdb;

		// Table name from trusted internal source (prefix + constant).
		$table_name = self::get_carts_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table for UCP carts, cleanup operation.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from trusted internal source.
				"DELETE FROM {$table_name} WHERE expires_at < %s",
				current_time( 'mysql', true )
			)
		);

		return $deleted ? $deleted : 0;
	}
}
