<?php
namespace MPAY_VG\Core;
if (!defined('ABSPATH')) { exit; }

/**
 * Event log DB: stores SOAP interactions & outcomes for stats/audit.
 * Minimal PII: order id, invoice id, payment id, amount, currency, op, result, ip, duration.
 */
class DB {
    const TABLE = 'mpay_vg_events';
    public static function init(){ add_action('shutdown', [__CLASS__, 'maybe_install']); }
    public static function maybe_install(){
        $v = get_option('mpay_vg_db_ver');
        if ($v !== '1') { self::install(); }
    }
    public static function install(){
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ts` DATETIME NOT NULL,
            `op` VARCHAR(40) NOT NULL,
            `order_id` BIGINT UNSIGNED NULL,
            `invoice_id` VARCHAR(64) NULL,
            `payment_id` VARCHAR(64) NULL,
            `amount` DECIMAL(18,2) NULL,
            `currency` VARCHAR(8) NULL,
            `result` VARCHAR(40) NOT NULL,
            `ip` VARCHAR(64) NULL,
            `duration_ms` INT NULL,
            `soap_file` TEXT NULL,
            PRIMARY KEY (`id`),
            KEY `ts` (`ts`),
            KEY `op` (`op`),
            KEY `order_id` (`order_id`),
            KEY `payment_id` (`payment_id`)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('mpay_vg_db_ver', '1');
    }

    public static function insert_event($data){
        $opts = \mpay_vg_get_settings();
        if (empty($opts['enable_event_log_db'])) return;
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $wpdb->insert($table, [
            'ts' => current_time('mysql'),
            'op' => sanitize_text_field($data['op'] ?? ''),
            'order_id' => intval($data['order_id'] ?? 0),
            'invoice_id' => sanitize_text_field($data['invoice_id'] ?? ''),
            'payment_id' => sanitize_text_field($data['payment_id'] ?? ''),
            'amount' => isset($data['amount']) ? floatval($data['amount']) : null,
            'currency' => sanitize_text_field($data['currency'] ?? ''),
            'result' => sanitize_text_field($data['result'] ?? ''),
            'ip' => sanitize_text_field($data['ip'] ?? ''),
            'duration_ms' => intval($data['duration_ms'] ?? 0),
            'soap_file' => $data['soap_file'] ?? null,
        ]);
    }
}
