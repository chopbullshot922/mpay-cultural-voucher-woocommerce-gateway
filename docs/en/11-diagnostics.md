# Diagnostics

## Debug Console

The plugin includes a built-in debug console accessible from the admin panel. It shows real-time information about plugin operations without needing to dig through log files.

To access the debug console:

1. Go to WooCommerce > Settings > Payments > MPay / Cultural Voucher
2. Open the Advanced tab
3. Enable Debug Mode
4. Access the diagnostics portal link that appears

## Diagnostics Portal

The diagnostics portal is a dedicated admin page that consolidates all troubleshooting information in one place:

- **System Status** - PHP version, extensions loaded, OpenSSL version, SOAP availability
- **Certificate Status** - Current certificate validity, expiry date, subject, issuer
- **Endpoint Status** - Whether the SOAP endpoint is reachable and responding
- **Recent Activity** - Last N SOAP requests/responses with timestamps
- **Error Log** - Recent errors specific to the MPay gateway

## Diagnostic Snapshot

The snapshot feature captures the complete plugin state at a point in time for support purposes:

```
WP Admin > MPay Diagnostics > Generate Snapshot
```

A snapshot includes:

- PHP and WordPress environment details
- Plugin version and configuration (sensitive values redacted)
- Certificate status (not the certificate itself)
- Recent log entries
- SOAP endpoint accessibility test result
- WooCommerce configuration relevant to payments

Snapshots are stored temporarily in `wp-content/uploads/` and can be downloaded for sharing with support. They auto-expire after 24 hours.

## Diagnostic Tools

### Certificate Validator

Tests your certificate without making a real payment:

- Verifies the private key can sign data
- Verifies the public certificate can verify the signature
- Confirms key pair matches
- Reports certificate details and expiry

### Endpoint Tester

Sends a test request to your own SOAP endpoint to verify:

- WordPress rewrite rules are working
- The SOAP handler receives the request
- Response is generated (signature not verified in self-test)
- No firewall or WAF blocks the request

### Configuration Checker

Reviews your settings for common issues:

- Service ID is populated
- Certificate is valid and not expired
- Return URL is accessible
- Profile endpoints match expected values
- Required PHP extensions are loaded

## Log Levels

When debug mode is enabled, the plugin logs at these levels:

| Level | What Is Logged |
|-------|---------------|
| ERROR | Signature failures, missing orders, certificate errors |
| WARNING | Duplicate confirmations, amount mismatches, near-expiry cert |
| INFO | Successful operations, status transitions |
| DEBUG | Full XML bodies, signature computation steps, timing data |

Logs are written to the WooCommerce logger under the "mpay-vg" source. Access them at WooCommerce > Status > Logs.

## Performance Impact of Debug Mode

Debug mode adds overhead:

- Full XML logging increases disk writes
- Signature computation details add processing time
- Log file size grows quickly with active stores

Enable debug mode only during troubleshooting. Disable it for normal production operation.

## Clearing Diagnostic Data

To clear accumulated diagnostic data:

- Log files: Delete via WooCommerce > Status > Logs
- Snapshots: Auto-expire after 24 hours, or delete from uploads directory
- Transients: Use WP-CLI `wp mpay cleanup` command

## Sharing Diagnostics with Support

When contacting incontact@terabitlab.com for support:

1. Generate a diagnostic snapshot
2. Download the snapshot file
3. Attach it to your support email
4. Include a description of the issue and when it started

The snapshot does not contain private keys, passphrases, or customer personal data.
