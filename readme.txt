=== UCP for WooCommerce ===
Contributors: harmonytics
Tags: woocommerce, ucp, ai, commerce, checkout
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable AI agents to discover, browse, and complete purchases on your WooCommerce store using the Universal Commerce Protocol (UCP).

== Description ==

**UCP for WooCommerce** brings the power of the [Universal Commerce Protocol](https://ucp.dev) to your WooCommerce store, making it accessible to AI shopping agents and automated commerce platforms.

= What is UCP? =

The Universal Commerce Protocol (UCP) is an open standard that enables AI agents to interact with e-commerce stores programmatically. With UCP, AI assistants can:

* Discover your store and its capabilities
* Browse products and check availability
* Create checkout sessions
* Complete purchases on behalf of users
* Track order status and updates

= Key Features =

**Discovery Endpoint**
Your store becomes discoverable at `/.well-known/ucp` with a complete business profile including capabilities, policies, and API endpoints.

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

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/.well-known/ucp` | Discovery / Business Profile |
| POST | `/wp-json/ucp/v1/checkout/sessions` | Create checkout session |
| GET | `/wp-json/ucp/v1/checkout/sessions/{id}` | Get session details |
| PATCH | `/wp-json/ucp/v1/checkout/sessions/{id}` | Update session |
| POST | `/wp-json/ucp/v1/checkout/sessions/{id}/confirm` | Confirm checkout |
| GET | `/wp-json/ucp/v1/orders/{id}` | Get order details |
| GET | `/wp-json/ucp/v1/orders` | List orders |
| GET | `/wp-json/ucp/v1/orders/{id}/events` | Order timeline |

= Requirements =

* WordPress 6.0 or higher
* WooCommerce 8.0 or higher
* PHP 8.0 or higher
* HTTPS enabled (required for secure API access)

== Installation ==

1. Upload the `ucp-for-woocommerce` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce → UCP to configure settings
4. Verify your discovery endpoint at `https://your-store.com/.well-known/ucp`

= Configuration =

1. **Enable UCP** - Toggle the integration on/off
2. **Guest Checkout** - Allow purchases without customer accounts (recommended)
3. **Webhook URL** - Optional: Enter a URL to receive order event notifications
4. **Debug Logging** - Enable for troubleshooting

== Frequently Asked Questions ==

= Is this compatible with all payment gateways? =

UCP for WooCommerce works with any payment gateway. For AI-driven checkouts, the plugin returns a web checkout URL where customers complete payment securely. This ensures compatibility with 3D Secure, PayPal, and other redirect-based payment flows.

= Do I need to modify my theme? =

No. UCP for WooCommerce operates entirely via REST API and doesn't affect your theme or frontend.

= Is it secure? =

Yes. The plugin follows WordPress and WooCommerce security best practices:
* All API inputs are validated and sanitized
* Webhook signatures use HMAC-SHA256
* Sessions expire after 24 hours
* No sensitive data is exposed in the discovery endpoint

= Can I use this with my existing WooCommerce extensions? =

Yes. UCP for WooCommerce integrates with WooCommerce core functionality and should work with most extensions. Shipping methods, tax calculations, and coupons are fully supported.

= What happens when an AI agent places an order? =

1. Agent creates a checkout session via API
2. Products are validated and added to an order
3. Agent provides shipping address and selects shipping method
4. If payment is required, agent receives a web checkout URL
5. Customer completes payment on your website
6. Order is confirmed and agent receives confirmation

= How do I test the integration? =

1. Enable the plugin
2. Visit `https://your-store.com/.well-known/ucp` to verify discovery
3. Use a tool like Postman or cURL to test the checkout API
4. For webhook testing, use services like webhook.site

== Privacy Policy ==

UCP for WooCommerce is designed with privacy in mind.

= Data Storage =

This plugin stores the following data locally in your WordPress database:
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
2. Discovery endpoint JSON response
3. Checkout session creation flow
4. Order details in UCP format

== Changelog ==

= 1.0.0 =
* Initial release
* Discovery endpoint (`/.well-known/ucp`)
* Checkout capability with session management
* Order capability with event timeline
* Webhook support with HMAC-SHA256 signing
* Admin settings panel

== Upgrade Notice ==

= 1.0.0 =
Initial release of UCP for WooCommerce.

== Technical Documentation ==

For detailed API documentation and integration guides, visit:
https://harmonytics.com/plugins/ucp-for-woocommerce/docs

= Example: Create Checkout Session =

`
POST /wp-json/ucp/v1/checkout/sessions
Content-Type: application/json

{
  "items": [
    {
      "sku": "PROD-001",
      "quantity": 2
    }
  ],
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
    "checkout": {
      "enabled": true,
      "rest": {
        "endpoint": "https://my-store.com/wp-json/ucp/v1/checkout"
      }
    },
    "order": {
      "enabled": true,
      "rest": {
        "endpoint": "https://my-store.com/wp-json/ucp/v1/orders"
      }
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

UCP for WooCommerce implements the [Universal Commerce Protocol](https://ucp.dev) specification.
