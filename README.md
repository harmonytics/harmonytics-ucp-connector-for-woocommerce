# UCP for WooCommerce

[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4.svg)](https://php.net/)

Universal Commerce Protocol (UCP) integration for WooCommerce. Enable AI agents to discover, browse, and complete purchases on your store.

## What is UCP?

The [Universal Commerce Protocol](https://ucp.dev) is an open standard that enables AI agents to interact with e-commerce stores programmatically. With UCP, AI assistants like ChatGPT and Claude can shop on behalf of users.

## Features

| Feature | Description |
|---------|-------------|
| **Discovery** | `/.well-known/ucp` exposes your store's capabilities |
| **Products** | Browse, search, and filter products with full details |
| **Categories** | Navigate product categories with hierarchy |
| **Cart** | Persistent cart management for AI agents |
| **Checkout** | Create and manage checkout sessions |
| **Orders** | Retrieve order details and track status |
| **Customers** | Create and manage customer profiles |
| **Shipping** | Calculate real-time shipping rates |
| **Reviews** | Access product reviews and ratings |
| **Coupons** | Discover and validate promotional codes |
| **Webhooks** | Real-time order event notifications |
| **API Keys** | Secure authentication with granular permissions |

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

## Quick Start

### 1. Generate API Key

Go to **WooCommerce → UCP → API Keys** and create a new key with the required permissions.

### 2. Verify Discovery

```bash
curl https://your-store.com/.well-known/ucp
```

### 3. Authenticate Requests

Include the API key in your requests:

```bash
# Via header (recommended)
curl -H "X-UCP-API-Key: ucp_xxx:ucp_secret_xxx" \
  https://your-store.com/wp-json/ucp/v1/products

# Via query parameter
curl "https://your-store.com/wp-json/ucp/v1/products?ucp_api_key=ucp_xxx:ucp_secret_xxx"
```

### 4. Browse Products

```bash
# List products
curl https://your-store.com/wp-json/ucp/v1/products

# Search products
curl "https://your-store.com/wp-json/ucp/v1/products/search?q=shirt&in_stock=true"

# Get product by SKU
curl https://your-store.com/wp-json/ucp/v1/products/sku/PROD-001
```

### 5. Create Cart and Checkout

```bash
# Create cart
curl -X POST https://your-store.com/wp-json/ucp/v1/carts \
  -H "X-UCP-API-Key: ucp_xxx:ucp_secret_xxx"

# Add item
curl -X POST https://your-store.com/wp-json/ucp/v1/carts/{cart_id}/items \
  -H "Content-Type: application/json" \
  -d '{"sku": "PROD-001", "quantity": 2}'

# Convert to checkout
curl -X POST https://your-store.com/wp-json/ucp/v1/carts/{cart_id}/checkout \
  -H "Content-Type: application/json" \
  -d '{
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

## REST API Endpoints

### Discovery

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/.well-known/ucp` | Business profile and capabilities |

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/auth/keys` | Generate API key |
| GET | `/wp-json/ucp/v1/auth/keys` | List API keys |
| DELETE | `/wp-json/ucp/v1/auth/keys/{id}` | Revoke API key |

### Products

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/products` | List products |
| GET | `/wp-json/ucp/v1/products/{id}` | Get product details |
| GET | `/wp-json/ucp/v1/products/search` | Search products |
| GET | `/wp-json/ucp/v1/products/sku/{sku}` | Get product by SKU |

### Categories

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/categories` | List categories |
| GET | `/wp-json/ucp/v1/categories/{id}` | Get category details |
| GET | `/wp-json/ucp/v1/categories/{id}/products` | Products in category |

### Cart

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/carts` | Create cart |
| GET | `/wp-json/ucp/v1/carts/{id}` | Get cart |
| DELETE | `/wp-json/ucp/v1/carts/{id}` | Delete cart |
| POST | `/wp-json/ucp/v1/carts/{id}/items` | Add item |
| PATCH | `/wp-json/ucp/v1/carts/{id}/items/{key}` | Update quantity |
| DELETE | `/wp-json/ucp/v1/carts/{id}/items/{key}` | Remove item |
| POST | `/wp-json/ucp/v1/carts/{id}/checkout` | Convert to checkout |

### Checkout

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/checkout/sessions` | Create session |
| GET | `/wp-json/ucp/v1/checkout/sessions/{id}` | Get session |
| PATCH | `/wp-json/ucp/v1/checkout/sessions/{id}` | Update session |
| POST | `/wp-json/ucp/v1/checkout/sessions/{id}/confirm` | Confirm checkout |

### Orders

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/orders` | List orders |
| GET | `/wp-json/ucp/v1/orders/{id}` | Get order details |
| GET | `/wp-json/ucp/v1/orders/{id}/events` | Order timeline |

### Customers

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/customers` | Create customer |
| GET | `/wp-json/ucp/v1/customers/{id}` | Get customer |
| PATCH | `/wp-json/ucp/v1/customers/{id}` | Update customer |

### Shipping

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/shipping/rates` | Calculate rates |

### Reviews

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/reviews` | List reviews |
| GET | `/wp-json/ucp/v1/reviews/{id}` | Get review |
| POST | `/wp-json/ucp/v1/reviews` | Create review |

### Coupons

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/coupons` | List public coupons |
| POST | `/wp-json/ucp/v1/coupons/validate` | Validate coupon |

## Authentication

API keys provide secure access for AI agents:

```php
// Key format
key_id:secret
// Example
ucp_abc123def456:ucp_secret_xyz789...
```

### Permission Levels

| Permission | Access |
|------------|--------|
| `read` | Browse products, categories, reviews |
| `write` | Create carts, checkout sessions, orders |
| `admin` | Manage API keys, access all endpoints |

### Including in Requests

```bash
# Header (recommended)
X-UCP-API-Key: ucp_xxx:ucp_secret_xxx

# Query parameter
?ucp_api_key=ucp_xxx:ucp_secret_xxx
```

## Webhooks

Receive real-time notifications for order events:

| Event | Trigger |
|-------|---------|
| `order.created` | New order placed |
| `order.status_changed` | Status updated |
| `order.paid` | Payment completed |
| `order.refunded` | Refund issued |

All webhooks are signed with HMAC-SHA256. Verify the `X-UCP-Signature` header.

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

### Plugin Check

```bash
composer plugin-check
```

## Security

- API secrets are hashed (never stored in plain text)
- All inputs validated and sanitized
- Prepared statements for all database queries
- Object caching for performance
- Webhook signatures use HMAC-SHA256
- Sessions expire after 24 hours

## Documentation

- [UCP Specification](https://ucp.dev/specification/overview/)
- [Plugin Documentation](https://harmonytics.com/plugins/ucp-for-woocommerce/docs)
- [GitHub Wiki](https://github.com/harmonytics/ucp-for-woocommerce/wiki)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Support

- **Issues:** [GitHub Issues](https://github.com/harmonytics/ucp-for-woocommerce/issues)
- **Email:** support@harmonytics.com
- **Website:** [harmonytics.com/support](https://harmonytics.com/support)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.

## Credits

Developed by [Harmonytics OÜ](https://harmonytics.com)

UCP for WooCommerce implements the [Universal Commerce Protocol](https://ucp.dev) specification.
