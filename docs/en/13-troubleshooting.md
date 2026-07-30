# Troubleshooting

## Common Issues and Solutions

### Payment Gateway Not Appearing at Checkout

**Symptoms:** Customers do not see the MPay payment option at checkout.

**Possible causes:**

1. Gateway is disabled - Go to WooCommerce > Settings > Payments and ensure MPay is toggled on
2. Certificate expired - Check certificate status in the Certificate tab
3. Service ID missing - Verify Service ID is configured
4. Currency mismatch - WooCommerce store currency should be MDL
5. Cultural Voucher only - If only Cultural Voucher is enabled, it only appears for eligible products

### Customer Stuck After Redirect

**Symptoms:** Customer clicks "Place Order" but nothing happens or they see a blank page.

**Possible causes:**

1. JavaScript disabled - The auto-submit form needs JS; verify the fallback button appears
2. Service ID incorrect - MPay rejects the request
3. Return URL unreachable - Check the Return URL in settings is a valid, accessible URL

### Order Stays in "Pending Payment" After Customer Returns

**Symptoms:** Customer completed payment on MPay but the order still shows pending.

**Possible causes:**

1. SOAP endpoint unreachable from MPay - Check firewall rules, ensure port 443 is open
2. WordPress rewrite rules broken - Visit Settings > Permalinks and save to flush
3. WAF blocking SOAP requests - Check if your firewall blocks XML POST requests
4. Certificate issue - Signature verification failing on your side (check logs)

**Resolution steps:**

- Enable debug mode and check the event log for incoming requests
- Verify the SOAP endpoint URL is correct and communicated to MPay
- Test the endpoint using the diagnostics portal endpoint tester

### Signature Verification Failed

**Symptoms:** Logs show "signature verification failed" for incoming MPay requests.

**Possible causes:**

1. Wrong certificate configured - Your certificate does not match what MPay expects
2. Algorithm mismatch - Your store uses sha256 but MPay sends sha1 (or vice versa)
3. XML modification in transit - A proxy or CDN is modifying the request body
4. Clock skew - Server time is significantly off (affects timestamp validation)

**Resolution:**

- Verify the signature algorithm setting matches MPay's configuration
- Check if a CDN or reverse proxy sits between MPay and your server
- Ensure server time is synchronized (NTP)

### "Could Not Parse PKCS#12" on Certificate Upload

**Symptoms:** Error when uploading certificate file.

**Possible causes:**

1. Wrong passphrase
2. Corrupted file (incomplete download)
3. Incompatible PKCS#12 encoding

**Resolution:**

- Verify the passphrase is correct (try it with the openssl CLI tool locally)
- Re-download the certificate file
- Check if the CLI fallback succeeds (requires openssl binary on server)

### SOAP Extension Not Available

**Symptoms:** Plugin shows warning about missing SOAP extension.

**Resolution:**

- For Debian/Ubuntu: `sudo apt-get install php-soap && sudo systemctl restart php-fpm`
- For CentOS/RHEL: `sudo yum install php-soap && sudo systemctl restart php-fpm`
- For managed hosting: Contact your host to enable the SOAP extension

### Duplicate Order Notes / Double Processing

**Symptoms:** Order notes show payment confirmed multiple times.

**Explanation:** MPay may retry callbacks. The idempotency lock (transient) prevents double-processing, but if the transient expired between retries, a second note may be added. The order is not charged twice - this is a display issue only.

### Cultural Voucher Option Not Showing

**Symptoms:** Cultural Voucher payment method not visible at checkout.

**Check:**

1. Cultural Voucher is enabled in settings
2. At least one eligible category is configured
3. Cart contains at least one product from an eligible category
4. IDNP field is enabled

### Debug Mode Fills Disk

**Symptoms:** Server disk filling up with log files.

**Resolution:**

1. Disable debug mode in production: Advanced tab > Debug Mode off
2. Run `wp mpay cleanup` to clear old logs
3. Set up a cron job for periodic cleanup

## MPay Does Not Call the Endpoint

Full checklist:
- DNS resolves correctly
- HTTPS valid and accessible
- Firewall allows inbound on 443
- IP whitelist configured (if required by MPay)
- Public IP is correct
- Cloudflare not blocking or redirecting
- No 301/302 redirect on /mpay/soap
- WordPress rewrite rules flushed
- Endpoint registered correctly in MPay panel
- Server ports open
- Server access log shows no requests
- WooCommerce log shows no activity

Test: `curl -vk https://shop.example.com/mpay/soap`

## Invalid Signature (Deep)

Full checklist:
- Correct certificate configured (test vs production)
- Correct private key (matches public cert)
- Actual algorithm matches Algorithm attribute
- Digest value correct
- Canonicalization method correct
- Namespace declarations not interfering
- XML element order matches contract
- No UTF-8 BOM
- No extra newline after signing
- No extra spaces
- No cache/minification modifying body
- Timestamp not expired
- Server time synchronized
- XML not modified after signing (by Cloudflare, WAF, proxy)

## Password Incorrect (PFX)

```
openssl pkcs12 -info -in provider.pfx -noout
```

Check:
- Password exact (no copied spaces before/after)
- Correct keyboard layout
- Correct PFX file (test vs production)
- OpenSSL version compatible with PFX format
- Plugin "Test private key" button for quick verification

## Timestamp Expired

Check:
- NTP running on host
- Host time correct
- Container time correct (if Docker)
- Timezone configuration
- Created/Expires values in SOAP header
- Time difference between your server and MPay

## Invalid XML Element Order

Element order must match the contract exactly. Syntactically valid XML does NOT mean valid for the MPay contract. If elements are reordered after construction, the signature or the contract validation fails.

## No Traffic in Log

Check:
- Endpoint registered correctly with MPay
- Domain resolves from MPay network
- DNS propagated
- Firewall rules
- Proxy configuration
- HTTPS accessible
- Cache not serving stale responses
- Server request logs (Apache/nginx access log)
- PHP SOAP extension loaded
- WordPress rewrite rules present

## Getting Help

If the above solutions do not resolve your issue:

1. Generate a diagnostic snapshot (Diagnostics > Generate Snapshot)
2. Note the exact error message and when it started occurring
3. Contact incontact@terabitlab.com with the snapshot and details
