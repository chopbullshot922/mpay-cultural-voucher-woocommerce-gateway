# Hooks Reference

## Overview

The plugin uses WordPress and WooCommerce hooks for extensibility. This documents the hooks fired by the plugin and the core hooks it attaches to.

## Actions Fired by the Plugin

| Action | When Fired | Parameters |
|--------|-----------|------------|
| `mpay_vg_before_redirect` | Before customer redirects to MPay | $order_id, $redirect_params |
| `mpay_vg_order_details_requested` | MPay calls GetOrderDetails | $order_id, $order_key |
| `mpay_vg_payment_confirmed` | Payment successfully confirmed | $order_id, $payment_data |
| `mpay_vg_payment_duplicate` | Duplicate confirmation received | $order_id, $order_key |
| `mpay_vg_signature_verified` | Signature verification passes | $operation, $order_key |
| `mpay_vg_signature_failed` | Signature verification fails | $operation, $error_message |
| `mpay_vg_certificate_expiring` | Certificate within 30 days of expiry | $days_remaining, $expiry_date |

## Filters Provided by the Plugin

| Filter | Purpose | Parameters |
|--------|---------|------------|
| `mpay_vg_redirect_params` | Modify redirect form parameters | $params, $order_id |
| `mpay_vg_order_description` | Customize order description for MPay | $description, $order |
| `mpay_vg_soap_endpoint_path` | Change SOAP endpoint path | $path |
| `mpay_vg_eligible_categories` | Modify eligible category IDs | $category_ids |
| `mpay_vg_idnp_validation` | Custom IDNP validation logic | $valid, $idnp_value |
| `mpay_vg_payment_status` | Override post-payment order status | $status, $order_id, $payment_data |
| `mpay_vg_lock_duration` | Change transient lock seconds | $seconds (default: 300) |

## WordPress/WooCommerce Hooks Used

### Actions

| Hook | Purpose |
|------|---------|
| `plugins_loaded` | Plugin initialization, load text domain |
| `init` | Register SOAP endpoint rewrite rules |
| `admin_notices` | Display certificate expiry warnings |
| `woocommerce_thankyou` | Custom thank-you page output for MPay orders |
| `woocommerce_order_status_changed` | Track status transitions for logging |
| `wp_enqueue_scripts` | Enqueue checkout JS for IDNP field |
| `admin_enqueue_scripts` | Enqueue admin settings page assets |
| `woocommerce_admin_order_data_after_billing_address` | Display payment meta in admin |

### Filters

| Hook | Purpose |
|------|---------|
| `woocommerce_payment_gateways` | Register the MPay gateway class |
| `woocommerce_available_payment_gateways` | Conditionally show/hide based on eligibility |
| `woocommerce_checkout_fields` | Add IDNP field when Cultural Voucher selected |
| `query_vars` | Register custom query variable for SOAP endpoint |
| `template_redirect` | Intercept requests to SOAP endpoint path |

## Usage Examples

```php
// Log payment confirmations
add_action('mpay_vg_payment_confirmed', function($order_id, $payment_data) {
    error_log("MPay confirmed order $order_id: " . $payment_data['reference']);
}, 10, 2);

// Custom order description for MPay
add_filter('mpay_vg_order_description', function($description, $order) {
    return 'Order #' . $order->get_order_number() . ' - ' . get_bloginfo('name');
}, 10, 2);

// Auto-complete downloadable orders
add_filter('mpay_vg_payment_status', function($status, $order_id, $payment_data) {
    $order = wc_get_order($order_id);
    if ($order && $order->is_downloadable()) {
        return 'completed';
    }
    return $status;
}, 10, 3);
```
