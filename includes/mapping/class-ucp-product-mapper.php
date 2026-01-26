<?php
/**
 * Product mapper for UCP schema conversion.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Product_Mapper
 *
 * Maps WooCommerce products to UCP product schema.
 */
class UCP_WC_Product_Mapper {

	/**
	 * Map a WooCommerce product to UCP format.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	public function map_product( $product ) {
		$mapped = array(
			'id'                => $product->get_id(),
			'sku'               => $product->get_sku(),
			'name'              => $product->get_name(),
			'slug'              => $product->get_slug(),
			'type'              => $product->get_type(),
			'status'            => $product->get_status(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'url'               => get_permalink( $product->get_id() ),
			'pricing'           => $this->map_pricing( $product ),
			'stock'             => $this->map_stock( $product ),
			'images'            => $this->map_images( $product ),
			'categories'        => $this->map_categories( $product ),
			'tags'              => $this->map_tags( $product ),
			'attributes'        => $this->map_attributes( $product ),
			'dimensions'        => $this->map_dimensions( $product ),
			'meta'              => $this->map_meta( $product ),
			'dates'             => $this->map_dates( $product ),
			'links'             => $this->map_links( $product ),
		);

		// Add variations for variable products.
		if ( $product->is_type( 'variable' ) ) {
			$mapped['variations'] = $this->map_variations( $product );
		}

		// Add downloadable/virtual flags.
		$mapped['is_virtual']      = $product->is_virtual();
		$mapped['is_downloadable'] = $product->is_downloadable();

		// Add featured flag.
		$mapped['is_featured'] = $product->is_featured();

		return $mapped;
	}

	/**
	 * Map product summary (for list views).
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	public function map_product_summary( $product ) {
		return array(
			'id'                => $product->get_id(),
			'sku'               => $product->get_sku(),
			'name'              => $product->get_name(),
			'slug'              => $product->get_slug(),
			'type'              => $product->get_type(),
			'short_description' => $product->get_short_description(),
			'url'               => get_permalink( $product->get_id() ),
			'pricing'           => $this->map_pricing( $product ),
			'stock'             => $this->map_stock_summary( $product ),
			'thumbnail'         => $this->get_product_thumbnail( $product ),
			'categories'        => $this->map_category_names( $product ),
			'is_featured'       => $product->is_featured(),
			'is_virtual'        => $product->is_virtual(),
		);
	}

	/**
	 * Map product pricing.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_pricing( $product ) {
		$pricing = array(
			'price'           => floatval( $product->get_price() ),
			'regular_price'   => floatval( $product->get_regular_price() ),
			'currency'        => get_woocommerce_currency(),
			'currency_symbol' => get_woocommerce_currency_symbol(),
			'price_html'      => $product->get_price_html(),
			'on_sale'         => $product->is_on_sale(),
		);

		// Add sale price if on sale.
		if ( $product->is_on_sale() ) {
			$pricing['sale_price'] = floatval( $product->get_sale_price() );

			$sale_start = $product->get_date_on_sale_from();
			$sale_end   = $product->get_date_on_sale_to();

			if ( $sale_start ) {
				$pricing['sale_start'] = $sale_start->format( 'c' );
			}
			if ( $sale_end ) {
				$pricing['sale_end'] = $sale_end->format( 'c' );
			}
		}

		// Add price range for variable products.
		if ( $product->is_type( 'variable' ) ) {
			$pricing['min_price'] = floatval( $product->get_variation_price( 'min' ) );
			$pricing['max_price'] = floatval( $product->get_variation_price( 'max' ) );
		}

		// Add tax information.
		$pricing['tax_status'] = $product->get_tax_status();
		$pricing['tax_class']  = $product->get_tax_class();

		return $pricing;
	}

	/**
	 * Map product stock information.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_stock( $product ) {
		$stock = array(
			'manage_stock'       => $product->managing_stock(),
			'stock_status'       => $product->get_stock_status(),
			'in_stock'           => $product->is_in_stock(),
			'purchasable'        => $product->is_purchasable(),
			'backorders'         => $product->get_backorders(),
			'backorders_allowed' => $product->backorders_allowed(),
			'sold_individually'  => $product->is_sold_individually(),
		);

		// Add quantity if stock is managed.
		if ( $product->managing_stock() ) {
			$stock['quantity']            = $product->get_stock_quantity();
			$stock['low_stock_threshold'] = $product->get_low_stock_amount();
		}

		return $stock;
	}

	/**
	 * Map stock summary for list views.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_stock_summary( $product ) {
		return array(
			'status'      => $product->get_stock_status(),
			'in_stock'    => $product->is_in_stock(),
			'purchasable' => $product->is_purchasable(),
		);
	}

	/**
	 * Map product images.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_images( $product ) {
		$images = array();

		// Main image.
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$images[] = $this->map_image( $image_id, true );
		}

		// Gallery images.
		$gallery_ids = $product->get_gallery_image_ids();
		foreach ( $gallery_ids as $gallery_id ) {
			$images[] = $this->map_image( $gallery_id, false );
		}

		return $images;
	}

	/**
	 * Map a single image.
	 *
	 * @param int  $image_id  Attachment ID.
	 * @param bool $is_primary Whether this is the primary image.
	 * @return array
	 */
	private function map_image( $image_id, $is_primary = false ) {
		$full_url      = wp_get_attachment_image_url( $image_id, 'full' );
		$thumbnail_url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
		$alt_text      = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		return array(
			'id'        => $image_id,
			'url'       => $full_url,
			'thumbnail' => $thumbnail_url,
			'alt'       => $alt_text,
			'primary'   => $is_primary,
		);
	}

