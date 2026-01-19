<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * Shipping mapper for UCP schema conversion.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Shipping_Mapper
 *
 * Maps WooCommerce shipping data to UCP schema.
 */
class UCP_WC_Shipping_Mapper {

    /**
     * Map shipping items from an order.
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    public function map_order_shipping( $order ) {
        $shipping_items = array();

        foreach ( $order->get_shipping_methods() as $item_id => $item ) {
            $shipping_items[] = $this->map_shipping_item( $item );
        }

        return $shipping_items;
    }

    /**
     * Map a single shipping item.
     *
     * @param WC_Order_Item_Shipping $item Shipping item.
     * @return array
     */
    public function map_shipping_item( $item ) {
        return array(
            'id'            => $item->get_id(),
            'method_id'     => $item->get_method_id(),
            'instance_id'   => $item->get_instance_id(),
            'method_title'  => $item->get_method_title(),
            'total'         => floatval( $item->get_total() ),
            'tax'           => floatval( $item->get_total_tax() ),
            'taxes'         => $item->get_taxes(),
            'meta_data'     => $this->map_item_meta( $item ),
        );
    }

    /**
     * Map available shipping methods for a destination.
     *
     * @param array $destination Shipping destination.
     * @param float $cart_total  Cart total for rate calculation.
     * @return array
     */
    public function map_available_methods( $destination, $cart_total = 0 ) {
        $packages = array(
            array(
                'contents'        => array(),
                'contents_cost'   => $cart_total,
                'applied_coupons' => array(),
                'destination'     => array(
                    'country'  => $destination['country'] ?? '',
                    'state'    => $destination['state'] ?? '',
                    'postcode' => $destination['postcode'] ?? '',
                    'city'     => $destination['city'] ?? '',
                ),
            ),
        );

        // Get shipping zone
        $shipping_zone = WC_Shipping_Zones::get_zone_matching_package( $packages[0] );
        $methods       = $shipping_zone->get_shipping_methods( true );

        $available = array();
        foreach ( $methods as $method ) {
            if ( ! $method->is_enabled() ) {
                continue;
            }

            $available[] = $this->map_shipping_method( $method, $packages[0] );
        }

        return $available;
    }

    /**
     * Map a shipping method.
     *
     * @param WC_Shipping_Method $method  Shipping method.
     * @param array              $package Shipping package.
     * @return array
     */
    public function map_shipping_method( $method, $package = array() ) {
        $mapped = array(
            'id'            => $method->id,
            'instance_id'   => $method->get_instance_id(),
            'rate_id'       => $method->get_rate_id(),
            'title'         => $method->get_title(),
            'method_title'  => $method->get_method_title(),
            'description'   => $method->get_method_description(),
            'supports'      => $method->supports,
        );

        // Calculate rate if package provided
        if ( ! empty( $package ) ) {
            $rates = $method->get_rates_for_package( $package );
            if ( ! empty( $rates ) ) {
                $rate = reset( $rates );
                $mapped['cost']     = floatval( $rate->get_cost() );
                $mapped['tax']      = floatval( $rate->get_shipping_tax() );
                $mapped['label']    = $rate->get_label();
            }
        }

        // Add estimated delivery if available
        $estimated_delivery = $this->get_estimated_delivery( $method );
        if ( $estimated_delivery ) {
            $mapped['estimated_delivery'] = $estimated_delivery;
        }

        return $mapped;
    }

    /**
     * Map shipping rate.
     *
     * @param WC_Shipping_Rate $rate Shipping rate.
     * @return array
     */
    public function map_shipping_rate( $rate ) {
        return array(
            'rate_id'       => $rate->get_id(),
            'method_id'     => $rate->get_method_id(),
            'instance_id'   => $rate->get_instance_id(),
            'label'         => $rate->get_label(),
            'cost'          => floatval( $rate->get_cost() ),
            'tax'           => floatval( $rate->get_shipping_tax() ),
            'taxes'         => $rate->get_taxes(),
            'meta_data'     => $rate->get_meta_data(),
        );
    }

    /**
     * Get estimated delivery info.
     *
     * @param WC_Shipping_Method $method Shipping method.
     * @return array|null
     */
    private function get_estimated_delivery( $method ) {
        // Try to get from method options
        $instance_options = $method->instance_settings ?? array();

        // Common patterns for delivery time settings
        $delivery_keys = array(
            'delivery_time',
            'estimated_delivery',
            'delivery_days',
            'shipping_time',
        );

        foreach ( $delivery_keys as $key ) {
            if ( ! empty( $instance_options[ $key ] ) ) {
                return array(
                    'description' => $instance_options[ $key ],
                );
            }
        }

        // Check for min/max days
        if ( isset( $instance_options['min_days'] ) && isset( $instance_options['max_days'] ) ) {
            return array(
                'min_days' => absint( $instance_options['min_days'] ),
                'max_days' => absint( $instance_options['max_days'] ),
            );
        }

        return null;
    }

