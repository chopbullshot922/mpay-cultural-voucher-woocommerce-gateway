<?php
namespace MPAY_VG\Core;
if (!defined('ABSPATH')) { exit; }

class DebugConsole {
    public static function render(array $payload, string $sharedKey) : void {
        $snapshot = $payload['snapshot'] ?? [];
        $site = $snapshot['site'] ?? [];
        $settings = $snapshot['settings'] ?? [];
        $wssec = $snapshot['ws_security'] ?? [];
        $runtime = $snapshot['runtime'] ?? [];
        $status = $snapshot['status'] ?? [];
        $soap = $snapshot['soap'] ?? [];
        $orderQuery = (string) ($payload['order_query'] ?? '');
        $orderInspection = $snapshot['order_inspection'] ?? null;
        $comparison = $payload['comparison'] ?? null;
        $comparisonResult = $payload['comparison_result'] ?? null;
        $generatedAt = $payload['generated_at'] ?? gmdate('c');
        $requestMeta = $payload['request'] ?? [];

        $soapFiles = $soap['persisted_samples'] ?? [];
        $dbEvents = $runtime['db_events'] ?? [];
        $debugEvents = $runtime['debug_events'] ?? [];
        $lastSignature = $wssec['last_signature'] ?? [];
        $lastVerify = $wssec['last_verification'] ?? [];
        $lastResponse = $wssec['last_response'] ?? [];
        $lastSoap = $runtime['last_soap'] ?? [];
        $checklist = $wssec['checklist'] ?? [];
        $soapEndpoint = (string) ($site['soap_endpoint'] ?? '');
        $debugUrl = self::build_url($site['home_url'] ?? '', 'mpay/debug', ['key' => $sharedKey]);
        $jsonUrl = self::with_query($debugUrl, ['format' => 'json']);
        $curlSnippet = self::curl_snippet($soapEndpoint);
        $orderUrl = $debugUrl;
        $endpointTargets = $payload['endpoint_targets'] ?? [];
        $endpointProbe = $payload['endpoint_probe'] ?? null;

        self::send_headers();
        echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>MPay Debug Console</title>';
        echo '<style>'.self::styles().'</style>';
        echo '</head><body>';
        echo '<header class="dbg-header">';
        echo '<div><h1>MPay Debug Console</h1><p>Eu folosesc această interfață pentru a corela semnătura WS-Security cu logurile și pentru a furniza rapid evidențe verificabile către MPay.</p></div>';
        echo '<div class="badge-group">';
        $mode = !empty($settings['mode']) ? $settings['mode'] : (!empty($settings['profile']) ? strtoupper($settings['profile']) : 'TEST');
        echo '<span class="badge '.($mode === 'PROD' ? 'warn' : 'ok').'">'.$mode.' MODE</span>';
        if (!empty($settings['service_id'])) {
            echo '<span class="badge">ServiceID '.self::esc($settings['service_id']).'</span>';
        }
        echo '</div></header>';

        echo '<div class="toolbar">';
        echo '<a class="btn" href="'.self::esc_attr($debugUrl).'">Actualizează</a>';
        echo '<a class="btn ghost" target="_blank" rel="noopener" href="'.self::esc_attr($jsonUrl).'">Descarcă JSON</a>';
        echo '<button class="btn ghost" type="button" data-copy="'.self::esc_attr($jsonUrl).'" data-copy-label="Copie URL JSON">Copie URL JSON</button>';
        echo '</div>';

        echo '<main class="dbg-content">';
        echo '<section class="card grid-3">';
        echo self::metric('Generat la', $generatedAt);
        echo self::metric('IP solicitant', $requestMeta['ip'] ?? '-');
        echo self::metric('HTTPS', !empty($requestMeta['https']) ? 'DA' : 'NU');
        echo self::metric('IP server', $snapshot['environment']['server_addr'] ?? ($_SERVER['SERVER_ADDR'] ?? '-'));
        echo self::metric('Plugin', $snapshot['meta']['plugin_version'] ?? '-');
        echo self::metric('WP', $snapshot['environment']['wp'] ?? 'n/a');
        echo self::metric('WooCommerce', $snapshot['environment']['woocommerce'] ?? 'n/a');
        echo '</section>';

        echo '<section class="card" id="wssec"><div class="section-head"><h2>WS-Security & certificate</h2><p>Verificări WS-Security și certificate.</p></div>';
        echo '<div class="grid-3">';
        echo self::wssec_card('Certificat prestator (SHA-256)', $wssec['service_certificate']['fingerprint_sha256'] ?? '-', [
            'CN' => $wssec['service_certificate']['subject_cn'] ?? '-',
            'Valabil' => $wssec['service_certificate']['valid_to'] ?? '-',
            'Serial' => $wssec['service_certificate']['serial'] ?? '-'
        ]);
        echo self::wssec_card('Certificat MPay (SHA-256)', $wssec['mpay_certificate']['fingerprint_sha256'] ?? '-', [
            'CN' => $wssec['mpay_certificate']['subject_cn'] ?? '-',
            'Valabil' => $wssec['mpay_certificate']['valid_to'] ?? '-',
            'Serial' => $wssec['mpay_certificate']['serial'] ?? '-'
        ]);
        echo self::wssec_card('Cheie privată', $wssec['private_key_path'] ? basename($wssec['private_key_path']) : '-', [
            'Enforced' => !empty($wssec['enforced']) ? 'DA' : 'NU',
            'Persist SOAP' => !empty($snapshot['settings']['soap_persist']) ? 'DA' : 'NU',
            'SOAP guard' => !empty($snapshot['settings']['soap_guard']) ? 'DA' : 'NU'
        ]);
        echo '</div>';
        echo '<div class="grid-2 stack">';
        echo self::telemetry_table('Ultima semnătură generată', $lastSignature, ['timestamp','body_id','signature_sha256','signature_length','signature_algorithm','envelope_bytes']);
        echo self::telemetry_table('Ultima verificare primită', $lastVerify, ['timestamp','result','code','message','digest','fingerprint','reference_count']);
        echo '</div>';
        echo '</section>';

        echo '<section class="card" id="response">';
        echo '<div class="section-head"><h2>Răspuns semnat & hash-uri</h2><p>Hash-ul trebuie să se potrivească cu fișierul transmis către MPay.</p></div>';
        echo '<ul class="kv">';
        echo self::kv('Ultimul răspuns', !empty($lastResponse['timestamp']) ? gmdate('c', (int) $lastResponse['timestamp']) : '-');
        echo self::kv('Dimensiune', isset($lastResponse['bytes']) ? $lastResponse['bytes'].' B' : '-');
        echo self::kv('SHA256', $lastResponse['sha256'] ?? '-');
        echo self::kv('SHA1', $lastResponse['sha1'] ?? '-');
        if (!empty($lastResponse['persist_path'])) {
            $download = self::soap_download_url($snapshot, $lastResponse['persist_path']);
            if ($download) {
                echo self::kv('Fișier', '<a href="'.self::esc_attr($download).'" target="_blank" rel="noopener">'.self::esc(basename($lastResponse['persist_path'])).'</a>');
            } else {
                echo self::kv('Fișier', self::esc($lastResponse['persist_path']));
            }
        }
        echo '</ul>';
        if ($lastSoap) {
            echo '<details><summary>Ultimul eveniment SOAP</summary><ul class="kv">';
            foreach ($lastSoap as $k => $v) {
                if (is_array($v)) { $v = self::to_json($v); }
                echo self::kv($k, (string) $v);
            }
            echo '</ul></details>';
        }
        echo '</section>';

        echo '<section class="card" id="compare">';
        echo '<div class="section-head"><h2>Comparator fișier MPay</h2><p>Încarcă răspunsul primit de la MPay și comparăm hash-urile pe server.</p></div>';
        echo '<form class="upload" method="post" enctype="multipart/form-data" action="'.self::esc_attr($debugUrl).'">';
        echo '<input type="hidden" name="key" value="'.self::esc_attr($sharedKey).'">';
        echo '<label class="file">Fișier XML <input type="file" name="compare" accept=".xml,.txt,application/xml" required></label>';
        echo '<button type="submit" class="btn">Calculează</button>';
        echo '</form>';
        if ($comparison) {
            echo '<div class="comparison-result">';
            if ($comparisonResult) {
                $state = $comparisonResult['status'] ?? 'unknown';
                $label = $state === 'match' ? 'Hash-urile coincid (OK)' : ($state === 'mismatch' ? 'Hash-urile nu coincid' : 'Rezultat parțial');
                echo '<p class="'.self::esc_attr('state-'.$state).'">'.self::esc($label).' – '.$comparisonResult['detail'].'</p>';
            }
            echo '<ul class="kv">';
            foreach (['label' => 'Fișier','bytes' => 'Dimensiune','sha256' => 'SHA256','sha1' => 'SHA1','md5' => 'MD5','preview_ascii' => 'Preview ASCII'] as $k => $label) {
                if (empty($comparison[$k])) { continue; }
                $value = $comparison[$k];
                if ($k === 'bytes') { $value .= ' B'; }
                echo self::kv($label, $value);
            }
            echo '</ul>';
            if (!empty($comparisonResult['differences'])) {
                echo self::comparison_diff_table($comparisonResult['differences']);
            }
            echo '</div>';
        }
        echo '<details><summary>Checklist WS-Security</summary><ul>';
        foreach ($checklist as $item) {
            echo '<li>'.self::esc($item).'</li>';
        }
        echo '</ul></details>';
        echo '</section>';

        echo '<section class="card" id="endpoint-probe">';
        echo '<div class="section-head"><h2>Test endpoint-uri MPay</h2><p>Rulează verificări HTTP direct din server și oferă comenzi cURL pentru debug rapid.</p></div>';
        echo '<form class="probe-form" method="post" action="'.self::esc_attr($debugUrl).'">';
        echo '<input type="hidden" name="key" value="'.self::esc_attr($sharedKey).'">';
        echo '<input type="hidden" name="debug_action" value="probe_endpoints">';
        $probeButtonLabel = $endpointProbe ? 'Rulează din nou testele' : 'Testează endpoint-urile';
        echo '<button type="submit" class="btn">'.self::esc($probeButtonLabel).'</button>';
        echo '</form>';
        if ($endpointTargets) {
            echo '<div class="table"><table><thead><tr><th>Endpoint</th><th>Metodă</th><th>URL</th><th>cURL</th></tr></thead><tbody>';
            foreach ($endpointTargets as $target) {
                $label = self::esc($target['label'] ?? '');
                $method = self::esc($target['method'] ?? 'GET');
                $url = $target['url'] ?? '';
                $urlCell = $url !== '' ? '<a href="'.self::esc_attr($url).'" target="_blank" rel="noopener">'.self::esc($url).'</a>' : '-';
                $note = $target['note'] ?? '';
                if ($note !== '') {
                    $label .= '<br><small>'.self::esc($note).'</small>';
                }
                $curl = $target['curl'] ?? '';
                $copyBtn = $curl !== ''
                    ? '<button class="btn ghost" type="button" data-copy="'.self::esc_attr($curl).'" data-copy-label="Copie cURL">Copie cURL</button>'
                    : '';
                echo '<tr><td>'.$label.'</td><td>'.$method.'</td><td>'.$urlCell.'</td><td>'.$copyBtn.'</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>Nu am putut determina URL-urile endpoint-urilor pentru test.</em></p>';
        }
        if (is_array($endpointProbe) && !empty($endpointProbe['results'])) {
            echo '<div class="probe-results">';
            echo '<p><strong>Ultima rulare:</strong> '.self::esc($endpointProbe['ran_at'] ?? '').'</p>';
            echo '<div class="table"><table><thead><tr><th>Endpoint</th><th>Status</th><th>HTTP</th><th>Durată (ms)</th><th>Detalii</th></tr></thead><tbody>';
            foreach ($endpointProbe['results'] as $result) {
                $statusOk = !empty($result['ok']);
                $statusClass = $statusOk ? 'status-ok' : 'status-fail';
                $statusLabel = $statusOk ? 'OK' : 'Necesită atenție';
                $code = $result['status_code'] ?? null;
                $message = $result['status_message'] ?? '';
                $http = $code ? trim($code.' '.$message) : ($statusOk ? '-' : '-');
                $duration = isset($result['duration_ms']) ? number_format((float) $result['duration_ms'], 1) : '-';
                $detail = $result['error'] ?? ($result['body_snippet'] ?? '');
                if ($detail === '') {
                    $detail = $result['note'] ?? '-';
                }
                echo '<tr>'
                    .'<td>'.self::esc($result['label'] ?? '').'</td>'
                    .'<td class="'.$statusClass.'">'.self::esc($statusLabel).'</td>'
                    .'<td>'.self::esc($http).'</td>'
                    .'<td>'.self::esc($duration).'</td>'
                    .'<td>'.self::esc($detail).'</td>'
                    .'</tr>';
            }
            echo '</tbody></table></div>';
            echo '</div>';
        }
        echo '</section>';

        echo '<section class="card" id="soap-files"><div class="section-head"><h2>Fișiere SOAP persistate</h2></div>';
        if ($soapFiles) {
            echo '<div class="table"><table><thead><tr><th>Fișier</th><th>Dimensiune</th><th>Ultima modificare</th></tr></thead><tbody>';
            foreach ($soapFiles as $file) {
                echo '<tr><td>'.self::esc($file['name']).'</td><td>'.self::esc(isset($file['size']) ? $file['size'].' B' : '-').'</td><td>'.self::esc($file['modified'] ?? '-').'</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>Persistența SOAP nu este activă sau directorul este gol.</em></p>';
        }
        echo '</section>';

        echo '<section class="card" id="order">';
        echo '<div class="section-head"><h2>Inspector OrderKey</h2></div>';
        echo '<form class="order-form" method="get" action="'.self::esc_attr($orderUrl).'">';
        echo '<input type="hidden" name="key" value="'.self::esc_attr($sharedKey).'">';
        echo '<input type="text" name="order" value="'.self::esc_attr($orderQuery).'" placeholder="OrderKey sau ID">';
        echo '<button type="submit" class="btn">Inspectează</button>';
        echo '</form>';
        if ($orderInspection) {
            echo '<ul class="kv">';
            foreach ($orderInspection as $k => $v) {
                if (is_array($v)) { $v = self::to_json($v); }
                echo self::kv($k, (string) $v);
            }
            echo '</ul>';
        }
        echo '</section>';

        echo '<section class="card" id="logs"><div class="section-head"><h2>Evenimente recente</h2></div>';
        echo '<div class="grid-2 stack">';
        echo '<div><h3>DB log (ultimele '.count($dbEvents).')</h3>';
        if ($dbEvents) {
            echo '<div class="table"><table><thead><tr><th>Timp</th><th>Op</th><th>Rezultat</th><th>Order</th><th>IP</th></tr></thead><tbody>';
            foreach ($dbEvents as $event) {
                echo '<tr><td>'.self::esc($event['ts'] ?? '').'</td><td>'.self::esc($event['op'] ?? '').'</td><td>'.self::esc($event['result'] ?? '').'</td><td>'.self::esc($event['order_id'] ?? '').'</td><td>'.self::esc($event['ip'] ?? '').'</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>Fără înregistrări (activează „Log interacțiuni DB”).</em></p>';
        }
        echo '</div>';
        echo '<div><h3>Evenimente debug</h3>';
        if ($debugEvents) {
            echo '<div class="table"><table><thead><tr><th>Timp</th><th>Nivel</th><th>Componentă</th><th>Mesaj</th></tr></thead><tbody>';
            foreach ($debugEvents as $event) {
                $time = !empty($event['time']) ? gmdate('Y-m-d H:i:s', (int) $event['time']) : '';
                echo '<tr><td>'.self::esc($time).'</td><td>'.self::esc($event['level'] ?? '').'</td><td>'.self::esc($event['component'] ?? '').'</td><td>'.self::esc($event['message'] ?? '').'</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>Nicio intrare recentă.</em></p>';
        }
        echo '</div></div>';
        echo '</section>';

        echo '<section class="card" id="curl">';
        echo '<div class="section-head"><h2>cURL rapid pentru reproducere</h2><p>Îl poți rula direct (înlocuiește fișierul cu ultimul răspuns). Reține encoding UTF-8 fără BOM.</p></div>';
        echo '<pre class="code">'.self::esc($curlSnippet)."\n--compressed".'</pre>';
        echo '</section>';

        echo '<section class="card" id="status">';
        echo '<div class="section-head"><h2>Stare generală</h2><p>Asigură-te că endpoint-urile sunt accesibile extern (ex.: https://example.com/mpay/soap, https://example.com/mpay/redirect, https://example.com/mpay/debug?key=...).</p></div>';
        echo '<div class="chips">';
        echo self::status_chip('HTTPS enforced', !empty($status['https_enforced']));
        echo self::status_chip('WS-Security ready', !empty($status['ws_security_ready']));
        echo self::status_chip('SOAP guard', !empty($status['soap_guard_enabled']));
        echo self::status_chip('SOAP persist ready', !empty($status['soap_persist_ready']));
        echo self::status_chip('Extensie SOAP PHP', !empty($status['soap_extension']));
        echo '</div>';
        echo '</section>';

        echo '</main>';
        echo '<script>'.self::script().'</script>';
        echo '</body></html>';
        exit;
    }

    private static function send_headers() : void {
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        if (function_exists('status_header')) {
            status_header(200);
        }
        header('Content-Type: text/html; charset=utf-8');
    }

    private static function metric(string $label, $value) : string {
        return '<div class="metric"><span>'.$label.'</span><strong>'.self::esc($value ?? '-').'</strong></div>';
    }

    private static function kv(string $label, $value) : string {
        $valueStr = is_string($value) ? $value : (string) $value;
        if (strpos($valueStr, '<a ') !== false || strpos($valueStr, '<') !== false) {
            return '<li><span>'.self::esc($label).'</span><strong>'.$valueStr.'</strong></li>';
        }
        return '<li><span>'.self::esc($label).'</span><strong>'.self::esc($valueStr).'</strong></li>';
    }

    private static function wssec_card(string $title, $value, array $rows) : string {
        $html = '<div class="wssec-card"><h3>'.self::esc($title).'</h3><p>'.self::esc($value).'</p><ul class="kv">';
        foreach ($rows as $k => $v) {
            $html .= self::kv($k, $v);
        }
        $html .= '</ul></div>';
        return $html;
    }

    private static function telemetry_table(string $title, array $data, array $fields) : string {
        $html = '<div><h3>'.self::esc($title).'</h3>';
        if (!$data) {
            $html .= '<p><em>Nu există date încă.</em></p></div>';
            return $html;
        }
        $html .= '<ul class="kv">';
        foreach ($fields as $field) {
            if (!isset($data[$field])) { continue; }
            $value = $data[$field];
            if ($field === 'timestamp') {
                $value = gmdate('c', (int) $value);
            }
            if ($field === 'references' && is_array($value)) {
                $value = count($value).' referințe';
            }
            if ($field === 'reference_count' && !isset($data[$field]) && isset($data['references'])) {
                $value = count((array) $data['references']).' referințe';
            }
            $html .= self::kv($field, $value);
        }
        $html .= '</ul></div>';
        return $html;
    }

    private static function status_chip(string $label, bool $ok) : string {
        $class = $ok ? 'ok' : 'warn';
        $text = $ok ? 'OK' : 'NEEDS FIX';
        return '<span class="chip '.$class.'"><strong>'.self::esc($label).'</strong><em>'.$text.'</em></span>';
    }

    private static function soap_download_url(array $snapshot, string $path) : ?string {
        $uploads = $snapshot['site']['uploads'] ?? [];
        $soapDir = $uploads['soap_dir'] ?? '';
        $soapUrl = $uploads['soap_url'] ?? '';
        if (!$soapDir || !$soapUrl) {
            return null;
        }
        if (strpos($path, $soapDir) !== 0) {
            return null;
        }
        $basename = basename($path);
        return self::trailingslashit($soapUrl).$basename;
    }

    private static function curl_snippet(string $endpoint) : string {
        $endpoint = rtrim($endpoint ?: '/mpay/soap', '/');
        return "curl -vk '$endpoint' \\\n  -H 'Content-Type: text/xml' \\\n  --data-binary @wp-content/uploads/mpay-vg/soap/ultimul-raspuns.xml";
    }

    private static function esc($value) : string {
        if (is_bool($value)) {
            $value = $value ? 'DA' : 'NU';
        }
        if ($value === null || $value === '') {
            $value = '-';
        }
        if (function_exists('esc_html')) {
            return esc_html((string) $value);
        }
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function esc_attr($value) : string {
        if (function_exists('esc_attr')) {
            return esc_attr((string) $value);
        }
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function styles() : string {
        return 'body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;background:#f5f5f7;margin:0;color:#111}header.dbg-header{display:flex;justify-content:space-between;align-items:center;padding:1.5rem 2rem;background:#0d1117;color:#fff}header h1{margin:0;font-size:1.6rem}.badge-group{display:flex;gap:.5rem;flex-wrap:wrap}.badge{padding:.35rem .75rem;border-radius:999px;font-size:.85rem;background:#1f6feb}.badge.warn{background:#d93025}.badge.ok{background:#2ea043}.toolbar{display:flex;gap:.5rem;padding:1rem 2rem;background:#fff;border-bottom:1px solid #e5e7eb}.btn{background:#2563eb;color:#fff;padding:.5rem 1rem;border-radius:.5rem;text-decoration:none;font-weight:600;border:none;cursor:pointer}.btn.ghost{background:transparent;border:1px solid #2563eb;color:#2563eb}.dbg-content{padding:2rem;display:flex;flex-direction:column;gap:1.5rem}.card{background:#fff;border-radius:1rem;padding:1.5rem;box-shadow:0 10px 30px rgba(15,23,42,.08)}.grid-3{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}.grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem}.stack{align-items:start}.metric{background:#f8fafc;padding:1rem;border-radius:.75rem}.metric span{display:block;font-size:.85rem;color:#64748b}.metric strong{font-size:1.15rem}.section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem}.section-head h2{margin:0;font-size:1.25rem}.kv{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:.4rem}.kv li{display:flex;justify-content:space-between;gap:1rem;font-size:.95rem}.kv span{color:#64748b}.wssec-card{background:#0f172a;color:#e2e8f0;border-radius:1rem;padding:1rem}.wssec-card ul span{color:#94a3b8}.telemetry-table{border:1px solid #e5e7eb;border-radius:.75rem;padding:1rem}.table{overflow:auto}.table table{width:100%;border-collapse:collapse;font-size:.9rem}.table th,.table td{padding:.35rem .5rem;border-bottom:1px solid #e5e7eb;text-align:left}.comparison-result{margin-top:1rem}.comparison-result p{font-weight:600}.comparison-result table{margin-top:1rem;border-collapse:collapse;width:100%;font-size:.9rem}.comparison-result th,.comparison-result td{padding:.4rem .5rem;border:1px solid #e5e7eb;text-align:left}.comparison-result tr.match{background:#ecfdf5}.comparison-result tr.mismatch{background:#fee2e2}.state-match{color:#15803d}.state-mismatch{color:#b91c1c}.chips{display:flex;flex-wrap:wrap;gap:.5rem}.chip{padding:.5rem .75rem;border-radius:.75rem;background:#f1f5f9;display:flex;flex-direction:column;font-size:.85rem}.chip.ok{background:#dcfce7}.chip.warn{background:#fee2e2}.upload{display:flex;gap:1rem;align-items:center;margin-bottom:1rem}.upload .file{flex:1;display:flex;flex-direction:column;gap:.3rem;font-weight:600;color:#334155}.probe-form{display:flex;gap:.5rem;margin-bottom:1rem}.probe-results{margin-top:1rem}.probe-results table td.status-ok{color:#15803d;font-weight:600}.probe-results table td.status-fail{color:#b91c1c;font-weight:600}.order-form{display:flex;gap:.5rem;margin-bottom:1rem}.order-form input{flex:1;padding:.5rem;border:1px solid #cbd5f5;border-radius:.5rem}.code{background:#0f172a;color:#e2e8f0;padding:1rem;border-radius:.75rem;overflow:auto}.badge[data-copy]{cursor:pointer}.table pre{white-space:pre-wrap}details summary{cursor:pointer;font-weight:600;margin-bottom:.5rem}details ul{margin-left:1.5rem}';
    }

    private static function script() : string {
        return 'document.querySelectorAll("[data-copy]").forEach(function(btn){var original=btn.getAttribute("data-copy-label")||btn.textContent;btn.addEventListener("click",function(){navigator.clipboard.writeText(btn.getAttribute("data-copy"));btn.textContent="Copiat!";setTimeout(function(){btn.textContent=original;},1500);});});';
    }

    private static function build_url(string $home, string $path, array $query) : string {
        $home = rtrim($home ?: '/', '/');
        $base = $home . '/' . ltrim($path, '/');
        return self::with_query($base, $query);
    }

    private static function comparison_diff_table(array $differences) : string {
        if (!$differences) {
            return '';
        }
        $rows = '';
        foreach ($differences as $entry) {
            $match = !empty($entry['match']);
            $class = $match ? 'match' : 'mismatch';
            $label = $match ? 'OK' : 'Diferență';
            $rows .= '<tr class="'.$class.'">'
                .'<td>'.self::esc($entry['field'] ?? '').'</td>'
                .'<td>'.self::esc($entry['server'] ?? '').'</td>'
                .'<td>'.self::esc($entry['file'] ?? '').'</td>'
                .'<td>'.self::esc($label).'</td>'
                .'</tr>';
        }
        return '<div class="table diff"><table><thead><tr><th>Metodă</th><th>Server (last_response)</th><th>Fișier MPay</th><th>Status</th></tr></thead><tbody>'.$rows.'</tbody></table></div>';
    }

    private static function with_query(string $url, array $params) : string {
        if (function_exists('add_query_arg')) {
            return add_query_arg($params, $url);
        }
        $query = http_build_query($params);
        if ($query === '') {
            return $url;
        }
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url.$separator.$query;
    }

    private static function trailingslashit(string $value) : string {
        if (function_exists('trailingslashit')) {
            return trailingslashit($value);
        }
        return rtrim($value, '/').'/';
    }

    private static function to_json($value) : string {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
