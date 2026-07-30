# Configuration

## Admin Settings Page

All plugin configuration is managed through the WooCommerce admin interface. Navigate to:

WooCommerce > Settings > Payments > MPay / Cultural Voucher

No configuration is read from wp-config.php. Everything is stored in the WordPress options table and edited through the settings page.

## Configuration Profiles

The plugin ships with four profiles that pre-fill endpoint URLs and related parameters:

| Profile | Purpose |
|---------|---------|
| store_test | MPay test/sandbox environment |
| store_prod | MPay production environment |
| terabitlab_test | TerabitLab internal testing |
| custom | All fields editable manually |

When you select a profile other than "custom", the endpoint URLs are locked to the profile values. Switching profiles does not erase your certificate or Service ID.

## Settings Tabs

The settings page is organized into tabs:

### General Tab

- **Enable/Disable** - Toggle the payment gateway on or off
- **Title** - Payment method name shown to customers at checkout
- **Description** - Text displayed under the payment method name
- **Profile** - Select configuration profile
- **Service ID** - Your unique MPay service identifier
- **Return URL** - URL where customers are redirected after payment on MPay side

### Certificate Tab

- **Certificate File** - Upload field for .pfx or .p12 files
- **Passphrase** - Password for the PKCS#12 container
- **Certificate Status** - Shows current certificate validity and expiry date
- **Signature Algorithm** - Choose between rsa-sha1 and rsa-sha256

### Cultural Voucher Tab

- **Enable Cultural Voucher** - Toggle Cultural Voucher payment support
- **Eligible Categories** - WooCommerce product categories that qualify for Cultural Voucher
- **IDNP Field** - Enable the IDNP identification field at checkout

### Advanced Tab

- **SOAP Endpoint Path** - Custom path for the inbound SOAP server (default is auto-generated)
- **Debug Mode** - Enable detailed logging for troubleshooting
- **Transient Lock Duration** - Seconds to hold the idempotency lock (default: 300)
- **Order Status Mapping** - Map MPay payment states to WooCommerce order statuses

## Service ID

The Service ID is assigned by MPay when your store is registered. It identifies your store in all communications with MPay. This value is sent in the redirect form and expected in SOAP callbacks.

## Return URL

The Return URL tells MPay where to send the customer's browser after payment completes (or fails). The plugin auto-detects this based on WooCommerce endpoint settings, but you can override it manually.

## Debug Mode

When debug mode is enabled:

- All SOAP requests and responses are logged
- XML signatures are logged before and after signing
- Payment flow steps are recorded with timestamps
- Logs appear in the diagnostics portal and in WooCommerce logs

Disable debug mode in production to avoid logging sensitive data.

## Saving Configuration

Click "Save changes" at the bottom of the settings page. The plugin validates certificate files on save and reports any issues immediately.

If validation fails (for example, wrong passphrase), the previous working configuration is retained.

## Profile Switching

You can switch between profiles at any time. Common workflow:

1. Start with `store_test` for integration testing
2. Switch to `store_prod` when ready to accept real payments
3. Keep the same certificate if it works for both environments, or upload a new one

Contact: incontact@terabitlab.com for profile configuration assistance.
