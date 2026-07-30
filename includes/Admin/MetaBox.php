<?php
namespace MPAY_VG\Admin;
use MPAY_VG\Core\OrderMapper;
if (!defined('ABSPATH')) { exit; }

class MetaBox {
    public static function init() { add_action('add_meta_boxes', [__CLASS__,'add_box']); }
    public static function add_box() { add_meta_box('mpay_meta', 'MPay', [__CLASS__,'render'], 'shop_order', 'side', 'default'); }
    public static function render($post) {
        $order_id = $post->ID;
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        $order_number = $order && method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order_id;
        $order_key = $order ? OrderMapper::ensure_order_key($order, $order_number) : '';

        $payment_id = get_post_meta($order_id, '_mpay_payment_id', true);
        $invoice_id = get_post_meta($order_id, '_mpay_invoice_id', true);
        $paid_at = get_post_meta($order_id, '_mpay_paid_at', true);
        $last_soap = \mpay_vg_get_runtime('last_soap', []);

        echo '<p><strong>Order #:</strong> '.esc_html($order_number ?: '-').'</p>';
        echo '<p><strong>OrderKey:</strong> '.esc_html($order_key ?: '-').'</p>';
        echo '<p><strong>PaymentID:</strong> '.esc_html($payment_id ?: '-').'</p>';
        echo '<p><strong>InvoiceID:</strong> '.esc_html($invoice_id ?: '-').'</p>';
        echo '<p><strong>PaidAt:</strong> '.esc_html($paid_at ?: '-').'</p>';

        if (!empty($last_soap)) {
            $label = sprintf('%s / %s', esc_html($last_soap['op'] ?? '-'), esc_html($last_soap['result'] ?? '-'));
            $time = !empty($last_soap['when']) ? esc_html(gmdate('Y-m-d H:i:s', (int) $last_soap['when'])) : '-';
            echo '<p><strong>Ultimul SOAP:</strong><br><span>'.$label.' @ '.$time.'</span></p>';
        }

        echo '<p><a class="button" href="'.esc_url(home_url('/mpay/redirect?order='.$order_id)).'" target="_blank">Trimite la MPay</a></p>';

        $o = \mpay_vg_get_settings();
        $is_prod = !empty($o['mode_prod']);
        $base = $is_prod ? 'https://mpay.gov.md' : 'https://testmpay.gov.md';
        $sid = $o['service_id'] ?? '';

        if (!empty($o['show_pos_shortcuts']) && $sid) {
            $orderKeyForUrl = $order_key ?: (string) $order_id;
            $pos_by_so = $base.'/PosTerminal/Pay/'.rawurlencode($sid).'/'.rawurlencode($orderKeyForUrl);
            echo '<p><a class="button" href="'.esc_url($pos_by_so).'" target="_blank">POS: Pay (ServiceID/OrderKey)</a></p>';
        }
        if (!empty($o['show_pos_shortcuts']) && $invoice_id) {
            $pos_by_inv = $base.'/PosTerminal/PayInvoice/'.rawurlencode($invoice_id);
            echo '<p><a class="button" href="'.esc_url($pos_by_inv).'" target="_blank">POS: Pay by InvoiceID</a></p>';
        }

        $debug_key = trim($o['debug_shared_key'] ?? '');
        if ($debug_key !== '') {
            $debug_url = add_query_arg(
                [
                    'key' => $debug_key,
                    'order' => $order_key ?: $order_number,
                ],
                home_url('/mpay/debug')
            );
            echo '<p><a class="button button-secondary" href="'.esc_url($debug_url).'" target="_blank">Diagnostice MPay</a></p>';
        }
    }
}
