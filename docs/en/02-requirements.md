# Requirements

## Server Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP | 7.4 | 8.1 or later |
| WordPress | 5.8 | 6.3 or later |
| WooCommerce | 6.0 | 8.0 or later |
| HTTPS | Required | Required |

## PHP Extensions

The following PHP extensions must be enabled on your server:

- **openssl** - Required for certificate handling, PKCS#12 parsing, and XML signature operations
- **soap** - Required for the SOAP server that receives MPay callbacks
- **dom** - Required for XML document manipulation during WS-Security signing
- **mbstring** - Required for proper string handling in XML operations
- **json** - Required for configuration storage and API responses

Optional but recommended:

- **posix** - Used by CLI commands for file permission checks
- **readline** - Improves WP-CLI interactive output

## HTTPS Requirement

Your store must be served over HTTPS. MPay will not send callbacks to HTTP endpoints. The SOAP endpoint URL exposed to MPay must use a valid TLS certificate trusted by standard certificate authorities.

Self-signed certificates on the web server are not acceptable for production use with MPay.

**Important:** The HTTPS certificate and the system certificate for SOAP signing are DIFFERENT products. STISC treats them separately. HTTPS can be Let's Encrypt. The SOAP signing certificate is a separate X.509 system certificate obtained from STISC.

## Certificate Requirement (Production)

For production use, you need a system certificate from STISC (X.509 for automatic SOAP signing). See docs/en/05-certificates.md for the complete STISC procedure, questions to ask, and document requirements.

For testing, you can use certificates provided by the MPay team.

## File System Access

The plugin needs:

- Read/write access to its own directory for storing uploaded certificates
- The `wp-content/uploads/` directory must be writable for temporary diagnostic snapshots
- If using CLI fallback for certificate operations, the `openssl` binary must be available in the system PATH

## WooCommerce Configuration

- WooCommerce must have at least one currency configured (MDL is expected for MPay transactions)
- Permalinks must be enabled (not "Plain" mode) for the SOAP endpoint rewrite rules to function
- The store checkout page must be functional and accessible

## WordPress Configuration

- WordPress cron (wp-cron or system cron) should be active for scheduled certificate expiry checks
- The REST API does not need to be enabled (the plugin uses its own SOAP endpoint)
- All plugin configuration is done through the admin settings page in WooCommerce

## Network Requirements

- Outbound: The server does not need to make outbound calls to MPay during normal payment flow
- Inbound: MPay must be able to reach your store SOAP endpoint on port 443
- Firewall rules should allow incoming POST requests from MPay IP ranges to the SOAP endpoint path

## Browser Requirements

No special browser requirements for customers. The redirect to MPay uses a standard HTML form with HTTP POST, which works in all modern browsers.

## Hosting Compatibility

The plugin works on:

- Standard shared hosting with PHP support
- VPS and dedicated servers
- Managed WordPress hosts (provided SOAP extension is available)
- Docker-based deployments

Note: Some managed WordPress hosts disable the PHP SOAP extension. Verify with your host before installation.
