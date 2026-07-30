<?php
namespace MPAY_VG\Admin;
use MPAY_VG\Core\DB;
if (!defined('ABSPATH')) { exit; }

class Stats {
    public static function init(){
        add_action('admin_menu', function(){
            // submenu added in Settings::menu
        });
        add_action('admin_init', [__CLASS__,'download_csv']);
    }

    public static function render_page(){
        if (!current_user_can('manage_woocommerce')) wp_die('Permisiune insuficientă.');
        $date_from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-d', strtotime('-30 days'));
        $date_to   = isset($_GET['to']) ? sanitize_text_field($_GET['to'])   : date('Y-m-d');
        echo '<div class="wrap"><h1>MPay – Statistici</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="mpay-vg-stats">';
        echo 'Interval: <input type="date" name="from" value="'.esc_attr($date_from).'"> – <input type="date" name="to" value="'.esc_attr($date_to).'"> ';
        submit_button('Aplică', 'secondary', '', false);
        echo ' <a class="button" href="'.esc_url(add_query_arg(['download'=>'csv','from'=>$date_from,'to'=>$date_to])).'">Descarcă CSV</a>';
        echo '</form>';

        $data = self::query_stats($date_from, $date_to);
        echo '<h2>Rezumat</h2><table class="widefat striped"><tbody>';
        echo '<tr><td>Total Confirmări</td><td>'.intval($data['conf_count']).'</td></tr>';
        echo '<tr><td>Suma Confirmată</td><td>'.number_format($data['conf_sum'],2,'.',' ').' '.$data['currency'].'</td></tr>';
        echo '<tr><td>Plăți Parțiale (status note)</td><td>'.intval($data['partial_count']).'</td></tr>';
        echo '<tr><td>Eșecuri/erori</td><td>'.intval($data['errors']).'</td></tr>';
        echo '</tbody></table>';

        echo '<h2 style="margin-top:2rem">Zilnic</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Data</th><th># Confirmări</th><th>Suma</th><th># Erori</th></tr></thead><tbody>';
        foreach ($data['by_day'] as $row){
            echo '<tr><td>'.esc_html($row['day']).'</td><td>'.intval($row['cnt']).'</td><td>'.number_format($row['sum'],2,'.',' ').'</td><td>'.intval($row['err']).'</td></tr>';
        }
        echo '</tbody></table>';

        echo '</div>';
    }

    public static function query_stats($from, $to){
        global $wpdb; $table = $wpdb->prefix . DB::TABLE;
        $from_ts = $from.' 00:00:00'; $to_ts = $to.' 23:59:59';
        $conf = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) c, COALESCE(SUM(amount),0) s, MAX(currency) cur FROM $table WHERE op='ConfirmOrderPayment' AND ts BETWEEN %s AND %s AND (result='Confirmed' OR result='AlreadyConfirmed' OR result='LockedDuplicate')", $from_ts,$to_ts), ARRAY_A);
        $partial = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE op='ConfirmOrderPayment' AND ts BETWEEN %s AND %s AND result='Confirmed' AND amount IS NOT NULL", $from_ts, $to_ts));
        $err = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE ts BETWEEN %s AND %s AND (result LIKE 'Unknown%%' OR result LIKE 'AuthenticationFailed' OR result LIKE 'AuthorizationFailed' OR result='InvalidXML')", $from_ts, $to_ts));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(ts) d, SUM(CASE WHEN op='ConfirmOrderPayment' AND (result IN ('Confirmed','AlreadyConfirmed','LockedDuplicate')) THEN 1 ELSE 0 END) c, SUM(CASE WHEN op='ConfirmOrderPayment' AND (result IN ('Confirmed','AlreadyConfirmed','LockedDuplicate')) THEN COALESCE(amount,0) ELSE 0 END) s, SUM(CASE WHEN (result LIKE 'Unknown%%' OR result IN ('AuthenticationFailed','AuthorizationFailed','InvalidXML')) THEN 1 ELSE 0 END) e FROM $table WHERE ts BETWEEN %s AND %s GROUP BY DATE(ts) ORDER BY DATE(ts)", $from_ts, $to_ts), ARRAY_A);
        $by_day = [];
        foreach ($rows as $r){
            $by_day[] = ['day'=>$r['d'],'cnt'=>$r['c'],'sum'=>floatval($r['s']),'err'=>$r['e']];
        }
        return ['conf_count'=>intval($conf['c'] ?? 0),'conf_sum'=>floatval($conf['s'] ?? 0),'currency'=>$conf['cur'] ?: 'MDL','partial_count'=>intval($partial),'errors'=>intval($err),'by_day'=>$by_day];
    }

    public static function download_csv(){
        if (!is_admin() || !current_user_can('manage_woocommerce')) return;
        if (!isset($_GET['page']) || $_GET['page']!=='mpay-vg-stats' || !isset($_GET['download']) || $_GET['download']!=='csv') return;
        $from = sanitize_text_field($_GET['from'] ?? date('Y-m-d', strtotime('-30 days')));
        $to   = sanitize_text_field($_GET['to'] ?? date('Y-m-d'));
        $data = self::query_stats($from,$to);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=mpay-stats-'.$from.'-'.$to.'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Day','ConfirmedCount','ConfirmedSum','Errors']);
        foreach ($data['by_day'] as $r) fputcsv($out, [$r['day'],$r['cnt'],$r['sum'],$r['err']]);
        fclose($out); exit;
    }
}
