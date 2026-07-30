<?php
namespace MPAY_VG\Core;
if (!defined('ABSPATH')) { exit; }
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }

class Rewrites {
    private const RULE_VERSION = 4;

    public static function init() {
        self::wp('add_action', 'init', [__CLASS__, 'add_rewrites']);

        self::wp('add_action', 'init', [__CLASS__, 'track_rewrite_health'], 5);
        self::wp('add_filter', 'query_vars', [__CLASS__, 'add_query_vars']);
        self::wp('add_action', 'template_redirect', [__CLASS__, 'dispatch']);
        self::wp('add_action', 'admin_init', [__CLASS__, 'maybe_flush_rules']);
        self::wp('add_action', 'update_option_permalink_structure', [__CLASS__, 'flag_rules_for_flush']);
    }
    public static function add_rewrites() {
        self::wp('add_rewrite_rule', '^mpay/redirect/?$', 'index.php?mpay_redirect=1', 'top');
        self::wp('add_rewrite_rule', '^mpay/soap/?$', 'index.php?mpay_soap=1', 'top');
        self::wp('add_rewrite_rule', '^mpay/debug/?$', 'index.php?mpay_debug=1', 'top');
        self::wp('add_rewrite_rule', '^mpay/diagnostics/?$', 'index.php?mpay_diagnostics=1', 'top');
        self::wp('add_rewrite_rule', '^mpay/playbook/?$', 'index.php?mpay_playbook=1', 'top');
    }
    public static function add_query_vars($vars) { $vars[]='mpay_redirect'; $vars[]='mpay_soap'; $vars[]='mpay_debug'; $vars[]='mpay_diagnostics'; $vars[]='mpay_playbook'; return $vars; }
    public static function dispatch() {
        if (self::wp('get_query_var', 'mpay_redirect') === '1') { self::render_redirect(); exit; }
        if (self::wp('get_query_var', 'mpay_soap') === '1') { self::handle_soap(); exit; }
        if (self::wp('get_query_var', 'mpay_debug') === '1') { self::render_debug(); exit; }
        if (self::wp('get_query_var', 'mpay_diagnostics') === '1') { self::render_diagnostics(); exit; }
        if (self::wp('get_query_var', 'mpay_playbook') === '1') { self::render_public_playbook(); exit; }
    }
    private static function render_redirect() {
        self::enforce_https_or_die('redirect');
        if (!class_exists('WC_Order')) {
            self::wp('wp_die', 'WooCommerce required.');
            return;
        }
        $order_id = isset($_GET['order']) ? (int) (self::wp('absint', $_GET['order']) ?? 0) : 0;
        if (!$order_id) {
            self::wp('wp_die', 'Order not found');
            return;
        }
        $opts = \mpay_vg_get_settings();
        $is_prod = !empty($opts['mode_prod']);
        $mpay_url = $is_prod ? 'https://mpay.gov.md/service/pay' : 'https://testmpay.gov.md/service/pay';
        $service_id = $opts['service_id'] ?? '';
        if (!$service_id) {
            self::wp('wp_die', 'ServiceID is not configured.');
            return;
        }
    $order = self::wp('wc_get_order', $order_id); if (!$order) { self::wp('wp_die', 'Order not found'); return; }
    $display_number = method_exists($order, 'get_order_number') ? $order->get_order_number() : $order_id;
    $order_key_value = \MPAY_VG\Core\OrderMapper::ensure_order_key($order, $display_number);
        $return_url = !empty($opts['return_url_override']) ? $opts['return_url_override'] : $order->get_checkout_order_received_url();
        self::wp('nocache_headers'); self::wp('status_header', 200);
        $form_action = self::wp('esc_url', $mpay_url) ?? $mpay_url;
        $service_attr = self::wp('esc_attr', $service_id) ?? $service_id;
        $order_attr = self::wp('esc_attr', $order_key_value) ?? $order_key_value;
        $return_attr = self::wp('esc_url', $return_url) ?? $return_url;
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: text/html; charset=utf-8'); ?>
        <!doctype html><html><head><meta charset="utf-8">
        <meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' https://mpay.gov.md https://testmpay.gov.md">
        <title>Redirecționare MPay…</title></head>
        <body onload="document.forms[0].submit()" style="font-family:system-ui;padding:2rem">
          <h1>Redirecționare către MPay…</h1>
          <p>Vă rugăm așteptați, transferăm comanda către MPay.</p>
                    <form method="post" action="<?php echo $form_action; ?>">
                        <input type="hidden" name="ServiceID" value="<?php echo $service_attr; ?>">
                        <input type="hidden" name="OrderKey" value="<?php echo $order_attr; ?>">
            <input type="hidden" name="ReturnUrl" value="<?php echo $return_attr; ?>">
            <noscript><button type="submit">Continuați la plată</button></noscript>
          </form>
        </body></html><?php
    }
    private static function render_debug() {
        self::enforce_https_or_die('debug');
        $opts = \mpay_vg_get_settings();
        $shared = trim($opts['debug_shared_key'] ?? '');
        if ($shared === '') {
            self::wp('status_header', 403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Debug endpoint disabled';
            return;
        }

        $provided = isset($_GET['key']) ? self::sanitize_text($_GET['key']) : '';
        if ($provided === '' || !hash_equals($shared, $provided)) {
            Logger::log('Acces debug respins', [
                'component' => 'remote.debug',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ], 'error');
            self::wp('status_header', 403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Invalid key';
            return;
        }

        Logger::log('Acces debug remote', [
            'component' => 'remote.debug',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $comparison = self::debug_comparison_payload();
            if ($comparison) {
            Logger::log('Debug payload comparat', [
                'component' => 'remote.debug',
                'bytes' => $comparison['bytes'] ?? 0,
                'sha256' => $comparison['sha256'] ?? '',
                'label' => $comparison['label'] ?? '',
            ]);
        }

        $orderQuery = isset($_GET['order']) ? self::sanitize_text($_GET['order']) : '';
        $snapshot = DiagnosticsSnapshot::build([
            'settings' => $opts,
            'order_query' => $orderQuery,
            'soap_limit' => 10,
            'db_limit' => 15,
            'debug_limit' => 15,
            'availability_limit' => 50,
        ]);

            $endpointTargets = self::build_endpoint_targets($snapshot, $shared);
            $endpointProbe = self::handle_endpoint_probe($endpointTargets);

        $payload = [
            'generated_at' => gmdate('c'),
            'request' => [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'https' => self::request_uses_https(),
            ],
            'snapshot' => $snapshot,
            'comparison' => $comparison,
            'comparison_result' => self::compare_with_last_response($comparison, $snapshot['ws_security']['last_response'] ?? []),
            'order_query' => $orderQuery,
                'endpoint_targets' => $endpointTargets,
                'endpoint_probe' => $endpointProbe,
        ];

        $format = isset($_GET['format']) ? strtolower(self::sanitize_text($_GET['format'])) : '';
        if ($format === 'json') {
            self::render_debug_json($payload);
            return;
        }

        DebugConsole::render($payload, $shared);
    }

    private static function render_debug_json(array $payload) : void {
        self::wp('nocache_headers');
        self::wp('status_header', 200);
        header('Content-Type: application/json; charset=utf-8');
        echo self::encode_json($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function render_diagnostics() {
        self::enforce_https_or_die('diagnostics');
        DiagnosticsPortal::render();
    }
    private static function render_public_playbook() {
        self::enforce_https_or_die('playbook');
        PublicPlaybook::render();
    }
    private static function handle_soap() {
        self::enforce_https_or_die('soap');
        $server = new \MPAY_VG\Soap\Server();
        $server->handle();
    }

    public static function flag_rules_for_flush() {
        self::wp('delete_option', 'mpay_vg_rewrite_version');
    }

    public static function track_rewrite_health() {
        if (self::rules_present()) {
            self::wp('delete_option', 'mpay_vg_rewrite_missing');
            return;
        }
        self::wp('update_option', 'mpay_vg_rewrite_missing', time(), false);
    }

    public static function maybe_flush_rules() {
        $version = (int) (self::wp('get_option', 'mpay_vg_rewrite_version', 0) ?? 0);
        $needs_rules = !self::rules_present();
        if (!$needs_rules && $version >= self::RULE_VERSION) {
            return;
        }
        self::add_rewrites();
        self::wp('flush_rewrite_rules', false);
        self::wp('update_option', 'mpay_vg_rewrite_version', self::RULE_VERSION, false);
        self::wp('delete_option', 'mpay_vg_rewrite_missing');
    }

    public static function overview() : array {
        return [
            'rules_present' => self::rules_present(),
            'rewrite_version' => (int) (self::wp('get_option', 'mpay_vg_rewrite_version', 0) ?? 0),
            'last_missing_timestamp' => (int) (self::wp('get_option', 'mpay_vg_rewrite_missing', 0) ?? 0),
        ];
    }

    private static function rules_present() : bool {
        $rules = self::wp('get_option', 'rewrite_rules');
        if (!is_array($rules)) {
            return false;
        }
        foreach (['^mpay/redirect/?$', '^mpay/soap/?$', '^mpay/debug/?$', '^mpay/diagnostics/?$', '^mpay/playbook/?$'] as $pattern) {
            if (empty($rules[$pattern])) {
                return false;
            }
        }
        return true;
    }

    private static function wp(string $name, ...$args) {
        if (function_exists($name)) {
            return $name(...$args);
        }
        return null;
    }

    private static function enforce_https_or_die($endpoint) {
        if (self::request_uses_https()) {
            return;
        }
        if (self::allow_insecure_http($endpoint)) {
            return;
        }
        self::wp('status_header', 403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Endpointul /mpay/'.$endpoint.' trebuie accesat prin HTTPS.';
        exit;
    }

    private static function sanitize_text($value) {
        $unslashed = self::wp('wp_unslash', $value);
        if ($unslashed !== null) {
            $value = $unslashed;
        }
        $sanitized = self::wp('sanitize_text_field', $value);
        return $sanitized === null ? (string) $value : $sanitized;
    }

    private static function encode_json($data, $flags) {
        $json = self::wp('wp_json_encode', $data, $flags);
        if ($json === null) {
            $json = json_encode($data, $flags);
        }
        return $json;
    }

    private static function request_uses_https() : bool {
        $https = (bool) (self::wp('is_ssl') ?? false);
        if (!$https && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
            if (strpos($proto, 'https') !== false) {
                $https = true;
            }
        }
        return $https;
    }

    private static function debug_comparison_payload() {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            return null;
        }
        $action = isset($_POST['debug_action']) ? strtolower(self::sanitize_text($_POST['debug_action'])) : '';
        if ($action === 'probe_endpoints') {
            return null;
        }
        if (!empty($_FILES['compare']['tmp_name']) && is_uploaded_file($_FILES['compare']['tmp_name'])) {
            $raw = file_get_contents($_FILES['compare']['tmp_name']);
            return self::summarize_payload($raw, $_FILES['compare']['name']);
        }
        if (isset($_POST['compare_base64'])) {
            $raw = base64_decode((string) $_POST['compare_base64'], true);
            if ($raw !== false) {
                return self::summarize_payload($raw, 'compare_base64');
            }
        }
        $input = file_get_contents('php://input');
        if ($input === '' || $input === false) {
            return null;
        }
        return self::summarize_payload($input, $_SERVER['CONTENT_TYPE'] ?? 'raw-body');
    }

    private static function summarize_payload($raw, $label = 'payload') {
        if ($raw === null || $raw === '') {
            return null;
        }
        $bytes = strlen($raw);
        return [
            'label' => $label,
            'bytes' => $bytes,
            'sha256' => strtoupper(hash('sha256', $raw)),
            'sha1' => strtoupper(hash('sha1', $raw)),
            'md5' => strtoupper(hash('md5', $raw)),
            'preview_base64' => base64_encode(substr($raw, 0, 120)),
            'preview_ascii' => preg_replace('/[^\x20-\x7E]/', '.', substr($raw, 0, 120)),
        ];
    }

    private static function allow_insecure_http($endpoint = '') : bool {
        $opts = \mpay_vg_get_settings();
        $allow = !empty($opts['allow_insecure_http']);
        $filtered = self::wp('apply_filters', 'mpay_vg_allow_insecure_endpoint', $allow, $endpoint);
        return !empty($filtered);
    }

    private static function compare_with_last_response(?array $comparison, $lastResponse) : ?array {
        if (!$comparison) {
            return null;
        }
        if (!is_array($lastResponse) || empty($lastResponse['sha256'])) {
            return [
                'status' => 'unknown',
                'detail' => 'Nu există un răspuns recent în cache pentru comparație.',
                'differences' => [],
            ];
        }

        $diffs = [];
        $diffs[] = self::build_comparison_entry('Dimensiune (bytes)', $lastResponse['bytes'] ?? null, $comparison['bytes'] ?? null);
        $diffs[] = self::build_comparison_entry('SHA256', $lastResponse['sha256'] ?? '', $comparison['sha256'] ?? '', true);
        $diffs[] = self::build_comparison_entry('SHA1', $lastResponse['sha1'] ?? '', $comparison['sha1'] ?? '', true);

        $status = 'match';
        $detail = 'Fișierul se potrivește cu ultimul răspuns salvat.';
        foreach ($diffs as $entry) {
            if (!$entry['match']) {
                $status = 'mismatch';
                $detail = 'Diferență la '.$entry['field'].'.';
                break;
            }
        }

        return [
            'status' => $status,
            'detail' => $detail,
            'differences' => $diffs,
        ];
    }

    private static function build_comparison_entry(string $field, $server, $file, bool $caseInsensitive = false) : array {
        $serverStr = self::stringify_metric($server);
        $fileStr = self::stringify_metric($file);
        if ($caseInsensitive) {
            $match = ($serverStr !== '' && $fileStr !== '') ? (strtoupper($serverStr) === strtoupper($fileStr)) : ($serverStr === $fileStr);
        } else {
            $match = $serverStr === $fileStr;
        }
        return [
            'field' => $field,
            'server' => $serverStr,
            'file' => $fileStr,
            'match' => $match,
        ];
    }

    private static function stringify_metric($value) : string {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
        }
        return (string) $value;
    }

    private static function insecure_mode_enabled() : bool {
        $opts = \mpay_vg_get_settings();
        return !empty($opts['allow_insecure_http']);
    }

    private static function site_prefers_https() : bool {
        $home = self::wp('home_url', '/') ?? '/';
        $scheme = parse_url($home, PHP_URL_SCHEME);
        return is_string($scheme) && strtolower($scheme) === 'https';
    }

    private static function build_endpoint_targets(array $snapshot, string $shared) : array {
        $site = $snapshot['site'] ?? [];
        $targets = [];

        $soap = $site['soap_endpoint'] ?? '';
        if ($soap !== '') {
            $targets[] = [
                'id' => 'soap',
                'label' => 'SOAP',
                'method' => 'GET',
                'url' => $soap,
                'ok_codes' => [200, 400, 401, 403, 405],
                'note' => 'Un răspuns 200/400/401/403/405 indică faptul că endpoint-ul răspunde (GET/HEAD poate returna 405).',
                'curl' => 'curl -I "'.$soap.'"',
            ];
        }

        $redirect = $site['redirect_endpoint'] ?? '';
        if ($redirect !== '') {
            $redirectUrl = str_replace('{ID}', '12345', $redirect);
            $targets[] = [
                'id' => 'redirect',
                'label' => 'Redirect',
                'method' => 'GET',
                'url' => $redirectUrl,
                'ok_codes' => [200, 301, 302, 307, 308],
                'note' => 'Ar trebui să redea pagina de redirect sau să răspundă cu 302 către MPay.',
                'curl' => 'curl -I "'.$redirectUrl.'"',
            ];
        }

        $home = $site['home_url'] ?? (function_exists('home_url') ? \home_url('/') : '/');
        $home = rtrim((string) $home, '/');
        $debugBase = $home.'/mpay/debug';
        $debugUrl = $debugBase.'?key='.rawurlencode($shared);
        $debugJson = $debugUrl.'&format=json';
        $targets[] = [
            'id' => 'debug-json',
            'label' => 'Debug JSON',
            'method' => 'GET',
            'url' => $debugJson,
            'ok_codes' => [200],
            'note' => 'Trebuie să livreze un payload JSON valid pentru consolă.',
            'curl' => 'curl -H "Accept: application/json" -I "'.$debugJson.'"',
        ];

        return $targets;
    }

    private static function handle_endpoint_probe(array $targets) : ?array {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'POST') {
            return null;
        }
        $action = isset($_POST['debug_action']) ? strtolower(self::sanitize_text($_POST['debug_action'])) : '';
        if ($action !== 'probe_endpoints') {
            return null;
        }

        $results = [];
        foreach ($targets as $target) {
            $url = $target['url'] ?? '';
            if ($url === '') {
                continue;
            }
            $requestArgs = [
                'method' => $target['method'] ?? 'GET',
                'timeout' => 8,
                'redirection' => 2,
                'headers' => [
                    'User-Agent' => 'MPay-VG-DebugProbe/14.3.2',
                    'Accept' => 'application/json, text/xml, */*;q=0.1',
                ],
            ];
            $start = microtime(true);
            $response = self::wp('wp_remote_request', $url, $requestArgs);
            $duration = round((microtime(true) - $start) * 1000, 1);

            $result = [
                'id' => $target['id'],
                'label' => $target['label'],
                'url' => $url,
                'method' => $requestArgs['method'],
                'duration_ms' => $duration,
                'note' => $target['note'] ?? '',
                'curl' => $target['curl'] ?? '',
            ];

            if ($response === null) {
                $result['ok'] = false;
                $result['error'] = 'wp_remote_request indisponibil';
                $results[] = $result;
                continue;
            }

            if (function_exists('is_wp_error') && is_wp_error($response)) {
                $result['ok'] = false;
                $result['error'] = $response->get_error_message();
                $result['error_code'] = $response->get_error_code();
                $results[] = $result;
                continue;
            }

            $code = self::wp('wp_remote_retrieve_response_code', $response);
            if ($code === null && is_array($response) && isset($response['response']['code'])) {
                $code = (int) $response['response']['code'];
            }
            $message = self::wp('wp_remote_retrieve_response_message', $response);
            if ($message === null && is_array($response) && isset($response['response']['message'])) {
                $message = (string) $response['response']['message'];
            }
            $body = self::wp('wp_remote_retrieve_body', $response);
            if ($body === null && is_array($response) && isset($response['body'])) {
                $body = (string) $response['body'];
            }

            $okCodes = $target['ok_codes'] ?? [];
            $ok = true;
            if ($okCodes) {
                $ok = in_array((int) $code, $okCodes, true);
            } elseif ($code !== null) {
                $ok = ($code >= 200 && $code < 400);
            }

            $result['ok'] = $ok;
            $result['status_code'] = $code;
            $result['status_message'] = $message;
            if ($body !== null && $body !== '') {
                $snippet = trim(substr($body, 0, 180));
                $result['body_snippet'] = $snippet;
            }

            $results[] = $result;
        }

        Logger::log('Debug endpoint probe executat', [
            'component' => 'remote.debug',
            'count' => count($results),
            'results' => array_map(function($entry) {
                return [
                    'id' => $entry['id'],
                    'ok' => !empty($entry['ok']),
                    'code' => $entry['status_code'] ?? null,
                ];
            }, $results),
        ]);

        return [
            'ran_at' => gmdate('c'),
            'results' => $results,
        ];
    }
}
