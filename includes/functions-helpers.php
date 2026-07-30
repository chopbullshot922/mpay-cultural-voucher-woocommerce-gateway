<?php
if (!defined('ABSPATH')) { exit; }

function mpay_vg_profile_options() {
    return [
        'custom' => 'Custom (manual)',
        'store_test' => 'Test environment',
        'store_prod' => 'Production environment',
        'terabitlab_test' => 'TerabitLab test environment',
    ];
}

function mpay_vg_default_cert_dir() {
    // Test certs directory under plugin root: "Certificatele MPay de test/"
    return trailingslashit(MPAY_VG_PLUGIN_DIR).'Certificatele MPay de test/';
}

function mpay_vg_profile_defaults($profile) {
    $cert_dir = mpay_vg_default_cert_dir();
    $pfx_path = $cert_dir.'YOUR_TEST_CERTIFICATE.pfx';
    $mpay_cer = $cert_dir.'YOUR_MPAY_TEST_PUBLIC.cer';
    $prod_cert_dir = trailingslashit(MPAY_VG_PLUGIN_DIR).'certs-prod/';
    $prod_pfx = $prod_cert_dir.'YOUR_PROD_CERTIFICATE.pfx';
    $prod_sp_cer = $prod_cert_dir.'YOUR_PROD_SP_PUBLIC.cer';
    $prod_mpay_cer = $prod_cert_dir.'YOUR_MPAY_PROD_PUBLIC.cer';

    $base = [
        'mode_prod' => 0,
        'service_id' => '',
        'api_test_base' => 'https://testmpay.gov.md:8443/api',
        'api_prod_base' => 'https://mpay.gov.md:8443/api',
        'gateway_title' => 'MPay / Voucher Cultural',
        'gateway_desc' => __('Veți fi redirecționat către MPay pentru finalizarea plății.', 'mpay-voucher-gateway'),
        'reason_template' => 'Comandă #%d',
        'lines_strategy' => 'single',
        'return_url_override' => '',
    'debug_shared_key' => '',
        'mpay_public_cert_path' => '',
        'sp_private_key_path' => '',
        'sp_public_cert_path' => '',
        'sp_key_passphrase' => '',
        'enforce_wssec' => 0,
        'enable_soap_guard' => 1,
        'enable_cert_monitor' => 1,
        'enable_event_log_db' => 1,
        'enable_soap_persist' => 0,
        'debug_log' => 1,
        'attach_invoice_pdf' => 0,
        'allow_partial' => 0,
        'allow_advance' => 0,
        'allow_virtual' => 0,
        'allow_guest' => 0,
        'require_cultural_flag' => 1,
        'show_pos_shortcuts' => 1,
        'min_total' => '',
        'max_total' => '',
        'allowed_countries' => '',
        'allowed_shipping_methods' => '',
        'allow_insecure_http' => 0,
        'autofill_test_bank' => 0,
        'treasury_account' => '',
        'treasury_account_name' => '',
    ];

    switch ($profile) {
        case 'store_test':
            $base['service_id'] = '';
            $base['mpay_public_cert_path'] = file_exists($mpay_cer) ? $mpay_cer : '';
            $base['sp_private_key_path'] = file_exists($pfx_path) ? $pfx_path : '';
            $base['sp_public_cert_path'] = '';
            $base['sp_key_passphrase'] = '';
            $base['enforce_wssec'] = 1;
            $base['relax_checkout_test'] = 1;
            $base['autofill_test_bank'] = 1;
            break;
        case 'store_prod':
            $base['service_id'] = '';
            $base['mode_prod'] = 1;
            $base['enforce_wssec'] = 1;
            $base['mpay_public_cert_path'] = file_exists($prod_mpay_cer) ? $prod_mpay_cer : '';
            $base['sp_private_key_path'] = file_exists($prod_pfx) ? $prod_pfx : '';
            $base['sp_public_cert_path'] = file_exists($prod_sp_cer) ? $prod_sp_cer : '';
            $base['sp_key_passphrase'] = '';
            $base['beneficiary'] = '';
            $base['bank_code'] = '';
            $base['bank_fiscal_code'] = '';
            $base['bank_account'] = '';
            break;
        case 'terabitlab_test':
            $base['service_id'] = '';
            $base['mpay_public_cert_path'] = file_exists($mpay_cer) ? $mpay_cer : '';
            $base['sp_private_key_path'] = file_exists($pfx_path) ? $pfx_path : '';
            $base['sp_public_cert_path'] = '';
            $base['sp_key_passphrase'] = '';
            $base['reason_template'] = 'Comandă #%d';
            $base['enforce_wssec'] = 1;
            $base['relax_checkout_test'] = 1;
            $base['autofill_test_bank'] = 1;
            break;
        default:
            break;
    }

    return $base;
}

