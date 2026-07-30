> Need MPay or Cultural Voucher implemented on your WordPress or WooCommerce website? Contact TerabitLab at [**incontact@terabitlab.com**](mailto:incontact@terabitlab.com).

[![English](assets/language/english-active.svg)](README.md) [![Romana](assets/language/romanian-inactive.svg)](README.ro.md)

# WooCommerce Gateway for Moldova MPay and Cultural Voucher Integrations

Developed by TerabitLab to support wider MPay adoption and make Moldova MPay and Cultural Voucher integrations easier for merchants and developers using WordPress and WooCommerce.

## What this plugin does

This is a functional WooCommerce payment gateway that connects online stores to Moldova MPay payment system and Cultural Voucher program. It handles the complete payment lifecycle: customer redirect, SOAP-based payment verification with WS-Security XML digital signatures, and automatic order status updates.

## How the payment flow works

1. Customer selects MPay at checkout.
2. Plugin redirects the browser to MPay via HTTP POST form (ServiceID, OrderKey, ReturnUrl).
3. Customer pays on the MPay portal.
4. MPay calls the store SOAP endpoint with GetOrderDetails. The store responds with order data.
5. After payment, MPay calls ConfirmOrderPayment on the store endpoint. The store records payment and updates the WooCommerce order.
6. ReturnUrl brings the browser back to the store. This is informational only and does not confirm payment.

## Features

- MPay and Cultural Voucher payment methods for WooCommerce
- SOAP server handling GetOrderDetails and ConfirmOrderPayment
- WS-Security XML Digital Signature (sign and verify) with X.509 certificates
- PKCS#12 certificate handling (PHP OpenSSL with CLI fallback)
- Idempotent payment confirmation with transient-based locking
- Partial payment support with custom order status
- Cultural Voucher product eligibility and IDNP payer identification
- Certificate expiry monitoring (daily cron)
- Admin settings page with configuration profiles (test/production)
- Admin diagnostics, statistics, and order meta box
- Remote debug console with shared key authentication
- Diagnostics portal and public playbook
- Event log in database with SOAP request persistence
- WP-CLI commands (cert-status, event-log, cleanup)
- Rate limiting and request size limits on SOAP endpoint
- Configurable availability conditions (min/max total, countries, shipping methods, virtual-only, guest)

## Requirements

- PHP 7.4 or higher
- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- PHP SOAP extension
- PHP OpenSSL extension
- Valid X.509 certificate (obtained during MPay merchant onboarding)
- HTTPS on the domain

## Installation

1. Upload the plugin folder to wp-content/plugins/
2. Activate the plugin in WordPress admin
3. Go to the MPay settings page (Settings link appears on the plugins page)
4. Select a configuration profile or use Custom
5. Configure your Service ID
6. Configure certificate paths and passphrase
7. Configure bank details (beneficiary, bank code, fiscal code, IBAN)
8. Test with a small transaction in the test environment

## Configuration

All settings are managed through the admin settings page (MPay Gateway menu in WordPress admin). The plugin supports configuration profiles for test and production environments.

## Documentation

Complete bilingual documentation: [English](docs/en/README.md) | [Romana](docs/ro/README.md)

Key documents:
- [Certificates and STISC procedure](docs/en/05-certificates.md) - what to obtain, how, questions to ask
- [Payment flow and production checklist](docs/en/06-payment-flow.md) - complete flow, MPay requirements
- [WS-Security](docs/en/08-ws-security.md) - cryptographic flow, XML-DSig rules
- [Troubleshooting](docs/en/13-troubleshooting.md) - common issues and deep debugging

## Payment flow diagram

![Payment Flow](assets/diagrams/mpay-woocommerce-payment-flow.svg)

## Plugin structure

```
includes/
  Admin/
    Diagnostics.php
    MetaBox.php
    Notices.php
    Settings.php
    Stats.php
  CLI/
    Commands.php
  Core/
    CertMonitor.php
    DB.php
    DebugConsole.php
    DiagnosticsPortal.php
    DiagnosticsSnapshot.php
    DiagnosticsTools.php
    Invoices.php
    Logger.php
    OrderMapper.php
    PublicPlaybook.php
    Rewrites.php
    TestPlaybook.php
  Soap/
    Server.php
    WsSecurity.php
  Woo/
    Checkout.php
    Eligibility.php
    Emails.php
    Gateway_MPay.php
    OrderStatus.php
    Thankyou.php
  functions-helpers.php
mpay-voucher-gateway.php
uninstall.php
```

## Endpoints

The plugin registers the following URL endpoints:

- /mpay/redirect - Redirect page that sends browser to MPay via POST form
- /mpay/soap - SOAP server for MPay callbacks (GetOrderDetails, ConfirmOrderPayment)
- /mpay/debug - Remote debug console (requires shared key)
- /mpay/diagnostics - Diagnostics portal
- /mpay/playbook - Public playbook

## What is not included

This repository does not contain:

- Real certificates or private keys
- Real passwords or passphrases
- Real Service IDs
- Real bank details (IBAN, BIC, fiscal codes)
- Client-specific configurations
- SOAP request/response logs
- Private documentation

You must obtain your own credentials through the MPay merchant onboarding process.

## MPay and Cultural Voucher implementation services

This gateway was developed by TerabitLab based on practical WordPress and WooCommerce integration work.

TerabitLab can assist with:

- installing and configuring the gateway;
- adapting the integration to an existing WooCommerce store;
- configuring merchant-specific parameters;
- configuring certificate handling;
- implementing payment confirmation workflows;
- testing the complete payment flow;
- diagnosing MPay integration errors;
- preparing the website for the merchant onboarding and testing process.

For implementation support, contact [incontact@terabitlab.com](mailto:incontact@terabitlab.com).

## License

Source-available, restricted commercial use. Free to evaluate, study, and test.

Commercial use on your own single store requires email notification to [incontact@terabitlab.com](mailto:incontact@terabitlab.com) before going live. Agency, multi-site, and client deployments require a commercial license.

See [LICENSE](LICENSE) for full terms.

## Referral Program

Earn 15% commission by referring new commercial customers to TerabitLab. See [referral/PROGRAM.md](referral/PROGRAM.md) for details and claim forms.

