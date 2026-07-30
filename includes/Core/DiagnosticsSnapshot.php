<?php
namespace MPAY_VG\Core;
use MPAY_VG\Core\OrderMapper;
use MPAY_VG\Core\DB;
use MPAY_VG\Soap\Server;
if (!defined('ABSPATH')) { exit; }

class DiagnosticsSnapshot {
    public static function build(array $args = []) : array {
        $opts = $args['settings'] ?? \mpay_vg_get_settings();
        $orderQuery = isset($args['order_query']) ? self::clean_text($args['order_query']) : '';
        $soapLimit = max(1, (int) ($args['soap_limit'] ?? 5));
        $debugLimit = max(1, (int) ($args['debug_limit'] ?? 10));
        $dbLimit = max(1, (int) ($args['db_limit'] ?? 10));
        $availabilityLimit = max(1, (int) ($args['availability_limit'] ?? 25));

        $availability = self::availability_log($availabilityLimit);
        $lastSoap = \mpay_vg_get_runtime('last_soap', []);
        if (!is_array($lastSoap)) {
            $lastSoap = [];
        }

        $site = self::site_overview($opts);
        $environment = self::environment_info();
        $certificates = self::certificates_info($opts);
        $extensions = $environment['extensions'];
        $storage = self::storage_info($site['uploads']);
        $snapshot = [
            'generated_at' => gmdate('c'),
            'site' => $site,
            'settings' => self::settings_summary($opts),
            'certificates' => $certificates,
            'environment' => $environment,
            'system' => self::system_info(),
            'meta' => self::meta_info(),
            'storage' => $storage,
            'soap' => [
                'config' => self::soap_config($opts),
                'persisted_samples' => self::recent_soap_files($opts, $soapLimit),
            ],
            'runtime' => [
                'availability' => $availability,
                'last_soap' => $lastSoap,
                'debug_events' => \mpay_vg_get_debug_events($debugLimit),
                'db_events' => self::recent_db_events($opts, $dbLimit),
                'cron' => self::cron_overview(),
                'last_soap_excerpt' => self::soap_excerpt($lastSoap['soap_file'] ?? ''),
            ],
            'rewrites' => Rewrites::overview(),
            'status' => self::status_matrix($opts, $certificates, $extensions, $storage),
            'ws_security' => self::ws_security_info($opts, $certificates),
        ];

        $snapshot['compliance'] = self::compliance_matrix(
            $site,
            $opts,
            $certificates,
            $snapshot['status'],
            $environment,
            $snapshot['runtime']
        );

        if ($orderQuery !== '') {
            $snapshot['order_inspection'] = self::inspect_order($orderQuery);
        } else {
            $snapshot['order_inspection'] = null;
        }

        return $snapshot;
    }

