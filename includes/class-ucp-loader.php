<?php
/**
 * The loader class responsible for orchestrating plugin hooks and filters.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class UCP_WC_Loader
 *
 * Registers all actions and filters for the plugin.
 */
class UCP_WC_Loader {

	/**
	 * Array of actions registered with WordPress.
	 *
	 * @var array
	 */
	protected $actions = array();

	/**
	 * Array of filters registered with WordPress.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * Well-known handler instance.
	 *
	 * @var UCP_WC_Well_Known
	 */
	protected $well_known;

	/**
	 * Checkout controller instance.
	 *
	 * @var UCP_WC_Checkout_Controller
	 */
	protected $checkout_controller;

	/**
	 * Order controller instance.
	 *
	 * @var UCP_WC_Order_Controller
	 */
	protected $order_controller;

	/**
	 * Product controller instance.
	 *
	 * @var UCP_WC_Product_Controller
	 */
	protected $product_controller;

	/**
	 * Category controller instance.
	 *
	 * @var UCP_WC_Category_Controller
	 */
	protected $category_controller;

	/**
	 * Cart controller instance.
	 *
	 * @var UCP_WC_Cart_Controller
	 */
	protected $cart_controller;

	/**
	 * Shipping controller instance.
	 *
	 * @var UCP_WC_Shipping_Controller
	 */
	protected $shipping_controller;

	/**
	 * Coupon controller instance.
	 *
	 * @var UCP_WC_Coupon_Controller
	 */
	protected $coupon_controller;

	/**
	 * Customer controller instance.
	 *
	 * @var UCP_WC_Customer_Controller
	 */
	protected $customer_controller;

	/**
	 * Review controller instance.
	 *
	 * @var UCP_WC_Review_Controller
	 */
	protected $review_controller;

	/**
	 * WooCommerce hooks handler instance.
	 *
	 * @var UCP_WC_Woo_Hooks
	 */
	protected $woo_hooks;

	/**
	 * Auth handler instance.
	 *
	 * @var UCP_WC_Auth
	 */
	protected $auth;

	/**
	 * Auth controller instance.
	 *
	 * @var UCP_WC_Auth_Controller
	 */
	protected $auth_controller;

	/**
	 * Initialize the loader.
	 */
	public function __construct() {
		$this->well_known          = new UCP_WC_Well_Known();
		$this->auth                = new UCP_WC_Auth();
		$this->auth_controller     = new UCP_WC_Auth_Controller();
		$this->checkout_controller = new UCP_WC_Checkout_Controller();
		$this->order_controller    = new UCP_WC_Order_Controller();
		$this->product_controller  = new UCP_WC_Product_Controller();
		$this->category_controller = new UCP_WC_Category_Controller();
		$this->cart_controller     = new UCP_WC_Cart_Controller();
		$this->shipping_controller = new UCP_WC_Shipping_Controller();
		$this->coupon_controller   = new UCP_WC_Coupon_Controller();
		$this->customer_controller = new UCP_WC_Customer_Controller();
		$this->review_controller   = new UCP_WC_Review_Controller();
		$this->woo_hooks           = new UCP_WC_Woo_Hooks();
	}

	/**
	 * Add an action hook.
	 *
	 * @param string $hook          The name of the WordPress action.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the callback method.
	 * @param int    $priority      The priority of the action.
	 * @param int    $accepted_args The number of arguments accepted.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a filter hook.
	 *
	 * @param string $hook          The name of the WordPress filter.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the callback method.
	 * @param int    $priority      The priority of the filter.
	 * @param int    $accepted_args The number of arguments accepted.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Utility function to register hooks into a collection.
	 *
	 * @param array  $hooks         The collection of hooks.
	 * @param string $hook          The name of the hook.
	 * @param object $component     A reference to the instance of the object.
	 * @param string $callback      The name of the callback method.
	 * @param int    $priority      The priority of the hook.
	 * @param int    $accepted_args The number of arguments accepted.
	 * @return array
	 */
	protected function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return $hooks;
	}

	/**
	 * Register all hooks with WordPress.
	 */
	public function run() {
		// Register /.well-known/ucp endpoint.
		$this->add_action( 'init', $this->well_known, 'register_rewrite_rules' );
		$this->add_action( 'parse_request', $this->well_known, 'handle_request' );
		$this->add_filter( 'query_vars', $this->well_known, 'add_query_vars' );

		// Register REST API endpoints.
		$this->add_action( 'rest_api_init', $this->auth_controller, 'register_routes' );
		$this->add_action( 'rest_api_init', $this->checkout_controller, 'register_routes' );
		$this->add_action( 'rest_api_init', $this->order_controller, 'register_routes' );
		$this->add_action( 'rest_api_init', $this->product_controller, 'register_routes' );
		$this->add_action( 'rest_api_init', $this->category_controller, 'register_routes' );
		$this->add_action( 'rest_api_init', $this->cart_controller, 'register_routes' );
		$this->add_action( 'rest_api_init', $this->shipping_controller, 'register_routes' );
		$this->add_action( 'rest_api_init', $this->coupon_controller, 'register_routes' );
		$this->add_action( 'rest_api_init', $this->customer_controller, 'register_routes' );
		$this->add_action( 'rest_api_init', $this->review_controller, 'register_routes' );

		// Register WooCommerce hooks.
		$this->add_action( 'woocommerce_new_order', $this->woo_hooks, 'on_order_created', 10, 2 );
		$this->add_action( 'woocommerce_order_status_changed', $this->woo_hooks, 'on_order_status_changed', 10, 4 );
		$this->add_action( 'woocommerce_payment_complete', $this->woo_hooks, 'on_payment_complete', 10, 1 );
		$this->add_action( 'woocommerce_order_refunded', $this->woo_hooks, 'on_order_refunded', 10, 2 );

		// Execute all registered hooks.
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
