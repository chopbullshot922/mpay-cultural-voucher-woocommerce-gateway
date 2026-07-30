<?php
namespace MPAY_VG\Admin;
use MPAY_VG\Core\DiagnosticsSnapshot;
use MPAY_VG\Core\TestPlaybook;
if (!defined('ABSPATH')) { exit; }

class Diagnostics {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    public static function register_menu() {
        add_submenu_page(
            'woocommerce',
            'MPay – Diagnostics',
            'MPay Diagnostics',
            'manage_woocommerce',
            'mpay-vg-diagnostics',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Permisiune insuficientă.');
        }

        $opts = \mpay_vg_get_settings();
        $orderQuery = isset($_GET['mpay_order_query']) ? sanitize_text_field(\wp_unslash($_GET['mpay_order_query'])) : '';
        $snapshot = DiagnosticsSnapshot::build([
            'settings' => $opts,
            'order_query' => $orderQuery,
            'soap_limit' => 5,
            'db_limit' => 10,
            'debug_limit' => 10,
        ]);

        echo '<div class="wrap">';
        echo '<h1>MPay – Panou de diagnostic</h1>';

        echo '<p><a class="button button-secondary" href="'.esc_url(home_url('/mpay/debug?key='.rawurlencode($opts['debug_shared_key'] ?? ''))).'" target="_blank">Deschide endpoint JSON /mpay/debug</a></p>';

        self::render_settings_section($snapshot);
        self::render_certificates_section($snapshot);
        self::render_environment_section($snapshot);
        self::render_runtime_section($snapshot);
        self::render_order_inspector($snapshot, $orderQuery);
        self::render_events_section($snapshot);
        self::render_test_playbook_section($snapshot);

        echo '<h2>Snapshot JSON</h2>';
        echo '<textarea readonly rows="12" style="width:100%;font-family:monospace">'.esc_textarea(wp_json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'</textarea>';

        echo '</div>';
    }

    // snapshot helpers now provided by DiagnosticsSnapshot