    private static function clean_text($value) : string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (function_exists('sanitize_text_field')) {
            $value = sanitize_text_field($value);
        }
        return $value;
    }

    private static function site_overview(array $opts) : array {
        $home = function_exists('home_url') ? home_url('/') : '/';
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => sys_get_temp_dir(), 'baseurl' => ''];
        $uploadsDir = $uploads['basedir'] ?? sys_get_temp_dir();
        $uploadsUrl = $uploads['baseurl'] ?? '';
        $soapDir = trailingslashit($uploadsDir).'mpay-vg/soap';
        $soapUrl = $uploadsUrl ? trailingslashit($uploadsUrl).'mpay-vg/soap' : '';

        return [
            'home_url' => $home,
            'site_url' => function_exists('site_url') ? site_url('/') : '/',
            'soap_endpoint' => $home.'mpay/soap',
            'redirect_endpoint' => $home.'mpay/redirect?order={ID}',
            'debug_endpoint' => $home.'mpay/debug?key=***',
            'diagnostics_endpoint' => $home.'mpay/diagnostics?key=***',
            'domain' => parse_url($home, PHP_URL_HOST),
            'home_https' => self::url_is_https($home),
            'request_https' => self::current_request_is_https(),
            'allow_insecure_http' => !empty($opts['allow_insecure_http']),
            'permalink_structure' => function_exists('get_option') ? get_option('permalink_structure') : '',
            'uploads' => [
                'basedir' => $uploadsDir,
                'baseurl' => $uploadsUrl,
                'soap_dir' => $soapDir,
                'soap_url' => $soapUrl,
            ],
        ];
    }

    private static function settings_summary(array $opts) : array {
        return [
            'profile' => $opts['config_profile'] ?? 'custom',
            'mode' => !empty($opts['mode_prod']) ? 'PROD' : 'TEST',
            'service_id' => $opts['service_id'] ?? '',
            'return_url_override' => $opts['return_url_override'] ?? '',
            'enforce_wssec' => !empty($opts['enforce_wssec']),
            'soap_guard' => !empty($opts['enable_soap_guard']),
            'soap_persist' => !empty($opts['enable_soap_persist']),
            'event_log_db' => !empty($opts['enable_event_log_db']),
            'attach_invoice_pdf' => !empty($opts['attach_invoice_pdf']),
            'allow_partial' => !empty($opts['allow_partial']),
            'allow_advance' => !empty($opts['allow_advance']),
            'allow_insecure_http' => !empty($opts['allow_insecure_http']),
            'debug_key_present' => !empty($opts['debug_shared_key']),
        ];
    }

    private static function certificates_info(array $opts) : array {
        return [
            'mpay_public_cert' => self::certificate_details($opts['mpay_public_cert_path'] ?? ''),
            'sp_public_cert' => self::certificate_details($opts['sp_public_cert_path'] ?? ''),
            'sp_private_key' => [
                'path' => $opts['sp_private_key_path'] ?? '',
                'exists' => (!empty($opts['sp_private_key_path']) && file_exists($opts['sp_private_key_path'])),
            ],
        ];
    }

    private static function certificate_details(string $path) : array {
        $info = [
            'path' => $path,
            'exists' => ($path !== '' && file_exists($path)),
        ];
        if (!$info['exists']) {
            return $info;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return $info;
        }
        $pem = \mpay_vg_normalize_certificate($raw);
        $parsed = @openssl_x509_parse($pem);
        if (!is_array($parsed)) {
            return $info;
        }
        $info['subject_cn'] = $parsed['subject']['CN'] ?? '';
        $info['issuer_cn'] = $parsed['issuer']['CN'] ?? '';
        if (!empty($parsed['validFrom_time_t'])) {
            $info['valid_from'] = gmdate('c', (int) $parsed['validFrom_time_t']);
        }
        if (!empty($parsed['validTo_time_t'])) {
            $info['valid_to'] = gmdate('c', (int) $parsed['validTo_time_t']);
            $info['days_left'] = floor(((int) $parsed['validTo_time_t'] - time()) / 86400);
        }
        if (!empty($parsed['serialNumber'])) {
            $info['serial'] = $parsed['serialNumber'];
        } elseif (!empty($parsed['serialNumberHex'])) {
            $info['serial'] = $parsed['serialNumberHex'];
        }
        $info['fingerprint_sha1'] = \mpay_vg_certificate_fingerprint($pem, 'sha1');
        $info['fingerprint_sha256'] = \mpay_vg_certificate_fingerprint($pem, 'sha256');
        return $info;
    }

    private static function environment_info() : array {
        $extensions = [
            'openssl' => extension_loaded('openssl'),
            'soap' => extension_loaded('soap'),
            'dom' => extension_loaded('dom'),
            'mbstring' => extension_loaded('mbstring'),
            'curl' => extension_loaded('curl'),
            'zip' => class_exists('\ZipArchive'),
        ];
        $phpBinary = PHP_BINARY ?: (defined('PHP_BINDIR') ? PHP_BINDIR : '');
        $opensslBin = \mpay_vg_locate_openssl();
        global $wpdb;
        return [
            'server_time' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : date_default_timezone_get(),
            'php' => PHP_VERSION,
            'php_binary' => $phpBinary,
            'wp' => function_exists('get_bloginfo') ? get_bloginfo('version') : null,
            'woocommerce' => defined('WC_VERSION') ? WC_VERSION : null,
            'extensions' => $extensions,
            'db_version' => isset($wpdb) && method_exists($wpdb, 'db_version') ? $wpdb->db_version() : null,
            'openssl_bin' => $opensslBin,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? '',
        ];
    }

    private static function system_info() : array {
        return [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_input_vars' => ini_get('max_input_vars'),
            'php_ini' => php_ini_loaded_file(),
            'php_sapi' => PHP_SAPI,
        ];
    }

    private static function storage_info(array $uploads) : array {
        $uploadsDir = $uploads['basedir'] ?? sys_get_temp_dir();
        $soapDir = $uploads['soap_dir'] ?? ($uploadsDir.'/mpay-vg/soap');
        $diskFree = @disk_free_space($uploadsDir);
        $diskTotal = @disk_total_space($uploadsDir);
        return [
            'uploads_dir' => $uploadsDir,
            'soap_dir' => $soapDir,
            'soap_dir_exists' => is_dir($soapDir),
            'soap_dir_writable' => self::path_is_writable($soapDir),
            'uploads_writable' => self::path_is_writable($uploadsDir),
            'disk_free_mb' => $diskFree ? round($diskFree / 1048576, 2) : null,
            'disk_total_mb' => $diskTotal ? round($diskTotal / 1048576, 2) : null,
        ];
    }

    private static function soap_config(array $opts) : array {
        return [
            'soap_guard_enabled' => !empty($opts['enable_soap_guard']),
            'persist_enabled' => !empty($opts['enable_soap_persist']),
            'max_body_bytes' => Server::MAX_BODY,
            'rate_window_seconds' => Server::RATE_WINDOW,
            'rate_limit_per_ip' => Server::RATE_LIMIT,
        ];
    }

    private static function recent_soap_files(array $opts, int $limit) : array {
        if (empty($opts['enable_soap_persist'])) {
            return [];
        }
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir() : ['basedir' => sys_get_temp_dir(), 'baseurl' => ''];
        $dir = trailingslashit($uploads['basedir'] ?? sys_get_temp_dir()).'mpay-vg/soap';
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir.'/*.xml');
        if (!is_array($files) || !$files) {
            return [];
        }
        rsort($files);
        $files = array_slice($files, 0, $limit);
        $baseUrl = $uploads['baseurl'] ?? '';
        $soapUrlBase = $baseUrl ? trailingslashit($baseUrl).'mpay-vg/soap' : '';
        return array_map(function($path) use ($soapUrlBase) {
            $name = basename($path);
            $size = file_exists($path) ? filesize($path) : null;
            $mtime = file_exists($path) ? filemtime($path) : false;
            $url = $soapUrlBase ? trailingslashit($soapUrlBase).$name : '';
            return [
                'name' => $name,
                'size' => $size,
                'modified' => $mtime ? gmdate('c', $mtime) : null,
                'path' => $path,
                'url' => $url,
            ];
        }, $files);
    }

    private static function recent_db_events(array $opts, int $limit) : array {
        if (empty($opts['enable_event_log_db'])) {
            return [];
        }
        global $wpdb;
        $table = $wpdb->prefix . DB::TABLE;
        $limit = max(1, $limit);
        $sql = $wpdb->prepare("SELECT ts,op,result,order_id,invoice_id,payment_id,amount,currency,ip,duration_ms,soap_file FROM $table ORDER BY id DESC LIMIT %d", $limit);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }
        return array_map(function($row) {
            return [
                'ts' => $row['ts'] ?? '',
                'op' => $row['op'] ?? '',
                'result' => $row['result'] ?? '',
                'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : 0,
                'invoice_id' => $row['invoice_id'] ?? '',
                'payment_id' => $row['payment_id'] ?? '',
                'amount' => isset($row['amount']) ? (float) $row['amount'] : null,
                'currency' => $row['currency'] ?? '',
                'ip' => $row['ip'] ?? '',
                'duration_ms' => isset($row['duration_ms']) ? (int) $row['duration_ms'] : 0,
                'soap_file' => $row['soap_file'] ?? '',
            ];
        }, $rows);
    }

    private static function inspect_order(string $query) : array {
        $result = [
            'requested' => $query,
            'resolved_id' => 0,
            'status' => 'not_found',
        ];

        $resolved = OrderMapper::resolve_order_id($query);
        if (!$resolved) {
            return $result;
        }

        $result['resolved_id'] = $resolved;
        $order = function_exists('wc_get_order') ? wc_get_order($resolved) : null;
        if (!$order) {
            $result['status'] = 'missing_order';
            return $result;
        }

        $result['status'] = 'ok';
        $result['order_number'] = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $resolved;
        $result['post_status'] = $order->get_status();
        $result['total'] = (float) $order->get_total();
        $result['currency'] = $order->get_currency();
        $result['mpay_meta'] = [
            'order_key' => $order->get_meta('_mpay_order_key', true),
            'payment_id' => $order->get_meta('_mpay_payment_id', true),
            'invoice_id' => $order->get_meta('_mpay_invoice_id', true),
            'paid_at' => $order->get_meta('_mpay_paid_at', true),
        ];
        $result['dates'] = [
            'created' => $order->get_date_created() ? $order->get_date_created()->date(DATE_ATOM) : null,
            'paid' => $order->get_date_paid() ? $order->get_date_paid()->date(DATE_ATOM) : null,
            'completed' => $order->get_date_completed() ? $order->get_date_completed()->date(DATE_ATOM) : null,
        ];
        $result['billing'] = [
            'name' => $order->get_formatted_billing_full_name(),
            'company' => $order->get_billing_company(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'idnp' => $order->get_meta('_billing_idnp', true),
            'idno' => $order->get_meta('_billing_idno', true),
            'fiscal_code' => $order->get_meta('_billing_fiscal_code', true),
        ];
        return $result;
    }

    private static function availability_log(int $limit) : array {
        $trace = \mpay_vg_get_runtime('availability', []);
        if (!is_array($trace)) {
            return [];
        }
        $trace = array_values($trace);
        $count = count($trace);
        if ($count > $limit) {
            $trace = array_slice($trace, $count - $limit);
        }
        return $trace;
    }

    private static function cron_overview() : array {
        $events = [
            'cert_monitor_next' => function_exists('wp_next_scheduled') ? wp_next_scheduled('mpay_vg_cert_check') : null,
            'cron_enabled' => function_exists('wp_next_scheduled') ? true : false,
        ];
        foreach ($events as $key => $ts) {
            if ($ts) {
                $events[$key] = gmdate('c', (int) $ts);
            } else {
                $events[$key] = null;
            }
        }
        return $events;
    }

    private static function meta_info() : array {
        $theme = function_exists('wp_get_theme') ? wp_get_theme() : null;
        return [
            'plugin_version' => function_exists('get_option') ? (get_option('mpay_vg_version') ?: '-') : '-',
            'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : null,
            'environment_type' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : null,
            'active_theme' => ($theme && $theme->exists()) ? ($theme->get('Name').' '.$theme->get('Version')) : null,
            'php_loaded_extensions' => implode(', ', get_loaded_extensions()),
        ];
    }

    private static function status_matrix(array $opts, array $certificates, array $extensions, array $storage) : array {
        $wsReady = !empty($opts['enforce_wssec']) && !empty($certificates['sp_private_key']['exists']) && !empty($certificates['mpay_public_cert']['exists']);
        $soapPersistReady = !empty($opts['enable_soap_persist']) ? !empty($storage['soap_dir_exists']) && !empty($storage['soap_dir_writable']) : false;
        return [
            'ws_security_ready' => $wsReady,
            'soap_guard_enabled' => !empty($opts['enable_soap_guard']),
            'soap_persist_ready' => $soapPersistReady,
            'https_enforced' => empty($opts['allow_insecure_http']),
            'soap_extension' => !empty($extensions['soap']),
        ];
    }

    private static function ws_security_info(array $opts, array $certificates) : array {
        $lastSignature = \mpay_vg_get_runtime('last_signature', []);
        if (!is_array($lastSignature)) {
            $lastSignature = [];
        }
        $lastVerify = \mpay_vg_get_runtime('last_verify', []);
        if (!is_array($lastVerify)) {
            $lastVerify = [];
        }
        $lastResponse = \mpay_vg_get_runtime('last_response', []);
        if (!is_array($lastResponse)) {
            $lastResponse = [];
        }
        return [
            'enforced' => !empty($opts['enforce_wssec']),
            'mpay_certificate' => $certificates['mpay_public_cert'] ?? [],
            'service_certificate' => $certificates['sp_public_cert'] ?? [],
            'private_key_path' => $opts['sp_private_key_path'] ?? '',
            'last_signature' => $lastSignature,
            'last_verification' => $lastVerify,
            'last_response' => $lastResponse,
            'checklist' => [
                'Verifică fingerprint-ul certificatului MPay din log cu cel comunicat de MPay.',
                'Exportă fingerprint-ul certificatului prestator și confirmă cu MPay că este înregistrat.',
                'Compară hash-ul SHA256 al răspunsului (last_response.sha256) cu fișierul livrat către MPay.',
                'Folosește /mpay/debug (POST cu fișier) pentru a obține hash-uri de comparație direct din site.',
            ],
        ];
    }

    private static function compliance_matrix(array $site, array $opts, array $certificates, array $status, array $environment, array $runtime) : array {
        $items = [];
        $httpsOk = !empty($status['https_enforced']) && !empty($site['home_https']);
        $items[] = self::compliance_item(
            'https_only',
            'TLS obligatoriu pentru /mpay/*',
            'HTTPS',
            $httpsOk,
            $httpsOk ? 'Endpointurile mpay/ folosesc doar HTTPS.' : 'Dezactivează „Permite HTTP nesecurizat” și configurează certificat TLS valid.'
        );

        $wsReady = !empty($status['ws_security_ready']);
        $items[] = self::compliance_item(
            'ws_security',
            'WS-Security activ și certificate valide',
            'Cap. 9.1',
            $wsReady,
            $wsReady ? 'Certificatul MPay și cheia prestatorului sunt încărcate, enforcement ON.' : 'Încarcă certificatul public MPay + cheia prestatorului și activează „Forțează WS-Security”.'
        );

        $guardReady = !empty($status['soap_guard_enabled']);
        $items[] = self::compliance_item(
            'soap_guard',
            'SOAP guard / rate-limit',
            'Cap. 5.3',
            $guardReady,
            $guardReady ? 'Limiterul de trafic și verificarea tipului MIME sunt active.' : 'Activează „SOAP Guard” pentru a bloca ping-uri și cereri nevalide.'
        );

        $persistReady = !empty($status['soap_persist_ready']);
        $items[] = self::compliance_item(
            'soap_logging',
            'Jurnalizare SOAP persistă',
            'Cap. 9.3',
            $persistReady,
            $persistReady ? 'Fișierele SOAP sunt păstrate în uploads/mpay-vg/soap.' : 'Activează „Păstrează SOAP” și asigură drepturi de scriere pe uploads/mpay-vg/soap.'
        );

        $extensions = $environment['extensions'] ?? [];
        $invoiceApiReady = !empty($opts['attach_invoice_pdf']) && !empty($opts['service_id']) && !empty($extensions['curl']);
        $items[] = self::compliance_item(
            'invoice_api',
            'Consum REST InvoiceID/PDF',
            'Cap. 10.3-10.5',
            $invoiceApiReady,
            $invoiceApiReady ? 'cURL disponibil și atașarea PDF-ului MPay este activă.' : 'Activează „Atașează PDF” și asigură extensia cURL + ServiceID.'
        );

        $cron = $runtime['cron'] ?? [];
        $cronReady = !empty($cron['cert_monitor_next']);
        $items[] = self::compliance_item(
            'cron_monitor',
            'Monitorizare certificate prin WP-Cron',
            'Cap. 8.2',
            $cronReady,
            $cronReady ? 'Evenimentul mpay_vg_cert_check este programat.' : 'Activează cron-ul WordPress pentru a verifica expirarea certificatelor.'
        );

        return $items;
    }

    private static function compliance_item(string $id, string $title, string $reference, bool $ok, string $detail) : array {
        return [
            'id' => $id,
            'title' => $title,
            'reference' => $reference,
            'status' => $ok ? 'ok' : 'action',
            'detail' => $detail,
        ];
    }

    private static function soap_excerpt($path) {
        if (!$path || !file_exists($path)) {
            return null;
        }
        $raw = @file_get_contents($path, false, null, 0, 4000);
        if ($raw === false) {
            return null;
        }
        return trim($raw);
    }

    private static function path_is_writable($path) {
        if (!$path) {
            return false;
        }
        if (!file_exists($path)) {
            $parent = dirname($path);
            return is_dir($parent) && is_writable($parent);
        }
        return is_writable($path);
    }

    private static function current_request_is_https() : bool {
        if (function_exists('is_ssl') && is_ssl()) {
            return true;
        }
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if ($proto && stripos($proto, 'https') !== false) {
            return true;
        }
        return !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }

    private static function url_is_https($url) : bool {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        return is_string($scheme) && strtolower($scheme) === 'https';
    }
}
