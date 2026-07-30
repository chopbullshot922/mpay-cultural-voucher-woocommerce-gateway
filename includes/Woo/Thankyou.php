<?php
namespace MPAY_VG\Woo;
if (!defined('ABSPATH')) { exit; }

class Thankyou {
    public static function render($order_id) {
        $order = wc_get_order($order_id); if (!$order) return;
        $order_number = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order->get_id();
        $order_key = \MPAY_VG\Core\OrderMapper::ensure_order_key($order, $order_number);

        if ($order_key) {
            echo '<p><strong>Cod MPay (OrderKey):</strong> '.esc_html($order_key).'</p>';
            echo '<p style="font-size:0.95em;">Păstrați acest cod. Veți avea nevoie de el dacă veți plăti sau verifica statusul plății direct în portalul MPay ori la POS &ndash; exact acest identificator este căutat de MPay conform documentației oficiale.</p>';
        }
        $pid = $order->get_meta('_mpay_payment_id'); $iid = $order->get_meta('_mpay_invoice_id'); $paid = $order->get_meta('_mpay_paid_at');
        if ($pid || $iid) {
            echo '<p><strong>MPay:</strong> ';
            if ($pid) echo 'PaymentID: '.esc_html($pid).' ';
            if ($iid) echo ' | InvoiceID: '.esc_html($iid).' ';
            if ($paid) echo ' | PaidAt: '.esc_html($paid);
            echo '</p>';
        } else {
            echo '<p><em>Plata MPay se procesează. Dacă ați plătit deja, confirmarea sosește în câteva momente.</em></p>';
        }
    }
}
