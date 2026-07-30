<?php
namespace MPAY_VG\Core;
if (!defined('ABSPATH')) { exit; }

class Invoices {
    public static function init() {
        add_action('woocommerce_order_status_completed', [__CLASS__,'maybe_attach_invoice_pdf'], 20, 1);
        add_action('woocommerce_order_status_processing', [__CLASS__,'maybe_attach_invoice_pdf'], 20, 1);
    }
    public static function maybe_attach_invoice_pdf($order_id) {
        $opts = \mpay_vg_get_settings();
        if (empty($opts['attach_invoice_pdf'])) return;
        $order = wc_get_order($order_id); if (!$order) return;
        $service_id = $opts['service_id'] ?? ''; if (!$service_id) return;
    $order_key = \MPAY_VG\Core\OrderMapper::ensure_order_key($order);
        if (!$order_key) return;

        $invoice_id = $order->get_meta('_mpay_invoice_id');
        if (!$invoice_id) {
            $invoice_id = self::fetch_invoice_id($service_id, $order_key, $opts);
            if ($invoice_id) { $order->update_meta_data('_mpay_invoice_id', $invoice_id); $order->save(); }
        }
        if ($invoice_id) {
            $pdf = self::fetch_invoice_pdf($service_id, $order_key, $opts);
            if ($pdf) { self::attach_pdf_to_order_emails($order_id, $pdf, "Nota-de-plata-{$invoice_id}.pdf"); }
        }
    }
    private static function base_api($opts) {
        $is_prod = !empty($opts['mode_prod']);
        $base = $is_prod ? ($opts['api_prod_base'] ?? 'https://mpay.gov.md:8443/api')
                         : ($opts['api_test_base'] ?? 'https://testmpay.gov.md:8443/api');
        return rtrim($base, '/');
    }
    public static function fetch_invoice_id($service_id, $order_key, $opts) {
        $url = self::base_api($opts)."/invoices?serviceID=".rawurlencode($service_id)."&orderKey=".rawurlencode($order_key);
        $res = self::curl_get($url, ['Accept: application/json']);
        if (!$res || empty($res['body'])) return null;
        $data = json_decode($res['body'], true);
        if (is_array($data) && !empty($data)) { $row = $data[0] ?? null; if ($row && isset($row['invoiceId'])) return $row['invoiceId']; }
        return null;
    }
    public static function fetch_invoice_pdf($service_id, $order_key, $opts) {
        $url = self::base_api($opts)."/Invoices/GetPdfInvoiceBytes?serviceID=".rawurlencode($service_id)."&orderKey=".rawurlencode($order_key);
        $res = self::curl_get($url, ['Accept: application/octet-stream']);
        if (!$res || empty($res['body'])) return null;
        return $res['body'];
    }
    private static function curl_get($url, $headers = []) {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err || $code >= 400) {
            $body_excerpt = '';
            if (is_string($body) && $body !== '') {
                if (function_exists('mb_substr')) {
                    $body_excerpt = mb_substr($body, 0, 500);
                } else {
                    $body_excerpt = substr($body, 0, 500);
                }
            }
            Logger::log('Invoice API error', [
                'component' => 'invoice.api',
                'code' => $code,
                'url' => $url,
                'error' => $err,
                'body' => $body_excerpt,
            ], 'error');
            return null;
        }
        return ['code'=>$code, 'body'=>$body];
    }
    private static function attach_pdf_to_order_emails($order_id, $pdf_bytes, $filename) {
    add_filter('woocommerce_email_attachments', function($attachments, $email_id, $object, $email) use ($order_id, $pdf_bytes, $filename) {
            if (!$object instanceof \WC_Order) return $attachments;
            if (intval($object->get_id()) !== intval($order_id)) return $attachments;
            $uploads = wp_upload_dir();
            $dir = trailingslashit($uploads['basedir']).'mpay-vg';
            if (!file_exists($dir)) wp_mkdir_p($dir);
            $path = trailingslashit($dir) . sanitize_file_name($filename);
            file_put_contents($path, $pdf_bytes);
            $attachments[] = $path;
            return $attachments;
        }, 10, 4);
    }
}