    /**
     * Map item metadata.
     *
     * @param WC_Order_Item $item Order item.
     * @return array
     */
    private function map_item_meta( $item ) {
        $meta = array();

        foreach ( $item->get_meta_data() as $meta_item ) {
            // Skip internal meta
            if ( substr( $meta_item->key, 0, 1 ) === '_' ) {
                continue;
            }

            $meta[] = array(
                'key'   => $meta_item->key,
                'value' => $meta_item->value,
            );
        }

        return $meta;
    }

    /**
     * Get tracking information if available.
     *
     * @param WC_Order $order Order object.
     * @return array|null
     */
    public function get_tracking_info( $order ) {
        // Check for common tracking meta keys
        $tracking_keys = array(
            '_wc_shipment_tracking_items',  // WooCommerce Shipment Tracking
            '_tracking_number',              // Generic
            '_tracking_numbers',             // Some plugins
            'shipment_tracking',             // Alternative
        );

        foreach ( $tracking_keys as $key ) {
            $tracking = $order->get_meta( $key );
            if ( ! empty( $tracking ) ) {
                return $this->normalize_tracking( $tracking );
            }
        }

        return null;
    }

    /**
     * Normalize tracking data from various formats.
     *
     * @param mixed $tracking Tracking data.
     * @return array
     */
    private function normalize_tracking( $tracking ) {
        // If it's already an array of tracking items
        if ( is_array( $tracking ) && isset( $tracking[0] ) ) {
            $normalized = array();
            foreach ( $tracking as $item ) {
                $normalized[] = array(
                    'carrier'         => $item['tracking_provider'] ?? $item['carrier'] ?? '',
                    'tracking_number' => $item['tracking_number'] ?? $item['number'] ?? '',
                    'tracking_url'    => $item['tracking_link'] ?? $item['url'] ?? '',
                    'date_shipped'    => $item['date_shipped'] ?? $item['date'] ?? '',
                );
            }
            return $normalized;
        }

        // If it's a single tracking number
        if ( is_string( $tracking ) ) {
            return array(
                array(
                    'tracking_number' => $tracking,
                ),
            );
        }

        // If it's an associative array
        if ( is_array( $tracking ) ) {
            return array(
                array(
                    'carrier'         => $tracking['carrier'] ?? $tracking['tracking_provider'] ?? '',
                    'tracking_number' => $tracking['tracking_number'] ?? $tracking['number'] ?? '',
                    'tracking_url'    => $tracking['tracking_url'] ?? $tracking['url'] ?? '',
                ),
            );
        }

        return array();
    }

    /**
     * Map a WC_Shipping_Rate object for API response.
     *
     * @param WC_Shipping_Rate   $rate   Shipping rate object.
     * @param WC_Shipping_Method $method Shipping method object (optional).
     * @return array
     */
    public function map_shipping_rate_for_api( $rate, $method = null ) {
        $currency = get_woocommerce_currency();

        $mapped = array(
            'id'           => $rate->get_id(),
            'method_id'    => $rate->get_method_id(),
            'method_title' => $method ? $method->get_method_title() : $rate->get_method_id(),
            'label'        => $rate->get_label(),
            'cost'         => wc_format_decimal( $rate->get_cost(), wc_get_price_decimals() ),
            'taxes'        => wc_format_decimal( $rate->get_shipping_tax(), wc_get_price_decimals() ),
            'currency'     => $currency,
        );

        // Add instance ID if available.
        $instance_id = $rate->get_instance_id();
        if ( $instance_id ) {
            $mapped['instance_id'] = $instance_id;
        }

        // Add estimated delivery if available.
        $estimated_delivery = $this->get_rate_estimated_delivery( $rate, $method );
        if ( $estimated_delivery ) {
            $mapped['estimated_delivery'] = $estimated_delivery;
        }

        // Add meta data if present.
        $meta_data = $rate->get_meta_data();
        if ( ! empty( $meta_data ) ) {
            $mapped['meta_data'] = $meta_data;
        }

        return $mapped;
    }

