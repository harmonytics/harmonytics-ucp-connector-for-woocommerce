<?php
/**
 * Address mapper for UCP schema conversion.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Address_Mapper
 *
 * Maps WooCommerce addresses to UCP address schema.
 */
class UCP_WC_Address_Mapper {

	/**
	 * Map order shipping address.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	public function map_order_shipping( $order ) {
		return $this->map_address(
			array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
				'phone'      => $order->get_shipping_phone(),
			)
		);
	}

	/**
	 * Map order billing address.
	 *
	 * @param WC_Order $order Order object.
	 * @return array
	 */
	public function map_order_billing( $order ) {
		return $this->map_address(
			array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
				'phone'      => $order->get_billing_phone(),
				'email'      => $order->get_billing_email(),
			)
		);
	}

	/**
	 * Map address array to UCP format.
	 *
	 * @param array $address Address data.
	 * @return array
	 */
	public function map_address( $address ) {
		$mapped = array(
			'first_name'     => $address['first_name'] ?? '',
			'last_name'      => $address['last_name'] ?? '',
			'full_name'      => trim( ( $address['first_name'] ?? '' ) . ' ' . ( $address['last_name'] ?? '' ) ),
			'company'        => $address['company'] ?? '',
			'address_line_1' => $address['address_1'] ?? '',
			'address_line_2' => $address['address_2'] ?? '',
			'city'           => $address['city'] ?? '',
			'state'          => $address['state'] ?? '',
			'state_name'     => $this->get_state_name( $address['country'] ?? '', $address['state'] ?? '' ),
			'postcode'       => $address['postcode'] ?? '',
			'country'        => $address['country'] ?? '',
			'country_name'   => $this->get_country_name( $address['country'] ?? '' ),
			'phone'          => $address['phone'] ?? '',
		);

		// Add email if present.
		if ( ! empty( $address['email'] ) ) {
			$mapped['email'] = $address['email'];
		}

		// Add formatted address.
		$mapped['formatted'] = $this->format_address( $mapped );

		return $mapped;
	}

	/**
	 * Map UCP address to WooCommerce format.
	 *
	 * @param array $ucp_address UCP address data.
	 * @return array
	 */
	public function map_to_wc( $ucp_address ) {
		return array(
			'first_name' => $ucp_address['first_name'] ?? '',
			'last_name'  => $ucp_address['last_name'] ?? '',
			'company'    => $ucp_address['company'] ?? '',
			'address_1'  => $ucp_address['address_line_1'] ?? $ucp_address['address_1'] ?? '',
			'address_2'  => $ucp_address['address_line_2'] ?? $ucp_address['address_2'] ?? '',
			'city'       => $ucp_address['city'] ?? '',
			'state'      => $ucp_address['state'] ?? '',
			'postcode'   => $ucp_address['postcode'] ?? '',
			'country'    => $ucp_address['country'] ?? '',
			'phone'      => $ucp_address['phone'] ?? '',
			'email'      => $ucp_address['email'] ?? '',
		);
	}

	/**
	 * Get country name from country code.
	 *
	 * @param string $country_code Country code.
	 * @return string
	 */
	private function get_country_name( $country_code ) {
		if ( empty( $country_code ) ) {
			return '';
		}

		$countries = WC()->countries->get_countries();
		return $countries[ $country_code ] ?? $country_code;
	}

	/**
	 * Get state name from state code.
	 *
	 * @param string $country_code Country code.
	 * @param string $state_code   State code.
	 * @return string
	 */
	private function get_state_name( $country_code, $state_code ) {
		if ( empty( $country_code ) || empty( $state_code ) ) {
			return $state_code;
		}

		$states = WC()->countries->get_states( $country_code );

		if ( is_array( $states ) && isset( $states[ $state_code ] ) ) {
			return $states[ $state_code ];
		}

		return $state_code;
	}

	/**
	 * Format address as a string.
	 *
	 * @param array $address Mapped address.
	 * @return string
	 */
	private function format_address( $address ) {
		$parts = array_filter(
			array(
				$address['full_name'],
				$address['company'],
				$address['address_line_1'],
				$address['address_line_2'],
				$address['city'],
				$address['state_name'],
				$address['postcode'],
				$address['country_name'],
			)
		);

		return implode( ', ', $parts );
	}

	/**
	 * Validate address fields.
	 *
	 * @param array $address Address data.
	 * @return array Array of validation errors.
	 */
	public function validate( $address ) {
		$errors = array();

		$required_fields = array(
			'first_name' => __( 'First name', 'harmonytics-ucp-connector-for-woocommerce' ),
			'last_name'  => __( 'Last name', 'harmonytics-ucp-connector-for-woocommerce' ),
			'address_1'  => __( 'Address', 'harmonytics-ucp-connector-for-woocommerce' ),
			'city'       => __( 'City', 'harmonytics-ucp-connector-for-woocommerce' ),
			'postcode'   => __( 'Postcode', 'harmonytics-ucp-connector-for-woocommerce' ),
			'country'    => __( 'Country', 'harmonytics-ucp-connector-for-woocommerce' ),
		);

		foreach ( $required_fields as $field => $label ) {
			$value = $address[ $field ] ?? $address[ str_replace( '_1', '_line_1', $field ) ] ?? '';
			if ( empty( $value ) ) {
				$errors[] = sprintf(
					/* translators: %s: field name */
					__( '%s is required.', 'harmonytics-ucp-connector-for-woocommerce' ),
					$label
				);
			}
		}

		// Validate country.
		if ( ! empty( $address['country'] ) ) {
			$countries = WC()->countries->get_countries();
			if ( ! isset( $countries[ $address['country'] ] ) ) {
				$errors[] = __( 'Invalid country code.', 'harmonytics-ucp-connector-for-woocommerce' );
			}
		}

		// Validate email if present.
		if ( ! empty( $address['email'] ) && ! is_email( $address['email'] ) ) {
			$errors[] = __( 'Invalid email address.', 'harmonytics-ucp-connector-for-woocommerce' );
		}

		return $errors;
	}
}
