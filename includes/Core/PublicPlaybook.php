<?php
namespace MPAY_VG\Core;
if (!defined('ABSPATH')) { exit; }

class PublicPlaybook {
    public static function render() {
        $opts = \mpay_vg_get_settings();
        if (!empty($opts['mode_prod'])) {
            self::send_headers('text/plain', 403);
            echo 'Endpointul /mpay/playbook este disponibil doar în modul TEST.';
            exit;
        }
        $orderQuery = isset($_GET['order']) ? self::sanitize_text($_GET['order']) : '';
        $orderOverrideId = 0;
        if ($orderQuery !== '') {
            $resolved = OrderMapper::resolve_order_id($orderQuery);
            if ($resolved > 0) {
                $orderOverrideId = $resolved;
            }
        }

        $snapshot = DiagnosticsSnapshot::build([
            'settings' => $opts,
            'order_query' => $orderQuery,
            'soap_limit' => 10,
            'db_limit' => 15,
            'debug_limit' => 15,
            'availability_limit' => 40,
        ]);

        $referenceOrder = DiagnosticsTools::ensure_playbook_order([
            'order_id' => $orderOverrideId,
            'auto_create' => true,
        ]);
        $scenarios = TestPlaybook::scenarios($snapshot, ['order' => $referenceOrder]);

        if (isset($_GET['format']) && $_GET['format'] === 'json') {
            self::render_json($snapshot, $referenceOrder);
            return;
        }

        self::send_headers();
        self::render_html($snapshot, $scenarios, $referenceOrder, $orderQuery, $opts);
    }

