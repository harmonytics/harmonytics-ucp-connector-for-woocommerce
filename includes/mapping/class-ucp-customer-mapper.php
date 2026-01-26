<?php
/**
 * Customer mapper for UCP schema conversion.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Customer_Mapper
 *
 * Maps WooCommerce customers to UCP customer schema.
 */
class UCP_WC_Customer_Mapper {

	/**
	 * Address mapper.
	 *
	 * @var UCP_WC_Address_Mapper
	 */
	protected $address_mapper;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->address_mapper = new UCP_WC_Address_Mapper();
	}

	/**
	 * Map a WooCommerce customer to UCP format.
	 *
	 * @param WC_Customer $customer Customer object.
	 * @return array
	 */
	public function map_customer( $customer ) {
		return array(
			'id'                 => $customer->get_id(),
			'email'              => $customer->get_email(),
			'first_name'         => $customer->get_first_name(),
			'last_name'          => $customer->get_last_name(),
			'display_name'       => $customer->get_display_name(),
			'billing_address'    => $this->map_billing_address( $customer ),
			'shipping_address'   => $this->map_shipping_address( $customer ),
			'is_paying_customer' => $customer->get_is_paying_customer(),
			'order_count'        => $customer->get_order_count(),
			'total_spent'        => $customer->get_total_spent(),
			'currency'           => get_woocommerce_currency(),
			'created_at'         => $customer->get_date_created() ? $customer->get_date_created()->format( 'c' ) : null,
		);
	}

	/**
	 * Map customer summary (for list views).
	 *
	 * @param WC_Customer $customer Customer object.
	 * @return array
	 */
	public function map_customer_summary( $customer ) {
		return array(
			'id'                 => $customer->get_id(),
			'email'              => $customer->get_email(),
			'first_name'         => $customer->get_first_name(),
			'last_name'          => $customer->get_last_name(),
			'display_name'       => $customer->get_display_name(),
			'is_paying_customer' => $customer->get_is_paying_customer(),
			'order_count'        => $customer->get_order_count(),
			'created_at'         => $customer->get_date_created() ? $customer->get_date_created()->format( 'c' ) : null,
		);
	}

	/**
	 * Map customer billing address.
	 *
	 * @param WC_Customer $customer Customer object.
	 * @return array
	 */
	public function map_billing_address( $customer ) {
		return $this->address_mapper->map_address(
			array(
				'first_name' => $customer->get_billing_first_name(),
				'last_name'  => $customer->get_billing_last_name(),
				'company'    => $customer->get_billing_company(),
				'address_1'  => $customer->get_billing_address_1(),
				'address_2'  => $customer->get_billing_address_2(),
				'city'       => $customer->get_billing_city(),
				'state'      => $customer->get_billing_state(),
				'postcode'   => $customer->get_billing_postcode(),
				'country'    => $customer->get_billing_country(),
				'phone'      => $customer->get_billing_phone(),
				'email'      => $customer->get_billing_email(),
			)
		);
	}

	/**
	 * Map customer shipping address.
	 *
	 * @param WC_Customer $customer Customer object.
	 * @return array
	 */
	public function map_shipping_address( $customer ) {
		return $this->address_mapper->map_address(
			array(
				'first_name' => $customer->get_shipping_first_name(),
				'last_name'  => $customer->get_shipping_last_name(),
				'company'    => $customer->get_shipping_company(),
				'address_1'  => $customer->get_shipping_address_1(),
				'address_2'  => $customer->get_shipping_address_2(),
				'city'       => $customer->get_shipping_city(),
				'state'      => $customer->get_shipping_state(),
				'postcode'   => $customer->get_shipping_postcode(),
				'country'    => $customer->get_shipping_country(),
				'phone'      => $customer->get_shipping_phone(),
			)
		);
	}

	/**
	 * Get all saved addresses for a customer.
	 *
	 * @param WC_Customer $customer Customer object.
	 * @return array
	 */
	public function get_customer_addresses( $customer ) {
		$addresses = array();

		// Add billing address if it has data.
		$billing = $this->map_billing_address( $customer );
		if ( ! empty( $billing['address_line_1'] ) ) {
			$addresses[] = array(
				'id'      => 'billing',
				'type'    => 'billing',
				'default' => true,
				'address' => $billing,
			);
		}

		// Add shipping address if it has data and is different from billing.
		$shipping = $this->map_shipping_address( $customer );
		if ( ! empty( $shipping['address_line_1'] ) ) {
			$is_different = $this->addresses_differ( $billing, $shipping );

			$addresses[] = array(
				'id'      => 'shipping',
				'type'    => 'shipping',
				'default' => true,
				'address' => $shipping,
			);
		}

		// Get additional addresses from customer meta (if stored).
		$additional_addresses = $customer->get_meta( '_ucp_additional_addresses' );
		if ( is_array( $additional_addresses ) ) {
			foreach ( $additional_addresses as $index => $addr ) {
				$addresses[] = array(
					'id'      => 'additional_' . $index,
					'type'    => $addr['type'] ?? 'shipping',
					'default' => false,
					'label'   => $addr['label'] ?? '',
					'address' => $this->address_mapper->map_address( $addr ),
				);
			}
		}

		return $addresses;
	}

	/**
	 * Check if two addresses are different.
	 *
	 * @param array $address1 First address.
	 * @param array $address2 Second address.
	 * @return bool
	 */
	private function addresses_differ( $address1, $address2 ) {
		$fields_to_compare = array(
			'first_name',
			'last_name',
			'address_line_1',
			'address_line_2',
			'city',
			'state',
			'postcode',
			'country',
		);

		foreach ( $fields_to_compare as $field ) {
			if ( ( $address1[ $field ] ?? '' ) !== ( $address2[ $field ] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Map customer order history summary.
	 *
	 * @param WC_Customer $customer Customer object.
	 * @param int         $limit    Maximum number of orders to include.
	 * @return array
	 */
	public function get_order_history_summary( $customer, $limit = 5 ) {
		$order_mapper = new UCP_WC_Order_Mapper();

		$orders = wc_get_orders(
			array(
				'customer_id' => $customer->get_id(),
				'limit'       => $limit,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$order_summaries = array();
		foreach ( $orders as $order ) {
			$order_summaries[] = $order_mapper->map_order_summary( $order );
		}

		return array(
			'total_orders'  => $customer->get_order_count(),
			'total_spent'   => $customer->get_total_spent(),
			'currency'      => get_woocommerce_currency(),
			'average_order' => $customer->get_order_count() > 0
				? round( floatval( $customer->get_total_spent() ) / $customer->get_order_count(), 2 )
				: 0,
			'recent_orders' => $order_summaries,
		);
	}

	/**
	 * Map order history with pagination.
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $args        Query arguments.
	 * @return array
	 */
	public function get_paginated_orders( $customer_id, $args = array() ) {
		$defaults = array(
			'page'     => 1,
			'per_page' => 10,
			'status'   => 'any',
		);

		$args         = wp_parse_args( $args, $defaults );
		$order_mapper = new UCP_WC_Order_Mapper();

		$query_args = array(
			'customer_id' => $customer_id,
			'limit'       => $args['per_page'],
			'offset'      => ( $args['page'] - 1 ) * $args['per_page'],
			'orderby'     => 'date',
			'order'       => 'DESC',
			'paginate'    => true,
		);

		if ( 'any' !== $args['status'] ) {
			$query_args['status'] = $args['status'];
		}

		$results = wc_get_orders( $query_args );

		$orders = array();
		foreach ( $results->orders as $order ) {
			$orders[] = $order_mapper->map_order_summary( $order );
		}

		return array(
			'orders'      => $orders,
			'total'       => $results->total,
			'total_pages' => $results->max_num_pages,
			'page'        => $args['page'],
			'per_page'    => $args['per_page'],
		);
	}

	/**
	 * Map input data to WooCommerce customer format for creation/update.
	 *
	 * @param array $data Input data.
	 * @return array
	 */
	public function map_to_wc( $data ) {
		$mapped = array();

		// Basic fields.
		$basic_fields = array(
			'email'      => 'email',
			'first_name' => 'first_name',
			'last_name'  => 'last_name',
			'username'   => 'username',
			'password'   => 'password',
		);

		foreach ( $basic_fields as $input_key => $wc_key ) {
			if ( isset( $data[ $input_key ] ) ) {
				$mapped[ $wc_key ] = $data[ $input_key ];
			}
		}

		// Map billing address.
		if ( isset( $data['billing_address'] ) && is_array( $data['billing_address'] ) ) {
			$billing = $this->address_mapper->map_to_wc( $data['billing_address'] );
			foreach ( $billing as $key => $value ) {
				$mapped[ 'billing_' . $key ] = $value;
			}
		}

		// Map shipping address.
		if ( isset( $data['shipping_address'] ) && is_array( $data['shipping_address'] ) ) {
			$shipping = $this->address_mapper->map_to_wc( $data['shipping_address'] );
			foreach ( $shipping as $key => $value ) {
				if ( 'email' !== $key ) { // Shipping doesn't have email.
					$mapped[ 'shipping_' . $key ] = $value;
				}
			}
		}

		return $mapped;
	}
}
