# Payment Flow

This is the most important document for understanding how the plugin works. The payment flow involves the customer's browser, your WooCommerce store, and the MPay payment system.

## Flow Direction

MPay calls the store - not the other way around. After the initial redirect, the store waits passively for MPay to call back with payment status.

## Complete Payment Sequence

### Step 1: Customer Selects MPay at Checkout

The customer adds products to cart, proceeds to checkout, and selects the MPay (or Cultural Voucher) payment method. If Cultural Voucher is enabled and the cart contains eligible items, the IDNP field is shown.

### Step 2: Store Generates Redirect Form

When the customer clicks "Place Order", WooCommerce creates the order with status "pending payment". The plugin generates an HTML page containing an auto-submitting form with HTTP POST method pointing to the MPay payment URL.

The form contains these fields:

- **ServiceID** - Your store's MPay service identifier
- **OrderKey** - A unique key identifying this order
- **ReturnUrl** - URL where the customer's browser returns after payment

The form auto-submits via JavaScript. If JavaScript is disabled, a submit button is shown.

### Step 3: Customer Is on MPay

The customer's browser is now on the MPay site. They complete payment (enter card details, authorize Cultural Voucher, etc.). The store has no visibility into this step.

### Step 4: MPay Calls GetOrderDetails

Before or during payment processing, MPay sends a SOAP request to your store's endpoint calling the `GetOrderDetails` operation. This request:

- Is signed with MPay's certificate (WS-Security)
- Contains the OrderKey
- Expects a signed SOAP response with order amount, currency, and description

Your store:

1. Receives the SOAP request
2. Verifies MPay's XML signature
3. Looks up the WooCommerce order by OrderKey
4. Builds a response with order total, currency (MDL), and description
5. Signs the response with your certificate
6. Returns the signed SOAP response

### Step 5: MPay Calls ConfirmOrderPayment

After successful payment, MPay sends a second SOAP request calling `ConfirmOrderPayment`. This request:

- Is signed with MPay's certificate
- Contains the OrderKey and payment confirmation details
- May include Cultural Voucher-specific data (IDNP, voucher reference)

Your store:

1. Receives the SOAP request
2. Verifies MPay's XML signature
3. Acquires a transient-based lock (idempotency protection)
4. Looks up the order by OrderKey
5. Records payment details as order meta
6. Transitions order status to "processing" (or "completed" depending on settings)
7. Signs and returns confirmation response
8. Releases the lock

### Step 6: Customer Returns to Store

The customer's browser is redirected back to the ReturnUrl. At this point, the payment is already confirmed via the SOAP callback. The customer sees the order confirmation page.

## Idempotency

MPay may call ConfirmOrderPayment more than once (network retries, timeouts). The plugin uses WordPress transients as a locking mechanism:

- Before processing, it sets a transient keyed by OrderKey
- If the transient already exists, the duplicate call is acknowledged without re-processing
- The transient expires after the configured lock duration (default: 300 seconds)

This prevents double-processing of payments.

## Error Scenarios

| Scenario | Behavior |
|----------|----------|
| GetOrderDetails - order not found | Returns SOAP fault |
| GetOrderDetails - signature invalid | Returns SOAP fault, logs error |
| ConfirmOrderPayment - order already paid | Returns success (idempotent) |
| ConfirmOrderPayment - signature invalid | Returns SOAP fault, logs error |
| Customer returns but callback not yet received | Order shows "pending"; updates when callback arrives |
| SOAP endpoint unreachable | MPay retries; payment may be delayed |

## Timing

The SOAP callbacks from MPay typically arrive within seconds of payment completion. However, the customer's browser redirect and the SOAP callback are separate events - they may arrive in any order.

The plugin handles the case where the customer returns to the store before the SOAP callback has been processed. The thank-you page checks order status and displays appropriate messaging.

## Diagram

```
Customer          Store                   MPay
   |                |                      |
   |-- Checkout --->|                      |
   |                |-- POST form -------->|
   |                |                      |
   |                |<-- GetOrderDetails --|
   |                |-- Order details ---->|
   |                |                      |
   |                |<-- ConfirmPayment ---|
   |                |-- Confirmation ----->|
   |                |                      |
   |<-- Return -----------------------------|
   |                |                      |
```

## Security at Each Step

- Redirect form: Parameters are not signed (security relies on the SOAP callback)
- GetOrderDetails and ConfirmOrderPayment: Incoming signature verified; response signed
- All SOAP communication uses WS-Security with X.509 certificates

## What to obtain from MPay

### For test

- ServiceID (test)
- Access to the test payment page
- MPay test public certificate
- Confirmation of the SOAP endpoint
- IP or whitelist rules (if applicable)
- Invoice API access (if used)
- WS-Security algorithm confirmation
- Cultural Voucher rules confirmation
- Acceptance test criteria

### For production

- ServiceID (production)
- Production endpoint registration
- Registration of the merchant public certificate
- MPay production public certificate
- IP configuration (if required)
- API access on specific port (if used)
- Service activation confirmation
- Final controlled test

### What can be sent to MPay

- Public certificate (.cer or .pem)
- CN
- Fingerprint (if requested)
- ServiceID
- SOAP endpoint URL
- ReturnUrl
- Public IP (if needed)
- Technical contact

## Production checklist

### STISC

- [ ] Certificate issued
- [ ] PFX opens successfully
- [ ] Password confirmed
- [ ] Public certificate extracted
- [ ] CN correct
- [ ] Issuer correct
- [ ] Validity verified
- [ ] Chain available
- [ ] Secure backup created
- [ ] Revocation procedure known
- [ ] Expiry date noted

### MPay

- [ ] Production ServiceID received
- [ ] Production endpoint registered
- [ ] Public certificate registered with MPay
- [ ] Fingerprint confirmed
- [ ] MPay production public certificate received
- [ ] Whitelist configured
- [ ] API access (if used)
- [ ] Cultural Voucher rules confirmed
- [ ] Final test passed

### Server

- [ ] Valid HTTPS
- [ ] Correct DNS
- [ ] Endpoint accessible externally
- [ ] No unexpected redirects
- [ ] Cache disabled on /mpay/*
- [ ] PHP SOAP extension installed
- [ ] PHP OpenSSL extension installed
- [ ] DOM/XML installed
- [ ] cURL installed
- [ ] Server time synchronized (NTP)
- [ ] Correct file permissions
- [ ] Certificates protected
- [ ] Logs active
- [ ] Debug key set and secret

### Plugin

- [ ] PROD mode enabled
- [ ] Production ServiceID configured
- [ ] Official bank details entered
- [ ] Official BeneficiaryName (exact legal name)
- [ ] Merchant public certificate uploaded
- [ ] PFX/private key uploaded
- [ ] Password verified (test button)
- [ ] MPay public certificate uploaded
- [ ] WS-Security enforcement ON
- [ ] HTTP test mode OFF
- [ ] Certificate monitoring active
- [ ] Endpoint verified accessible
- [ ] Controlled test order placed
- [ ] Confirmation verified
- [ ] Idempotency verified (duplicate call test)
- [ ] ReturnUrl verified
