<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Category mapper for UCP schema conversion.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Category_Mapper
 *
 * Maps WooCommerce product categories to UCP category schema.
 */
class UCP_WC_Category_Mapper {

	/**
	 * Map a WooCommerce product category to UCP format.
	 *
	 * @param WP_Term $term         Term object.
	 * @param bool    $include_children Whether to include child categories.
	 * @return array
	 */
	public function map_category( $term, $include_children = true ) {
		$mapped = array(
			'id'            => $term->term_id,
			'name'          => $term->name,
			'slug'          => $term->slug,
			'description'   => $term->description,
			'parent_id'     => $term->parent > 0 ? $term->parent : null,
			'product_count' => $this->get_product_count( $term->term_id ),
			'image'         => $this->get_category_image( $term->term_id ),
			'url'           => get_term_link( $term ),
			'links'         => $this->map_links( $term ),
		);

		// Include children if requested
		if ( $include_children ) {
			$mapped['children'] = $this->get_children( $term->term_id );
		}

		return $mapped;
	}

	/**
	 * Map a category summary (for list views).
	 *
	 * @param WP_Term $term Term object.
	 * @return array
	 */
	public function map_category_summary( $term ) {
		return array(
			'id'            => $term->term_id,
			'name'          => $term->name,
			'slug'          => $term->slug,
			'description'   => $term->description,
			'parent_id'     => $term->parent > 0 ? $term->parent : null,
			'product_count' => $this->get_product_count( $term->term_id ),
			'image'         => $this->get_category_image( $term->term_id ),
			'url'           => get_term_link( $term ),
		);
	}

	/**
	 * Get category image.
	 *
	 * @param int $term_id Term ID.
	 * @return array|null
	 */
	private function get_category_image( $term_id ) {
		$thumbnail_id = get_term_meta( $term_id, 'thumbnail_id', true );

		if ( ! $thumbnail_id ) {
			return null;
		}

		$full_url      = wp_get_attachment_image_url( $thumbnail_id, 'full' );
		$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, 'woocommerce_thumbnail' );
		$alt_text      = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );

		if ( ! $full_url ) {
			return null;
		}

		return array(
			'id'        => $thumbnail_id,
			'url'       => $full_url,
			'thumbnail' => $thumbnail_url ?: $full_url,
			'alt'       => $alt_text ?: '',
		);
	}

	/**
	 * Get product count for a category.
	 *
	 * @param int $term_id Term ID.
	 * @return int
	 */
	private function get_product_count( $term_id ) {
		$term = get_term( $term_id, 'product_cat' );

		if ( ! $term || is_wp_error( $term ) ) {
			return 0;
		}

		return (int) $term->count;
	}

	/**
	 * Get child categories.
	 *
	 * @param int $term_id Parent term ID.
	 * @return array
	 */
	private function get_children( $term_id ) {
		$children = array();

		$child_terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $term_id,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $child_terms ) ) {
			return $children;
		}

		foreach ( $child_terms as $child ) {
			$children[] = array(
				'id'            => $child->term_id,
				'name'          => $child->name,
				'slug'          => $child->slug,
				'product_count' => $this->get_product_count( $child->term_id ),
			);
		}

		return $children;
	}

	/**
	 * Map category links.
	 *
	 * @param WP_Term $term Term object.
	 * @return array
	 */
	private function map_links( $term ) {
		$links = array(
			'self'      => rest_url( 'ucp/v1/categories/' . $term->term_id ),
			'products'  => rest_url( 'ucp/v1/categories/' . $term->term_id . '/products' ),
			'permalink' => get_term_link( $term ),
		);

		// Add parent link if category has a parent
		if ( $term->parent > 0 ) {
			$links['parent'] = rest_url( 'ucp/v1/categories/' . $term->parent );
		}

		return $links;
	}

	/**
	 * Build a hierarchical category tree.
	 *
	 * @param array $categories Flat list of categories.
	 * @param int   $parent_id  Parent ID to start from (0 for root).
	 * @return array
	 */
	public function build_hierarchy( $categories, $parent_id = 0 ) {
		$tree = array();

		foreach ( $categories as $category ) {
			if ( $category['parent_id'] === ( $parent_id > 0 ? $parent_id : null ) ) {
				$children = $this->build_hierarchy( $categories, $category['id'] );

				if ( ! empty( $children ) ) {
					$category['children'] = $children;
				}

				$tree[] = $category;
			}
		}

		return $tree;
	}
}
