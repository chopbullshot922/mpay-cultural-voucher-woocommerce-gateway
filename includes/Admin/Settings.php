<?php
namespace MPAY_VG\Admin;
use MPAY_VG\Core\Logger;
use function __;
use function add_action;
use function add_menu_page;
use function add_query_arg;
use function add_settings_error;
use function add_submenu_page;
use function admin_url;
use function checked;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_textarea;
use function esc_url;
use function esc_url_raw;
use function home_url;
use function is_wp_error;
use function register_setting;
use function sanitize_key;
use function sanitize_text_field;
use function sanitize_textarea_field;
use function selected;
use function settings_errors;
use function settings_fields;
use function submit_button;
use function wp_die;
use function wp_normalize_path;
use function wp_nonce_field;
use function wp_parse_url;
use function wp_safe_redirect;
use function wp_unslash;
if (!defined('ABSPATH')) { exit; }

class Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__,'menu']);
        add_action('admin_init', [__CLASS__,'register']);
        add_action('admin_post_mpay_vg_test_key', [__CLASS__,'handle_test_key']);
        add_action('admin_post_mpay_vg_clear_log', [__CLASS__,'handle_clear_log']);
        add_action('admin_post_mpay_vg_report', [__CLASS__,'handle_report']);
    add_action('admin_post_mpay_vg_clear_debug', [__CLASS__,'handle_clear_debug']);
    add_action('admin_post_mpay_vg_export_debug', [__CLASS__,'handle_export_debug']);
    }
    public static function menu() {
        add_menu_page('MPay Gateway','MPay Gateway','manage_woocommerce','mpay-vg',[__CLASS__,'render_page'],'dashicons-tickets-alt',56);
        add_submenu_page('mpay-vg', 'Diagnostic', 'Diagnostic', 'manage_woocommerce', 'mpay-vg-diagnostics', ['MPAY_VG\\Admin\\Diagnostics','render_page']);
        add_submenu_page('mpay-vg', 'Statistici', 'Statistici', 'manage_woocommerce', 'mpay-vg-stats', ['MPAY_VG\\Admin\\Stats','render_page']);
    }
    public static function tabs() {
        $tabs = [
            'general'=>'General',
            'bank'=>'Cont bancar',
            'rules'=>'Reguli plată',
            'security'=>'Securitate (WS-Security)',
            'invoices'=>'Note/PDF (8443)',
            'woo'=>'Woo Conditions',
            'logs'=>'Log & Health',
            'about'=>'Despre',
        ];
        $current = $_GET['tab'] ?? 'general';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $id=>$label) {
            $url = admin_url('admin.php?page=mpay-vg&tab='.$id);
            $cls = ($current===$id)?' nav-tab nav-tab-active':' nav-tab';
            echo '<a class="'.$cls.'" href="'.$url.'">'.esc_html($label).'</a>';
        }
        echo '</h2>';
        return $current;
    }
    public static function register() {
        register_setting('mpay_vg_group', 'mpay_vg_settings', ['sanitize_callback' => [__CLASS__,'sanitize']]);
    }
    public static function render_page() {
        if (!current_user_can('manage_woocommerce')) wp_die('Permisiune insuficientă.');
        $tab = self::tabs();
        echo '<div class="wrap"><h1>MPay Gateway</h1>';
        echo '<style>.mpay-vg-info{position:relative;cursor:help;color:#2271b1;display:inline-block;margin-left:4px}.mpay-vg-tooltip{display:none;position:absolute;left:1.4em;top:-.3em;background:#1d2327;color:#fff;padding:6px 8px;border-radius:4px;max-width:360px;z-index:99;white-space:normal;font-weight:400;font-size:12px;line-height:1.3}.mpay-vg-info:hover .mpay-vg-tooltip,.mpay-vg-info:focus .mpay-vg-tooltip{display:block}</style>';
        echo '<p style="margin-top:.5rem;color:#444">În producție <strong>trebuie</strong> să validez semnăturile WS-Security primite de la MPay și să semnez răspunsurile mele cu certificatul calificat. În test pot lăsa validarea dezactivată, dar înainte de go-live o activez (tabul Securitate).</p>';
        settings_errors('mpay_vg_settings');
        echo '<form method="post" action="options.php" enctype="multipart/form-data">';
    settings_fields('mpay_vg_group');
    echo '<input type="hidden" name="mpay_vg_current_tab" value="'.esc_attr($tab).'">';
        $opts = \mpay_vg_get_settings();
        echo '<table class="form-table"><tbody>';
        switch ($tab) {
            case 'general': self::section_general($opts); break;
            case 'bank': self::section_bank($opts); break;
            case 'rules': self::section_rules($opts); break;
            case 'security': self::section_security($opts); break;
            case 'invoices': self::section_invoices($opts); break;
            case 'woo': self::section_woo($opts); break;
            case 'logs': self::section_logs($opts); break;
            case 'about': self::section_about(); break;
        }
        echo '</tbody></table>';
        submit_button('Salvează setările');
        echo '</form>';
    echo '<script>(function(){var wrap=document.querySelector(".wrap"),form=wrap?wrap.querySelector("form"):null,nav=document.querySelector(".nav-tab-wrapper");if(!form||!nav)return;var dirty=false;function sd(){dirty=true;}form.addEventListener("change",sd,true);form.addEventListener("input",sd,true);nav.addEventListener("click",function(e){var a=e.target.closest("a");if(!a)return;if(!dirty)return;e.preventDefault();var href=a.getAttribute("href")||a.href;var ref=form.querySelector("input[name=_wp_http_referer]");if(ref&&href){ref.value=href;}var tabField=form.querySelector("input[name=mpay_vg_current_tab]");if(tabField&&href){var match=href.match(/tab=([^&#]+)/);if(match){tabField.value=match[1];}}form.submit();});})();</script>';
        self::render_aux_forms();
        echo '</div>';
    }

    private static function field($key, $label, $html) {
        echo '<tr><th scope="row"><label for="'.$key.'">'.$label.'</label></th><td>'.$html.'</td></tr>';
    }

    private static function label($text, $info = '') {
        $h = esc_html($text);
        if ($info === '') return $h;
        $tooltip = '<span class="mpay-vg-info dashicons dashicons-info" tabindex="0" aria-label="'.esc_attr($info).'">'
                 . '<span class="mpay-vg-tooltip">'.esc_html($info).'</span>'
                 . '</span>';
        return $h.' '.$tooltip;
    }

    private static function section_general($o) {
        $profiles = \mpay_vg_profile_options();
        $select = '<select name="mpay_vg_settings[config_profile]" id="mpay_vg_settings_config_profile">';
        foreach ($profiles as $id => $label) {
            $select .= '<option value="'.esc_attr($id).'" '.selected(($o['config_profile'] ?? 'custom'), $id, false).'>'.esc_html($label).'</option>';
        }
        $select .= '</select>';
        self::field('config_profile', self::label('Profil configurație','Selectează un set predefinit de valori pentru test/producție. Poți ajusta ulterior.'), $select);
        self::field('mode_prod', self::label('Mod producție','Comută mediul de lucru. În producție se folosesc endpointurile live, în test cele de sandbox.'), '<label><input type="checkbox" name="mpay_vg_settings[mode_prod]" value="1" '.checked(!empty($o['mode_prod']),1,false).'> activez producția</label> <code>'.(!empty($o['mode_prod'])?'PROD':'TEST').'</code>');
        self::field('service_id', self::label('ServiceID','Identificatorul serviciului MPay al prestatorului (ex. YOUR_SERVICE_ID).'), '<input type="text" class="regular-text" name="mpay_vg_settings[service_id]" value="'.esc_attr($o['service_id'] ?? '').'">');
        self::field('title', self::label('Titlu gateway (checkout)','Textul afișat ca nume al metodei în pagina de checkout.'), '<input type="text" class="regular-text" name="mpay_vg_settings[gateway_title]" value="'.esc_attr($o['gateway_title'] ?? 'Achitare cu voucher cultural').'">');
        self::field('desc', self::label('Descriere gateway','Descriere scurtă afișată sub titlul metodei în checkout.'), '<textarea class="large-text" rows="3" name="mpay_vg_settings[gateway_desc]">'.esc_textarea($o['gateway_desc'] ?? 'Veți fi redirecționat către MPay pentru finalizarea plății (inclusiv Voucher Cultural).').'</textarea>');
        self::field('reason_template', self::label('Reason (template)','Descrierea plății transmisă în MPay. Folosește %d pentru a insera ID-ul comenzii.'), '<input type="text" class="regular-text" name="mpay_vg_settings[reason_template]" value="'.esc_attr($o['reason_template'] ?? 'Comandă #%d').'">');
        self::field('lines_strategy', self::label('Strategie linii','Modul de agregare a pozițiilor: o singură linie cu totalul sau câte o linie pentru fiecare produs.'), '<select name="mpay_vg_settings[lines_strategy]"><option value="single" '.selected(($o['lines_strategy']??'single'),'single',false).'>O linie</option><option value="per_item" '.selected(($o['lines_strategy']??'single'),'per_item',false).'>Pe produs</option></select>');
        self::field('return_url_override', self::label('ReturnUrl override (opțional)','URL personalizat la care este redirecționat plătitorul după finalizarea plății.'), '<input type="url" class="regular-text" name="mpay_vg_settings[return_url_override]" value="'.esc_attr($o['return_url_override'] ?? '').'">');
        self::field('debug_log', self::label('Log debug','Scrie evenimente în WooCommerce → Status → Logs pentru depanare.'), '<label><input type="checkbox" name="mpay_vg_settings[debug_log]" value="1" '.checked(!empty($o['debug_log']),1,false).'></label>');
    }

    private static function section_bank($o) {
        $bankDisplay = [
            'bank_code' => $o['bank_code'] ?? '',
            'bank_fiscal_code' => $o['bank_fiscal_code'] ?? '',
            'bank_account' => $o['bank_account'] ?? '',
            'beneficiary' => $o['beneficiary'] ?? '',
        ];
        if (empty($o['mode_prod']) && !empty($o['autofill_test_bank'])) {
            $defaults = [
                'bank_code' => 'TREZMD2X',
                'bank_fiscal_code' => '1000000000000',
                'bank_account' => 'MD00TREZ0000000000000000',
                'beneficiary' => 'Prestator Test',
            ];
            foreach ($bankDisplay as $key => $value) {
                if ($value === '' || $value === null) {
                    $bankDisplay[$key] = $defaults[$key];
                }
            }
        }
        // Test helper: completează automat cont bancar dacă lipsește (doar în TEST)
        self::field('autofill_test_bank', self::label('Autofill cont bancar (TEST)','Dacă este bifat și rulezi în TEST, completează automat contul bancar de test când câmpurile lipsesc.'), '<label><input type="checkbox" name="mpay_vg_settings[autofill_test_bank]" value="1" '.checked(!empty($o['autofill_test_bank']),1,false).'></label>');
        self::field('bank_code', self::label('BankCode','Codul băncii/trezoreriei (ex. TREZMD2X).'), '<input type="text" class="regular-text" name="mpay_vg_settings[bank_code]" value="'.esc_attr($bankDisplay['bank_code']).'">');
        self::field('bank_fiscal_code', self::label('BankFiscalCode (IDNO/CF)','Codul fiscal al prestatorului (IDNO) sau codul fiscal al beneficiarului.'), '<input type="text" class="regular-text" name="mpay_vg_settings[bank_fiscal_code]" value="'.esc_attr($bankDisplay['bank_fiscal_code']).'">');
        self::field('bank_account', self::label('BankAccount (IBAN/trezorerie)','Contul IBAN sau contul de trezorerie în care se decontează plățile.'), '<input type="text" class="regular-text" name="mpay_vg_settings[bank_account]" value="'.esc_attr($bankDisplay['bank_account']).'">');
        self::field('beneficiary', self::label('BeneficiaryName','Denumirea beneficiarului plății (prestatorul).'), '<input type="text" class="regular-text" name="mpay_vg_settings[beneficiary]" value="'.esc_attr($bankDisplay['beneficiary']).'">');
        self::field('treasury_account', self::label('TreasuryAccount (opțional)','Pentru prestatori publici, contul trezorerial (dacă este necesar de MPay).'), '<input type="text" class="regular-text" name="mpay_vg_settings[treasury_account]" value="'.esc_attr($o['treasury_account'] ?? '').'">');
        self::field('treasury_account_name', self::label('TreasuryAccountName (opțional)','Denumirea contului trezorerial, conform cerințelor MPay.'), '<input type="text" class="regular-text" name="mpay_vg_settings[treasury_account_name]" value="'.esc_attr($o['treasury_account_name'] ?? '').'">');
        if (empty($o['mode_prod']) && (empty($o['bank_code']) || empty($o['bank_fiscal_code']) || empty($o['bank_account']) || empty($o['beneficiary']))) {
            echo '<tr><th></th><td><em>În modul TEST, gateway-ul poate apărea la checkout chiar și fără completarea acestor câmpuri. În producție sunt obligatorii.</em></td></tr>';
        }
    }

    private static function section_rules($o) {
        self::field('allow_partial', self::label('Permite plată parțială','Permite achitarea parțială a sumei comenzii în MPay.'), '<label><input type="checkbox" name="mpay_vg_settings[allow_partial]" value="1" '.checked(!empty($o['allow_partial']),1,false).'></label>');
        self::field('allow_advance', self::label('Permite plată în avans','Permite achitarea în avans a sumei (acolo unde are sens).'), '<label><input type="checkbox" name="mpay_vg_settings[allow_advance]" value="1" '.checked(!empty($o['allow_advance']),1,false).'></label>');
        echo '<tr><th></th><td><em>La mai multe linii, doar prima linie păstrează aceste proprietăți (restul FALSE), conform MPay.</em></td></tr>';
    }

    private static function cert_info($path) {
        if (!$path || !file_exists($path)) return '<em>-</em>';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['pfx','p12'], true)) {
            $finger = function_exists('sha1_file') ? strtoupper(sha1_file($path)) : '';
            return '<code>'.esc_html($path).'</code><br><small>Format: PKCS#12 (.'.$ext.')<br>SHA1: '.$finger.'</small>';
        }
        $pemRaw = @file_get_contents($path);
        if ($pemRaw===false) return '<code>'.esc_html($path).'</code>';
        $pem = \mpay_vg_normalize_certificate($pemRaw);
        $info = @openssl_x509_parse($pem);
        $finger = function_exists('sha1_file') ? strtoupper(sha1_file($path)) : '';
        if (!$info) return '<code>'.esc_html($path).'</code><br><small>SHA1: '.$finger.'</small>';
        $subject = isset($info['subject']) ? implode(', ', array_map(function($k,$v){ return $k.'='.$v; }, array_keys($info['subject']), $info['subject'])) : '';
        $issuer  = isset($info['issuer'])  ? implode(', ', array_map(function($k,$v){ return $k.'='.$v; }, array_keys($info['issuer']),  $info['issuer']))  : '';
        $validFrom = isset($info['validFrom_time_t']) ? date('Y-m-d H:i:s', $info['validFrom_time_t']) : '';
        $validTo   = isset($info['validTo_time_t']) ? date('Y-m-d H:i:s', $info['validTo_time_t']) : '';
        return '<code>'.esc_html($path).'</code><br><small>Subject: '.esc_html($subject).'<br>Issuer: '.esc_html($issuer).'<br>Valid: '.esc_html($validFrom).' → '.esc_html($validTo).'<br>SHA1: '.$finger.'</small>';
    }

    private static function section_security($o) {
        self::render_cert_upload_field(
            'sp_public_cert',
            self::label('Certificat public Prestator (.cer/.pem)','Certificatul X.509 public al prestatorului; poate fi inclus și în PFX, dar este util pentru KeyInfo.'),
            $o,
            'Dacă ai doar pachetul .pfx, extrage certificatul public cu „openssl pkcs12 -in fisier.pfx -nokeys -out prestator.cer”.'
        );

        self::render_cert_upload_field(
            'sp_private_key',
            self::label('Cheie privată Prestator (.pfx/.pem)','Cheia privată a prestatorului pentru semnarea răspunsurilor SOAP (PKCS#12 sau PEM).'),
            $o,
            'Acceptă .pfx/.p12 (cu parola de mai jos) sau o cheie PEM exportată din acesta.'
        );

        self::render_cert_upload_field(
            'mpay_public_cert',
            self::label('Certificat public MPay (.cer/.pem)','Certificatul public al MPay folosit pentru a valida semnătura mesajelor primite.'),
            $o,
            'Certificatele de test sunt incluse în plugin; încarcă aici dacă primești o versiune nouă de la MPay.'
        );

        echo '<tr><th>Certificate test integrate</th><td><p style=”margin:0”>Profilurile de test folosesc implicit fișierele din directorul <code>Certificatele MPay de test/</code>. Un upload le suprascrie doar pentru acest site.</p></td></tr>';

        self::field('sp_key_passphrase', self::label('Parolă cheie privată','Parola pachetului PKCS#12 (.pfx/.p12) sau a cheii private PEM.'), '<input type="password" class="regular-text" name="mpay_vg_settings[sp_key_passphrase]" value="'.esc_attr($o['sp_key_passphrase'] ?? '').'" autocomplete="new-password">');

        echo '<tr><th>Test cheie privată</th><td>';
        echo '<button type="submit" class="button" form="mpay-vg-test-key-form">'.esc_html__('Rulează test', 'mpay-voucher-gateway').'</button>';
        echo '<p class="description">Rulează o verificare rapidă a parolei și a structurii PKCS#12.</p>';
        $keyTest = \mpay_vg_get_runtime('key_test');
        if ($keyTest) {
            $ok = !empty($keyTest['ok']);
            $msg = $keyTest['message'] ?? '';
            $src = $keyTest['source'] ?? '';
            if ($ok) {
                echo '<p style="margin-top:6px"><span class="dashicons dashicons-yes" style="color:#0a0"></span> '.esc_html__('Cheia privată: OK', 'mpay-voucher-gateway');
                if ($msg) {
                    echo ' – '.esc_html($msg);
                }
                if ($src) {
                    $labels = ['php'=>'OpenSSL PHP','cli'=>'OpenSSL CLI','pem'=>'PEM'];
                    $label = $labels[$src] ?? strtoupper($src);
                    echo '<br><small>'.esc_html(sprintf(__('Verifier: %s', 'mpay-voucher-gateway'), $label)).'</small>';
                }
                echo '</p>';
            } else {
                echo '<p style="margin-top:6px"><span class="dashicons dashicons-warning" style="color:#a00"></span> '.esc_html__('Cheia privată nu a putut fi deschisă.', 'mpay-voucher-gateway');
                if ($msg) {
                    echo ' '.esc_html($msg);
                }
                if ($src) {
                    $labels = ['php'=>'OpenSSL PHP','cli'=>'OpenSSL CLI','pem'=>'PEM'];
                    $label = $labels[$src] ?? strtoupper($src);
                    echo '<br><small>'.esc_html(sprintf(__('Verifier: %s', 'mpay-voucher-gateway'), $label)).'</small>';
                }
                echo '</p>';
            }
        } elseif (isset($_GET['key_test'])) {
            if ($_GET['key_test'] === 'ok') {
                echo '<p style="margin-top:6px"><span class="dashicons dashicons-yes" style="color:#0a0"></span> '.esc_html__('Cheia privată: OK', 'mpay-voucher-gateway').'</p>';
            } else {
                echo '<p style="margin-top:6px"><span class="dashicons dashicons-warning" style="color:#a00"></span> '.esc_html__('Cheia privată nu a putut fi deschisă. Verifică parola/formatul.', 'mpay-voucher-gateway').'</p>';
            }
        }
        echo '</td></tr>';

        self::field('enforce_wssec', self::label('Aplic WS-Security (producție)','Verific semnătura digitală a mesajelor primite și semnez răspunsurile cu certificatul prestatorului.'), '<label><input type="checkbox" name="mpay_vg_settings[enforce_wssec]" value="1" '.checked(!empty($o['enforce_wssec']),1,false).'></label>');
        $http_warning = 'Endpointurile trebuie expuse prin HTTPS. Bifează doar pe un server de test controlat (ex. test.example.com). În producție trebuie dezactivat.';
        self::field('allow_insecure_http', self::label('Permite HTTP (doar test)', $http_warning), '<label><input type="checkbox" name="mpay_vg_settings[allow_insecure_http]" value="1" '.checked(!empty($o['allow_insecure_http']),1,false).'> Accept risc (TEST)</label>');
    }

    private static function render_cert_upload_field($field, $label, $o, $note = '') {
        $path_key = $field.'_path';
        $current = $o[$path_key] ?? '';
        $input_id = 'mpay_vg_'.$field.'_upload';
        $accept = '.cer,.pem,.crt';
        if ($field === 'sp_private_key') {
            $accept .= ',.pfx,.p12';
        }

        $html  = '<input type="hidden" name="mpay_vg_settings['.$path_key.']" value="'.esc_attr($current).'">';
        $html .= '<input type="file" id="'.$input_id.'" name="mpay_vg_settings_uploads['.$field.']" accept="'.esc_attr($accept).'">';
        if ($note) {
            $html .= '<p class="description">'.esc_html($note).'</p>';
        }

        if ($current) {
            $html .= '<div style="margin:.5rem 0;padding:.5rem;background:#f6f7f7;border:1px solid #ccd0d4;border-radius:4px">'.self::cert_info($current).'</div>';
            $html .= '<label style="display:inline-block;margin-top:4px"><input type="checkbox" name="mpay_vg_settings_clear['.$field.']" value="1"> elimină fișierul salvat</label>';
        } else {
            $html .= '<div style="margin:.5rem 0"><em>Nu este încărcat niciun fișier.</em></div>';
        }

        self::field($input_id, $label, $html);
    }

    private static function render_aux_forms() {
        $action = admin_url('admin-post.php');
        echo '<form id="mpay-vg-test-key-form" method="post" action="'.esc_url($action).'" style="display:none">';
        echo '<input type="hidden" name="action" value="mpay_vg_test_key">';
        wp_nonce_field('mpay_vg_test_key');
        echo '</form>';
        echo '<form id="mpay-vg-clear-log-form" method="post" action="'.esc_url($action).'" style="display:none">';
        echo '<input type="hidden" name="action" value="mpay_vg_clear_log">';
        wp_nonce_field('mpay_vg_clear_log');
        echo '</form>';
        echo '<form id="mpay-vg-report-form" method="post" action="'.esc_url($action).'" style="display:none">';
        echo '<input type="hidden" name="action" value="mpay_vg_report">';
        wp_nonce_field('mpay_vg_report');
        echo '</form>';
        echo '<form id="mpay-vg-clear-debug-form" method="post" action="'.esc_url($action).'" style="display:none">';
        echo '<input type="hidden" name="action" value="mpay_vg_clear_debug">';
        wp_nonce_field('mpay_vg_clear_debug');
        echo '</form>';
        echo '<form id="mpay-vg-export-debug-form" method="post" action="'.esc_url($action).'" style="display:none">';
        echo '<input type="hidden" name="action" value="mpay_vg_export_debug">';
        wp_nonce_field('mpay_vg_export_debug');
        echo '</form>';
    }

    private static function section_invoices($o) {
        self::field('api_test_base', self::label('Bază API test (8443)','Endpointul de test pentru serviciile 8443 (InvoiceID, PDF).'), '<input type="url" class="regular-text" name="mpay_vg_settings[api_test_base]" value="'.esc_attr($o['api_test_base'] ?? 'https://testmpay.gov.md:8443/api').'">');
        self::field('api_prod_base', self::label('Bază API producție (8443)','Endpointul de producție pentru serviciile 8443 (InvoiceID, PDF).'), '<input type="url" class="regular-text" name="mpay_vg_settings[api_prod_base]" value="'.esc_attr($o['api_prod_base'] ?? 'https://mpay.gov.md:8443/api').'">');
        self::field('attach_invoice_pdf', self::label('Atașez PDF în email','Atașează Nota de plată ca PDF la emailurile WooCommerce (necesită whitelisting IP la MPay).'), '<label><input type="checkbox" name="mpay_vg_settings[attach_invoice_pdf]" value="1" '.checked(!empty($o['attach_invoice_pdf']),1,false).'></label>');
    }

    private static function section_woo($o) {
        self::field('min_total', self::label('Suma minimă','Ascunde metoda dacă totalul coșului este sub această sumă.'), '<input type="number" step="0.01" name="mpay_vg_settings[min_total]" value="'.esc_attr($o['min_total'] ?? '').'"> MDL');
        self::field('max_total', self::label('Suma maximă','Ascunde metoda dacă totalul coșului depășește această sumă.'), '<input type="number" step="0.01" name="mpay_vg_settings[max_total]" value="'.esc_attr($o['max_total'] ?? '').'"> MDL');
        self::field('allow_virtual', self::label('Permite coș doar virtual','Dacă există produse fizice în coș, metoda se ascunde (când este bifat).'), '<label><input type="checkbox" name="mpay_vg_settings[allow_virtual]" value="1" '.checked(!empty($o['allow_virtual']),1,false).'></label>');
        self::field('allow_guest', self::label('Permite oaspeți (fără cont)','Permite folosirea metodei și pentru clienți neautentificați.'), '<label><input type="checkbox" name="mpay_vg_settings[allow_guest]" value="1" '.checked(!empty($o['allow_guest']),1,false).'></label>');
        self::field('allowed_countries', self::label('Țări permise (MD,RO etc.)','Listă separată prin virgulă de coduri ISO (ex. MD,RO). Dacă este setată, metoda apare doar pentru țările listate.'), '<input type="text" class="regular-text" name="mpay_vg_settings[allowed_countries]" value="'.esc_attr($o['allowed_countries'] ?? '').'">');
        self::field('allowed_shipping_methods', self::label('Shipping methods permise (IDs)','Listă separată prin virgulă cu ID-urile metodelor de livrare (ex. flat_rate:1,local_pickup:2).'), '<input type="text" class="regular-text" name="mpay_vg_settings[allowed_shipping_methods]" value="'.esc_attr($o['allowed_shipping_methods'] ?? '').'">');
        self::field('require_cultural_flag', self::label('Permit doar produse “culturale”','Metoda apare doar dacă toate produsele din coș sunt marcate eligibile pentru Voucher Cultural.'), '<label><input type="checkbox" name="mpay_vg_settings[require_cultural_flag]" value="1" '.checked(!empty($o['require_cultural_flag']),1,false).'></label>');
        self::field('relax_checkout_test', self::label('Relaxează condițiile în TEST','Ignoră restricțiile min/max total, oaspeți, țări și metode de livrare când rulezi în TEST. Nu afectează producția.'), '<label><input type="checkbox" name="mpay_vg_settings[relax_checkout_test]" value="1" '.checked(!empty($o['relax_checkout_test']),1,false).'></label>');
    }

    private static function tail_log($handle='mpay-voucher-gateway', $lines=200) {
        if (!function_exists('wc_get_log_file_path')) return '<em>Woo logger indisponibil</em>';
        $path = wc_get_log_file_path($handle);
        if (!file_exists($path)) return '<em>Nu există log curent.</em>';
        $data = @file($path);
        if (!$data) return '<em>Log gol.</em>';
        $slice = array_slice($data, -1 * abs(intval($lines)));
        return '<pre style="max-height:300px;overflow:auto;background:#111;color:#eee;padding:10px;border-radius:4px">'.esc_html(implode('', $slice)).'</pre>';
    }

    private static function section_logs($o) {
        $shared = $o['debug_shared_key'] ?? '';
        $debug_base = home_url('/mpay/debug');
        $debug_note = $shared
            ? '<p class="description">Accesează diagnosticul remote: <code>'.esc_html(add_query_arg('key', rawurlencode($shared), $debug_base)).'</code></p>'
            : '<p class="description">Lasă gol pentru a dezactiva /mpay/debug. Partajează tokenul doar cu echipa MPay.</p>';
        self::field('debug_shared_key', self::label('Cheie acces debug remote','Token de acces pentru pagina publică /mpay/debug. Este necesar pentru depanare la distanță.'), '<input type="text" class="regular-text" name="mpay_vg_settings[debug_shared_key]" value="'.esc_attr($shared).'">'.$debug_note);
        // Environment summary
        $env = [
            'Plugin version' => get_option('mpay_vg_version') ?: '-',
            'WordPress' => function_exists('get_bloginfo') ? get_bloginfo('version') : '-',
            'WooCommerce' => defined('WC_VERSION') ? WC_VERSION : (class_exists('WooCommerce') ? 'unknown' : 'not installed'),
            'PHP' => PHP_VERSION,
            'Mode' => !empty($o['mode_prod']) ? 'PROD' : 'TEST',
            'Currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '-',
        ];
        echo '<tr><th>Mediu</th><td><table class="widefat striped"><tbody>';
        foreach ($env as $k=>$v) echo '<tr><td>'.esc_html($k).'</td><td>'.esc_html($v).'</td></tr>';
        echo '</tbody></table></td></tr>';

        // Endpoints + config sanity
        $svc_ok = !empty($o['service_id']);
        $bank_ok = !empty($o['bank_code']) && !empty($o['bank_fiscal_code']) && !empty($o['bank_account']) && !empty($o['beneficiary']);
        echo '<tr><th>Endpoint SOAP</th><td><code>'.esc_html(home_url('/mpay/soap')).'</code></td></tr>';
        echo '<tr><th>Pagină redirect</th><td><code>'.esc_html(home_url('/mpay/redirect?order={ID}')).'</code></td></tr>';
        echo '<tr><th>Config</th><td><ul style="margin:0">'
            .'<li>ServiceID: <strong>'.($svc_ok?'OK':'LIPSEȘTE').'</strong></li>'
            .'<li>Cont bancar: <strong>'.($bank_ok?'OK':(!empty($o['mode_prod'])?'LIPSEȘTE':'(TEST – poate lipsi)')).'</strong></li>'
            .'<li>WS-Security: <strong>'.(!empty($o['enforce_wssec'])?'Activ':'Inactiv').'</strong></li>'
            .'<li>Cert MPay: <strong>'.(!empty($o['mpay_public_cert_path'])&&file_exists($o['mpay_public_cert_path'])?'OK':'-').'</strong></li>'
            .'<li>Cheie privată Prestator: <strong>'.(!empty($o['sp_private_key_path'])&&file_exists($o['sp_private_key_path'])?'OK':'-').'</strong></li>'
            .'</ul></td></tr>';

        // Last gateway availability decision
        $trace = \mpay_vg_get_runtime('availability', []);
        echo '<tr><th>Ultima decizie gateway</th><td>';
        if ($trace && is_array($trace)) {
            echo '<ol style="margin:0;padding-left:1.2rem">';
            foreach ($trace as $t) {
                $ok = !empty($t['pass']); $name = isset($t['check'])?$t['check']:'?'; $det = isset($t['detail'])?$t['detail']:'';
                echo '<li>'.esc_html($name).': '.($ok?'<span style="color:#0a0">OK</span>':'<span style="color:#a00">FAIL</span>').($det!==''?' – <code>'.esc_html(is_string($det)?$det:wp_json_encode($det)).'</code>':'').'</li>';
            }
            echo '</ol>';
        } else {
            echo '<em>Fără date. Deschide checkout pentru a recalcula.</em>';
        }
        echo '</td></tr>';

        // Last SOAP interaction
        $lastSoap = \mpay_vg_get_runtime('last_soap', []);
        echo '<tr><th>Ultimul apel SOAP</th><td>';
        if (!empty($lastSoap)) {
            echo '<code>'.esc_html(($lastSoap['op'] ?? '-').' • '.($lastSoap['result'] ?? '-').' • IP '.($lastSoap['ip'] ?? '-').' • '.date('Y-m-d H:i:s', $lastSoap['when'] ?? time())).'</code>';
            if (!empty($lastSoap['soap_file'])) echo '<br><small>XML: '.esc_html($lastSoap['soap_file']).'</small>';
        } else {
            echo '<em>Fără date.</em>';
        }
        echo '</td></tr>';

        // Woo log tail + actions
        echo '<tr><th>Log Woo</th><td>';
        echo self::tail_log();
        $action = admin_url('admin-post.php');
    echo '<div style="margin-top:8px;display:flex;gap:8px">';
    echo '<button type="submit" class="button" form="mpay-vg-clear-log-form">Șterge log curent</button>';
    echo '<button type="submit" class="button button-primary" form="mpay-vg-report-form">Descarcă raport complet</button>';
    echo '</div>';
        echo '</td></tr>';

        $debugEvents = \mpay_vg_get_debug_events(50);
        $debugHint = '';
        if (isset($_GET['debug_cleared'])) {
            $debugHint = '<p style="margin:0;color:#156f00">Am șters evenimentele debug.</p>';
        }
        echo '<tr><th>Evenimente debug</th><td>';
        if ($debugHint) {
            echo $debugHint;
        }
        if ($debugEvents) {
            echo '<table class="widefat striped" style="margin-top:8px"><thead><tr><th>Timp</th><th>Nivel</th><th>Componentă</th><th>Cod</th><th>Mesaj</th><th>Hint</th><th>Context</th></tr></thead><tbody>';
            foreach ($debugEvents as $event) {
                $when = !empty($event['time']) ? date_i18n('Y-m-d H:i:s', $event['time']) : '-';
                $level = strtoupper($event['level'] ?? 'info');
                $component = $event['component'] ?? 'general';
                $code = $event['code'] ?? '';
                $message = $event['message'] ?? '';
                $hint = \mpay_vg_debug_event_hint($event);
                $contextHtml = self::format_context($event['context'] ?? []);
                echo '<tr>'
                    .'<td>'.esc_html($when).'</td>'
                    .'<td>'.esc_html($level).'</td>'
                    .'<td><code>'.esc_html($component).'</code></td>'
                    .'<td>'.($code ? '<code>'.esc_html($code).'</code>' : '-').'</td>'
                    .'<td>'.esc_html($message).'</td>'
                    .'<td>'.($hint ? esc_html($hint) : '-').'</td>'
                    .'<td>'.$contextHtml.'</td>'
                .'</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<em>Nu există încă evenimente debug.</em>';
        }
    echo '<div style="margin-top:8px;display:flex;gap:8px">';
    echo '<button type="submit" class="button" form="mpay-vg-clear-debug-form">Șterge evenimente debug</button>';
    echo '<button type="submit" class="button" form="mpay-vg-export-debug-form">Export JSON debug</button>';
    echo '</div>';
        echo '</td></tr>';

        // Ultimele 50 evenimente din DB
        global $wpdb; $table = $wpdb->prefix . \MPAY_VG\Core\DB::TABLE;
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 50", ARRAY_A);
        echo '<tr><th>Evenimente recente (DB)</th><td>';
        if ($rows) {
            echo '<table class="widefat striped"><thead><tr><th>ts</th><th>op</th><th>result</th><th>order</th><th>invoice</th><th>payment</th><th>amount</th><th>ip</th><th>ms</th></tr></thead><tbody>';
            foreach ($rows as $r) {
                echo '<tr>'
                    .'<td>'.esc_html($r['ts']).'</td>'
                    .'<td>'.esc_html($r['op']).'</td>'
                    .'<td>'.esc_html($r['result']).'</td>'
                    .'<td>'.esc_html($r['order_id']).'</td>'
                    .'<td>'.esc_html($r['invoice_id']).'</td>'
                    .'<td>'.esc_html($r['payment_id']).'</td>'
                    .'<td>'.esc_html($r['amount']).'</td>'
                    .'<td>'.esc_html($r['ip']).'</td>'
                    .'<td>'.esc_html($r['duration_ms']).'</td>'
                .'</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<em>Nu există evenimente înregistrate în DB.</em>';
        }
        echo '</td></tr>';

        self::field('enable_soap_guard', self::label('Activez protecții SOAP','Întărește endpointul: forțează POST + Content-Type, limitează mărimea corpului și aplică rate-limit per IP.'), '<label><input type="checkbox" name="mpay_vg_settings[enable_soap_guard]" value="1" '.checked(!empty($o['enable_soap_guard']),1,false).'></label>');
        self::field('enable_cert_monitor', self::label('Monitor expirare certificate','Verifică zilnic expirarea certificatelor și notifică la 30/15/7/1 zile.'), '<label><input type="checkbox" name="mpay_vg_settings[enable_cert_monitor]" value="1" '.checked(!empty($o['enable_cert_monitor']),1,false).'></label>');
        self::field('enable_event_log_db', self::label('Log evenimente în DB (statistici)','Stochează evenimentele cheie în baza de date pentru statistici și audit.'), '<label><input type="checkbox" name="mpay_vg_settings[enable_event_log_db]" value="1" '.checked(!empty($o['enable_event_log_db']),1,false).'></label>');
        self::field('enable_soap_persist', self::label('Stochez raw SOAP (XML) pe disc','Salvează mesajele SOAP primite în uploads/mpay-vg/soap pentru depanare (evită în producție).'), '<label><input type="checkbox" name="mpay_vg_settings[enable_soap_persist]" value="1" '.checked(!empty($o['enable_soap_persist']),1,false).'></label>');
        self::field('show_pos_shortcuts', self::label('Afișează Shortcuts POS (meta box)','Afișează butoane pentru plăți la POS MPay în ecranul comenzii.'), '<label><input type="checkbox" name="mpay_vg_settings[show_pos_shortcuts]" value="1" '.checked(!empty($o['show_pos_shortcuts']),1,false).'></label>');
    }

    private static function section_about() {
        echo '<tr><th>Dezvoltator</th><td><strong>Victor Luncașu – TerabitLab</strong> • <a href="https://terabitlab.com" target="_blank">terabitlab.com</a><br>Dezvoltat de TerabitLab.</td></tr>';
        echo '<tr><th>Licență</th><td>GPLv2 sau ulterior</td></tr>';
    }

    public static function sanitize($opts) {
        $opts = is_array($opts) ? $opts : [];
        $prev = \mpay_vg_get_settings_raw();
        if (!is_array($prev)) { $prev = []; }
        $final = $prev;

        $valid_tabs = ['general','bank','rules','security','invoices','woo','logs','about'];
        $active_tab = 'general';
        $posted_tab = isset($_POST['mpay_vg_current_tab']) ? sanitize_key($_POST['mpay_vg_current_tab']) : '';
        if ($posted_tab && in_array($posted_tab, $valid_tabs, true)) {
            $active_tab = $posted_tab;
        } else {
            $referer = isset($_POST['_wp_http_referer']) ? wp_unslash($_POST['_wp_http_referer']) : '';
            if ($referer !== '') {
                $parts = wp_parse_url($referer);
                if (!empty($parts['query'])) {
                    parse_str($parts['query'], $params);
                    if (!empty($params['tab'])) {
                        $candidate = sanitize_key($params['tab']);
                        if (in_array($candidate, $valid_tabs, true)) {
                            $active_tab = $candidate;
                        }
                    }
                }
            }
        }

        $cert_fields = [
            'mpay_public_cert' => __('certificatul public MPay', 'mpay-voucher-gateway'),
            'sp_private_key' => __('cheia privată a prestatorului', 'mpay-voucher-gateway'),
            'sp_public_cert' => __('certificatul public al prestatorului', 'mpay-voucher-gateway'),
        ];

        $clear_flags = isset($_POST['mpay_vg_settings_clear']) && is_array($_POST['mpay_vg_settings_clear']) ? $_POST['mpay_vg_settings_clear'] : [];
        foreach ($clear_flags as $field => $value) {
            if (!$value) { continue; }
            $field = sanitize_key($field);
            $key = $field.'_path';
            if (!empty($final[$key]) && file_exists($final[$key])) {
                @unlink($final[$key]);
            }
            unset($final[$key]);
        }

        if (!empty($_FILES['mpay_vg_settings_uploads']) && is_array($_FILES['mpay_vg_settings_uploads'])) {
            $files = $_FILES['mpay_vg_settings_uploads'];
            foreach ($cert_fields as $field => $label) {
                if (empty($files['name'][$field])) { continue; }
                $file = [
                    'name' => $files['name'][$field],
                    'type' => $files['type'][$field] ?? '',
                    'tmp_name' => $files['tmp_name'][$field] ?? '',
                    'error' => $files['error'][$field] ?? UPLOAD_ERR_OK,
                    'size' => $files['size'][$field] ?? 0,
                ];

                if (!empty($file['error']) && UPLOAD_ERR_OK !== $file['error']) {
                    add_settings_error('mpay_vg_settings', $field.'_upload_error', sprintf(__('Încărcarea pentru %1$s a eșuat (cod eroare %2$d).', 'mpay-voucher-gateway'), $label, $file['error']), 'error');
                    continue;
                }

                $result = \mpay_vg_upload_cert($field, $file);
                if (is_wp_error($result)) {
                    add_settings_error('mpay_vg_settings', $field.'_upload_fail', $result->get_error_message(), 'error');
                    continue;
                }

                $final[$field.'_path'] = $result['path'];
                add_settings_error('mpay_vg_settings', $field.'_upload_success', sprintf(__('Am actualizat %s.', 'mpay-voucher-gateway'), $label), 'updated');
            }
        }

        foreach (array_keys($cert_fields) as $field) {
            $key = $field.'_path';
            if (isset($final[$key]) && is_string($final[$key])) {
                $final[$key] = sanitize_text_field(wp_normalize_path($final[$key]));
            }
        }

        $profiles = \mpay_vg_profile_options();
        $profile_candidate = isset($opts['config_profile']) ? sanitize_key($opts['config_profile']) : ($prev['config_profile'] ?? 'custom');
        if (!array_key_exists($profile_candidate, $profiles)) {
            $profile_candidate = 'custom';
        }
        $defaults = \mpay_vg_profile_defaults($profile_candidate);

        $boolean_tabs = [
            'mode_prod' => 'general',
            'allow_partial' => 'rules',
            'allow_advance' => 'rules',
            'debug_log' => 'general',
            'attach_invoice_pdf' => 'invoices',
            'enforce_wssec' => 'security',
            'allow_virtual' => 'woo',
            'allow_guest' => 'woo',
            'require_cultural_flag' => 'woo',
            'enable_soap_guard' => 'logs',
            'enable_cert_monitor' => 'logs',
            'enable_event_log_db' => 'logs',
            'enable_soap_persist' => 'logs',
            'show_pos_shortcuts' => 'logs',
            'autofill_test_bank' => 'bank',
            'relax_checkout_test' => 'woo',
            'allow_insecure_http' => 'security',
        ];

        foreach ($boolean_tabs as $field => $tab) {
            if ($tab === $active_tab) {
                $final[$field] = empty($opts[$field]) ? 0 : 1;
            } else {
                if (array_key_exists($field, $prev)) {
                    $final[$field] = empty($prev[$field]) ? 0 : 1;
                } else {
                    $final[$field] = empty($defaults[$field] ?? 0) ? 0 : 1;
                }
            }
        }
        $text_handlers = [
            'config_profile' => [
                'tab' => 'general',
                'sanitize' => function($value) use ($profiles) {
                    $profile = sanitize_key($value);
                    return array_key_exists($profile, $profiles) ? $profile : 'custom';
                },
            ],
            'service_id' => ['tab' => 'general', 'sanitize' => function($value) { return sanitize_text_field($value); }],
            'gateway_title' => ['tab' => 'general', 'sanitize' => function($value) {
                $val = sanitize_text_field($value);
                return $val === '' ? 'Achitare cu voucher cultural' : $val;
            }],
            'gateway_desc' => ['tab' => 'general', 'sanitize' => function($value) { return sanitize_textarea_field($value); }],
            'reason_template' => ['tab' => 'general', 'sanitize' => function($value) {
                $val = sanitize_text_field($value);
                return $val === '' ? 'Comandă #%d' : $val;
            }],
            'lines_strategy' => ['tab' => 'general', 'sanitize' => function($value) use ($prev, $defaults) {
                $val = is_string($value) ? $value : '';
                if (!in_array($val, ['single','per_item'], true)) {
                    if (in_array($prev['lines_strategy'] ?? '', ['single','per_item'], true)) {
                        return $prev['lines_strategy'];
                    }
                    return $defaults['lines_strategy'] ?? 'single';
                }
                return $val;
            }],
            'return_url_override' => ['tab' => 'general', 'sanitize' => function($value) { return esc_url_raw($value); }],
            'debug_shared_key' => ['tab' => 'logs', 'sanitize' => function($value) {
                $val = sanitize_text_field($value);
                return $val === '' ? '' : substr($val, 0, 64);
            }],
            'api_test_base' => ['tab' => 'invoices', 'sanitize' => function($value) { return esc_url_raw($value); }],
            'api_prod_base' => ['tab' => 'invoices', 'sanitize' => function($value) { return esc_url_raw($value); }],
            'sp_key_passphrase' => ['tab' => 'security', 'sanitize' => function($value) { return (string) $value; }],
            'min_total' => ['tab' => 'woo', 'sanitize' => function($value) { $value = is_string($value) ? trim($value) : $value; return ($value === '' || $value === null) ? '' : floatval($value); }],
            'max_total' => ['tab' => 'woo', 'sanitize' => function($value) { $value = is_string($value) ? trim($value) : $value; return ($value === '' || $value === null) ? '' : floatval($value); }],
            'allowed_countries' => ['tab' => 'woo', 'sanitize' => function($value) { $value = is_string($value) ? $value : ''; return strtoupper(preg_replace('/\s+/', '', $value)); }],
            'allowed_shipping_methods' => ['tab' => 'woo', 'sanitize' => function($value) { $value = is_string($value) ? $value : ''; return preg_replace('/\s+/', '', $value); }],
            'bank_code' => ['tab' => 'bank', 'sanitize' => function($value) { return sanitize_text_field($value); }],
            'bank_fiscal_code' => ['tab' => 'bank', 'sanitize' => function($value) { return sanitize_text_field($value); }],
            'bank_account' => ['tab' => 'bank', 'sanitize' => function($value) { return sanitize_text_field($value); }],
            'beneficiary' => ['tab' => 'bank', 'sanitize' => function($value) { return sanitize_text_field($value); }],
            'treasury_account' => ['tab' => 'bank', 'sanitize' => function($value) { return sanitize_text_field($value); }],
            'treasury_account_name' => ['tab' => 'bank', 'sanitize' => function($value) { return sanitize_text_field($value); }],
        ];

        foreach ($text_handlers as $field => $meta) {
            $tab = $meta['tab'] ?? null;
            if ($tab !== null && $tab !== $active_tab) {
                if (!array_key_exists($field, $final)) {
                    if (array_key_exists($field, $prev)) {
                        $final[$field] = $prev[$field];
                    } else {
                        $final[$field] = $defaults[$field] ?? '';
                    }
                }
                continue;
            }
            if (!array_key_exists($field, $opts)) {
                if (!array_key_exists($field, $final)) {
                    $final[$field] = $defaults[$field] ?? '';
                }
                continue;
            }
            $sanitizer = $meta['sanitize'];
            $value = $opts[$field];
            $final[$field] = is_callable($sanitizer)
                ? $sanitizer($value)
                : $sanitizer($value);
        }

        if (!isset($final['config_profile'])) {
            $final['config_profile'] = 'custom';
        }
        if (!array_key_exists($final['config_profile'], $profiles)) {
            $final['config_profile'] = 'custom';
        }

        if (!in_array($final['lines_strategy'] ?? '', ['single','per_item'], true)) {
            $final['lines_strategy'] = 'single';
        }

        $final['sp_key_passphrase'] = isset($final['sp_key_passphrase']) ? (string) $final['sp_key_passphrase'] : '';

        if (!empty($final['debug_shared_key'])) {
            $final['debug_shared_key'] = substr(sanitize_text_field($final['debug_shared_key']), 0, 64);
        }

        return $final;
    }

    public static function handle_test_key() {
        if (!current_user_can('manage_woocommerce')) wp_die('Permisiune insuficientă.');
        check_admin_referer('mpay_vg_test_key');
        $o = \mpay_vg_get_settings();
        $priv = $o['sp_private_key_path'] ?? '';
        $pass = $o['sp_key_passphrase'] ?? '';
        $ok = false;
        $message = '';
        $source = '';
        if ($priv && file_exists($priv)) {
            $ext = strtolower(pathinfo($priv, PATHINFO_EXTENSION));
            if (in_array($ext, ['pfx','p12'], true)) {
                $bundle = \mpay_vg_read_pkcs12($priv, $pass ?: '');
                if (is_wp_error($bundle)) {
                    $message = $bundle->get_error_message();
                    $code = $bundle->get_error_code();
                    if (is_string($code) && strpos($code, 'openssl_cli') === 0) {
                        $source = 'cli';
                    }
                } else {
                    $source = $bundle['source'] ?? 'php';
                    $pkeyPem = $bundle['pkey'] ?? '';
                    if ($pkeyPem) {
                        $pkey = @openssl_pkey_get_private($pkeyPem, $pass ?: '');
                        if ($pkey) {
                            $ok = true;
                            $subject = '';
                            if (!empty($bundle['cert'])) {
                                $certInfo = @openssl_x509_parse(\mpay_vg_normalize_certificate($bundle['cert']));
                                if ($certInfo && !empty($certInfo['subject'])) {
                                    if (!empty($certInfo['subject']['CN'])) {
                                        $subject = 'CN='.$certInfo['subject']['CN'];
                                    } else {
                                        $subject = implode(', ', array_map(function($k,$v){ return $k.'='.$v; }, array_keys($certInfo['subject']), $certInfo['subject']));
                                    }
                                }
                            }
                            $message = $subject ? sprintf(__('Certificat: %s', 'mpay-voucher-gateway'), $subject) : __('Cheia privată a fost deschisă cu succes.', 'mpay-voucher-gateway');
                        } else {
                            $message = __('Cheia privată nu poate fi deschisă cu parola furnizată.', 'mpay-voucher-gateway');
                        }
                    } else {
                        $message = __('Bundle-ul PKCS#12 nu conține o cheie privată.', 'mpay-voucher-gateway');
                    }
                }
            } else {
                $body = @file_get_contents($priv);
                if ($body === false) {
                    $message = __('Nu pot citi fișierul cheii private.', 'mpay-voucher-gateway');
                } else {
                    $pkey = @openssl_pkey_get_private($body, $pass ?: '');
                    if ($pkey) {
                        $ok = true;
                        $message = __('Cheia privată PEM este validă.', 'mpay-voucher-gateway');
                        $source = 'pem';
                    } else {
                        $message = __('Cheia privată PEM nu poate fi deschisă cu parola dată.', 'mpay-voucher-gateway');
                    }
                }
            }
        } else {
            $message = __('Configurează mai întâi calea către cheia privată.', 'mpay-voucher-gateway');
        }
        $logContext = [
            'component' => 'security.key_test',
            'source' => $source ?: 'unknown',
            'status' => $ok ? 'success' : 'failure',
            'message' => $message,
        ];
        if ($ok) {
            Logger::log('Test cheie privată: succes', $logContext);
        } else {
            Logger::log('Test cheie privată: eșec', $logContext, 'error');
        }
        \mpay_vg_set_runtime('key_test', ['ok'=>$ok, 'message'=>$message, 'source'=>$source]);
        wp_safe_redirect(add_query_arg(['page'=>'mpay-vg','tab'=>'security','key_test'=>$ok?'ok':'fail'], admin_url('admin.php')));
        exit;
    }

    public static function handle_clear_log() {
        if (!current_user_can('manage_woocommerce')) wp_die('Permisiune insuficientă.');
        check_admin_referer('mpay_vg_clear_log');
        if (function_exists('wc_get_log_file_path')) {
            $path = wc_get_log_file_path('mpay-voucher-gateway');
            if ($path && file_exists($path)) { @unlink($path); }
        }
        wp_safe_redirect(add_query_arg(['page'=>'mpay-vg','tab'=>'logs','log_cleared'=>'1'], admin_url('admin.php')));
        exit;
    }

    public static function handle_clear_debug() {
        if (!current_user_can('manage_woocommerce')) wp_die('Permisiune insuficientă.');
        check_admin_referer('mpay_vg_clear_debug');
        \mpay_vg_clear_debug_events();
        wp_safe_redirect(add_query_arg(['page'=>'mpay-vg','tab'=>'logs','debug_cleared'=>'1'], admin_url('admin.php')));
        exit;
    }

    public static function handle_report() {
        if (!current_user_can('manage_woocommerce')) wp_die('Permisiune insuficientă.');
        check_admin_referer('mpay_vg_report');
        $o = \mpay_vg_get_settings();
        $san = $o;
        // Sanitize sensitive fields
        unset($san['sp_key_passphrase']);
        unset($san['debug_shared_key']);
        foreach (['mpay_public_cert_path','sp_private_key_path','sp_public_cert_path'] as $p) {
            if (!empty($san[$p])) { $san[$p] = basename($san[$p]); }
        }
        $env = [
            'Plugin version' => get_option('mpay_vg_version') ?: '-',
            'WordPress' => function_exists('get_bloginfo') ? get_bloginfo('version') : '-',
            'WooCommerce' => defined('WC_VERSION') ? WC_VERSION : (class_exists('WooCommerce') ? 'unknown' : 'not installed'),
            'PHP' => PHP_VERSION,
            'Mode' => !empty($o['mode_prod']) ? 'PROD' : 'TEST',
            'Currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '-',
            'SOAP endpoint' => home_url('/mpay/soap'),
            'Redirect page' => home_url('/mpay/redirect?order={ID}'),
        ];
        $trace = \mpay_vg_get_runtime('availability', []);
        $lastSoap = \mpay_vg_get_runtime('last_soap', []);
        // DB stats
        global $wpdb; $table = $wpdb->prefix . \MPAY_VG\Core\DB::TABLE;
        $recent = $wpdb->get_results("SELECT ts,op,result,order_id,invoice_id,payment_id,amount,currency,ip,duration_ms FROM $table ORDER BY id DESC LIMIT 50", ARRAY_A);
        // SOAP persisted dir
        $uploads = function_exists('wp_upload_dir') ? wp_upload_dir() : null;
        $soap_dir = $uploads ? trailingslashit($uploads['basedir']).'mpay-vg/soap' : '';
        $soap_files = [];
        if ($soap_dir && is_dir($soap_dir)) {
            $ls = @scandir($soap_dir, SCANDIR_SORT_DESCENDING);
            if (is_array($ls)) {
                foreach ($ls as $f) { if ($f==='.'||$f==='..') continue; $p = $soap_dir.'/'.$f; if (is_file($p)) { $soap_files[] = $f.' ('.filesize($p).' bytes)'; if (count($soap_files)>=10) break; } }
            }
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename=mpay-diagnostic-'.date('Ymd-His').'.txt');
        echo "== Environment ==\n";
        foreach ($env as $k=>$v) echo $k.': '.$v."\n";
        echo "\n== Settings (sanitized) ==\n";
        foreach ($san as $k=>$v) { if (is_array($v)) $v = json_encode($v); echo $k.': '.(string)$v."\n"; }
        echo "\n== Last Gateway Availability Trace ==\n";
        if ($trace) { foreach ($trace as $t){ $line = ($t['check']??'?').': '.(!empty($t['pass'])?'OK':'FAIL'); if (!empty($t['detail'])) $line .= ' – '.(is_string($t['detail'])?$t['detail']:json_encode($t['detail'])); echo $line."\n"; } } else { echo "(none)\n"; }
        echo "\n== Last SOAP ==\n";
        if ($lastSoap) { foreach ($lastSoap as $k=>$v) echo $k.': '.$v."\n"; } else { echo "(none)\n"; }
        echo "\n== Recent DB Events (last 50) ==\n";
        if ($recent) { foreach ($recent as $r){ echo sprintf('%s | %s | %s | order:%s | invoice:%s | payment:%s | amount:%s %s | ip:%s | %sms', $r['ts'],$r['op'],$r['result'],$r['order_id'],$r['invoice_id'],$r['payment_id'],$r['amount'],$r['currency'],$r['ip'],$r['duration_ms'])."\n"; } } else { echo "(none)\n"; }
        echo "\n== SOAP Files (last 10) ==\n";
        if ($soap_files) { foreach ($soap_files as $sf) echo $sf."\n"; } else { echo "(none)\n"; }
        echo "\n== Debug Events (last 100) ==\n";
        $debug = \mpay_vg_get_debug_events(100);
        if ($debug) {
            foreach ($debug as $evt) {
                $line = date_i18n('Y-m-d H:i:s', $evt['time'] ?? time())
                    .' | '.($evt['level'] ?? 'info')
                    .' | '.($evt['component'] ?? 'general')
                    .' | '.($evt['code'] ?? '')
                    .' | '.($evt['message'] ?? '');
                if (!empty($evt['hint'])) {
                    $line .= ' | hint: '.$evt['hint'];
                }
                if (!empty($evt['context'])) {
                    $line .= ' | context: '.wp_json_encode($evt['context'], JSON_UNESCAPED_UNICODE);
                }
                echo $line."\n";
            }
        } else {
            echo "(none)\n";
        }
        exit;
    }

    public static function handle_export_debug() {
        if (!current_user_can('manage_woocommerce')) wp_die('Permisiune insuficientă.');
        check_admin_referer('mpay_vg_export_debug');
        $events = \mpay_vg_get_debug_events(0);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=mpay-debug-'.date('Ymd-His').'.json');
        echo wp_json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function format_context($ctx) {
        if (empty($ctx) || !is_array($ctx)) {
            return '<em>-</em>';
        }
        $lines = [];
        foreach ($ctx as $key => $value) {
            $lines[] = '<span><strong>'.esc_html($key).'</strong>: '.esc_html((string)$value).'</span>';
        }
        return implode('<br>', $lines);
    }
}
