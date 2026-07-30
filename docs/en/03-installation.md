# Installation

## Upload and Activate

1. Download the plugin archive (ZIP file) from the source provided by TerabitLab.

2. In your WordPress admin panel, navigate to Plugins > Add New > Upload Plugin.

3. Select the ZIP file and click "Install Now".

4. After installation completes, click "Activate".

Alternatively, extract the ZIP file into `wp-content/plugins/` via FTP or SSH:

```bash
unzip mpay-voucher-gateway.zip -d /path/to/wp-content/plugins/
```

Then activate from Plugins > Installed Plugins.

## Verify Activation

After activation, confirm the following:

- A new payment method "MPay / Cultural Voucher" appears under WooCommerce > Settings > Payments
- No PHP errors appear in the admin notices area
- The plugin appears in the active plugins list without warnings

If you see a notice about missing PHP extensions (openssl, soap, dom), install them before proceeding.

## First-Time Setup

### Step 1: Select a Configuration Profile

Go to WooCommerce > Settings > Payments > MPay / Cultural Voucher.

Choose a configuration profile:

- **store_test** - Pre-configured for the MPay test environment
- **store_prod** - Pre-configured for the MPay production environment
- **terabitlab_test** - TerabitLab testing profile
- **custom** - Manual configuration of all endpoints and parameters

For initial testing, select `store_test`.

### Step 2: Upload Your Certificate

You need a PKCS#12 certificate file (.pfx or .p12) provided by your MPay integration contact.

In the Certificates tab:

1. Upload your .pfx or .p12 file
2. Enter the certificate passphrase
3. Click Save

The plugin will extract the private key and public certificate from the PKCS#12 container.

### Step 3: Configure Service Parameters

In the main settings tab, ensure these fields are populated:

- **Service ID** - Your MPay service identifier
- **Return URL** - The URL where customers return after payment (usually auto-detected)

### Step 4: Enable the Gateway

Toggle the gateway to "Enabled" and save.

### Step 5: Test a Payment

1. Add a product to the cart on your store
2. Proceed to checkout
3. Select the MPay payment method
4. Complete the redirect to MPay test environment
5. Verify the order status updates correctly after MPay calls back

## Upgrading

To upgrade from a previous version:

1. Deactivate the current plugin
2. Upload and overwrite with the new version
3. Reactivate

Your settings and certificates are preserved during upgrades. The plugin stores configuration in the WordPress options table, not in plugin files.

## Uninstallation

Deactivating the plugin disables the payment gateway but preserves all settings.

Deleting the plugin removes its files but WordPress options remain in the database. To fully clean up, remove the relevant options from the `wp_options` table (prefixed with `mpay_vg_`).
