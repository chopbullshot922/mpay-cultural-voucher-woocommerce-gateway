=== MPay Voucher Cultural Gateway (WooCommerce) ===
Contributors: terabitlab
Tags: woocommerce, payments, mpay, moldova, voucher cultural
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 14.3.2
License: Source-available, non-commercial
License URI: See LICENSE file

Gateway WooCommerce pentru MPay (Republica Moldova) - voucher cultural, redirect HTTP POST, SOAP WS-Security (semnare/verificare), ConfirmOrderPayment idempotent, PDF nota, diagnostic, status partial platit, conditii Woo, log viewer, eligibilitate produse, monitor expirare certificate, WP-CLI, statistici, log DB.
Dezvoltat de TerabitLab.

== Instalare ==
1. Upload ZIP sau folder in wp-content/plugins/ apoi Activeaza.
2. MPay Gateway - configureaza: Test/Prod, ServiceID, cont bancar, certificate X.509, WS-Security, conditii Woo, log, PDF, eligibilitate cultural, statistici.
3. WooCommerce - Payments - activeaza "MPay / Voucher Cultural".
4. Configureaza endpoint SOAP {site}/mpay/soap in panoul MPay.

== Nota ==
In productie, activeaza WS-Security si incarca certificatul public MPay plus cheia privata/cert public al prestatorului.
Toate setarile sunt gestionate prin pagina de administrare a pluginului.
