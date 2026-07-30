# Order Management

## Order Statuses

The plugin maps MPay payment events to WooCommerce order statuses:

| Event | Order Status |
|-------|-------------|
| Order placed, redirect to MPay | Pending Payment |
| GetOrderDetails received | Pending Payment (unchanged) |
| ConfirmOrderPayment received | Processing |
| Payment confirmation with auto-complete | Completed |
| Payment failed or declined | Failed |
| Customer abandoned (no callback received) | Pending Payment (until cancelled by WooCommerce) |

### Status Transitions

```
Pending Payment --> Processing --> Completed
       |
       +--> Failed
       |
       +--> Cancelled (by WooCommerce auto-cancel for unpaid orders)
```

The transition from Pending Payment to Processing happens automatically when MPay sends a successful ConfirmOrderPayment callback. No manual intervention is needed for normal payment flow.

## Payment Meta Box

On the order edit screen in WooCommerce admin, the plugin adds a meta box showing:

- **Payment Method** - MPay or Cultural Voucher
- **Service ID** - The MPay service identifier used
- **Order Key** - The key sent to MPay
- **Payment Reference** - MPay's transaction reference (after confirmation)
- **Payment Date** - When MPay confirmed the payment
- **Amount Paid** - Confirmed payment amount
- **IDNP** - Payer identification (Cultural Voucher only, partially masked)
- **Voucher Reference** - Cultural Voucher reference (if applicable)

## Payment Recording

When ConfirmOrderPayment is received:

1. Payment amount is recorded as order meta
2. MPay payment reference is stored
3. An order note is added with payment details
4. Order status transitions to Processing
5. WooCommerce triggers standard payment-complete actions (stock reduction, emails)

## Order Notes

The plugin adds timestamped order notes at key events:

- "MPay payment initiated - customer redirected to MPay"
- "MPay GetOrderDetails received - order details provided"
- "MPay payment confirmed - Reference: [ref] - Amount: [amount] MDL"
- "Cultural Voucher payment - IDNP: [masked] - Voucher: [ref]"
- Error notes if signature verification fails or order lookup fails

These notes are visible in the Order Notes panel on the order edit screen.

## Handling Edge Cases

### Duplicate Confirmations

If MPay sends ConfirmOrderPayment multiple times for the same order:

- First call: Processes normally, sets transient lock
- Subsequent calls within lock duration: Returns success without re-processing
- Calls after lock expires but order already paid: Checks order status, returns success

### Order Already Cancelled

If WooCommerce auto-cancelled an unpaid order before MPay's callback arrived:

- The plugin logs a warning
- The payment confirmation is recorded in notes but the order remains cancelled
- Manual review may be needed

### Amount Mismatch

If the confirmed amount differs from the order total:

- For Cultural Voucher partial payments: This is expected and handled normally
- For full payments with mismatch: The plugin logs a warning and records both amounts
- Order is still marked as paid (MPay is the authority on payment status)

## Searching Orders

MPay payment data is stored as order meta, so you can search for:

- Payment reference numbers
- Order keys
- IDNP values (if stored)

Use WooCommerce's order search or filter by payment method in the orders list.

## Refunds

The plugin does not currently support automated refunds through MPay. Refunds must be processed:

1. Manually through the MPay merchant portal
2. Then manually updated in WooCommerce

WooCommerce refund actions in admin do not trigger any communication with MPay.

## Bulk Operations

Standard WooCommerce bulk actions (change status, etc.) work normally with MPay orders. The plugin does not interfere with manual status changes made by store administrators.
