<?php
namespace MPAY_VG\Core;
if (!defined('ABSPATH')) { exit; }

class Logger {
    public static function log($msg, $context = [], $level = 'info') {
        $opts = \mpay_vg_get_settings();
        if (empty($opts['debug_log'])) return;
        $message = is_array($msg) || is_object($msg)
            ? wp_json_encode($msg, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
            : (string) $msg;
        $component = is_array($context) && isset($context['component']) ? (string) $context['component'] : 'general';
        $code = is_array($context) && isset($context['code']) ? (string) $context['code'] : '';
        $hint = is_array($context) && isset($context['hint']) ? (string) $context['hint'] : '';
        $storeContext = is_array($context) ? $context : [];
        unset($storeContext['component'], $storeContext['code'], $storeContext['hint']);
        \mpay_vg_record_debug_event([
            'level' => $level,
            'message' => $message,
            'component' => $component,
            'code' => $code,
            'hint' => $hint,
            'context' => $storeContext,
        ]);
        $logLine = $message;
        if (!empty($context)) {
            $logLine .= ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context_arr = ['source' => 'mpay-voucher-gateway'];
            if ($level === 'error') $logger->error($logLine, $context_arr);
            else $logger->info($logLine, $context_arr);
        } else {
            error_log('[MPAY] '.$logLine);
        }
    }
}
