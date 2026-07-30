<?php
namespace MPAY_VG\Core;
if (!defined('ABSPATH')) { exit; }

class TestPlaybook {
    public static function scenarios(array $snapshot, array $context = []) : array {
        $site = $snapshot['site'] ?? [];
        $settings = $snapshot['settings'] ?? [];
        $serviceId = (string) ($settings['service_id'] ?? '');
        $soapEndpoint = self::soap_endpoint($site);
        $soapDir = (string) ($site['uploads']['soap_dir'] ?? '');
        $cli = self::wp_cli_binary();
        $orderContext = $context['order'] ?? self::detect_reference_order($snapshot);
        $tokens = self::build_tokens($orderContext, $serviceId);

        $curlGetOrder = self::curl_command($soapEndpoint, 'soap/GetOrderDetails.xml');
        $curlConfirmOk = self::curl_command($soapEndpoint, 'soap/ConfirmOrderPayment-success.xml');
        $curlConfirmPartial = self::curl_command($soapEndpoint, 'soap/ConfirmOrderPayment-partial.xml');
        $curlConfirmInvalid = self::curl_command($soapEndpoint, 'soap/ConfirmOrderPayment-invalid.xml');
        $guardPing = "curl -vk -X POST \"{$soapEndpoint}\" -H 'Content-Length:0' -H 'User-Agent:mpay-monitor'";

        $scenarios = [
            [
                'id' => 'soap-handshake',
                'title' => 'Scenariul 1 – Handshake & ping MPay',
                'objective' => 'Validează că endpoint-ul SOAP răspunde în TEST, semnează răspunsurile și acceptă ping-ul de monitorizare MPay.',
                'commands' => array_filter([
                    $curlGetOrder,
                    $cli.' mpay diagnostics soap --limit=10',
                ]),
                'validations' => array_filter([
                    'Răspunsul la GetOrderDetails trebuie să fie HTTP 200 și să conțină wsse:Security cu semnătură validă.',
                    'În "Runtime / SOAP" apare ultima operațiune = GetOrderDetails cu result=OK.',
                    $soapDir ? 'Directorul '.$soapDir.' este populat cu fișierul XML persistat (Enable SOAP persist).' : 'Activează "Persistență SOAP" dacă vrei să salvezi cererile în uploads/mpay-vg/soap.',
                    $serviceId ? 'În fișierul SOAP trebuie folosit ServiceID '.$serviceId.' exact ca în configurare.' : '',
                ]),
                'simulations' => [
                    'Ping guard fără corp: '.$guardPing.' → status 204 + antet X-MPay-Guard: PingAck (confirmă că throttling-ul funcționează).',
                ],
                'notes' => [
                    'Folosește un OrderKey real (vezi secțiunea „Inspector OrderKey”) pentru a obține un răspuns complet.',
                ],
            ],
            [
                'id' => 'confirm-order',
                'title' => 'Scenariul 2 – ConfirmOrderPayment (happy path)',
                'objective' => 'Simulează fluxul complet: creare comandă test, ConfirmOrderPayment reușit și validare status în WooCommerce.',
                'commands' => [
                    $cli.' mpay diagnostics create-order --amount=250 --reason="QA Flow complet"',
                    $curlConfirmOk,
                    $cli.' mpay diagnostics snapshot --order=<ORDER_KEY> --db-limit=5 --soap-limit=5',
                ],
                'validations' => [
                    'Comanda generată trece în status processing/completed după ConfirmOrderPayment (sau „mpay-partial” dacă totalul din SOAP < totalul comenzii).',
                    'În logul DB (secțiunea „Log interacțiuni SOAP”) apare result=Confirmed cu PaymentID-ul folosit.',
                    'Evenimentul „Ultimul SOAP” arată ConfirmOrderPayment, iar OrderKey-ul coincide cu cel din fișier.',
                ],
                'simulations' => [
                    'Actualizează fișierul ConfirmOrderPayment-success.xml cu valorile ServiceID='.$serviceId.', OrderKey + PaymentID/InvoiceID unice și PaidAt actual.',
                ],
                'notes' => [
                    'Reține OrderKey-ul afișat de comanda create-order și înlocuiește-l în fișierul SOAP + în comanda snapshot.',
                ],
            ],
            [
                'id' => 'confirm-duplicate',
                'title' => 'Scenariul 3 – Idempotent / duplicate ConfirmOrderPayment',
                'objective' => 'Demonstrează că același PaymentID nu dublează plata și primește AlreadyConfirmed/LockedDuplicate.',
                'commands' => [
                    $curlConfirmOk,
                    $curlConfirmOk,
                    $cli.' mpay diagnostics snapshot --order=<ORDER_KEY> --db-limit=5 --debug-limit=5',
                ],
                'validations' => [
                    'A doua execuție returnează tot HTTP 200 cu ConfirmOrderPaymentResponse gol.',
                    'În DB apare result=AlreadyConfirmed (sau LockedDuplicate dacă execuțiile sunt simultane).',
                    'Meta `_mpay_payment_id` nu se modifică și nu apar note duplicate în comandă.',
                ],
                'simulations' => [
                    'Rulează al doilea cURL imediat (sub 1s) pentru a valida LockedDuplicate via transientul de protecție.',
                ],
                'notes' => [
                    'Nu schimba PaymentID între cele două apeluri; doar confirmi aceeași plată în TEST.',
                ],
            ],
            [
                'id' => 'partial-and-errors',
                'title' => 'Scenariul 4 – Plăți parțiale & erori controlate',
                'objective' => 'Verifică comportamentul atunci când suma diferă sau moneda este invalidă.',
                'commands' => [
                    $curlConfirmPartial,
                    $curlConfirmInvalid,
                    $cli.' mpay diagnostics snapshot --order=<ORDER_KEY> --db-limit=5 --debug-limit=5',
                ],
                'validations' => [
                    'ConfirmOrderPayment-partial trebuie să returneze 200; comanda intră în status „mpay-partial” iar logul DB păstrează result=Confirmed.',
                    'ConfirmOrderPayment-invalid forțează fault „InvalidCurrency/AmountMismatch” (HTTP 500) și eveniment de eroare în tab-ul „Evenimente debug”.',
                    'În /mpay/debug și în snapshot JSON se văd codurile de eroare cu timestampul testului.',
                ],
                'simulations' => [
                    'Pentru partial setează <TotalAmount> cu cel puțin 1 MDL mai mic decât totalul comenzii.',
                    'Pentru fault setează <Currency> EUR sau modifică ServiceID pentru a provoca UnknownService.',
                ],
                'notes' => [
                    'Scenariul negative se rulează după ce OrderKey-ul există; repetă testele pentru diferite comenzi.',
                ],
            ],
            [
                'id' => 'wssec-debug-toolkit',
                'title' => 'Scenariul 5 – Toolkit debug WS-Security',
                'objective' => 'Pregătește toate dovezile tehnice pentru MPay (fingerprint, hash, fișier semnat) fără a depinde de echipa MPay.',
                'commands' => [
                    $cli.' mpay wssec inspect --format=json_pretty',
                    $cli.' mpay diagnostics soap --limit=3',
                    'curl -vk --trace-ascii trace.log --data-binary @soap/ServiceProviderSettingsResponse.xml "'.$soapEndpoint.'"',
                    'curl -s -X POST -F "compare=@wp-content/uploads/mpay-vg/soap/soap-response-*.xml" "'.rtrim($site['soap_endpoint'] ?? '/mpay/soap', '/').'/../debug?key=<DEBUG_KEY>"',
                ],
                'validations' => [
                    'În ieșirea `mpay wssec inspect` apare fingerprint-ul certificatului prestator (SHA-256) identic cu cel înregistrat la MPay.',
                    'Hash-urile SHA256/SHA1 ale ultimului răspuns (`last_response`) coincid cu fișierul persistat și cu rezultatul `curl -vk` din rețea.',
                    'Fișierul semnat trece `xmlsec1 --verify --pubkey-pem <service-cert>.pem soap-response-*.xml` local.',
                ],
                'notes' => [
                    'Trage atenție la encoding: fișierele SOAP trebuie salvate în UTF-8 fără BOM (verifică cu `xxd -g1 -l4 <fisier>` → `3c 3f 78 6d`).',
                    'După semnare nu modifica structura sau datele XML (dezactivează minify/caching pentru /mpay/soap).',
                    'Poți încărca fișierul primit de la MPay în `/mpay/debug?key=...` (metoda POST câmp `compare`) pentru a compara hash-urile direct pe server.',
                ],
            ],
        ];

        $scenarios = self::apply_tokens($scenarios, $tokens);

        if (!empty($orderContext)) {
            $reference = self::reference_block($orderContext);
            foreach ($scenarios as &$scenario) {
                $scenario['reference'] = $reference;
            }
            unset($scenario);
        }

        return $scenarios;
    }