function mpay_vg_get_settings($merge_defaults = true) {
    $opts = get_option('mpay_vg_settings', []);
    if (!is_array($opts)) { $opts = []; }

    $profiles = mpay_vg_profile_options();
    $profile = isset($opts['config_profile']) ? sanitize_key($opts['config_profile']) : 'custom';
    if (!array_key_exists($profile, $profiles)) { $profile = 'custom'; }
    $defaults = [];
    if ($merge_defaults) {
        $defaults = mpay_vg_profile_defaults($profile);
        $opts = array_merge($defaults, $opts);
    }
    $opts['config_profile'] = $profile;

    if (!empty($opts['mode_prod']) && empty($opts['enforce_wssec'])) {
        $opts['enforce_wssec'] = 1;
    }

    if ($merge_defaults && empty($opts['mode_prod'])) {
        $cert_dir = mpay_vg_default_cert_dir();
        $test_pfx = $cert_dir.'YOUR_TEST_CERTIFICATE.pfx';
        $test_cer = $cert_dir.'YOUR_MPAY_TEST_PUBLIC.cer';
        if (empty($opts['sp_private_key_path']) && file_exists($test_pfx)) {
            $opts['sp_private_key_path'] = $test_pfx;
        }
        if (empty($opts['mpay_public_cert_path']) && file_exists($test_cer)) {
            $opts['mpay_public_cert_path'] = $test_cer;
        }
        if (empty($opts['sp_key_passphrase'])) {
            $opts['sp_key_passphrase'] = '';
        }
        if (empty($opts['service_id'])) {
            $opts['service_id'] = '';
        }
    } elseif ($merge_defaults && !empty($opts['mode_prod'])) {
        $prod_cert_dir = trailingslashit(MPAY_VG_PLUGIN_DIR).'certs-prod/';
        $prod_pfx = $prod_cert_dir.'YOUR_PROD_CERTIFICATE.pfx';
        $prod_sp_cer = $prod_cert_dir.'YOUR_PROD_SP_PUBLIC.cer';
        $prod_mpay_cer = $prod_cert_dir.'YOUR_MPAY_PROD_PUBLIC.cer';
        if (empty($opts['sp_private_key_path']) && file_exists($prod_pfx)) {
            $opts['sp_private_key_path'] = $prod_pfx;
        }
        if (empty($opts['sp_public_cert_path']) && file_exists($prod_sp_cer)) {
            $opts['sp_public_cert_path'] = $prod_sp_cer;
        }
        if (empty($opts['mpay_public_cert_path']) && file_exists($prod_mpay_cer)) {
            $opts['mpay_public_cert_path'] = $prod_mpay_cer;
        }
        if (empty($opts['sp_key_passphrase'])) {
            $opts['sp_key_passphrase'] = '';
        }
    }
    if (empty($opts['service_id']) && !empty($defaults['service_id'])) {
        $opts['service_id'] = $defaults['service_id'];
    }
    return $opts;
}

function mpay_vg_get_settings_raw() {
    $opts = get_option('mpay_vg_settings', []);
    return is_array($opts) ? $opts : [];
}

function mpay_vg_get_setting($key, $default = null) {
    $opts = mpay_vg_get_settings();
    return array_key_exists($key, $opts) ? $opts[$key] : $default;
}

function mpay_vg_upload_cert($field, $file) {
    $uploads = wp_upload_dir();
    $dir = trailingslashit($uploads['basedir']).'mpay-vg';
    if (!file_exists($dir)) { wp_mkdir_p($dir); }
    $ht = trailingslashit($dir).'.htaccess'; if (!file_exists($ht)) { file_put_contents($ht, "Require all denied\n"); }
    $idx_path = trailingslashit($dir).'index.html'; if (!file_exists($idx_path)) { file_put_contents($idx_path, ''); }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['cer','pem','crt','pfx','p12'])) return new \WP_Error('upload', 'Tip fișier invalid (.cer,.pem,.crt,.pfx,.p12)');
    if (!is_uploaded_file($file['tmp_name'])) return new \WP_Error('upload', 'Upload invalid.');
    $hash = wp_hash($file['name'].microtime(true).wp_rand());
    $filename = $field.'-'.$hash.'.'.$ext;
    $dest = trailingslashit($dir).$filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return new \WP_Error('move', 'Nu pot salva fișierul');
    return ['path'=>$dest, 'url'=>trailingslashit($uploads['baseurl']).'mpay-vg/'.$filename];
}

