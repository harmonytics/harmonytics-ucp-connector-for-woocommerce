=== Harmonytics UCP Connector for WooCommerce ===
Contributors: harmonytics
Tags: woocommerce, ucp, ai, checkout, api
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable AI agents to discover, browse, and complete purchases on your WooCommerce store using the Universal Commerce Protocol (UCP).

== Description ==

**Harmonytics UCP Connector for WooCommerce** brings the power of the [Universal Commerce Protocol](https://ucp.dev) to your WooCommerce store, making it accessible to AI shopping agents and automated commerce platforms.

== Disclaimer ==

- "WooCommerce" is a registered trademark of Automattic Inc. This plugin is not affiliated with, endorsed, or sponsored by Automattic.
- "UCP" (Universal Commerce Protocol) is a project by Google. This plugin is not affiliated with, endorsed, or sponsored by Google.

= What is UCP? =

The Universal Commerce Protocol (UCP) is an open standard that enables AI agents to interact with e-commerce stores programmatically. With UCP, AI assistants can:

* Discover your store and its capabilities
* Browse products and categories
* Search and filter products
* Manage shopping carts
* Create checkout sessions
* Complete purchases on behalf of users
* Track order status and updates

= Key Features =

**Discovery Endpoint**
Your store becomes discoverable at `/.well-known/ucp` with a complete business profile including capabilities, policies, and API endpoints.

**Product Catalog**
Full product browsing capabilities:
* List and search products with filters (price, stock, category)
* Get detailed product information including variants
* Browse product categories with hierarchy
* Access product reviews and ratings

**Shopping Cart**
Persistent cart management for AI agents:
* Create and manage multiple carts
* Add, update, and remove items
* Automatic stock validation
* Cart expiration handling
* Convert cart to checkout session

**Checkout Sessions**
AI agents can create and manage checkout sessions via REST API:
* Add products by SKU, product ID, or variation ID
* Set shipping and billing addresses
* Apply coupon codes
* Select shipping methods
* Get real-time totals and tax calculations

**Order Management**
Full order lifecycle support:
* Retrieve order details in UCP format
* List orders with filtering and pagination
* Access order event timeline
* Track status changes

**Customer Management**
Customer operations for authenticated agents:
* Create and update customer profiles
* Manage customer addresses
* View order history

**Shipping Rates**
Real-time shipping calculations:
* Get available shipping methods
* Calculate rates based on address and cart contents
* Support for all WooCommerce shipping zones

**Public Coupons**
Expose promotional codes to AI agents:
* Mark coupons as "UCP Public" in admin
* AI agents can discover and apply valid coupons
* Automatic expiry validation

**API Key Authentication**
Secure authentication for AI agents:
* Generate API keys with granular permissions (read, write, admin)
* Key-based authentication via header or query parameter
* Usage tracking and key management

**Webhook Notifications**
Real-time event notifications to your platform:
* `order.created` - New order placed
* `order.status_changed` - Status updates
* `order.paid` - Payment completed
* `order.refunded` - Refund issued

All webhooks are signed with HMAC-SHA256 for security.

**Guest Checkout Support**
Works without requiring customer accounts, perfect for agent-driven purchases.

= Use Cases =

* **AI Shopping Assistants** - Let ChatGPT, Claude, or custom AI agents shop on your store
* **Automated Procurement** - Enable B2B systems to place orders programmatically
* **Voice Commerce** - Power voice-activated shopping experiences
* **Conversational Commerce** - Integrate with messaging platforms and chatbots
* **Multi-platform Aggregators** - Allow comparison shopping services to include your products

= REST API Endpoints =

**Discovery**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/.well-known/ucp` | Discovery / Business Profile |

**Authentication**

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/auth/keys` | Generate API key |
| GET | `/wp-json/ucp/v1/auth/keys` | List API keys |
| DELETE | `/wp-json/ucp/v1/auth/keys/{id}` | Revoke API key |

**Products**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/products` | List products |
| GET | `/wp-json/ucp/v1/products/{id}` | Get product details |
| GET | `/wp-json/ucp/v1/products/search` | Search products |
| GET | `/wp-json/ucp/v1/products/sku/{sku}` | Get product by SKU |

**Categories**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/categories` | List categories |
| GET | `/wp-json/ucp/v1/categories/{id}` | Get category details |
| GET | `/wp-json/ucp/v1/categories/{id}/products` | Products in category |

**Cart**

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/carts` | Create cart |
| GET | `/wp-json/ucp/v1/carts/{id}` | Get cart |
| POST | `/wp-json/ucp/v1/carts/{id}/items` | Add item to cart |
| PATCH | `/wp-json/ucp/v1/carts/{id}/items/{key}` | Update item quantity |
| DELETE | `/wp-json/ucp/v1/carts/{id}/items/{key}` | Remove item |
| POST | `/wp-json/ucp/v1/carts/{id}/checkout` | Convert to checkout |

**Checkout**

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/checkout/sessions` | Create checkout session |
| GET | `/wp-json/ucp/v1/checkout/sessions/{id}` | Get session details |
| PATCH | `/wp-json/ucp/v1/checkout/sessions/{id}` | Update session |
| POST | `/wp-json/ucp/v1/checkout/sessions/{id}/confirm` | Confirm checkout |

**Orders**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/orders` | List orders |
| GET | `/wp-json/ucp/v1/orders/{id}` | Get order details |
| GET | `/wp-json/ucp/v1/orders/{id}/events` | Order timeline |

**Customers**

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/customers` | Create customer |
| GET | `/wp-json/ucp/v1/customers/{id}` | Get customer |
| PATCH | `/wp-json/ucp/v1/customers/{id}` | Update customer |

**Shipping**

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/ucp/v1/shipping/rates` | Calculate shipping rates |

**Reviews**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/reviews` | List reviews |
| GET | `/wp-json/ucp/v1/reviews/{id}` | Get review |
| POST | `/wp-json/ucp/v1/reviews` | Create review |

**Coupons**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/ucp/v1/coupons` | List public coupons |
| POST | `/wp-json/ucp/v1/coupons/validate` | Validate coupon code |

= Requirements =

* WordPress 6.0 or higher
* WooCommerce 8.0 or higher
* PHP 8.0 or higher
* HTTPS enabled (required for secure API access)

== Installation ==

1. Upload the `harmonytics-ucp-connector-for-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce → UCP to configure settings
4. Generate an API key for your AI agent
5. Verify your discovery endpoint at `https://your-store.com/.well-known/ucp`

= Configuration =

1. **Enable UCP** - Toggle the integration on/off
2. **Guest Checkout** - Allow purchases without customer accounts (recommended)
3. **API Keys** - Generate keys for AI agent authentication
4. **Public Coupons** - Mark coupons as discoverable by AI agents
5. **Webhook URL** - Optional: Enter a URL to receive order event notifications
6. **Debug Logging** - Enable for troubleshooting

== Frequently Asked Questions ==

= How do AI agents authenticate? =

AI agents authenticate using API keys. Generate a key in WooCommerce → UCP → API Keys, then include it in requests:

* Header: `X-UCP-API-Key: your_key_id:your_secret`
* Query: `?ucp_api_key=your_key_id:your_secret`

= Is this compatible with all payment gateways? =

Harmonytics UCP Connector for WooCommerce works with any payment gateway. For AI-driven checkouts, the plugin returns a web checkout URL where customers complete payment securely. This ensures compatibility with 3D Secure, PayPal, and other redirect-based payment flows.

= Do I need to modify my theme? =

No. Harmonytics UCP Connector for WooCommerce operates entirely via REST API and doesn't affect your theme or frontend.

= Is it secure? =

Yes. The plugin follows WordPress and WooCommerce security best practices:
* API key authentication with hashed secrets
* All API inputs are validated and sanitized
* Webhook signatures use HMAC-SHA256
* Sessions expire after 24 hours
* No sensitive data is exposed in the discovery endpoint
* Prepared statements for all database queries
* Object caching for performance and security

= Can I use this with my existing WooCommerce extensions? =

Yes. Harmonytics UCP Connector for WooCommerce integrates with WooCommerce core functionality and should work with most extensions. Shipping methods, tax calculations, and coupons are fully supported.

= What happens when an AI agent places an order? =

1. Agent authenticates with API key
2. Agent browses products or creates a cart
3. Agent creates a checkout session via API
4. Products are validated and added to an order
5. Agent provides shipping address and selects shipping method
6. If payment is required, agent receives a web checkout URL
7. Customer completes payment on your website
8. Order is confirmed and agent receives confirmation

= How do I test the integration? =

1. Enable the plugin and generate an API key
2. Visit `https://your-store.com/.well-known/ucp` to verify discovery
3. Use a tool like Postman or cURL to test the API
4. For webhook testing, use services like webhook.site

= Can AI agents apply coupon codes? =

Yes. You can mark coupons as "UCP Public" in the coupon edit screen. AI agents can then discover and apply these coupons. Non-public coupons can still be applied if the agent knows the code.

== Privacy Policy ==

Harmonytics UCP Connector for WooCommerce is designed with privacy in mind.

= Data Storage =

This plugin stores the following data locally in your WordPress database:
* API keys (hashed secrets, never stored in plain text)
* Cart data (items, expiration, linked to anonymous cart IDs)
* Checkout session data (cart contents, shipping addresses, order references)
* Webhook signing keys (for secure webhook delivery)
* Failed webhook records (for retry purposes, automatically purged after 24 hours)

= External Services =

This plugin does NOT send any data to external servers by default.

**Optional Webhook Feature:**
If you configure a webhook URL in the plugin settings, the following data will be sent to that URL when order events occur:
* Order ID and status
* Order event type (created, paid, status changed, refunded)
* Your store URL and plugin version

Webhooks are:
* Disabled by default (no URL configured)
* Only sent to URLs you explicitly configure
* Signed with HMAC-SHA256 for security
* Never sent to the plugin developers

You are responsible for ensuring your webhook endpoint complies with applicable privacy laws.

= Third-Party Services =

This plugin implements the [Universal Commerce Protocol (UCP)](https://ucp.dev) specification. The UCP discovery endpoint (`/.well-known/ucp`) exposes only public business information that you configure in WooCommerce settings (store name, URL, currency). No customer data is exposed through the discovery endpoint.

== Screenshots ==

1. UCP Settings page in WooCommerce admin
2. API Key management interface
3. Discovery endpoint JSON response
4. Checkout session creation flow
5. Order details in UCP format

== Changelog ==

= 1.0.0 =
* Initial release
* Discovery endpoint (`/.well-known/ucp`)
* API key authentication system
* Product catalog API (list, search, filter, details)
* Category browsing with hierarchy
* Shopping cart management
* Checkout capability with session management
* Order capability with event timeline
* Customer management API
* Shipping rates calculation
* Product reviews API
* Public coupons for AI agents
* Webhook support with HMAC-SHA256 signing
* Admin settings panel
* Object caching for performance

== Upgrade Notice ==

= 1.0.0 =
Initial release of Harmonytics UCP Connector for WooCommerce.

== Technical Documentation ==

For detailed API documentation and integration guides, visit:
https://harmonytics.com/plugins/harmonytics-ucp-connector-for-woocommerce/docs

= Example: Authenticate and Create Cart =

`
# Generate API Key (admin only)
POST /wp-json/ucp/v1/auth/keys
{
  "description": "My AI Agent",
  "permissions": ["read", "write"]
}

# Response includes key_id and secret (shown only once)
{
  "key_id": "ucp_abc123...",
  "secret": "ucp_secret_xyz789..."
}

# Use the key to create a cart
POST /wp-json/ucp/v1/carts
X-UCP-API-Key: ucp_abc123...:ucp_secret_xyz789...

# Add items to cart
POST /wp-json/ucp/v1/carts/{cart_id}/items
{
  "sku": "PROD-001",
  "quantity": 2
}
`

= Example: Search Products =

`
GET /wp-json/ucp/v1/products/search?q=blue+shirt&min_price=20&max_price=100&in_stock=true
`

= Example: Get Shipping Rates =

`
POST /wp-json/ucp/v1/shipping/rates
{
  "address": {
    "country": "US",
    "state": "NY",
    "postcode": "10001",
    "city": "New York"
  },
  "items": [
    {"product_id": 123, "quantity": 2}
  ]
}
`

= Example: Discovery Response =

`
GET /.well-known/ucp

{
  "schema_version": "1.0",
  "business": {
    "name": "My Store",
    "url": "https://my-store.com",
    "currency": "USD"
  },
  "capabilities": {
    "products": {
      "enabled": true,
      "rest": {"endpoint": "https://my-store.com/wp-json/ucp/v1/products"}
    },
    "cart": {
      "enabled": true,
      "rest": {"endpoint": "https://my-store.com/wp-json/ucp/v1/carts"}
    },
    "checkout": {
      "enabled": true,
      "rest": {"endpoint": "https://my-store.com/wp-json/ucp/v1/checkout"}
    },
    "order": {
      "enabled": true,
      "rest": {"endpoint": "https://my-store.com/wp-json/ucp/v1/orders"}
    }
  }
}
`

== Support ==

For support inquiries, please contact:
* Email: support@harmonytics.com
* Website: https://harmonytics.com/support

== Credits ==

Developed by [Harmonytics OÜ](https://harmonytics.com)

Harmonytics UCP Connector for WooCommerce implements the [Universal Commerce Protocol](https://ucp.dev) specification.