	/**
	 * Get product thumbnail URL.
	 *
	 * @param WC_Product $product Product object.
	 * @return string|null
	 */
	private function get_product_thumbnail( $product ) {
		$image_id = $product->get_image_id();

		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
			return $image_url ? $image_url : null;
		}

		// Try parent product for variations.
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				return $this->get_product_thumbnail( $parent );
			}
		}

		return wc_placeholder_img_src( 'woocommerce_thumbnail' );
	}

	/**
	 * Map product categories.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_categories( $product ) {
		$categories = array();
		$term_ids   = $product->get_category_ids();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$categories[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
					'url'  => get_term_link( $term ),
				);
			}
		}

		return $categories;
	}

	/**
	 * Map category names only (for list views).
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_category_names( $product ) {
		$names    = array();
		$term_ids = $product->get_category_ids();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}

		return $names;
	}

	/**
	 * Map product tags.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_tags( $product ) {
		$tags     = array();
		$term_ids = $product->get_tag_ids();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, 'product_tag' );
			if ( $term && ! is_wp_error( $term ) ) {
				$tags[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		return $tags;
	}

	/**
	 * Map product attributes.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_attributes( $product ) {
		$attributes = array();

		foreach ( $product->get_attributes() as $attribute ) {
			if ( is_a( $attribute, 'WC_Product_Attribute' ) ) {
				$attr_data = array(
					'id'        => $attribute->get_id(),
					'name'      => wc_attribute_label( $attribute->get_name() ),
					'slug'      => $attribute->get_name(),
					'position'  => $attribute->get_position(),
					'visible'   => $attribute->get_visible(),
					'variation' => $attribute->get_variation(),
				);

				// Get options/values.
				if ( $attribute->is_taxonomy() ) {
					$terms = wc_get_product_terms(
						$product->get_id(),
						$attribute->get_name(),
						array( 'fields' => 'all' )
					);

					$attr_data['options'] = array_map(
						function ( $term ) {
							return array(
								'id'   => $term->term_id,
								'name' => $term->name,
								'slug' => $term->slug,
							);
						},
						$terms
					);
				} else {
					$attr_data['options'] = $attribute->get_options();
				}

				$attributes[] = $attr_data;
			}
		}

		return $attributes;
	}

	/**
	 * Map product dimensions.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_dimensions( $product ) {
		return array(
			'weight'         => $product->get_weight(),
			'length'         => $product->get_length(),
			'width'          => $product->get_width(),
			'height'         => $product->get_height(),
			'weight_unit'    => get_option( 'woocommerce_weight_unit' ),
			'dimension_unit' => get_option( 'woocommerce_dimension_unit' ),
		);
	}

	/**
	 * Map product meta information.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_meta( $product ) {
		$meta = array(
			'purchase_note' => $product->get_purchase_note(),
			'menu_order'    => $product->get_menu_order(),
		);

		// Add review data.
		$meta['reviews'] = array(
			'enabled'        => $product->get_reviews_allowed(),
			'average_rating' => floatval( $product->get_average_rating() ),
			'review_count'   => intval( $product->get_review_count() ),
			'rating_count'   => intval( $product->get_rating_count() ),
		);

		// Add cross-sells and upsells.
		$meta['upsell_ids']     = $product->get_upsell_ids();
		$meta['cross_sell_ids'] = $product->get_cross_sell_ids();

		return $meta;
	}

	/**
	 * Map product dates.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_dates( $product ) {
		return array(
			'created'  => $product->get_date_created() ? $product->get_date_created()->format( 'c' ) : null,
			'modified' => $product->get_date_modified() ? $product->get_date_modified()->format( 'c' ) : null,
		);
	}

	/**
	 * Map product links.
	 *
	 * @param WC_Product $product Product object.
	 * @return array
	 */
	private function map_links( $product ) {
		return array(
			'self'        => rest_url( 'ucp/v1/products/' . $product->get_id() ),
			'permalink'   => get_permalink( $product->get_id() ),
			'add_to_cart' => $product->add_to_cart_url(),
		);
	}

	/**
	 * Map variations for a variable product.
	 *
	 * @param WC_Product_Variable $product Variable product object.
	 * @return array
	 */
	private function map_variations( $product ) {
		$variations = array();

		foreach ( $product->get_available_variations() as $variation_data ) {
			$variation = wc_get_product( $variation_data['variation_id'] );

			if ( ! $variation ) {
				continue;
			}

			$variations[] = array(
				'id'             => $variation->get_id(),
				'sku'            => $variation->get_sku(),
				'price'          => floatval( $variation->get_price() ),
				'regular_price'  => floatval( $variation->get_regular_price() ),
				'sale_price'     => $variation->get_sale_price() ? floatval( $variation->get_sale_price() ) : null,
				'on_sale'        => $variation->is_on_sale(),
				'in_stock'       => $variation->is_in_stock(),
				'stock_status'   => $variation->get_stock_status(),
				'stock_quantity' => $variation->get_stock_quantity(),
				'purchasable'    => $variation->is_purchasable(),
				'image'          => $this->get_product_thumbnail( $variation ),
				'attributes'     => $this->map_variation_attributes( $variation ),
				'dimensions'     => $this->map_dimensions( $variation ),
				'weight'         => $variation->get_weight(),
			);
		}

		return $variations;
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
				'slug'  => $attribute_name,
				'value' => $attribute_value,
			);
		}

		return $attributes;
	}
}
