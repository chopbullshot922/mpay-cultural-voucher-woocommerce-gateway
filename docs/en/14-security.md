# Security Considerations

## Overview

This plugin handles payment data and cryptographic keys. Security is enforced at multiple levels: transport (HTTPS), message (WS-Security), application (WordPress capabilities), and storage (file permissions).

## Transport Security

- All communication between the customer's browser and your store uses HTTPS
- All SOAP callbacks from MPay arrive over HTTPS
- The redirect to MPay uses HTTPS
- The plugin refuses to operate if the site URL is not HTTPS

## Message Security

- Every SOAP request from MPay is verified using XML digital signatures
- Every SOAP response to MPay is signed with your private key
- Unsigned requests are rejected before any business logic executes
- Signature verification uses the WS-Security standard with X.509 certificates

## Private Key Protection

Your private key is the most sensitive asset managed by the plugin:

- Stored on disk with restrictive file permissions (0600 where hosting allows)
- Protected by .htaccess rules preventing direct HTTP access
- Never transmitted over the network
- Never displayed in the admin interface
- Never included in diagnostic snapshots
- Not stored in the WordPress database

### Recommendations

- Use a hosting environment where you control file permissions
- Regularly rotate certificates (annually at minimum)
- Keep the certificate passphrase in a password manager, not in shared documents
- Restrict SSH/FTP access to the server

## Rate Limiting

The SOAP endpoint implements basic rate limiting to prevent abuse:

- Repeated failed signature verifications from the same IP trigger temporary blocks
- The idempotency transient prevents processing the same payment callback multiple times
- WordPress nonce verification protects admin AJAX operations

For additional rate limiting, configure your web server or CDN:

```nginx
# Example: Nginx rate limiting for SOAP endpoint
location /mpay-soap/ {
    limit_req zone=mpay burst=10 nodelay;
}
```

## Input Validation

- All incoming SOAP XML is parsed with libxml security flags (no external entities)
- OrderKey values are validated against expected format before database queries
- IDNP input is validated as exactly 13 digits
- Certificate upload validates file type and content before processing
- Settings inputs are sanitized using WordPress sanitization functions

## Protection Against Common Attacks

- **XXE** - XML parsing disables external entity loading; DOMDocument does not resolve externals
- **SQL Injection** - All database queries use WordPress prepared statements ($wpdb->prepare)
- **CSRF** - Admin forms use WordPress nonces; SOAP endpoint authenticates via WS-Security
- **XSS** - All admin output is escaped using WordPress escaping functions
- **Path Traversal** - Certificate paths are validated and restricted to the designated directory

## Admin Access Control

- Plugin settings require `manage_woocommerce` capability
- Diagnostic tools require `manage_woocommerce` capability
- WP-CLI commands require server-level access
- No public-facing admin endpoints exist

## Logging Security

When debug mode is enabled:

- Private keys are never logged
- Passphrases are never logged
- IDNP values are partially masked in logs
- Full XML is logged but can be disabled separately
- Logs are stored in WooCommerce's log directory (not publicly accessible)

## Certificate Expiry

An expired certificate means:

- Outgoing responses cannot be properly signed
- MPay will reject your store's responses
- The plugin automatically disables the payment gateway when the certificate expires
- Admin notices warn at 30 days and 7 days before expiry

## Recommendations for Production

1. Disable debug mode
2. Set up certificate expiry monitoring (WP-CLI cron job)
3. Keep WordPress, WooCommerce, and PHP updated
4. Use a WAF that understands SOAP (whitelist the endpoint path)
5. Restrict admin access with strong passwords and two-factor authentication
6. Regular backups of the certificate files
7. Monitor the event log for failed signature verifications (potential attack indicator)

## Reporting Security Issues

Report security vulnerabilities to incontact@terabitlab.com. Do not open public issues for security-sensitive findings.
