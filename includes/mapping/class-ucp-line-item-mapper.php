<?php
/**
 * Line item mapper for UCP schema conversion.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Line_Item_Mapper
 *
 * Maps WooCommerce order items to UCP line item schema.
 */
class UCP_WC_Line_Item_Mapper {

	/**
	 * Map order items to UCP format.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	public function map_order_items( $order ) {
		$items = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$items[] = $this->map_item( $item );
		}

		return $items;
	}

	/**
	 * Map a single order item.
	 *
	 * @param WC_Order_Item_Product $item Order item.
	 * @return array
	 */
	public function map_item( $item ) {
		$product = $item->get_product();

		$mapped = array(
			'id'         => $item->get_id(),
			'product_id' => $item->get_product_id(),
			'variant_id' => $item->get_variation_id() ? $item->get_variation_id() : null,
			'sku'        => $product ? $product->get_sku() : null,
			'name'       => $item->get_name(),
			'quantity'   => $item->get_quantity(),
			'unit_price' => floatval( $item->get_subtotal() / max( 1, $item->get_quantity() ) ),
			'subtotal'   => floatval( $item->get_subtotal() ),
			'total'      => floatval( $item->get_total() ),
			'tax'        => floatval( $item->get_total_tax() ),
		);

		// Add product details if available.
		if ( $product ) {
			$mapped['product'] = array(
				'id'        => $product->get_id(),
				'name'      => $product->get_name(),
				'sku'       => $product->get_sku(),
				'type'      => $product->get_type(),
				'weight'    => $product->get_weight(),
				'image_url' => $this->get_product_image_url( $product ),
				'url'       => get_permalink( $product->get_id() ),
			);

			// Add variation attributes if applicable.
			if ( $product->is_type( 'variation' ) ) {
				$mapped['attributes'] = $this->map_variation_attributes( $product );
			}
		}

		return $mapped;
	}

	/**
	 * Map cart items (before order creation).
	 *
	 * @param array $cart_items Cart items array.
	 * @return array
	 */
	public function map_cart_items( $cart_items ) {
		$items = array();

		foreach ( $cart_items as $cart_key => $cart_item ) {
			$product = $cart_item['data'];

			$items[] = array(
				'cart_key'   => $cart_key,
				'product_id' => $cart_item['product_id'],
				'variant_id' => ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : null,
				'sku'        => $product->get_sku(),
				'name'       => $product->get_name(),
				'quantity'   => $cart_item['quantity'],
				'unit_price' => floatval( $product->get_price() ),
				'subtotal'   => floatval( $cart_item['line_subtotal'] ),
				'total'      => floatval( $cart_item['line_total'] ),
				'tax'        => floatval( $cart_item['line_tax'] ),
				'image_url'  => $this->get_product_image_url( $product ),
			);
		}

		return $items;
	}

	/**
	 * Get product image URL.
	 *
	 * @param WC_Product $product Product object.
	 * @return string|null
	 */
	private function get_product_image_url( $product ) {
		$image_id = $product->get_image_id();

		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
			return $image_url ? $image_url : null;
		}

		// Try parent product for variations.
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				return $this->get_product_image_url( $parent );
			}
		}

		return wc_placeholder_img_src( 'woocommerce_thumbnail' );
	}

	/**
	 * Map variation attributes.
	 *
	 * @param WC_Product_Variation $variation Variation product.
	 * @return array
	 */
	private function map_variation_attributes( $variation ) {
		$attributes = array();

		foreach ( $variation->get_attributes() as $attribute_name => $attribute_value ) {
			// Get clean attribute name.
			$name = wc_attribute_label( str_replace( 'pa_', '', $attribute_name ) );

			$attributes[] = array(
				'name'  => $name,
				'value' => $attribute_value,
			);
		}

		return $attributes;
	}
}
