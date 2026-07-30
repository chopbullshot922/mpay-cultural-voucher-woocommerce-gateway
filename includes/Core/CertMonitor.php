<?php
namespace MPAY_VG\Core;
if (!defined('ABSPATH')) { exit; }

class CertMonitor {
    public static function init(){
        add_action('wp_loaded', function(){
            $o = \mpay_vg_get_settings();
            $enabled = !empty($o['enable_cert_monitor']);
            $hook = 'mpay_vg_cert_check';
            $ts = wp_next_scheduled($hook);
            if ($enabled && !$ts) { wp_schedule_event(time()+3600, 'daily', $hook); }
            if (!$enabled && $ts) { wp_unschedule_event($ts, $hook); }
        });
        add_action('mpay_vg_cert_check', [__CLASS__,'run']);
    }
    public static function run(){
        $o = \mpay_vg_get_settings();
        if (empty($o['enable_cert_monitor'])) return;
        foreach (['mpay_public_cert_path','sp_public_cert_path'] as $k){
            $p = $o[$k] ?? ''; if (!$p || !file_exists($p)) continue;
            $pemRaw = @file_get_contents($p); if ($pemRaw===false) continue;
            $pem = \mpay_vg_normalize_certificate($pemRaw);
            $info = @openssl_x509_parse($pem); if (!$info) continue;
            $days = floor(($info['validTo_time_t'] - time())/86400);
            if (in_array($days, [30,15,7,1])){
                add_action('admin_notices', function() use ($k,$days){
                    echo '<div class="notice notice-warning"><p>Certificatul <code>'.esc_html($k).'</code> expiră în '.intval($days).' zile.</p></div>';
                });
            }
        }
    }
}
