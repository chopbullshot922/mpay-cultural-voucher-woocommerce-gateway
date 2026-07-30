> Need MPay or Cultural Voucher implemented on your WordPress or WooCommerce website? Contact TerabitLab at [**incontact@terabitlab.com**](mailto:incontact@terabitlab.com).

[![English](assets/language/english-active.svg)](README.md) [![Romana](assets/language/romanian-inactive.svg)](README.ro.md)

# WooCommerce Gateway for Moldova MPay and Cultural Voucher

[![Version](https://img.shields.io/badge/version-14.3.2-blue.svg)](../../releases) [![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net) [![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759B.svg)](https://wordpress.org) [![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-96588A.svg)](https://woocommerce.com) [![License](https://img.shields.io/badge/license-source--available-orange.svg)](LICENSE)

**Production-ready WooCommerce payment gateway for MPay Moldova and Cultural Voucher.**
Complete SOAP/WS-Security integration with X.509 certificate signing, built for real-world merchant use.

Developed by [TerabitLab](mailto:incontact@terabitlab.com).

<a href="../../releases"><img src="https://img.shields.io/badge/Download_Plugin_ZIP-v14.3.2-success?style=for-the-badge" alt="Download"></a>

---

## How the payment flow works

1. Customer selects MPay at checkout.
2. Plugin redirects the browser to MPay via HTTP POST form (ServiceID, OrderKey, ReturnUrl).
3. Customer pays on the MPay portal.
4. MPay calls the store SOAP endpoint with GetOrderDetails. The store responds with order data.
5. After payment, MPay calls ConfirmOrderPayment on the store endpoint. The store records payment and updates the WooCommerce order.
6. ReturnUrl brings the browser back to the store. This is informational only and does not confirm payment.

![Payment Flow](assets/diagrams/mpay-woocommerce-payment-flow.svg)

---

## Installation

1. Download the plugin ZIP from [Releases](../../releases)
2. In WordPress admin: **Plugins > Add New > Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Click **Activate Plugin**
5. Go to **MPay Gateway** in the WordPress admin menu

No programming, no FTP, no file editing required.

---

## Setup: TEST environment

After activation, configure everything from the plugin admin page:

| Step | Where in plugin | What to do |
|------|----------------|------------|
| 1 | Profile dropdown | Select **store_test** or **terabitlab_test** |
| 2 | Service ID | Enter the test Service ID provided by MPay |
| 3 | WS-Security tab | Upload the test PFX certificate and enter the passphrase |
| 4 | WS-Security tab | Upload the MPay test public certificate |
| 5 | Bank details tab | Enter test bank details (beneficiary, IBAN, fiscal code) |
| 6 | Save | Click Save Changes |
| 7 | Test | Place a small order using MPay at checkout |

<img src="assets/screenshots/02-production-profile-and-serviceid.png" alt="Profile and Service ID configuration" width="800">

<img src="assets/screenshots/06-ws-security-certificate-settings.png" alt="WS-Security certificate settings" width="800">

---

## Setup: PRODUCTION environment

When MPay confirms your test integration is approved:

| Step | Where in plugin | What to do |
|------|----------------|------------|
| 1 | Profile dropdown | Switch to **store_prod** |
| 2 | Service ID | Enter the production Service ID from MPay |
| 3 | WS-Security tab | Upload the STISC production PFX certificate and passphrase |
| 4 | WS-Security tab | Upload the MPay production public certificate |
| 5 | Bank details tab | Enter real bank details |
| 6 | HTTPS | Confirm your domain has a valid HTTPS certificate |
| 7 | Save and verify | Save, then place a real small-amount transaction |

**Before production you need:**
- A production system certificate from STISC (see [Certificate documentation](docs/en/05-certificates.md))
- Production Service ID from MPay
- MPay production public certificate
- HTTPS on your domain
- MPay approval of your test integration
- Your server public IP communicated to MPay (they may whitelist it)
- MPay production IP addresses whitelisted on your server firewall

All configuration is done from the plugin settings page. No code changes needed.

---

## Plugin Admin Interface

### Main Settings

<img src="assets/screenshots/01-wordpress-admin-menu-entry.png" alt="WordPress admin menu entry" width="700">

<img src="assets/screenshots/03-general-gateway-settings.png" alt="General gateway settings" width="800">

### Bank Account and Payment Rules

<img src="assets/screenshots/04-merchant-bank-account-settings.png" alt="Bank account settings" width="800">

<img src="assets/screenshots/05-payment-rule-settings.png" alt="Payment rule settings" width="800">

### Invoice and PDF Settings

<img src="assets/screenshots/07-invoice-api-and-pdf-settings.png" alt="Invoice API and PDF settings" width="700">

### WooCommerce Eligibility

<img src="assets/screenshots/08-woocommerce-eligibility-conditions.png" alt="Eligibility conditions" width="700">

<img src="assets/screenshots/09-woocommerce-eligibility-and-test-overrides.png" alt="Eligibility and test overrides" width="700">

---

## Monitoring and Diagnostics

### Log and Health

<img src="assets/screenshots/10-log-and-health-overview.png" alt="Log and health overview" width="800">

<img src="assets/screenshots/11-debug-event-log.png" alt="Debug event log" width="700">

<img src="assets/screenshots/12-event-history-and-monitoring-controls.png" alt="Event history and monitoring" width="700">

### Diagnostics Dashboard

<img src="assets/screenshots/14-diagnostics-dashboard-overview.png" alt="Diagnostics dashboard" width="800">

<img src="assets/screenshots/15-soap-runtime-and-orderkey-inspector.png" alt="SOAP runtime inspector" width="700">

<img src="assets/screenshots/16-structured-debug-event-stream.png" alt="Structured debug event stream" width="700">

### Troubleshooting Playbook

<img src="assets/screenshots/17-mpay-troubleshooting-playbook-scenarios-1-to-4.png" alt="Troubleshooting scenarios" width="700">

<img src="assets/screenshots/18-mpay-troubleshooting-playbook-ws-security-toolkit.png" alt="WS-Security toolkit" width="700">

### Statistics

<img src="assets/screenshots/19-mpay-statistics-dashboard.png" alt="Statistics dashboard" width="800">

---

## Plugin Information

<img src="assets/screenshots/13-plugin-information-and-attribution.png" alt="Plugin information" width="600">

---

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

## Documentation

Complete bilingual documentation: [English](docs/en/README.md) | [Romana](docs/ro/README.md)

Key documents:
- [Certificates and STISC procedure](docs/en/05-certificates.md) - what to obtain, how, questions to ask
- [Payment flow and production checklist](docs/en/06-payment-flow.md) - complete flow, MPay requirements
- [WS-Security](docs/en/08-ws-security.md) - cryptographic flow, XML-DSig rules
- [Troubleshooting](docs/en/13-troubleshooting.md) - common issues and deep debugging

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
