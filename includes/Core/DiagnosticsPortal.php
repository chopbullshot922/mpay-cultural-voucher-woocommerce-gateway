<?php
namespace MPAY_VG\Core;
use MPAY_VG\Core\DiagnosticsSnapshot;
use MPAY_VG\Core\DiagnosticsTools;
use MPAY_VG\Core\TestPlaybook;
if (!defined('ABSPATH')) { exit; }

class DiagnosticsPortal {
    private const LOG_ZIP_MAX_BYTES = 10485760; // ~10 MB per export pentru a proteja serverul
    private const LOG_ZIP_MAX_SOAP_FILES = 25;
    public static function render() {
        $opts = \mpay_vg_get_settings();
        if (!empty($opts['mode_prod'])) {
            self::forbidden('Portalul /mpay/diagnostics este disponibil exclusiv în modul TEST.');
        }
        $shared = trim($opts['debug_shared_key'] ?? '');
        if ($shared === '') {
            self::forbidden('Cheia partajată pentru debug lipsește. Setează „Cheie acces debug” în setări.');
        }
        $provided = self::extract_key();
        if ($provided === '' || !hash_equals($shared, $provided)) {
            self::forbidden('Cheie invalidă pentru MPay Diagnostics.');
        }

        $orderQuery = isset($_REQUEST['order']) ? self::sanitize_text($_REQUEST['order']) : '';
        $actionState = null;
        if (self::is_post()) {
            $actionState = self::handle_post($opts);
            if (!empty($actionState['order']['order_key'])) {
                $orderQuery = $actionState['order']['order_key'];
            }
        }

        $downloadRequest = isset($_GET['download']) ? self::sanitize_text($_GET['download']) : '';
        if ($downloadRequest !== '') {
            self::handle_download($downloadRequest, $opts, $orderQuery);
            return;
        }

        if (isset($_GET['format']) && $_GET['format'] === 'json') {
            self::render_json($opts, $orderQuery);
            return;
        }

        $snapshot = DiagnosticsSnapshot::build([
            'settings' => $opts,
            'order_query' => $orderQuery,
            'soap_limit' => 20,
            'db_limit' => 25,
            'debug_limit' => 25,
            'availability_limit' => 60,
        ]);

        self::send_headers();
        self::render_html($snapshot, $opts, $shared, $orderQuery, $actionState);
    }

