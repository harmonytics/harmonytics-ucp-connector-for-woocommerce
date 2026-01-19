<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Coupon mapper for UCP schema conversion.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Coupon_Mapper
 *
 * Maps WooCommerce coupons to UCP coupon schema.
 */
class UCP_WC_Coupon_Mapper {

	/**
	 * Map a WooCommerce coupon to UCP validation response format.
	 *
	 * @param WC_Coupon $coupon Coupon object.
	 * @param bool      $valid  Whether the coupon is valid.
	 * @return array
	 */
	public function map_coupon_validation( $coupon, $valid = true ) {
		return array(
			'valid'               => $valid,
			'code'                => $coupon->get_code(),
			'discount_type'       => $this->map_discount_type( $coupon->get_discount_type() ),
			'amount'              => $coupon->get_amount(),
			'description'         => $this->get_coupon_description( $coupon ),
			'minimum_spend'       => $coupon->get_minimum_amount() ? wc_format_decimal( $coupon->get_minimum_amount(), 2 ) : null,
			'maximum_spend'       => $coupon->get_maximum_amount() ? wc_format_decimal( $coupon->get_maximum_amount(), 2 ) : null,
			'usage_limit'         => $coupon->get_usage_limit() ? $coupon->get_usage_limit() : null,
			'usage_count'         => $coupon->get_usage_count(),
			'expiry_date'         => $this->format_expiry_date( $coupon ),
			'applicable_products' => $coupon->get_product_ids(),
			'excluded_products'   => $coupon->get_excluded_product_ids(),
			'free_shipping'       => $coupon->get_free_shipping(),
		);
	}

	/**
	 * Map coupon for public listing (active promotions).
	 *
	 * @param WC_Coupon $coupon Coupon object.
	 * @return array
	 */
	public function map_coupon_public( $coupon ) {
		return array(
			'code'          => $coupon->get_code(),
			'discount_type' => $this->map_discount_type( $coupon->get_discount_type() ),
			'amount'        => $coupon->get_amount(),
			'description'   => $this->get_coupon_description( $coupon ),
			'minimum_spend' => $coupon->get_minimum_amount() ? wc_format_decimal( $coupon->get_minimum_amount(), 2 ) : null,
			'expiry_date'   => $this->format_expiry_date( $coupon ),
			'free_shipping' => $coupon->get_free_shipping(),
		);
	}

	/**
	 * Map discount calculation result.
	 *
	 * @param WC_Coupon $coupon          Coupon object.
	 * @param float     $discount_amount Calculated discount amount.
	 * @param float     $subtotal_before Subtotal before discount.
	 * @param float     $subtotal_after  Subtotal after discount.
	 * @return array
	 */
	public function map_discount_calculation( $coupon, $discount_amount, $subtotal_before, $subtotal_after ) {
		return array(
			'code'            => $coupon->get_code(),
			'discount_amount' => wc_format_decimal( $discount_amount, 2 ),
			'subtotal_before' => wc_format_decimal( $subtotal_before, 2 ),
			'subtotal_after'  => wc_format_decimal( $subtotal_after, 2 ),
			'currency'        => get_woocommerce_currency(),
		);
	}

	/**
	 * Map WooCommerce discount type to UCP format.
	 *
	 * @param string $wc_type WooCommerce discount type.
	 * @return string
	 */
	public function map_discount_type( $wc_type ) {
		$type_mapping = array(
			'percent'       => 'percent',
			'fixed_cart'    => 'fixed_cart',
			'fixed_product' => 'fixed_product',
		);

		return isset( $type_mapping[ $wc_type ] ) ? $type_mapping[ $wc_type ] : $wc_type;
	}

