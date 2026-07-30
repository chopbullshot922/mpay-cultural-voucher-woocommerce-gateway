# SOAP Server

## Overview

The plugin exposes a SOAP endpoint on your store that MPay calls to retrieve order details and confirm payments. This is not a REST API - it uses SOAP 1.1 with WS-Security headers for authentication and message integrity.

## Endpoint URL

The SOAP endpoint is registered as a WordPress rewrite rule. The default path is:

```
https://your-store.example/mpay-soap/
```

You can customize this path in the Advanced settings tab. After changing the path, flush WordPress rewrite rules by visiting Settings > Permalinks and clicking Save.

The full endpoint URL must be communicated to your MPay integration contact so they configure their system to call your store.

## Operations

The SOAP server handles two operations:

### GetOrderDetails

Called by MPay to retrieve information about an order before processing payment.

**Inbound request contains:**
- OrderKey (string) - The unique order identifier

**Response contains:**
- OrderAmount (decimal) - Total amount in MDL
- Currency (string) - Always "MDL"
- Description (string) - Human-readable order description
- Status (string) - Current order status indicator

**When this is called:**
MPay calls this after the customer arrives on the MPay payment page. It may be called multiple times for the same order.

### ConfirmOrderPayment

Called by MPay after the customer successfully completes payment.

**Inbound request contains:**
- OrderKey (string) - The unique order identifier
- PaymentReference (string) - MPay's payment transaction reference
- PaymentDate (datetime) - When payment was processed
- Amount (decimal) - Amount paid
- Cultural Voucher fields (optional) - IDNP, voucher reference, partial amount

**Response contains:**
- Confirmation status (success or error code)
- OrderKey echo

**When this is called:**
After successful payment on MPay side. May be called more than once due to network retries.

## Request Processing Pipeline

For every incoming SOAP request:

1. WordPress routes the request to the plugin's SOAP handler
2. Raw POST body is captured
3. XML is parsed into a DOMDocument
4. WS-Security header is located
5. MPay's signature is verified against their known certificate
6. If verification passes, the operation is dispatched
7. Response XML is built
8. Response is signed with your certificate (WS-Security)
9. Signed response is returned with Content-Type: text/xml

## WSDL

The plugin does not serve a public WSDL file. The SOAP contract is defined by MPay's integration specification. The PHP SOAP server operates in non-WSDL mode with explicitly defined operations.

## Error Responses

When something goes wrong, the server returns a SOAP Fault:

```xml
<soap:Fault>
  <faultcode>soap:Server</faultcode>
  <faultstring>Order not found</faultstring>
</soap:Fault>
```

Common fault conditions:
- Invalid or missing WS-Security signature
- Order not found for the given OrderKey
- Order in an unexpected state
- Internal processing error

## Logging

When debug mode is enabled, the SOAP server logs:

- Full request XML (with sensitive data masked in logs)
- Signature verification result
- Operation dispatched
- Response XML before signing
- Response XML after signing
- Any errors encountered

Logs are viewable in the diagnostics portal and WooCommerce log files.

## Server Configuration

The SOAP server requires:

- PHP SOAP extension enabled
- POST requests allowed to the endpoint URL
- Request body size sufficient for signed XML (typically under 64KB)
- No WAF rules blocking SOAP/XML content types

If your hosting provider's firewall blocks XML POST requests, you may need to whitelist the SOAP endpoint path.

## Testing the Endpoint

You can verify the endpoint is reachable by sending a GET request to the endpoint URL. The plugin returns a simple text response confirming the SOAP server is active. Actual SOAP operations require properly signed POST requests.
