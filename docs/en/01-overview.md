# Plugin Overview

## What It Does

This WooCommerce payment gateway plugin (v14.3.2) connects your WordPress store to the Moldova MPay payment system and the Cultural Voucher program. It allows customers to pay for orders using MPay, including payments made with Cultural Vouchers issued under the national cultural program.

The plugin handles the full payment lifecycle: redirecting customers to MPay for payment, receiving payment confirmations via SOAP callbacks, and updating WooCommerce order statuses accordingly.

## Key Capabilities

- WooCommerce payment gateway integration with MPay
- Cultural Voucher eligibility checking and IDNP payer identification
- SOAP WS-Security with X.509 certificate signing (rsa-sha1, rsa-sha256)
- HTTP POST redirect to MPay with ServiceID, OrderKey, and ReturnUrl
- Inbound SOAP endpoint handling GetOrderDetails and ConfirmOrderPayment calls
- Idempotent payment confirmation with transient-based locking
- PKCS#12 (.pfx/.p12) certificate management via PHP OpenSSL with CLI fallback
- Multiple configuration profiles (store_test, store_prod, terabitlab_test, custom)
- WP-CLI commands for certificate status, event logs, and cleanup
- Built-in diagnostics portal and debug console

## Who It Is For

- WooCommerce store operators in Moldova accepting MPay payments
- Stores participating in the Cultural Voucher program
- Developers integrating WooCommerce with the MPay payment infrastructure

## Three integration components

The integration has three distinct components:

1. **HTTPS/TLS** - protects the network connection (can be Let's Encrypt)
2. **Merchant system certificate** - signs SOAP responses sent to MPay (obtained from STISC for production)
3. **MPay public certificate** - verifies SOAP requests received from MPay

These certificates are NOT interchangeable. See docs/en/05-certificates.md for complete STISC procedure.

## Architecture

The plugin follows a modular structure under the `MPAY_VG\` namespace:

```
includes/
  Admin/      - Settings pages, meta boxes, diagnostics
  CLI/        - WP-CLI command handlers
  Core/       - Bootstrap, configuration profiles, helpers
  Soap/       - SOAP server, WS-Security, XML signing
  Woo/        - WooCommerce gateway, order handling, checkout fields
```

## Payment Flow Summary

1. Customer selects MPay at checkout
2. Store generates an HTTP POST form that redirects the customer to MPay
3. MPay processes the payment
4. MPay calls back to the store SOAP endpoint with payment details
5. Store confirms the order and updates WooCommerce order status

The store does not poll MPay. MPay initiates the callback to the store.

## Version

Current release: 14.3.2
Developer: TerabitLab
Contact: incontact@terabitlab.com

## License

Source-available, restricted commercial use. Free to evaluate and test. Commercial use on your own store requires email notification to incontact@terabitlab.com. Agency/multi-site deployments require a commercial license.