    /**
     * Get estimated delivery for a shipping rate.
     *
     * @param WC_Shipping_Rate   $rate   Shipping rate object.
     * @param WC_Shipping_Method $method Shipping method object (optional).
     * @return string|null
     */
    private function get_rate_estimated_delivery( $rate, $method = null ) {
        // Check rate meta data first.
        $meta_data = $rate->get_meta_data();

        $delivery_keys = array(
            'delivery_time',
            'estimated_delivery',
            'delivery_days',
            'shipping_time',
            'estimated_days',
        );

        foreach ( $delivery_keys as $key ) {
            if ( isset( $meta_data[ $key ] ) && ! empty( $meta_data[ $key ] ) ) {
                return $meta_data[ $key ];
            }
        }

        // Check method instance settings if method provided.
        if ( $method ) {
            $estimated = $this->get_estimated_delivery( $method );
            if ( $estimated ) {
                if ( isset( $estimated['min_days'] ) && isset( $estimated['max_days'] ) ) {
                    return sprintf(
                        /* translators: 1: minimum days, 2: maximum days */
                        __( '%1$d-%2$d business days', 'harmonytics-ucp-connector-for-woocommerce' ),
                        $estimated['min_days'],
                        $estimated['max_days']
                    );
                }
                if ( isset( $estimated['description'] ) ) {
                    return $estimated['description'];
                }
            }
        }

        return null;
    }

    /**
     * Map a shipping zone for API response.
     *
     * @param WC_Shipping_Zone $zone Shipping zone object.
     * @return array
     */
    public function map_shipping_zone( $zone ) {
        $zone_locations = $zone->get_zone_locations();
        $zone_methods   = $zone->get_shipping_methods( false );

        $locations = array();
        foreach ( $zone_locations as $location ) {
            $locations[] = array(
                'code' => $location->code,
                'type' => $location->type, // 'country', 'state', 'postcode', 'continent'.
            );
        }

        $methods = array();
        foreach ( $zone_methods as $method ) {
            $methods[] = array(
                'id'          => $method->id,
                'instance_id' => $method->get_instance_id(),
                'title'       => $method->get_title(),
                'enabled'     => $method->is_enabled(),
            );
        }

        return array(
            'id'        => $zone->get_id(),
            'name'      => $zone->get_zone_name(),
            'order'     => $zone->get_zone_order(),
            'locations' => $locations,
            'methods'   => $methods,
        );
    }

    /**
     * Map shipping method info for API response.
     *
     * @param WC_Shipping_Method $method Shipping method object.
     * @return array
     */
    public function map_shipping_method_info( $method ) {
        return array(
            'id'                 => $method->id,
            'title'              => $method->get_method_title(),
            'description'        => $method->get_method_description(),
            'supports'           => $method->supports,
            'has_settings'       => $method->has_settings(),
            'supports_shipping_zones' => $method->supports( 'shipping-zones' ),
            'supports_instance_settings' => $method->supports( 'instance-settings' ),
        );
    }

    /**
     * Calculate package weight from items.
     *
     * @param array $items Array of cart items.
     * @return float
     */
    public function calculate_package_weight( $items ) {
        $total_weight = 0;

        foreach ( $items as $item ) {
            $product = isset( $item['data'] ) ? $item['data'] : null;

            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                continue;
            }

            $weight   = floatval( $product->get_weight() );
            $quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;

            if ( $weight > 0 ) {
                $total_weight += $weight * $quantity;
            }
        }

        return $total_weight;
    }

    /**
     * Calculate package dimensions from items.
     *
     * @param array $items Array of cart items.
     * @return array
     */
    public function calculate_package_dimensions( $items ) {
        $total_volume  = 0;
        $max_length    = 0;
        $max_width     = 0;
        $max_height    = 0;

        foreach ( $items as $item ) {
            $product = isset( $item['data'] ) ? $item['data'] : null;

            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                continue;
            }

            $quantity = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;

            $length = floatval( $product->get_length() );
            $width  = floatval( $product->get_width() );
            $height = floatval( $product->get_height() );

            if ( $length > 0 && $width > 0 && $height > 0 ) {
                // Calculate volume.
                $item_volume   = $length * $width * $height * $quantity;
                $total_volume += $item_volume;

                // Track max dimensions.
                $max_length = max( $max_length, $length );
                $max_width  = max( $max_width, $width );
                $max_height = max( $max_height, $height );
            }
        }

        return array(
            'length'       => (string) $max_length,
            'width'        => (string) $max_width,
            'height'       => (string) $max_height,
            'total_volume' => (string) $total_volume,
            'unit'         => get_option( 'woocommerce_dimension_unit', 'cm' ),
        );
    }
}
