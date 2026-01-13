# UCP for WooCommerce

[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg)](https://php.net/)

Universal Commerce Protocol (UCP) integration for WooCommerce. Enable AI agents to discover, browse, and complete purchases on your store.

## What is UCP?

The [Universal Commerce Protocol](https://ucp.dev) is an open standard that enables AI agents to interact with e-commerce stores programmatically. With UCP, AI assistants like ChatGPT and Claude can shop on behalf of users.

## Features

- **Discovery Endpoint** - `/.well-known/ucp` exposes your store's capabilities
- **Checkout Sessions** - REST API for cart management and order creation
- **Order Management** - Retrieve order details in UCP schema format
- **Webhooks** - Real-time order event notifications with HMAC-SHA256 signing
- **Guest Checkout** - No customer account required for agent purchases

## Installation

### From GitHub

```bash
cd wp-content/plugins/
git clone https://github.com/harmonytics/ucp-for-woocommerce.git
cd ucp-for-woocommerce
composer install --no-dev
```

Then activate the plugin in WordPress admin.

### From WordPress.org

Search for "UCP for WooCommerce" in Plugins > Add New.

## REST API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/.well-known/ucp` | Discovery / Business Profile |
| POST | `/wp-json/ucp/v1/checkout/sessions` | Create checkout session |
| GET | `/wp-json/ucp/v1/checkout/sessions/{id}` | Get session details |
| PATCH | `/wp-json/ucp/v1/checkout/sessions/{id}` | Update session |
| POST | `/wp-json/ucp/v1/checkout/sessions/{id}/confirm` | Confirm checkout |
| GET | `/wp-json/ucp/v1/orders/{id}` | Get order details |
| GET | `/wp-json/ucp/v1/orders` | List orders |

## Quick Start

### 1. Verify Discovery

```bash
curl https://your-store.com/.well-known/ucp
```

### 2. Create Checkout Session

```bash
curl -X POST https://your-store.com/wp-json/ucp/v1/checkout/sessions \
  -H "Content-Type: application/json" \
  -d '{
    "items": [{"sku": "PROD-001", "quantity": 2}],
    "shipping_address": {
      "first_name": "John",
      "last_name": "Doe",
      "address_1": "123 Main St",
      "city": "New York",
      "state": "NY",
      "postcode": "10001",
      "country": "US",
      "email": "john@example.com"
    }
  }'
```

## Development

### Requirements

- PHP 8.0+
- WordPress 6.0+
- WooCommerce 8.0+
- Composer

### Setup

```bash
git clone https://github.com/harmonytics/ucp-for-woocommerce.git
cd ucp-for-woocommerce
composer install
```

### Testing

```bash
composer test
```

### Coding Standards

```bash
composer phpcs    # Check
composer phpcbf   # Auto-fix
```

## Documentation

- [UCP Specification](https://ucp.dev/specification/overview/)
- [Plugin Documentation](https://github.com/harmonytics/ucp-for-woocommerce/wiki)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Support

- **Issues:** [GitHub Issues](https://github.com/harmonytics/ucp-for-woocommerce/issues)
- **Email:** support@harmonytics.com

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.

## Credits

Developed by [Harmonytics](https://harmonytics.com)
