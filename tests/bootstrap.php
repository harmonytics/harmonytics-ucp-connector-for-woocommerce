<?php
// SPDX-License-Identifier: GPL-2.0-or-later
/**
 * PHPUnit bootstrap file for WooCommerce UCP tests.
 *
 * @package WooCommerce_UCP
 * @copyright 2026 Harmonytics OÜ
 * @license GPL-2.0-or-later
 */

// Composer autoloader
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
    require_once $autoloader;
}

// Define test constants
define( 'UCP_WC_TESTING', true );
// Disable WP-Cron during tests to avoid core warnings accessing REQUEST_URI in CLI.
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
    define( 'DISABLE_WP_CRON', true );
}

// Provide sane server defaults for CLI test runs.
if ( ! isset( $_SERVER['REQUEST_URI'] ) || null === $_SERVER['REQUEST_URI'] ) {
    $_SERVER['REQUEST_URI'] = '/';
}
if ( ! isset( $_SERVER['HTTP_HOST'] ) ) {
    $_SERVER['HTTP_HOST'] = 'example.org';
}
if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
    $_SERVER['SERVER_NAME'] = 'example.org';
}

// Load WordPress test environment
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// WooCommerce path
$_wc_dir = getenv( 'WP_CORE_DIR' ) ? getenv( 'WP_CORE_DIR' ) . '/wp-content/plugins/woocommerce' : dirname( dirname( __DIR__ ) ) . '/woocommerce';
define( 'UCP_WC_TEST_WC_DIR', $_wc_dir );

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
} elseif ( file_exists( dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/' );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
    echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    // Load WooCommerce first
    require UCP_WC_TEST_WC_DIR . '/woocommerce.php';

    // Load our plugin
    require dirname( __DIR__ ) . '/harmonytics-ucp-connector-woocommerce.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Install WooCommerce tables after WP is loaded.
 */
function _install_woocommerce() {
    global $wpdb;

    // Ensure WooCommerce is defined
    if ( ! defined( 'WC_ABSPATH' ) ) {
        return;
    }

    // Include WooCommerce install
    include_once WC_ABSPATH . 'includes/class-wc-install.php';

    // Install WooCommerce
    WC_Install::install();

    // Reload capabilities after install
    $GLOBALS['wp_roles'] = null;
    wp_roles();
}

tests_add_filter( 'setup_theme', '_install_woocommerce' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Load WooCommerce test helpers if available
$_wc_tests_framework = $_wc_dir . '/tests/legacy/framework';
if ( file_exists( $_wc_tests_framework ) ) {
    // Load WooCommerce test case
    require_once $_wc_tests_framework . '/class-wc-unit-test-case.php';
    require_once $_wc_tests_framework . '/helpers/class-wc-helper-product.php';
    require_once $_wc_tests_framework . '/helpers/class-wc-helper-order.php';
    require_once $_wc_tests_framework . '/helpers/class-wc-helper-shipping.php';
    require_once $_wc_tests_framework . '/helpers/class-wc-helper-customer.php';
    require_once $_wc_tests_framework . '/helpers/class-wc-helper-coupon.php';
} else {
    // Fallback: Create a simple WC_Unit_Test_Case if WooCommerce tests not available
    if ( ! class_exists( 'WC_Unit_Test_Case' ) ) {
        class WC_Unit_Test_Case extends WP_UnitTestCase {
            public function setUp(): void {
                parent::setUp();
            }

            public function tearDown(): void {
                parent::tearDown();
            }
        }
    }

    // Create helper stubs if not available
    if ( ! class_exists( 'WC_Helper_Product' ) ) {
        class WC_Helper_Product {
            public static function create_simple_product( $save = true, $props = array() ) {
                $product = new WC_Product_Simple();
                $product->set_name( 'Test Product ' . wp_rand() );
                $product->set_regular_price( '10.00' );
                $product->set_sku( 'test-sku-' . wp_rand() );
                $product->set_manage_stock( true );
                $product->set_stock_quantity( 100 );
                $product->set_stock_status( 'instock' );

                foreach ( $props as $key => $value ) {
                    $setter = "set_{$key}";
                    if ( method_exists( $product, $setter ) ) {
                        $product->$setter( $value );
                    }
                }

                if ( $save ) {
                    $product->save();
                }

                return $product;
            }

            public static function create_variation_product( $save = true ) {
                $product = new WC_Product_Variable();
                $product->set_name( 'Test Variable Product ' . wp_rand() );

                if ( $save ) {
                    $product->save();
                }

                return $product;
            }
        }
    }

    if ( ! class_exists( 'WC_Helper_Order' ) ) {
        class WC_Helper_Order {
            public static function create_order( $customer_id = 0, $product = null ) {
                if ( ! $product ) {
                    $product = WC_Helper_Product::create_simple_product();
                }

                $order = wc_create_order( array( 'customer_id' => $customer_id ) );
                $order->add_product( $product, 1 );
                $order->calculate_totals();
                $order->save();

                return $order;
            }
        }
    }

    if ( ! class_exists( 'WC_Helper_Customer' ) ) {
        class WC_Helper_Customer {
            public static function create_customer( $username = '', $password = '', $email = '' ) {
                $customer = new WC_Customer();
                $customer->set_email( $email ?: 'test' . wp_rand() . '@example.com' );
                $customer->set_username( $username ?: 'testuser' . wp_rand() );
                $customer->set_password( $password ?: 'password' );
                $customer->save();

                return $customer;
            }
        }
    }

    if ( ! class_exists( 'WC_Helper_Coupon' ) ) {
        class WC_Helper_Coupon {
            public static function create_coupon( $code = '' ) {
                $coupon = new WC_Coupon();
                $coupon->set_code( $code ?: 'test-coupon-' . wp_rand() );
                $coupon->set_discount_type( 'percent' );
                $coupon->set_amount( 10 );
                $coupon->save();

                return $coupon;
            }
        }
    }
}