    private static function render_settings_section(array $snapshot) {
        echo '<h2>Setări cheie</h2>';
        echo '<table class="widefat striped"><tbody>';
        foreach ($snapshot['settings'] as $label => $value) {
            echo '<tr><td>'.esc_html($label).'</td><td>'.esc_html(is_bool($value) ? ($value ? 'DA' : 'NU') : $value).'</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_certificates_section(array $snapshot) {
        echo '<h2>Certificate</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Tip</th><th>Detalii</th></tr></thead><tbody>';
        foreach ($snapshot['certificates'] as $key => $info) {
            echo '<tr><td>'.esc_html($key).'</td><td>';
            if (empty($info['exists'])) {
                echo '<em>fișier inexistent</em> (' . esc_html($info['path'] ?? '') . ')';
            } else {
                $lines = [];
                $lines[] = 'Path: '.$info['path'];
                if (!empty($info['subject_cn'])) { $lines[] = 'Subject CN: '.$info['subject_cn']; }
                if (!empty($info['issuer_cn'])) { $lines[] = 'Issuer CN: '.$info['issuer_cn']; }
                if (!empty($info['valid_from'])) { $lines[] = 'Valid from: '.$info['valid_from']; }
                if (!empty($info['valid_to'])) { $lines[] = 'Valid to: '.$info['valid_to']; }
                if (isset($info['days_left'])) { $lines[] = 'Days left: '.$info['days_left']; }
                if (!empty($info['serial'])) { $lines[] = 'Serial: '.$info['serial']; }
                echo esc_html(implode(' | ', $lines));
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_environment_section(array $snapshot) {
        echo '<h2>Mediu server</h2>';
        $env = $snapshot['environment'];
        echo '<table class="widefat striped"><tbody>';
        foreach (['server_time','timezone','php','wordpress','woocommerce'] as $field) {
            echo '<tr><td>'.esc_html($field).'</td><td>'.esc_html($env[$field] ?? '').'</td></tr>';
        }
        echo '<tr><td>Extensions</td><td>';
        $extParts = [];
        foreach ($env['extensions'] as $ext => $enabled) {
            $extParts[] = $ext.': '.($enabled ? 'YES' : 'NO');
        }
        echo esc_html(implode(' | ', $extParts));
        echo '</td></tr>';
        echo '</tbody></table>';
    }

    private static function render_runtime_section(array $snapshot) {
        echo '<h2>Runtime / SOAP</h2>';
        $soap = $snapshot['soap'];
        echo '<p><strong>SOAP endpoint:</strong> '.esc_html($snapshot['site']['soap_endpoint']).'</p>';
        echo '<p><strong>Redirect endpoint:</strong> '.esc_html($snapshot['site']['redirect_endpoint']).'</p>';
        echo '<table class="widefat striped"><tbody>';
        foreach ($soap['config'] as $key => $value) {
            echo '<tr><td>'.esc_html($key).'</td><td>'.esc_html(is_bool($value) ? ($value ? 'DA' : 'NU') : $value).'</td></tr>';
        }
        echo '</tbody></table>';

        if (!empty($soap['persisted_samples'])) {
            echo '<h3>Fișiere SOAP recente</h3><table class="widefat striped"><thead><tr><th>Fișier</th><th>Dimensiune</th><th>Modificat</th></tr></thead><tbody>';
            foreach ($soap['persisted_samples'] as $file) {
                echo '<tr><td>'.esc_html($file['name']).'</td><td>'.esc_html(is_null($file['size']) ? '-' : $file['size'].' B').'</td><td>'.esc_html($file['modified'] ?? '-').'</td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h3>Ultimul SOAP</h3>';
        $last = $snapshot['runtime']['last_soap'] ?? [];
        if (!$last) {
            echo '<p><em>Niciun apel înregistrat.</em></p>';
        } else {
            echo '<table class="widefat striped"><tbody>';
            foreach ($last as $key => $value) {
                if ($key === 'soap_file' && $value) {
                    $display = basename($value);
                    echo '<tr><td>'.esc_html($key).'</td><td><a href="'.esc_url($value).'" target="_blank">'.esc_html($display).'</a></td></tr>';
                    continue;
                }
                echo '<tr><td>'.esc_html($key).'</td><td>'.esc_html(is_scalar($value) ? (string) $value : wp_json_encode($value)).'</td></tr>';
            }
            echo '</tbody></table>';
        }
    }

    private static function render_order_inspector(array $snapshot, string $orderQuery) {
        echo '<h2>Inspector OrderKey</h2>';
        echo '<form method="get"><input type="hidden" name="page" value="mpay-vg-diagnostics" />';
        echo '<p><label for="mpay_order_query">OrderKey / Order ID:</label> ';
        echo '<input type="text" id="mpay_order_query" name="mpay_order_query" value="'.esc_attr($orderQuery).'" style="min-width:260px" /> ';
        echo '<button class="button">Inspectează</button></p></form>';

        if (!$snapshot['order_inspection']) {
            echo '<p style="color:#666">Introduceți OrderKey-ul exact din MPay sau ID-ul intern pentru a vedea mapping-ul.</p>';
            return;
        }

        $info = $snapshot['order_inspection'];
        echo '<table class="widefat striped"><tbody>';
        foreach ($info as $key => $value) {
            if (is_array($value)) {
                echo '<tr><td>'.esc_html($key).'</td><td>'.esc_html(wp_json_encode($value, JSON_UNESCAPED_UNICODE)).'</td></tr>';
            } else {
                echo '<tr><td>'.esc_html($key).'</td><td>'.esc_html((string) $value).'</td></tr>';
            }
        }
        echo '</tbody></table>';
    }

    private static function render_events_section(array $snapshot) {
        echo '<h2>Evenimente și disponibilitate</h2>';

        $availability = $snapshot['runtime']['availability'];
        echo '<h3>Disponibilitate SOAP</h3>';
        if (!$availability) {
            echo '<p><em>Fără date.</em></p>';
        } else {
            echo '<pre>'.esc_html(wp_json_encode($availability, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</pre>';
        }

        echo '<h3>Evenimente debug</h3>';
        if (!$snapshot['runtime']['debug_events']) {
            echo '<p><em>Niciun eveniment debug memorat.</em></p>';
        } else {
            echo '<pre>'.esc_html(wp_json_encode($snapshot['runtime']['debug_events'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)).'</pre>';
        }

        echo '<h3>Log interacțiuni SOAP (DB)</h3>';
        if (!$snapshot['runtime']['db_events']) {
            echo '<p><em>Log DB dezactivat sau fără înregistrări.</em></p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Timestamp</th><th>Op</th><th>Result</th><th>Order</th><th>Invoice</th><th>Payment</th><th>Amount</th><th>IP</th></tr></thead><tbody>';
            foreach ($snapshot['runtime']['db_events'] as $event) {
                echo '<tr>';
                echo '<td>'.esc_html($event['ts']).'</td>';
                echo '<td>'.esc_html($event['op']).'</td>';
                echo '<td>'.esc_html($event['result']).'</td>';
                echo '<td>'.esc_html($event['order_id']).'</td>';
                echo '<td>'.esc_html($event['invoice_id']).'</td>';
                echo '<td>'.esc_html($event['payment_id']).'</td>';
                $amount = $event['amount'];
                $amountDisplay = is_null($amount) ? '-' : number_format((float)$amount, 2).' '.$event['currency'];
                echo '<td>'.esc_html($amountDisplay).'</td>';
                echo '<td>'.esc_html($event['ip']).'</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }

    private static function render_test_playbook_section(array $snapshot) {
        $scenarios = TestPlaybook::scenarios($snapshot);
        if (!$scenarios) {
            return;
        }

        static $stylesPrinted = false;
        if (!$stylesPrinted) {
            echo '<style>
.mpay-playbook-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin:20px 0}
.mpay-play-card{border:1px solid #ccd0d4;border-radius:8px;padding:16px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.mpay-play-card pre{background:#1e1e1e;color:#e2e8f0;padding:10px;border-radius:6px;font-size:12px;white-space:pre-wrap}
.mpay-play-card ul{margin:0 0 15px 18px}
.mpay-play-card h4{margin-top:1em;margin-bottom:.4em}
.mpay-play-card p{margin-top:0;margin-bottom:.6em}
.mpay-play-card .kv{list-style:none;margin:.4rem 0 0;padding:0;border-top:1px solid #e2e8f0}
.mpay-play-card .kv li{display:flex;justify-content:space-between;font-size:.9em;padding:.25rem 0;border-bottom:1px dotted #d1d5db}
.mpay-play-card .kv span{color:#475569}
</style>';
            $stylesPrinted = true;
        }

        $publicUrl = function_exists('home_url') ? home_url('/mpay/playbook') : '';
        echo '<h2>Playbook testare MPay (server MPay)</h2>';
        echo '<p>Scenariile enumerate mai jos reprezintă playbook-ul oficial MPay: comenzi exacte și criterii de validare pentru serverul lor.</p>';
        if ($publicUrl) {
            echo '<p><strong>URL public pentru MPay:</strong> <a target="_blank" rel="noopener" href="'.esc_url($publicUrl).'">'.esc_html($publicUrl).'</a></p>';
        }
        echo '<div class="mpay-playbook-grid">';
        foreach ($scenarios as $scenario) {
            echo '<div class="mpay-play-card">';
            echo '<h3>'.esc_html($scenario['title']).'</h3>';
            if (!empty($scenario['objective'])) {
                echo '<p>'.esc_html($scenario['objective']).'</p>';
            }
            if (!empty($scenario['commands'])) {
                echo '<h4>Comenzi</h4>';
                foreach ($scenario['commands'] as $command) {
                    echo '<pre>'.esc_html($command).'</pre>';
                }
            }
            if (!empty($scenario['validations'])) {
                echo '<h4>Validări așteptate</h4><ul>';
                foreach ($scenario['validations'] as $validation) {
                    echo '<li>'.esc_html($validation).'</li>';
                }
                echo '</ul>';
            }
            if (!empty($scenario['simulations'])) {
                echo '<h4>Simulări / variații</h4><ul>';
                foreach ($scenario['simulations'] as $simulation) {
                    echo '<li>'.esc_html($simulation).'</li>';
                }
                echo '</ul>';
            }
            if (!empty($scenario['notes'])) {
                echo '<h4>Note</h4><ul>';
                foreach ($scenario['notes'] as $note) {
                    echo '<li>'.esc_html($note).'</li>';
                }
                echo '</ul>';
            }
            if (!empty($scenario['reference'])) {
                echo '<h4>Date concrete</h4><ul class="kv">';
                foreach ($scenario['reference'] as $label => $value) {
                    echo '<li><span>'.esc_html($label).'</span><strong>'.esc_html($value).'</strong></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}