    private static function render_json(array $opts, string $orderQuery) {
        $snapshot = DiagnosticsSnapshot::build([
            'settings' => $opts,
            'order_query' => $orderQuery,
            'soap_limit' => 50,
            'db_limit' => 50,
            'debug_limit' => 50,
            'availability_limit' => 100,
        ]);
        nocache_headers();
        status_header(200);
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode([
            'generated_at' => gmdate('c'),
            'snapshot' => $snapshot,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function handle_post(array $opts) : array {
        $action = isset($_POST['mpay_diag_action']) ? self::sanitize_text($_POST['mpay_diag_action']) : '';
        if ($action === 'create_order') {
            $amount = isset($_POST['diag_amount']) ? (float) $_POST['diag_amount'] : 250.0;
            $reason = isset($_POST['diag_reason']) ? self::sanitize_text($_POST['diag_reason']) : '';
            $payload = DiagnosticsTools::create_test_order([
                'amount' => $amount,
                'reason' => $reason !== '' ? $reason : 'MPay Diagnostics Test',
            ]);
            if (!empty($payload['success'])) {
                return [
                    'notice' => $payload['message'] ?? 'Comanda a fost creată.',
                    'order' => $payload['order'] ?? [],
                ];
            }
            return [
                'error' => $payload['message'] ?? 'Nu pot crea comanda de test.',
                'order' => [],
            ];
        }
        return [];
    }

    private static function render_html(array $snapshot, array $opts, string $shared, string $orderQuery, ?array $actionState) {
        $site = $snapshot['site'];
        $title = 'MPay Diagnostics – '.($site['domain'] ?? parse_url($site['home_url'], PHP_URL_HOST) ?: 'site');
        $navArgs = $orderQuery !== '' ? ['order' => $orderQuery] : [];
        $baseUrl = self::portal_url($shared, $navArgs);
        $jsonUrl = self::portal_url($shared, array_merge($navArgs, ['format' => 'json']));
        $downloadJsonUrl = self::portal_url($shared, array_merge($navArgs, ['download' => 'snapshot-json']));
        $downloadZipUrl = self::portal_url($shared, array_merge($navArgs, ['download' => 'logs-zip']));
        $soapEndpoint = $site['soap_endpoint'];
        $curlSample = self::curl_snippet($soapEndpoint);
        $messageHtml = '';
        $createdOrder = [];
        if (is_array($actionState)) {
            if (!empty($actionState['notice'])) {
                $messageHtml .= '<div class="mpay-alert success">'.esc_html($actionState['notice']).'</div>';
            }
            if (!empty($actionState['error'])) {
                $messageHtml .= '<div class="mpay-alert error">'.esc_html($actionState['error']).'</div>';
            }
            $createdOrder = $actionState['order'] ?? [];
        }

        $settings = $snapshot['settings'];
        $environment = $snapshot['environment'];
        $system = $snapshot['system'] ?? [];
        $meta = $snapshot['meta'] ?? [];
        $storage = $snapshot['storage'] ?? [];
        $statusMatrix = $snapshot['status'] ?? [];
        $compliance = $snapshot['compliance'] ?? [];
        $certs = $snapshot['certificates'];
        $runtime = $snapshot['runtime'];
        $soapFiles = $snapshot['soap']['persisted_samples'];
        $orderInspection = $snapshot['order_inspection'];
        $dbEvents = $runtime['db_events'];
        $debugEvents = $runtime['debug_events'];
        $availability = $runtime['availability'];
        $lastSoap = $runtime['last_soap'] ?? [];
        $lastSoapExcerpt = $runtime['last_soap_excerpt'] ?? '';
        $cron = $runtime['cron'] ?? [];
        $rewrites = $snapshot['rewrites'] ?? [];
        $cliCommands = [
            'wp mpay diagnostics snapshot --order=35243 --soap-limit=20',
            'wp mpay diagnostics soap --limit=25',
            'wp mpay diagnostics create-order --amount=250 --reason="MPay QA"',
            'wp mpay check --order=1234',
        ];
        $testScenarios = TestPlaybook::scenarios($snapshot);

        echo '<!doctype html><html lang="ro"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>'.esc_html($title).'</title>';
        echo '<style>'.self::styles().'</style></head><body>';
        echo '<header class="mpay-header">';
        echo '<div><h1>MPay Diagnostics</h1><p>Toolkit complet pentru testare, export și depanare.</p></div>';
        echo '<div class="mpay-badges">';
        echo '<span class="badge '.(!empty($opts['mode_prod']) ? 'warn' : 'ok').'">'.(!empty($opts['mode_prod']) ? 'PROD' : 'TEST').' MODE</span>';
        echo '<span class="badge">ServiceID '.$settings['service_id'].'</span>';
        echo '</div></header>';
        echo '<nav class="mpay-nav">';
        foreach ([
            'overview' => 'Overview',
            'compliance' => 'Conformitate',
            'endpoints' => 'Endpoint-uri',
            'certificates' => 'Certificate',
            'environment' => 'Mediu',
            'soap' => 'SOAP',
            'logs' => 'Loguri',
            'tools' => 'Unelte',
            'playbook' => 'Playbook MPay',
            'cli' => 'CLI / Ghid'
        ] as $anchor => $label) {
            echo '<a href="#'.esc_attr($anchor).'">'.esc_html($label).'</a>';
        }
        echo '</nav>';
        echo '<main class="mpay-content">';
        echo $messageHtml;

        echo '<section id="overview"><div class="section-head"><h2>Overview</h2><span class="subtle">Generat la '.esc_html($snapshot['generated_at']).'</span></div>';
        echo '<div class="grid cols-3">';
        echo self::metric('Profil', $settings['profile']);
        echo self::metric('Mod', $settings['mode']);
        echo self::metric('WP', $environment['wp']);
        echo self::metric('WooCommerce', $environment['woocommerce'] ?: '-');
        echo self::metric('PHP', $environment['php']);
        echo self::metric('Plugin', $meta['plugin_version'] ?? '-');
        echo '</div>';
        echo '<div class="status-grid">';
        echo self::status_chip('HTTPS enforcement', !empty($statusMatrix['https_enforced']));
        echo self::status_chip('WS-Security ready', !empty($statusMatrix['ws_security_ready']));
        echo self::status_chip('SOAP Guard', !empty($statusMatrix['soap_guard_enabled']));
        echo self::status_chip('SOAP persist storage', !empty($statusMatrix['soap_persist_ready']));
        echo self::status_chip('PHP SOAP ext', !empty($statusMatrix['soap_extension']));
        echo '</div>';
        echo '</section>';

        if ($compliance) {
            echo '<section id="compliance" class="card">';
            echo '<div class="section-head"><h2>Verificări tehnice</h2></div>';
            echo '<ul class="compliance-list">';
            foreach ($compliance as $item) {
                $state = ($item['status'] ?? '') === 'ok' ? 'ok' : 'warn';
                $label = $state === 'ok' ? 'OK' : 'NEEDS ACTION';
                echo '<li>';
                echo '<div class="comp-head">';
                echo '<div><strong>'.esc_html($item['title'] ?? '').'</strong><span>'.esc_html($item['reference'] ?? '').'</span></div>';
                echo '<span class="pill '.$state.'">'.esc_html($label).'</span>';
                echo '</div>';
                echo '<p>'.esc_html($item['detail'] ?? '').'</p>';
                echo '</li>';
            }
            echo '</ul>';
            echo '</section>';
        }

        echo '<section id="endpoints" class="card">';
        echo '<div class="section-head"><h2>Endpoint-uri & Export</h2><div class="btn-row">';
        echo '<a class="btn" href="'.esc_url($downloadJsonUrl).'">Download snapshot.json</a>';
        echo '<a class="btn" href="'.esc_url($downloadZipUrl).'">Download logs.zip</a>';
        echo '<a class="btn ghost" href="'.esc_url($jsonUrl).'" target="_blank" rel="noopener">Vizualizează JSON</a>';
        echo '</div></div>';
        echo '<ul class="endpoint-list">';
        echo '<li><span>SOAP</span><a href="'.esc_url($soapEndpoint).'" target="_blank" rel="noopener">'.esc_html($soapEndpoint).'</a></li>';
        echo '<li><span>Redirect</span>'.esc_html($site['redirect_endpoint']).'</li>';
        echo '<li><span>Debug JSON</span>'.esc_html($site['debug_endpoint']).'</li>';
        echo '<li><span>Diagnostics</span>'.esc_html($site['diagnostics_endpoint']).'</li>';
        echo '</ul>';
        echo '<h4>cURL exemplu (folosiți fișierul SOAP complet)</h4>';
        echo '<pre class="code-block">'.esc_html($curlSample)."\n--compressed".'</pre>';
        echo '</section>';

        echo '<section id="certificates"><div class="section-head"><h2>Certificate & Chei</h2></div><div class="table-scroll"><table><thead><tr><th>Tip</th><th>Detalii</th></tr></thead><tbody>';
        foreach ($certs as $label => $info) {
            echo '<tr><td>'.esc_html($label).'</td><td>';
            if (empty($info['exists'])) {
                echo '<span class="warn">Missing</span> '.esc_html($info['path'] ?? '');
            } else {
                $details = [];
                if (!empty($info['path'])) { $details[] = basename($info['path']); }
                if (!empty($info['subject_cn'])) { $details[] = 'CN='.$info['subject_cn']; }
                if (!empty($info['valid_to'])) { $details[] = 'Valabil până la '.$info['valid_to']; }
                if (isset($info['days_left'])) { $details[] = 'Zile rămase '.$info['days_left']; }
                echo esc_html(implode(' | ', $details));
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section>';

        echo '<section id="environment" class="grid cols-2">';
        echo '<div class="card"><h3>Mediu server</h3><ul class="kv">';
        foreach ([
            'server_time' => 'Server time',
            'timezone' => 'Timezone',
            'php' => 'PHP',
            'php_binary' => 'PHP Binary',
            'server_software' => 'Server',
            'server_addr' => 'Server IP',
            'db_version' => 'DB Version',
        ] as $field => $label) {
            if (!isset($environment[$field]) || $environment[$field] === '') { continue; }
            echo '<li><span>'.$label.'</span><strong>'.esc_html((string) $environment[$field]).'</strong></li>';
        }
        echo '</ul><h4>Extensii critice</h4><ul class="kv">';
        foreach ($environment['extensions'] as $ext => $enabled) {
            echo '<li><span>'.esc_html($ext).'</span><strong>'.($enabled ? 'YES' : 'NO').'</strong></li>';
        }
        echo '</ul></div>';

        echo '<div class="card"><h3>Config PHP / Storage</h3><ul class="kv">';
        foreach ([
            'memory_limit' => 'memory_limit',
            'max_execution_time' => 'max_execution_time',
            'post_max_size' => 'post_max_size',
            'upload_max_filesize' => 'upload_max_filesize',
            'max_input_vars' => 'max_input_vars',
            'php_ini' => 'php.ini',
        ] as $field => $label) {
            if (empty($system[$field])) { continue; }
            echo '<li><span>'.$label.'</span><strong>'.esc_html((string) $system[$field]).'</strong></li>';
        }
        echo '</ul><h4>Storage</h4><ul class="kv">';
        foreach ([
            'uploads_dir' => 'Uploads dir',
            'soap_dir' => 'SOAP dir',
            'soap_dir_exists' => 'SOAP dir exists',
            'soap_dir_writable' => 'SOAP dir writable',
            'disk_free_mb' => 'Disk free (MB)',
            'disk_total_mb' => 'Disk total (MB)',
        ] as $field => $label) {
            if (!array_key_exists($field, $storage)) { continue; }
            $value = $storage[$field];
            if (is_bool($value)) {
                $value = $value ? 'YES' : 'NO';
            }
            echo '<li><span>'.$label.'</span><strong>'.esc_html((string) $value).'</strong></li>';
        }
        echo '</ul></div>';
        echo '</section>';

        echo '<section id="soap"><div class="section-head"><h2>SOAP Runtime</h2></div>';
        echo '<div class="grid cols-2">';
        echo '<div class="card"><h3>Ultimul apel SOAP</h3>';
        if ($lastSoap) {
            echo '<ul class="kv">';
            foreach ($lastSoap as $k => $v) {
                echo '<li><span>'.esc_html($k).'</span><strong>'.esc_html(is_scalar($v) ? (string) $v : wp_json_encode($v)).'</strong></li>';
            }
            echo '</ul>';
        } else {
            echo '<p><em>Încă nu există apeluri capturate.</em></p>';
        }
        if ($lastSoapExcerpt) {
            echo '<h4>Fragment XML</h4><pre class="code-block">'.esc_html($lastSoapExcerpt).'</pre>';
        }
        echo '</div>';
        echo '<div class="card"><h3>Persistență SOAP ('.count($soapFiles).')</h3>';
        if ($soapFiles) {
            echo '<div class="table-scroll"><table><thead><tr><th>Fișier</th><th>Dimensiune</th><th>Modificat</th></tr></thead><tbody>';
            foreach ($soapFiles as $file) {
                echo '<tr><td>'.esc_html($file['name']).'</td><td>'.esc_html(isset($file['size']) ? $file['size'].' B' : '-').'</td><td>'.esc_html($file['modified'] ?? '-').'</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>Persistența este dezactivată sau folderul este gol.</em></p>';
        }
        echo '</div></div>';
        echo '</section>';

        echo '<section id="logs"><div class="section-head"><h2>Loguri & Disponibilitate</h2></div>';
        echo '<div class="grid cols-2">';
        echo '<div class="card"><h3>Log DB</h3>';
        if ($dbEvents) {
            echo '<div class="table-scroll"><table><thead><tr><th>Timp</th><th>Op</th><th>Rezultat</th><th>Order</th><th>IP</th></tr></thead><tbody>';
            foreach ($dbEvents as $event) {
                echo '<tr><td>'.esc_html($event['ts']).'</td><td>'.esc_html($event['op']).'</td><td>'.esc_html($event['result']).'</td><td>'.esc_html($event['order_id']).'</td><td>'.esc_html($event['ip']).'</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>DB logging dezactivat sau fără date.</em></p>';
        }
        echo '</div>';
        echo '<div class="card"><h3>Evenimente debug</h3>';
        if ($debugEvents) {
            echo '<div class="table-scroll"><table><thead><tr><th>Timp</th><th>Nivel</th><th>Componentă</th><th>Mesaj</th></tr></thead><tbody>';
            foreach ($debugEvents as $event) {
                echo '<tr><td>'.esc_html(gmdate('Y-m-d H:i:s', (int) ($event['time'] ?? time()))).'</td><td>'.esc_html($event['level'] ?? '').'</td><td>'.esc_html($event['component'] ?? '').'</td><td>'.esc_html($event['message'] ?? '').'</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>Fără evenimente în jurnal.</em></p>';
        }
        echo '</div></div>';
        echo '<div class="card"><h3>Trasabilitate disponibilitate</h3>';
        if ($availability) {
            echo '<div class="table-scroll"><table><thead><tr><th>Timp</th><th>Cod</th><th>Detaliu</th></tr></thead><tbody>';
            foreach ($availability as $event) {
                echo '<tr><td>'.esc_html($event['time'] ?? '').'</td><td>'.esc_html($event['code'] ?? '').'</td><td>'.esc_html($event['detail'] ?? '').'</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>Fără date colectate încă.</em></p>';
        }
        echo '</div>';
        echo '<div class="grid cols-2">';
        echo '<div class="card"><h3>Cron & monitor</h3>';
        if ($cron) {
            echo '<ul class="kv">';
            foreach ($cron as $k => $v) {
                echo '<li><span>'.esc_html($k).'</span><strong>'.esc_html((string) $v).'</strong></li>';
            }
            echo '</ul>';
        } else {
            echo '<p><em>Nu există informații cron.</em></p>';
        }
        echo '</div>';
        echo '<div class="card"><h3>Rewrite health</h3><ul class="kv">';
        foreach ($rewrites as $k => $v) {
            echo '<li><span>'.esc_html($k).'</span><strong>'.esc_html((string) $v).'</strong></li>';
        }
        echo '</ul></div></div>';
        echo '</section>';

        echo '<section id="tools" class="grid cols-2">';
        echo '<div class="card">';
        echo '<h3>Inspector OrderKey</h3>';
        echo '<form method="get" action="'.esc_url($baseUrl).'">';
        echo '<input type="hidden" name="key" value="'.esc_attr($shared).'" />';
        echo '<label>OrderKey / Order ID</label>';
        echo '<input type="text" name="order" value="'.esc_attr($orderQuery).'" placeholder="35243" />';
        echo '<button type="submit">Inspectează</button>';
        echo '</form>';
        if ($orderInspection) {
            echo '<ul class="kv">';
            foreach ($orderInspection as $k => $v) {
                if (is_array($v)) {
                    $v = wp_json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                echo '<li><span>'.esc_html($k).'</span><strong>'.esc_html((string) $v).'</strong></li>';
            }
            echo '</ul>';
        } else {
            echo '<p><em>Introduceți un OrderKey pentru a vedea mapping-ul.</em></p>';
        }
        echo '</div>';

        echo '<div class="card">';
        echo '<h3>Generator comandă test</h3>';
        echo '<form method="post" action="'.esc_url($baseUrl).'">';
        echo '<label>Suma (MDL)</label><input type="number" step="0.01" name="diag_amount" value="250" min="1" />';
        echo '<label>Motiv</label><input type="text" name="diag_reason" value="MPay Diagnostics Test" />';
        echo '<input type="hidden" name="mpay_diag_action" value="create_order" />';
        echo '<button type="submit">Generează Order ID + Key</button>';
        echo '</form>';
        if (!empty($createdOrder)) {
            echo '<div class="order-card">';
            echo '<p><strong>Order ID:</strong> '.esc_html($createdOrder['id']).'</p>';
            echo '<p><strong>OrderKey:</strong> '.esc_html($createdOrder['order_key']).'</p>';
            if (!empty($createdOrder['redirect_url'])) {
                echo '<p><a class="mpay-link" href="'.esc_url($createdOrder['redirect_url']).'" target="_blank">Deschide /mpay/redirect</a></p>';
            }
            echo '<p>Total: '.esc_html(number_format((float) $createdOrder['total'], 2)).' '.esc_html($createdOrder['currency']).'</p>';
            $confirm = [
                'ServiceID' => $settings['service_id'],
                'OrderKey' => $createdOrder['order_key'],
                'PaymentID' => 'PAY-'.time(),
                'InvoiceID' => 'INV-'.time(),
                'TotalAmount' => number_format((float) $createdOrder['total'], 2, '.', ''),
                'Currency' => 'MDL',
                'PaidAt' => gmdate('c'),
            ];
            echo '<h4>Payload ConfirmOrderPayment</h4><pre class="code-block">'.esc_html(wp_json_encode($confirm, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</pre>';
            echo '</div>';
        }
        echo '</div></section>';

        if (!empty($testScenarios)) {
            $serviceIdLabel = esc_html($settings['service_id']);
            echo '<section id="playbook" class="card">';
            echo '<div class="section-head"><h2>Playbook testare MPay</h2><span class="subtle">Instrucțiuni exacte pentru serverele MPay</span></div>';
                $publicUrl = function_exists('home_url') ? home_url('/mpay/playbook') : '';
                echo '<p class="subtle">Actualizează fișierele SOAP menționate (OrderKey, ServiceID '.$serviceIdLabel.', PaymentID etc.) înainte de a rula comenzile pe infrastructura MPay.</p>';
                if ($publicUrl) {
                    echo '<p class="subtle">Link public partajat cu MPay: <a href="'.esc_url($publicUrl).'" target="_blank" rel="noopener">'.esc_html($publicUrl).'</a></p>';
                }
            echo '<div class="playbook-grid">';
            foreach ($testScenarios as $scenario) {
                echo '<article class="play-card">';
                echo '<div><h3>'.esc_html($scenario['title']).'</h3>';
                if (!empty($scenario['objective'])) {
                    echo '<p>'.esc_html($scenario['objective']).'</p>';
                }
                echo '</div>';
                if (!empty($scenario['commands'])) {
                    echo '<div class="play-block"><strong>Comenzi</strong>';
                    foreach ($scenario['commands'] as $command) {
                        echo '<pre class="play-command">'.esc_html($command).'</pre>';
                    }
                    echo '</div>';
                }
                if (!empty($scenario['validations'])) {
                    echo '<div class="play-block"><strong>Validări</strong><ul>';
                    foreach ($scenario['validations'] as $validation) {
                        echo '<li>'.esc_html($validation).'</li>';
                    }
                    echo '</ul></div>';
                }
                if (!empty($scenario['simulations'])) {
                    echo '<div class="play-block"><strong>Simulări</strong><ul>';
                    foreach ($scenario['simulations'] as $sim) {
                        echo '<li>'.esc_html($sim).'</li>';
                    }
                    echo '</ul></div>';
                }
                if (!empty($scenario['notes'])) {
                    echo '<div class="play-block"><strong>Note</strong><ul>';
                    foreach ($scenario['notes'] as $note) {
                        echo '<li>'.esc_html($note).'</li>';
                    }
                    echo '</ul></div>';
                }
                if (!empty($scenario['reference'])) {
                    echo '<div class="play-block"><strong>Date concrete</strong><ul class="kv">';
                    foreach ($scenario['reference'] as $refLabel => $refValue) {
                        echo '<li><span>'.esc_html($refLabel).'</span><strong>'.esc_html($refValue).'</strong></li>';
                    }
                    echo '</ul></div>';
                }
                echo '</article>';
            }
            echo '</div></section>';
        }

        echo '<section id="cli" class="card">';
        echo '<div class="section-head"><h2>CLI & ghid pentru QA</h2></div>';
        echo '<div class="grid cols-2 compact">';
        echo '<div><h3>Comenzi WP-CLI</h3><ul class="command-list">';
        foreach ($cliCommands as $cmd) {
            echo '<li><code>'.esc_html($cmd).'</code></li>';
        }
        echo '</ul></div>';
        echo '<div><h3>Pași recomandați</h3><ol class="guide-list">';
        echo '<li>Rulează `curl` cu fișierul SOAP furnizat de MPay și verifică răspunsul semnat.</li>';
        echo '<li>Generează o comandă test și confirmă OrderKey-ul rezultat.</li>';
        echo '<li>Verifică tab-ul Log DB pentru `ConfirmOrderPayment` după testele MPay.</li>';
        echo '<li>Exportă snapshot.json sau logs.zip și trimite-le către echipa MPay.</li>';
        echo '</ol></div>';
        echo '</div></section>';

        echo '</main><footer class="mpay-footer">MPay gateway TerabitLab · '.esc_html(gmdate('Y')).'</footer></body></html>';
        exit;
    }

    private static function curl_snippet(string $endpoint) : string {
        $trimmed = rtrim($endpoint, '/').'/';
        return "curl -vk \"{$trimmed}\" \\\n  -H 'Content-Type: text/xml' \\\n  --data-binary @soap-request.xml";
    }

    private static function styles() : string {
        return <<<'CSS'
body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:0;background:#0f172a;color:#e2e8f0}
a{color:#38bdf8}
main{padding:2rem;margin:auto;max-width:1200px}
header{background:#020617;padding:1.5rem 2rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #1e293b}
nav.mpay-nav{display:flex;flex-wrap:wrap;gap:1rem;background:#020617;padding:.75rem 2rem;border-bottom:1px solid #1e293b}
nav.mpay-nav a{text-decoration:none;color:#94a3b8;font-weight:600}
nav.mpay-nav a:hover{color:#38bdf8}
h1,h2,h3,h4{margin:0 0 .5rem}
section{margin-bottom:2rem;background:#0b1324;padding:1.5rem;border-radius:12px;border:1px solid #1e293b}
section.grid{display:grid;gap:1.5rem;background:transparent;border:none;padding:0}
section .section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
.section-head .subtle{color:#94a3b8;font-size:.85rem}
.grid.cols-2{grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
.grid.cols-3{grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
.grid.cols-2.compact{gap:1rem}
.card{background:#0b1324;padding:1.25rem;border-radius:12px;border:1px solid #1e293b}
.badge{display:inline-block;padding:.35rem .75rem;border-radius:999px;background:#1e293b;margin-left:.4rem;font-size:.85rem;text-transform:uppercase}
.badge.ok{background:#065f46}
.badge.warn{background:#7c2d12}
.metric{background:#020617;padding:1rem;border-radius:10px;border:1px solid #1e293b}
.metric span{display:block;color:#94a3b8;font-size:.85rem;margin-bottom:.35rem}
.metric strong{font-size:1.2rem}
.status-grid{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}
.status-chip{display:flex;flex-direction:column;padding:.5rem .9rem;border-radius:12px;border:1px solid #1e293b;background:#020617;font-size:.8rem}
.status-chip strong{font-size:.95rem}
.status-chip.ok{border-color:#10b981}
.status-chip.warn{border-color:#f97316}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem 1rem;border-radius:999px;background:#2563eb;color:#e2e8f0;text-decoration:none;font-weight:600}
.btn:hover{background:#1d4ed8}
.btn.ghost{background:transparent;border:1px solid #2563eb}
.btn-row{display:flex;gap:.75rem;flex-wrap:wrap}
.mpay-alert{padding:1rem;border-radius:10px;margin-bottom:1rem}
.mpay-alert.success{background:#064e3b;color:#ecfdf5}
.mpay-alert.error{background:#7c2d12;color:#fee2e2}
.compliance-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:1rem}
.compliance-list li{padding:1rem;border-radius:10px;background:#020617;border:1px solid #1e293b}
.comp-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;gap:1rem}
.comp-head div span{display:block;color:#94a3b8;font-size:.8rem}
.pill{padding:.2rem .8rem;border-radius:999px;font-size:.75rem;text-transform:uppercase;font-weight:700}
.pill.ok{background:#064e3b;color:#a7f3d0}
.pill.warn{background:#7c2d12;color:#fecaca}
.table-scroll{overflow:auto;max-height:320px}
.table-scroll table{width:100%;border-collapse:collapse;font-size:.9rem}
.table-scroll th,.table-scroll td{padding:.35rem .5rem;border-bottom:1px solid #1e293b;text-align:left}
.kv{list-style:none;padding:0;margin:1rem 0 0}
.kv li{display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px dotted #1e293b;gap:1rem}
.kv span{color:#94a3b8}
.order-card{margin-top:1rem;padding:1rem;border:1px solid #1e293b;border-radius:10px;background:#020617}
form{display:flex;flex-direction:column;gap:.6rem;margin-top:.5rem}
input,button{font:inherit;padding:.5rem .75rem;border-radius:8px;border:1px solid #1e293b;background:#0f172a;color:#e2e8f0}
button{background:#2563eb;border:none;cursor:pointer}
button:hover{background:#1d4ed8}
.mpay-footer{text-align:center;padding:1rem;color:#94a3b8;border-top:1px solid #1e293b;background:#020617}
.code-block,pre{background:#020617;padding:.75rem;border-radius:10px;overflow:auto;font-size:.85rem;line-height:1.4}
.endpoint-list{list-style:none;padding:0;margin:0 0 1rem}
.endpoint-list li{display:flex;justify-content:space-between;padding:.35rem 0;border-bottom:1px solid #1e293b;gap:1rem}
.endpoint-list span{color:#94a3b8}
.command-list,.guide-list{list-style:none;padding-left:0;margin:0}
.command-list li{margin-bottom:.5rem}
.command-list code{background:#020617;padding:.3rem .5rem;border-radius:6px;font-size:.85rem;display:inline-block}
.guide-list{list-style:decimal;margin-left:1.25rem;color:#94a3b8}
.guide-list li{margin-bottom:.4rem;color:#e2e8f0}
.mpay-link{color:#38bdf8;text-decoration:none}
.playbook-grid{display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
.play-card{background:#020617;border:1px solid #1e293b;border-radius:12px;padding:1.25rem;display:flex;flex-direction:column;gap:.75rem}
.play-card h3{margin-bottom:.3rem}
.play-block strong{display:block;color:#94a3b8;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem}
.play-command{background:#0b1324;padding:.75rem;border-radius:10px;font-family:SFMono-Regular,Consolas,monospace;font-size:.8rem;white-space:pre-wrap}
.play-card ul{margin:0;padding-left:1.1rem;color:#94a3b8}
.play-card li{margin-bottom:.35rem}
.play-card .kv{list-style:none;padding-left:0;margin:.6rem 0 0;border-top:1px solid #1e293b}
.play-card .kv li{display:flex;justify-content:space-between;gap:.8rem;padding:.3rem 0;border-bottom:1px dotted #1e293b;font-size:.85rem}
.play-card .kv span{color:#94a3b8}
CSS;
    }

    private static function metric(string $label, $value) : string {
        return '<div class="metric"><span>'.esc_html($label).'</span><strong>'.esc_html((string) $value).'</strong></div>';
    }

    private static function status_chip(string $label, bool $ok) : string {
        $class = $ok ? 'ok' : 'warn';
        $value = $ok ? 'OK' : 'CHECK';
        return '<span class="status-chip '.$class.'"><strong>'.esc_html($value).'</strong><small>'.esc_html($label).'</small></span>';
    }

    private static function send_headers() {
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
    }

    private static function handle_download(string $mode, array $opts, string $orderQuery) {
        $snapshot = DiagnosticsSnapshot::build([
            'settings' => $opts,
            'order_query' => $orderQuery,
            'soap_limit' => 50,
            'db_limit' => 50,
            'debug_limit' => 50,
            'availability_limit' => 100,
        ]);

        if ($mode === 'snapshot-json') {
            $payload = [
                'generated_at' => gmdate('c'),
                'snapshot' => $snapshot,
            ];
            $json = self::json_encode_pretty($payload);
            $filename = 'mpay-diagnostics-snapshot-'.date('Ymd-His').'.json';
            self::send_download_headers($filename, 'application/json', strlen($json));
            echo $json;
            exit;
        }

        if ($mode === 'logs-zip') {
            self::download_logs_zip($snapshot);
            return;
        }

        self::forbidden('Parametru download invalid.');
    }

    private static function download_logs_zip(array $snapshot) {
        if (!class_exists('\ZipArchive')) {
            self::forbidden('Extensia ZipArchive nu este disponibilă pe acest server.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'mpay_diag_');
        if (!$tmp) {
            self::forbidden('Nu pot crea fișier temporar.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            self::forbidden('Nu pot inițializa arhiva ZIP.');
        }

        $budget = self::LOG_ZIP_MAX_BYTES;
        $notes = [];
        $addString = function($filename, $data) use ($zip, &$budget, &$notes) {
            if ($data === null || $data === '') {
                return;
            }
            $bytes = strlen($data);
            if ($bytes > $budget) {
                $notes[] = $filename.' omis (depaseste bugetul de export).';
                return;
            }
            $zip->addFromString($filename, $data);
            $budget -= $bytes;
        };

        $addString('snapshot.json', self::json_encode_pretty([
            'generated_at' => gmdate('c'),
            'snapshot' => $snapshot,
        ]));
        $addString('debug-events.json', self::json_encode_pretty($snapshot['runtime']['debug_events'] ?? []));
        $addString('db-events.json', self::json_encode_pretty($snapshot['runtime']['db_events'] ?? []));
        $addString('availability.json', self::json_encode_pretty($snapshot['runtime']['availability'] ?? []));
        $addString('rewrites.json', self::json_encode_pretty($snapshot['rewrites'] ?? []));
        if (!empty($snapshot['runtime']['last_soap_excerpt'])) {
            $addString('last-soap.xml', $snapshot['runtime']['last_soap_excerpt']);
        }

        $soapFiles = $snapshot['soap']['persisted_samples'] ?? [];
        $soapAdded = 0;
        foreach ($soapFiles as $file) {
            if ($soapAdded >= self::LOG_ZIP_MAX_SOAP_FILES) {
                $notes[] = 'Limită atinsă: doar primele '.self::LOG_ZIP_MAX_SOAP_FILES.' fișiere SOAP au fost exportate.';
                break;
            }
            $path = $file['path'] ?? '';
            $name = $file['name'] ?? ('soap-'.sha1((string) $path).'.xml');
            if (!$path || !file_exists($path)) {
                continue;
            }
            $size = filesize($path);
            if ($size === false) {
                continue;
            }
            if ($size > $budget) {
                $notes[] = $name.' omis ('.number_format($size / 1048576, 2).' MB) – depășește bugetul de '.number_format(self::LOG_ZIP_MAX_BYTES / 1048576, 2).' MB.';
                continue;
            }
            $zip->addFile($path, 'soap/'.$name);
            $budget -= $size;
            $soapAdded++;
        }

        $readme = "MPay diagnostics export\nGenerat la: ".gmdate('c')."\nBuget rămas: ".max(0, $budget).' bytes.';
        $addString('README.txt', $readme);
        if ($notes) {
            $addString('SKIPPED.txt', implode("\n", $notes));
        }

        $zip->close();

        $filename = 'mpay-diagnostics-'.date('Ymd-His').'.zip';
        self::send_download_headers($filename, 'application/zip', filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    private static function json_encode_pretty($data) {
        if (function_exists('wp_json_encode')) {
            $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                return $json;
            }
        }
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function send_download_headers(string $filename, string $contentType, $length = null) {
        nocache_headers();
        header('Content-Type: '.$contentType);
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        if ($length !== null) {
            header('Content-Length: '.$length);
        }
    }

    private static function forbidden(string $message) {
        status_header(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }

    private static function extract_key() : string {
        $value = isset($_GET['key']) ? $_GET['key'] : null;
        if ($value === null && isset($_POST['key'])) {
            $value = $_POST['key'];
        }
        if ($value === null) {
            return '';
        }
        return self::sanitize_text($value);
    }

    private static function sanitize_text($value) : string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (function_exists('sanitize_text_field')) {
            $value = sanitize_text_field($value);
        }
        return $value;
    }

    private static function portal_url(string $key, array $args) : string {
        $base = function_exists('home_url') ? home_url('/mpay/diagnostics') : '/mpay/diagnostics';
        $params = array_merge(['key' => $key], $args);
        if (function_exists('add_query_arg')) {
            return add_query_arg($params, $base);
        }
        return $base.'?'.http_build_query($params, '', '&');
    }

    private static function is_post() : bool {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