	/**
	 * Get human-readable coupon description.
	 *
	 * @param WC_Coupon $coupon Coupon object.
	 * @return string
	 */
	public function get_coupon_description( $coupon ) {
		// Return custom description if set.
		$description = $coupon->get_description();
		if ( ! empty( $description ) ) {
			return $description;
		}

		// Generate description based on coupon type and amount.
		$amount        = $coupon->get_amount();
		$discount_type = $coupon->get_discount_type();

		switch ( $discount_type ) {
			case 'percent':
				return sprintf(
					/* translators: %s: discount percentage */
					__( '%s%% off your order', 'harmonytics-ucp-connector-woocommerce' ),
					$amount
				);
			case 'fixed_cart':
				return sprintf(
					/* translators: %s: discount amount with currency */
					__( '%s off your order', 'harmonytics-ucp-connector-woocommerce' ),
					wc_price( $amount )
				);
			case 'fixed_product':
				return sprintf(
					/* translators: %s: discount amount with currency */
					__( '%s off per product', 'harmonytics-ucp-connector-woocommerce' ),
					wc_price( $amount )
				);
			default:
				return __( 'Discount coupon', 'harmonytics-ucp-connector-woocommerce' );
		}
	}

	/**
	 * Format coupon expiry date.
	 *
	 * @param WC_Coupon $coupon Coupon object.
	 * @return string|null
	 */
	private function format_expiry_date( $coupon ) {
		$expiry_date = $coupon->get_date_expires();

		if ( $expiry_date ) {
			return $expiry_date->format( 'Y-m-d' );
		}

		return null;
	}

	/**
	 * Calculate discount for items based on coupon type.
	 *
	 * @param WC_Coupon $coupon Coupon object.
	 * @param array     $items  Array of items with product_id, quantity, and price.
	 * @return float Calculated discount amount.
	 */
	public function calculate_discount( $coupon, $items ) {
		$discount_type = $coupon->get_discount_type();
		$amount        = floatval( $coupon->get_amount() );
		$subtotal      = 0;
		$discount      = 0;

		// Calculate subtotal.
		foreach ( $items as $item ) {
			$price    = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
			$quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
			$subtotal += $price * $quantity;
		}

		// Calculate discount based on type.
		switch ( $discount_type ) {
			case 'percent':
				$discount = ( $subtotal * $amount ) / 100;
				break;

			case 'fixed_cart':
				$discount = min( $amount, $subtotal );
				break;

			case 'fixed_product':
				foreach ( $items as $item ) {
					$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
					$quantity   = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
					$price      = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;

					// Check if product is applicable.
					if ( $this->is_product_applicable( $coupon, $product_id ) ) {
						$item_discount = min( $amount * $quantity, $price * $quantity );
						$discount     += $item_discount;
					}
				}
				break;
		}

		// Apply maximum discount if set.
		$max_discount = $coupon->get_maximum_amount();
		if ( $max_discount && $discount > $max_discount ) {
			$discount = floatval( $max_discount );
		}

		return $discount;
	}

	/**
	 * Check if a product is applicable for the coupon.
	 *
	 * @param WC_Coupon $coupon     Coupon object.
	 * @param int       $product_id Product ID.
	 * @return bool
	 */
	private function is_product_applicable( $coupon, $product_id ) {
		$product_ids          = $coupon->get_product_ids();
		$excluded_product_ids = $coupon->get_excluded_product_ids();

		// Check exclusions first.
		if ( ! empty( $excluded_product_ids ) && in_array( $product_id, $excluded_product_ids, true ) ) {
			return false;
		}

		// If specific products are set, check inclusion.
		if ( ! empty( $product_ids ) ) {
			return in_array( $product_id, $product_ids, true );
		}

		// Check category restrictions.
		$product_categories          = $coupon->get_product_categories();
		$excluded_product_categories = $coupon->get_excluded_product_categories();

		if ( ! empty( $product_categories ) || ! empty( $excluded_product_categories ) ) {
			$product      = wc_get_product( $product_id );
			$category_ids = $product ? $product->get_category_ids() : array();

			// Check category exclusions.
			if ( ! empty( $excluded_product_categories ) && array_intersect( $category_ids, $excluded_product_categories ) ) {
				return false;
			}

			// Check category inclusions.
			if ( ! empty( $product_categories ) ) {
				return (bool) array_intersect( $category_ids, $product_categories );
			}
		}

		return true;
	}
}