function mpay_vg_store_currency_is_mdl() {
    if (!function_exists('get_woocommerce_currency')) return true;
    return strtoupper(get_woocommerce_currency()) === 'MDL';
}

function mpay_vg_normalize_certificate($contents) {
    if (!$contents) return '';
    if (strpos($contents, '-----BEGIN') !== false) return $contents;
    $body = chunk_split(base64_encode($contents), 64, "\n");
    return "-----BEGIN CERTIFICATE-----\n".$body."-----END CERTIFICATE-----\n";
}

function mpay_vg_certificate_fingerprint($certificate, $algo = 'sha1') {
    $certificate = (string) $certificate;
    if ($certificate === '') {
        return null;
    }
    $algo = strtolower((string) $algo);
    if (!in_array($algo, hash_algos(), true)) {
        $algo = 'sha1';
    }
    if (function_exists('openssl_x509_fingerprint')) {
        $fp = @openssl_x509_fingerprint($certificate, strtoupper($algo));
        if (is_string($fp) && $fp !== '') {
            return strtoupper($fp);
        }
    }
    $normalized = mpay_vg_normalize_certificate($certificate);
    $body = preg_replace('/\-*BEGIN CERTIFICATE\-*/i', '', $normalized);
    $body = preg_replace('/\-*END CERTIFICATE\-*/i', '', $body);
    $body = preg_replace('/\s+/', '', $body);
    if ($body === '') {
        return null;
    }
    $binary = base64_decode($body, true);
    if ($binary === false) {
        return null;
    }
    $hash = hash($algo, $binary, false);
    if (!is_string($hash) || $hash === '') {
        return null;
    }
    return strtoupper(implode(':', str_split($hash, 2)));
}

// Runtime diagnostics helpers (in-memory via transients)
function mpay_vg_set_runtime($key, $data, $ttl = 1800) {
    if (!function_exists('set_transient')) return false;
    return set_transient('mpay_vg_rt_'.$key, $data, $ttl);
}
function mpay_vg_get_runtime($key, $default = null) {
    if (!function_exists('get_transient')) return $default;
    $v = get_transient('mpay_vg_rt_'.$key);
    return $v === false ? $default : $v;
}

function mpay_vg_trim_debug_value($value, $max = 400) {
    $value = (string) $value;
    if (strlen($value) <= $max) {
        return $value;
    }
    return substr($value, 0, $max - 3).'...';
}

function mpay_vg_normalize_debug_context($context) {
    if ($context instanceof \Throwable) {
        return [
            'exception' => get_class($context).': '.$context->getMessage(),
            'file' => $context->getFile().':'.$context->getLine(),
        ];
    }
    if (!is_array($context)) {
        if ($context === null) {
            return [];
        }
        return ['value' => mpay_vg_trim_debug_value($context)];
    }
    $normalized = [];
    foreach ($context as $key => $value) {
        if ($value instanceof \Throwable) {
            $normalized[$key] = get_class($value).': '.$value->getMessage();
            $normalized[$key.'_file'] = $value->getFile().':'.$value->getLine();
            continue;
        }
        if (is_bool($value)) {
            $normalized[$key] = $value ? 'true' : 'false';
            continue;
        }
        if ($value === null) {
            $normalized[$key] = 'null';
            continue;
        }
        if (is_scalar($value)) {
            $normalized[$key] = mpay_vg_trim_debug_value((string) $value);
            continue;
        }
        $normalized[$key] = mpay_vg_trim_debug_value(wp_json_encode($value, JSON_UNESCAPED_UNICODE));
    }
    return $normalized;
}

function mpay_vg_record_debug_event(array $entry) {
    $defaults = [
        'time' => current_time('timestamp'),
        'level' => 'info',
        'message' => '',
        'component' => 'general',
        'code' => '',
        'hint' => '',
        'context' => [],
    ];
    $entry = array_merge($defaults, $entry);
    if ($entry['message'] === '') {
        return;
    }
    if (!is_array($entry['context'])) {
        $entry['context'] = mpay_vg_normalize_debug_context($entry['context']);
    } else {
        $entry['context'] = mpay_vg_normalize_debug_context($entry['context']);
    }
    $events = get_option('mpay_vg_debug_events', []);
    if (!is_array($events)) {
        $events = [];
    }
    $events[] = [
        'time' => intval($entry['time']),
        'level' => $entry['level'],
        'component' => $entry['component'],
        'code' => $entry['code'],
        'message' => $entry['message'],
        'hint' => $entry['hint'],
        'context' => $entry['context'],
    ];
    $max = apply_filters('mpay_vg_debug_events_limit', 200);
    if ($max > 0 && count($events) > $max) {
        $events = array_slice($events, -1 * $max);
    }
    update_option('mpay_vg_debug_events', array_values($events), false);
}

