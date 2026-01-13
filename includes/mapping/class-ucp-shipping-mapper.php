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
}
