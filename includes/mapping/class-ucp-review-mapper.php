<?php
/**
 * Review mapper for UCP schema conversion.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OU
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Review_Mapper
 *
 * Maps WooCommerce product reviews (WP_Comment) to UCP review schema.
 */
class UCP_WC_Review_Mapper {

	/**
	 * Map a WooCommerce product review to UCP format.
	 *
	 * @param WP_Comment $comment Comment object representing a product review.
	 * @return array
	 */
	public function map_review( $comment ) {
		$rating   = $this->get_review_rating( $comment->comment_ID );
		$verified = $this->is_verified_purchase( $comment );

		return array(
			'id'             => (int) $comment->comment_ID,
			'product_id'     => (int) $comment->comment_post_ID,
			'reviewer'       => $comment->comment_author,
			'reviewer_email' => $comment->comment_author_email,
			'rating'         => $rating,
			'review'         => $comment->comment_content,
			'verified'       => $verified,
			'date_created'   => mysql2date( 'c', $comment->comment_date_gmt, false ),
			'status'         => $this->map_comment_status( $comment->comment_approved ),
		);
	}

	/**
	 * Map a review for list views (summary format).
	 *
	 * @param WP_Comment $comment Comment object representing a product review.
	 * @return array
	 */
	public function map_review_summary( $comment ) {
		$rating   = $this->get_review_rating( $comment->comment_ID );
		$verified = $this->is_verified_purchase( $comment );

		return array(
			'id'           => (int) $comment->comment_ID,
			'product_id'   => (int) $comment->comment_post_ID,
			'reviewer'     => $comment->comment_author,
			'rating'       => $rating,
			'review'       => wp_trim_words( $comment->comment_content, 30, '...' ),
			'verified'     => $verified,
			'date_created' => mysql2date( 'c', $comment->comment_date_gmt, false ),
			'status'       => $this->map_comment_status( $comment->comment_approved ),
		);
	}

	/**
	 * Generate review summary statistics for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function get_product_review_summary( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array(
				'product_id'          => $product_id,
				'average_rating'      => 0,
				'review_count'        => 0,
				'rating_distribution' => $this->get_empty_distribution(),
			);
		}

		$rating_counts = $product->get_rating_counts();
		$distribution  = $this->get_empty_distribution();

		// Populate distribution from rating counts.
		foreach ( $rating_counts as $rating => $count ) {
			if ( isset( $distribution[ (string) $rating ] ) ) {
				$distribution[ (string) $rating ] = (int) $count;
			}
		}

		return array(
			'product_id'          => (int) $product_id,
			'average_rating'      => (float) $product->get_average_rating(),
			'review_count'        => (int) $product->get_review_count(),
			'rating_distribution' => $distribution,
		);
	}

	/**
	 * Get an empty rating distribution array.
	 *
	 * @return array
	 */
	private function get_empty_distribution() {
		return array(
			'5' => 0,
			'4' => 0,
			'3' => 0,
			'2' => 0,
			'1' => 0,
		);
	}

	/**
	 * Get the rating for a review from comment meta.
	 *
	 * @param int $comment_id Comment ID.
	 * @return int Rating value (1-5) or 0 if not set.
	 */
	private function get_review_rating( $comment_id ) {
		$rating = get_comment_meta( $comment_id, 'rating', true );
		return $rating ? (int) $rating : 0;
	}

	/**
	 * Check if a review is from a verified purchaser.
	 *
	 * @param WP_Comment $comment Comment object.
	 * @return bool
	 */
	private function is_verified_purchase( $comment ) {
		$verified = get_comment_meta( $comment->comment_ID, 'verified', true );

		if ( '' !== $verified ) {
			return (bool) $verified;
		}

		// Fall back to checking purchase history if meta not set.
		if ( $comment->user_id ) {
			return wc_customer_bought_product(
				$comment->comment_author_email,
				$comment->user_id,
				$comment->comment_post_ID
			);
		}

		return false;
	}

	/**
	 * Map WordPress comment status to UCP status.
	 *
	 * @param string $status WordPress comment approved status.
	 * @return string UCP status string.
	 */
	private function map_comment_status( $status ) {
		switch ( $status ) {
			case '1':
			case 'approve':
				return 'approved';
			case '0':
			case 'hold':
				return 'pending';
			case 'spam':
				return 'spam';
			case 'trash':
				return 'trash';
			default:
				return 'pending';
		}
	}

	/**
	 * Map UCP status to WordPress comment status.
	 *
	 * @param string $status UCP status string.
	 * @return string WordPress comment status.
	 */
	public function map_ucp_status_to_wp( $status ) {
		switch ( $status ) {
			case 'approved':
				return 'approve';
			case 'pending':
				return 'hold';
			case 'spam':
				return 'spam';
			case 'trash':
				return 'trash';
			default:
				return 'hold';
		}
	}

	/**
	 * Prepare review data for insertion from UCP request.
	 *
	 * @param array $data Review data from request.
	 * @param int   $product_id Product ID.
	 * @return array Data prepared for wp_insert_comment.
	 */
	public function prepare_review_for_insert( $data, $product_id ) {
		$comment_data = array(
			'comment_post_ID'      => $product_id,
			'comment_author'       => sanitize_text_field( $data['reviewer'] ?? '' ),
			'comment_author_email' => sanitize_email( $data['reviewer_email'] ?? '' ),
			'comment_content'      => sanitize_textarea_field( $data['review'] ?? '' ),
			'comment_type'         => 'review',
			'comment_parent'       => 0,
		);

		// Set comment approved status based on WooCommerce settings.
		$comment_data['comment_approved'] = $this->get_default_approval_status();

		// Add user ID if authenticated.
		if ( ! empty( $data['user_id'] ) ) {
			$comment_data['user_id'] = absint( $data['user_id'] );
		}

		return $comment_data;
	}

	/**
	 * Get the default approval status based on WooCommerce settings.
	 *
	 * @return string|int
	 */
	private function get_default_approval_status() {
		// Check if reviews require moderation.
		$moderation = get_option( 'woocommerce_review_rating_verification_required', 'no' );

		if ( 'yes' === $moderation ) {
			return 0; // Hold for moderation.
		}

		// Check WordPress discussion settings.
		if ( get_option( 'comment_moderation' ) ) {
			return 0;
		}

		return 1; // Auto-approve.
	}
}