    private static function detect_reference_order(array $snapshot) : ?array {
        if (!empty($snapshot['order_inspection']) && ($snapshot['order_inspection']['status'] ?? '') === 'ok') {
            $info = $snapshot['order_inspection'];
            return [
                'id' => (int) ($info['resolved_id'] ?? 0),
                'order_key' => (string) ($info['mpay_meta']['order_key'] ?? ''),
                'order_number' => (string) ($info['order_number'] ?? ''),
                'total' => (float) ($info['total'] ?? 0),
                'currency' => (string) ($info['currency'] ?? 'MDL'),
            ];
        }

        $events = $snapshot['runtime']['db_events'] ?? [];
        foreach ($events as $event) {
            $orderId = (int) ($event['order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            if (function_exists('wc_get_order')) {
                $order = wc_get_order($orderId);
                if ($order) {
                    $brief = DiagnosticsTools::serialize_order_brief($order);
                    if ($brief) {
                        return $brief;
                    }
                }
            }
        }

        return DiagnosticsTools::get_reference_order();
    }

    private static function build_tokens(?array $order, string $serviceId) : array {
        $tokens = [];
        if (!empty($order['order_key'])) {
            $tokens['ORDER_KEY'] = $order['order_key'];
        }
        if (!empty($order['id'])) {
            $tokens['ORDER_ID'] = (string) $order['id'];
        }
        if (!empty($order['total'])) {
            $tokens['ORDER_TOTAL'] = number_format((float) $order['total'], 2, '.', '');
        }
        if (!empty($order['currency'])) {
            $tokens['ORDER_CURRENCY'] = $order['currency'];
        }
        if ($serviceId !== '') {
            $tokens['SERVICE_ID'] = $serviceId;
        }
        return $tokens;
    }

    private static function apply_tokens($value, array $tokens)
    {
        if (!$tokens) {
            return $value;
        }
        $search = [];
        $replace = [];
        foreach ($tokens as $token => $replacement) {
            $search[] = '<'.$token.'>';
            $replace[] = $replacement;
        }
        if (is_string($value)) {
            return str_replace($search, $replace, $value);
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::apply_tokens($item, $tokens);
            }
            return $value;
        }
        return $value;
    }