function mpay_vg_get_debug_events($limit = 50, $filters = []) {
    $events = get_option('mpay_vg_debug_events', []);
    if (!is_array($events)) {
        return [];
    }
    $events = array_reverse($events);
    if (!empty($filters['component'])) {
        $comp = (array) $filters['component'];
        $events = array_filter($events, function($e) use ($comp) {
            return in_array($e['component'] ?? 'general', $comp, true);
        });
    }
    if (!empty($filters['level'])) {
        $levels = (array) $filters['level'];
        $events = array_filter($events, function($e) use ($levels) {
            return in_array($e['level'] ?? 'info', $levels, true);
        });
    }
    $events = array_values($events);
    if ($limit > 0) {
        $events = array_slice($events, 0, $limit);
    }
    return $events;
}

function mpay_vg_clear_debug_events() {
    delete_option('mpay_vg_debug_events');
}

function mpay_vg_debug_event_hint($event) {
    if (!empty($event['hint'])) {
        return $event['hint'];
    }
    $ctx = $event['context'] ?? [];
    foreach (['message','error','details','detail','reason'] as $key) {
        if (!empty($ctx[$key])) {
            return $ctx[$key];
        }
    }
    return '';
}

function mpay_vg_is_function_available($function) {
    if (!function_exists($function)) return false;
    $disabled = ini_get('disable_functions');
    if ($disabled) {
        $list = array_map('trim', explode(',', $disabled));
        if (in_array($function, $list, true)) return false;
    }
    if (function_exists('ini_get')) {
        $suhosin = ini_get('suhosin.executor.func.blacklist');
        if ($suhosin) {
            $list = array_map('trim', explode(',', $suhosin));
            if (in_array($function, $list, true)) return false;
        }
    }
    return true;
}

function mpay_vg_locate_openssl() {
    static $cached = null;
    if ($cached !== null) return $cached ?: null;
    $candidates = [];
    if (defined('MPAY_VG_OPENSSL_BIN')) {
        $candidates[] = MPAY_VG_OPENSSL_BIN;
    }
    $candidates = array_merge($candidates, [
        '/usr/bin/openssl',
        '/usr/local/bin/openssl',
        '/opt/homebrew/bin/openssl',
        '/opt/local/bin/openssl',
    ]);
    if (mpay_vg_is_function_available('exec')) {
        $out = [];
        $code = 0;
        @exec('command -v openssl 2>/dev/null', $out, $code);
        if ($code === 0 && !empty($out[0])) {
            $candidates[] = trim($out[0]);
        }
    }
    foreach ($candidates as $path) {
        if ($path && @is_executable($path)) {
            $cached = $path;
            return $path;
        }
    }
    $cached = '';
    return null;
}

