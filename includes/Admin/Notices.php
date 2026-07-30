<?php
namespace MPAY_VG\Admin;
use function add_action;
use function current_user_can;
use function esc_html;
use function home_url;
use function wp_parse_url;
if (!defined('ABSPATH')) { exit; }

class Notices {
    public static function init() { add_action('admin_notices', [__CLASS__,'check']); }
    public static function check() {
        if (!current_user_can('manage_woocommerce')) return;
        if (isset($_GET['page']) && $_GET['page']==='mpay-vg' && isset($_GET['key_test'])) {
            if ($_GET['key_test']==='ok') echo '<div class="notice notice-success"><p>Cheia privată: <strong>OK</strong></p></div>';
            else echo '<div class="notice notice-error"><p>Cheia privată: <strong>NU a putut fi deschisă</strong>. Verifică parola/formatul.</p></div>';
        }
        $o = \mpay_vg_get_settings();
        $missing = [];
        foreach (['service_id','bank_code','bank_fiscal_code','bank_account','beneficiary'] as $k) if (empty($o[$k])) $missing[]=$k;
        if ($missing) echo '<div class="notice notice-warning"><p><strong>MPay Gateway:</strong> Lipsesc câmpuri: '.esc_html(join(', ',$missing)).'</p></div>';
        if (!\mpay_vg_store_currency_is_mdl()) echo '<div class="notice notice-warning"><p><strong>MPay Gateway:</strong> Valuta magazinului nu este MDL.</p></div>';
        if (!empty($o['allow_insecure_http'])) {
            echo '<div class="notice notice-error"><p><strong>MPay Gateway:</strong> Opțiunea „Permite HTTP (doar test)” este activă. Endpointurile trebuie publicate prin HTTPS în producție.</p></div>';
        } else {
            $scheme = wp_parse_url(home_url('/'), PHP_URL_SCHEME);
            if (!is_string($scheme) || strtolower($scheme) !== 'https') {
                echo '<div class="notice notice-warning"><p><strong>MPay Gateway:</strong> Site-ul rulează pe HTTP. Pentru conformitate MPay configurează HTTPS sau activează temporar opțiunea „Permite HTTP (doar test)” doar pe un server de sandbox.</p></div>';
            }
        }
    }
}
