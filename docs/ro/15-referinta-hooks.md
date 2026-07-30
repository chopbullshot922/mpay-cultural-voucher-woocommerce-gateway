# Referinta hooks si filtre

Pluginul expune hooks (actions) si filtre (filters) WordPress pentru extensibilitate si integrare cu alte plugin-uri.

## Actions (hooks)

### mpay_vg_before_redirect

Se executa inainte de generarea formularului de redirect catre MPay.

```php
do_action('mpay_vg_before_redirect', $order_id, $order);
```

Parametri:
- `$order_id` (int) - ID comanda WooCommerce
- `$order` (WC_Order) - Obiectul comanda

Utilizare: logging, validari suplimentare, notificari.

### mpay_vg_payment_confirmed

Se executa dupa confirmarea reusita a platii.

```php
do_action('mpay_vg_payment_confirmed', $order_id, $transaction_data);
```

Parametri:
- `$order_id` (int) - ID comanda
- `$transaction_data` (array) - Date tranzactie (transaction_id, amount, status, datetime)

Utilizare: integrari CRM, notificari custom, sincronizare externe.

### mpay_vg_payment_failed

Se executa cand plata esueaza.

```php
do_action('mpay_vg_payment_failed', $order_id, $error_data);
```

Parametri:
- `$order_id` (int) - ID comanda
- `$error_data` (array) - Informatii eroare

### mpay_vg_soap_request_received

Se executa la primirea unei cereri SOAP de la MPay.

```php
do_action('mpay_vg_soap_request_received', $operation, $order_key);
```

Parametri:
- `$operation` (string) - "GetOrderDetails" sau "ConfirmOrderPayment"
- `$order_key` (string) - OrderKey din cerere

### mpay_vg_certificate_expiry_warning

Se executa cand certificatul se apropie de expirare.

```php
do_action('mpay_vg_certificate_expiry_warning', $days_remaining, $cert_info);
```

Parametri:
- `$days_remaining` (int) - Zile pana la expirare
- `$cert_info` (array) - Informatii certificat

### mpay_vg_signature_verified

Se executa dupa verificarea reusita a semnaturii WS-Security.

```php
do_action('mpay_vg_signature_verified', $operation, $order_key);
```

### mpay_vg_signature_failed

Se executa cand verificarea semnaturii esueaza.

```php
do_action('mpay_vg_signature_failed', $operation, $error_message);
```

## Filters (filtre)

### mpay_vg_order_details_response

Filtreaza raspunsul GetOrderDetails inainte de trimitere.

```php
$response = apply_filters('mpay_vg_order_details_response', $response, $order);
```

Parametri:
- `$response` (array) - Datele raspunsului
- `$order` (WC_Order) - Obiectul comanda

Return: array modificat

### mpay_vg_redirect_params

Filtreaza parametrii formularului de redirect.

```php
$params = apply_filters('mpay_vg_redirect_params', $params, $order);
```

Parametri:
- `$params` (array) - ServiceID, OrderKey, ReturnUrl
- `$order` (WC_Order) - Obiectul comanda

### mpay_vg_is_voucher_eligible

Filtreaza eligibilitatea unui produs pentru Voucher Cultural.

```php
$eligible = apply_filters('mpay_vg_is_voucher_eligible', $eligible, $product_id, $product);
```

Parametri:
- `$eligible` (bool) - Eligibil sau nu
- `$product_id` (int) - ID produs
- `$product` (WC_Product) - Obiectul produs

Utilizare: logica custom de eligibilitate dincolo de categorii.

### mpay_vg_soap_endpoint_url

Filtreaza URL-ul endpoint-ului SOAP.

```php
$url = apply_filters('mpay_vg_soap_endpoint_url', $url);
```

### mpay_vg_signature_algorithm

Filtreaza algoritmul de semnatura folosit.

```php
$algorithm = apply_filters('mpay_vg_signature_algorithm', $algorithm);
```

### mpay_vg_payment_description

Filtreaza descrierea comenzii trimisa catre MPay.

```php
$description = apply_filters('mpay_vg_payment_description', $description, $order);
```

### mpay_vg_idnp_validation

Filtreaza validarea IDNP.

```php
$valid = apply_filters('mpay_vg_idnp_validation', $valid, $idnp);
```

### mpay_vg_log_entry

Filtreaza o intrare de log inainte de salvare.

```php
$entry = apply_filters('mpay_vg_log_entry', $entry, $level);
```

## Hooks WooCommerce folosite de plugin

Pluginul se conecteaza la urmatoarele hooks WooCommerce:

| Hook | Tip | Utilizare |
|------|-----|-----------|
| woocommerce_payment_gateways | filter | Inregistrare gateway MPay |
| woocommerce_checkout_process | action | Validare IDNP la checkout |
| woocommerce_order_status_changed | action | Reactie la schimbari status |
| woocommerce_api_mpay-soap | action | Inregistrare endpoint SOAP |
| woocommerce_admin_order_data_after_billing | action | Afisare meta box |
| woocommerce_receipt_{gateway_id} | action | Pagina redirect plata |

## Exemple utilizare

### Notificare Slack la plata confirmata

```php
add_action('mpay_vg_payment_confirmed', function($order_id, $data) {
    $message = "Plata confirmata: Comanda #{$order_id}, Suma: {$data['amount']} MDL";
    // Trimite notificare Slack
    wp_remote_post($slack_webhook_url, ['body' => json_encode(['text' => $message])]);
}, 10, 2);
```

### Eligibilitate custom Voucher Cultural

```php
add_filter('mpay_vg_is_voucher_eligible', function($eligible, $product_id, $product) {
    // Exclude produse cu pret peste 500 MDL
    if ($product->get_price() > 500) {
        return false;
    }
    return $eligible;
}, 10, 3);
```