function mpay_vg_read_pkcs12_via_cli($path, $passphrase = '') {
    $bin = mpay_vg_locate_openssl();
    if (!$bin) {
        return new \WP_Error('openssl_cli_missing', __('Nu pot găsi binarul openssl pentru a procesa PKCS#12.', 'mpay-voucher-gateway'));
    }
    $can_run = mpay_vg_is_function_available('proc_open') || mpay_vg_is_function_available('exec');
    if (!$can_run) {
        return new \WP_Error('openssl_cli_blocked', __('Funcțiile PHP necesare pentru a rula openssl sunt dezactivate.', 'mpay-voucher-gateway'));
    }

    $tmpDir = sys_get_temp_dir();
    $tmpPfx = tempnam($tmpDir, 'mpay_pfx_');
    $tmpPass = tempnam($tmpDir, 'mpay_pass_');
    if (!$tmpPfx || !$tmpPass) {
        if ($tmpPfx) @unlink($tmpPfx);
        if ($tmpPass) @unlink($tmpPass);
        return new \WP_Error('tmp_fail', __('Nu pot crea fișiere temporare pentru verificarea PKCS#12.', 'mpay-voucher-gateway'));
    }
    $cleanup = function() use ($tmpPfx, $tmpPass) {
        if ($tmpPfx && file_exists($tmpPfx)) @unlink($tmpPfx);
        if ($tmpPass && file_exists($tmpPass)) @unlink($tmpPass);
    };

    $copied = @copy($path, $tmpPfx);
    $passBuffer = (string)$passphrase;
    if (substr($passBuffer, -1) !== "\n") {
        $passBuffer .= "\n";
    }
    $passSaved = file_put_contents($tmpPass, $passBuffer);
    if ($passSaved !== false) {
        @chmod($tmpPass, 0600);
    }
    if (!$copied || $passSaved === false) {
        $cleanup();
        return new \WP_Error('tmp_copy_fail', __('Nu pot pregăti fișierele temporare pentru PKCS#12.', 'mpay-voucher-gateway'));
    }

    $runCommand = function($useLegacy, $passphrase) use ($bin, $tmpPfx, $tmpPass) {
        $parts = [
            escapeshellarg($bin),
            'pkcs12',
            '-nodes',
            '-passin', escapeshellarg('file:'.$tmpPass),
            '-in', escapeshellarg($tmpPfx),
        ];
        if ($useLegacy) {
            array_splice($parts, 2, 0, '-legacy');
        }
        $cmd = implode(' ', $parts);

        $stdout = '';
        $stderr = '';
        $code = 1;

        if (mpay_vg_is_function_available('proc_open')) {
            $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = @proc_open($cmd, $descriptor, $pipes, null, null);
            if (is_resource($proc)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
                $code = proc_close($proc);
            }
        } elseif (mpay_vg_is_function_available('exec')) {
            $out = [];
            @exec($cmd.' 2>&1', $out, $code);
            $stdout = implode("\n", $out);
            $stderr = '';
        }

        if ($code !== 0 && $passphrase !== '' && stripos($stderr.$stdout, 'reading password from bio') !== false) {
            $passIndex = array_search('-passin', $parts, true);
            if ($passIndex !== false && isset($parts[$passIndex + 1])) {
                $parts[$passIndex + 1] = escapeshellarg('pass:'.$passphrase);
            }
            $cmd = implode(' ', $parts);
            if (mpay_vg_is_function_available('proc_open')) {
                $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                $proc = @proc_open($cmd, $descriptor, $pipes, null, null);
                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
                    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
                    $code = proc_close($proc);
                }
            } elseif (mpay_vg_is_function_available('exec')) {
                $out = [];
                @exec($cmd.' 2>&1', $out, $code);
                $stdout = implode("\n", $out);
                $stderr = '';
            }
        }

        return [$code, $stdout, $stderr];
    };

    [$code, $stdout, $stderr] = $runCommand(true, (string)$passphrase);
    if ($code !== 0 && stripos($stderr.$stdout, 'unknown option -legacy') !== false) {
        [$code, $stdout, $stderr] = $runCommand(false, (string)$passphrase);
    }

    $cleanup();

    if ($code !== 0) {
        $msg = trim($stderr ?: $stdout);
        if ($msg === '') {
            $msg = __('Comanda openssl a eșuat fără un mesaj specific.', 'mpay-voucher-gateway');
        }
    return new \WP_Error('openssl_cli_error', $msg);
    }

    $priv = null;
    if (preg_match('/-----BEGIN (?:ENCRYPTED )?PRIVATE KEY-----.*?-----END (?:ENCRYPTED )?PRIVATE KEY-----/s', $stdout, $m)) {
        $priv = $m[0];
    }
    preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $stdout, $certMatches);
    $certs = $certMatches[0] ?? [];
    $primaryCert = $certs ? $certs[0] : null;
    $extra = $certs ? array_slice($certs, 1) : [];

    if (!$priv) {
        return new \WP_Error('openssl_cli_parse', __('OpenSSL nu a returnat cheia privată.', 'mpay-voucher-gateway'));
    }

    return [
        'pkey' => $priv,
        'cert' => $primaryCert,
        'extracerts' => $extra,
        'source' => 'cli',
    ];
}

function mpay_vg_read_pkcs12($path, $passphrase = '') {
    if (!$path || !file_exists($path)) {
        return new \WP_Error('pkcs12_missing', __('Fișierul PKCS#12 nu există.', 'mpay-voucher-gateway'));
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return new \WP_Error('pkcs12_unreadable', __('Nu pot citi fișierul PKCS#12.', 'mpay-voucher-gateway'));
    }
    $pass = (string)$passphrase;
    $certs = [];
    if (function_exists('openssl_pkcs12_read')) {
        $ok = @openssl_pkcs12_read($raw, $certs, $pass);
        if ($ok) {
            return [
                'pkey' => $certs['pkey'] ?? null,
                'cert' => $certs['cert'] ?? null,
                'extracerts' => $certs['extracerts'] ?? [],
                'source' => 'php',
            ];
        }
    }
    return mpay_vg_read_pkcs12_via_cli($path, $pass);
}