    private static function reference_block(array $order) : array {
        $block = [];
        if (!empty($order['order_key'])) {
            $block['OrderKey'] = $order['order_key'];
        }
        if (!empty($order['id'])) {
            $block['Order ID'] = (string) $order['id'];
        }
        if (!empty($order['total'])) {
            $amount = number_format((float) $order['total'], 2).' '.($order['currency'] ?? 'MDL');
            $block['Total'] = $amount;
        }
        if (!empty($order['order_number'])) {
            $block['Order number'] = $order['order_number'];
        }
        if (!empty($order['created_at'])) {
            $block['Creată la'] = $order['created_at'];
        }
        return $block;
    }

    private static function soap_endpoint(array $site) : string {
        $endpoint = $site['soap_endpoint'] ?? '';
        if ($endpoint === '' && function_exists('home_url')) {
            $endpoint = home_url('/mpay/soap');
        }
        if ($endpoint === '') {
            $endpoint = '/mpay/soap';
        }
        return rtrim($endpoint, '/');
    }

    private static function wp_cli_binary() : string {
        $default = 'wp';
        if (function_exists('apply_filters')) {
            $custom = apply_filters('mpay_vg_wp_cli_binary', $default);
            if (is_string($custom) && $custom !== '') {
                return $custom;
            }
        }
        return $default;
    }

    private static function curl_command(string $endpoint, string $payloadFile) : string {
        $endpoint = rtrim($endpoint, '/');
        return "curl -vk \"{$endpoint}\" \\\n  -H 'Content-Type: text/xml' \\\n  --data-binary @{$payloadFile}";
    }
}