    private static function render_html(array $snapshot, array $scenarios, ?array $referenceOrder, string $orderQuery, array $opts) {
        $site = $snapshot['site'] ?? [];
        $runtime = $snapshot['runtime'] ?? [];
        $dbEvents = array_slice($runtime['db_events'] ?? [], 0, 6);
        $debugEvents = array_slice($runtime['debug_events'] ?? [], 0, 6);
        $serviceId = $snapshot['settings']['service_id'] ?? '';
        $jsonPreview = self::encode_json([
            'generated_at' => gmdate('c'),
            'order' => $referenceOrder,
            'snapshot' => $snapshot,
        ]);
        $orderInfo = $referenceOrder ? self::format_order_info($referenceOrder) : 'Nu există încă o comandă test marcată "Diagnostics". Accesează din nou această pagină pentru a genera una automat (doar în mediul TEST).';
        $modeBadge = !empty($opts['mode_prod']) ? 'PROD' : 'TEST';
        $playbookUrl = function_exists('home_url') ? home_url('/mpay/playbook') : '/mpay/playbook';
        $jsonUrl = function_exists('add_query_arg')
            ? add_query_arg(['format' => 'json'], $playbookUrl)
            : rtrim($playbookUrl, '/').'?format=json';

        echo '<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>MPay – Playbook public</title>';
        echo '<style>'.self::styles().'</style></head><body>';
        echo '<header class="pp-header">';
        echo '<div><h1>MPay – Playbook public</h1><p>Instrucțiuni concrete pentru echipa MPay (SOAP + ConfirmOrderPayment).</p></div>';
        echo '<div class="pp-badges"><span class="badge">'.$modeBadge.' MODE</span>';
        if ($serviceId) {
            echo '<span class="badge">ServiceID '.esc_html($serviceId).'</span>';
        }
        echo '</div></header>';

        echo '<main class="pp-content">';
        echo '<section class="pp-callout">';
        echo '<div class="order-meta">';
        echo '<h2>OrderKey pregătit</h2>';
        if (is_string($orderInfo)) {
            echo '<p>'.esc_html($orderInfo).'</p>';
        } else {
            echo '<ul>';
            foreach ($orderInfo as $label => $value) {
                echo '<li><span>'.esc_html($label).'</span><strong>'.esc_html($value).'</strong></li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        echo '<form method="get" class="order-form">';
        echo '<label for="playbook-order">OrderKey / Order ID</label>';
        echo '<input type="text" id="playbook-order" name="order" value="'.esc_attr($orderQuery).'" placeholder="35243" />';
        echo '<button type="submit">Actualizează</button>';
        echo '<a class="ghost" href="'.esc_url($jsonUrl).'" target="_blank" rel="noopener">Snapshot JSON</a>';
        echo '</form>';
        echo '</section>';

        echo '<section id="scenarios">';
        echo '<div class="section-head"><h2>Scenarii recomandate</h2><p>Rulează comenzile exact cum sunt listate mai jos în infrastructura MPay.</p></div>';
        echo '<div class="playbook-grid">';
        foreach ($scenarios as $scenario) {
            echo '<article class="play-card">';
            echo '<header><div><h3>'.esc_html($scenario['title']).'</h3>';
            if (!empty($scenario['objective'])) {
                echo '<p>'.esc_html($scenario['objective']).'</p>';
            }
            echo '</div></header>';
            if (!empty($scenario['commands'])) {
                echo '<div class="play-block"><strong>Comenzi</strong>';
                foreach ($scenario['commands'] as $command) {
                    echo '<pre>'.esc_html($command).'</pre>';
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
                echo '<div class="play-block"><strong>Simulări / variații</strong><ul>';
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
                foreach ($scenario['reference'] as $label => $value) {
                    echo '<li><span>'.esc_html($label).'</span><strong>'.esc_html($value).'</strong></li>';
                }
                echo '</ul></div>';
            }
            echo '</article>';
        }
        echo '</div></section>';

        echo '<section class="card" id="snapshot">';
        echo '<div class="section-head"><h2>Snapshot JSON</h2><a class="ghost" href="'.esc_url($jsonUrl).'" target="_blank" rel="noopener">Descarcă</a></div>';
        echo '<pre class="code-block">'.esc_html($jsonPreview).'</pre>';
        echo '</section>';

        echo '<section class="grid cols-2">';
        echo '<div class="card"><h3>Log interacțiuni SOAP (DB)</h3>';
        if ($dbEvents) {
            echo '<div class="table-scroll"><table><thead><tr><th>Timp</th><th>Op</th><th>Result</th><th>Order</th><th>Amount</th><th>IP</th></tr></thead><tbody>';
            foreach ($dbEvents as $event) {
                $amount = isset($event['amount']) && $event['amount'] !== null ? number_format((float) $event['amount'], 2).' '.($event['currency'] ?? '') : '-';
                echo '<tr>';
                echo '<td>'.esc_html($event['ts'] ?? '').'</td>';
                echo '<td>'.esc_html($event['op'] ?? '').'</td>';
                echo '<td>'.esc_html($event['result'] ?? '').'</td>';
                echo '<td>'.esc_html($event['order_id'] ?? 0).'</td>';
                echo '<td>'.esc_html($amount).'</td>';
                echo '<td>'.esc_html($event['ip'] ?? '').'</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>Nu există încă evenimente înregistrate.</em></p>';
        }
        echo '</div>';

        echo '<div class="card"><h3>Evenimente debug</h3>';
        if ($debugEvents) {
            echo '<div class="table-scroll"><table><thead><tr><th>Timp</th><th>Nivel</th><th>Componentă</th><th>Mesaj</th></tr></thead><tbody>';
            foreach ($debugEvents as $event) {
                $time = !empty($event['time']) ? gmdate('Y-m-d H:i:s', (int) $event['time']) : '';
                echo '<tr>';
                echo '<td>'.esc_html($time).'</td>';
                echo '<td>'.esc_html($event['level'] ?? '').'</td>';
                echo '<td>'.esc_html($event['component'] ?? '').'</td>';
                echo '<td>'.esc_html($event['message'] ?? '').'</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p><em>Fără evenimente recente.</em></p>';
        }
        echo '</div>';
        echo '</section>';

        echo '</main><footer class="pp-footer">MPay Gateway · '.esc_html(gmdate('Y')).'</footer></body></html>';
        exit;
    }

    private static function render_json(array $snapshot, ?array $order) {
        $payload = [
            'generated_at' => gmdate('c'),
            'order' => $order,
            'snapshot' => $snapshot,
        ];
        $json = self::encode_json($payload);
        self::send_headers('application/json');
        echo $json;
        exit;
    }

    private static function format_order_info(array $order) {
        $info = [];
        if (!empty($order['order_key'])) {
            $info['OrderKey'] = $order['order_key'];
        }
        if (!empty($order['id'])) {
            $info['Order ID'] = (string) $order['id'];
        }
        if (!empty($order['order_number'])) {
            $info['Order number'] = $order['order_number'];
        }
        if (isset($order['total'])) {
            $info['Total'] = number_format((float) $order['total'], 2).' '.($order['currency'] ?? 'MDL');
        }
        if (!empty($order['created_at'])) {
            $info['Creată la'] = $order['created_at'];
        }
        if (!$info) {
            return 'Nu am putut determina un OrderKey disponibil.';
        }
        return $info;
    }

    private static function sanitize_text($value) {
        $value = (string) $value;
        if (function_exists('sanitize_text_field')) {
            $value = sanitize_text_field($value);
        }
        return trim($value);
    }

    private static function encode_json($data) {
        if (function_exists('wp_json_encode')) {
            $json = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                return $json;
            }
        }
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function send_headers(string $contentType = 'text/html', int $status = 200) {
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        if (function_exists('status_header')) {
            status_header($status);
        } else {
            $phrases = [
                200 => 'OK',
                403 => 'Forbidden',
            ];
            $phrase = $phrases[$status] ?? 'OK';
            header('HTTP/1.1 '.$status.' '.$phrase);
        }
        header('Content-Type: '.$contentType.'; charset=utf-8');
    }

    private static function styles() : string {
        return <<<'CSS'
body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:0;background:#0f172a;color:#e2e8f0}
a{color:#38bdf8}
header,section{box-sizing:border-box}
.pp-header{background:#020617;padding:1.5rem 2rem;display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #1e293b}
.pp-header h1{margin:0 0 .4rem}
.pp-badges{display:flex;gap:.5rem;flex-wrap:wrap}
.badge{padding:.35rem .75rem;border-radius:999px;background:#1e293b;font-size:.85rem;font-weight:600}
.pp-content{max-width:1200px;margin:auto;padding:2rem}
.pp-callout{display:flex;flex-wrap:wrap;gap:1.5rem;border:1px solid #1e293b;border-radius:14px;padding:1.5rem;background:#0b1324;margin-bottom:2rem}
.order-meta ul{list-style:none;padding:0;margin:0}
.order-meta li{display:flex;gap:1rem;padding:.2rem 0;border-bottom:1px dotted #1e293b}
.order-meta span{color:#94a3b8}
.order-form{display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end}
.order-form input{padding:.6rem .8rem;border-radius:8px;border:1px solid #1e293b;background:#020617;color:#e2e8f0;min-width:220px}
.order-form button,.order-form .ghost{padding:.6rem 1rem;border-radius:999px;border:none;background:#2563eb;color:#fff;cursor:pointer;text-decoration:none;font-weight:600}
.order-form .ghost{background:transparent;border:1px solid #2563eb}
.section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
.playbook-grid{display:grid;gap:1.25rem;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
.play-card{background:#0b1324;border:1px solid #1e293b;border-radius:14px;padding:1.25rem;display:flex;flex-direction:column;gap:.75rem}
.play-card h3{margin:0 0 .4rem}
.play-block strong{display:block;color:#94a3b8;font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem}
.play-block ul{margin:0;padding-left:1.15rem}
.play-block li{margin-bottom:.35rem;color:#e2e8f0}
.play-card pre{background:#020617;padding:.75rem;border-radius:10px;font-size:.85rem;overflow:auto}
.play-card .kv{list-style:none;padding:0;margin:.5rem 0 0;border-top:1px solid #1e293b}
.play-card .kv li{display:flex;justify-content:space-between;gap:.8rem;padding:.3rem 0;border-bottom:1px dotted #1e293b;font-size:.85rem}
.card{background:#0b1324;border:1px solid #1e293b;border-radius:14px;padding:1.25rem;margin-top:2rem}
.code-block{background:#020617;padding:1rem;border-radius:12px;max-height:420px;overflow:auto}
.grid{display:grid;gap:1.25rem}
.grid.cols-2{grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
.table-scroll{overflow:auto;max-height:320px}
.table-scroll table{width:100%;border-collapse:collapse;font-size:.85rem}
.table-scroll th,.table-scroll td{padding:.35rem;border-bottom:1px solid #1e293b;text-align:left}
.pp-footer{text-align:center;padding:1rem;color:#94a3b8;border-top:1px solid #1e293b;margin-top:3rem}
CSS;
    }
}
