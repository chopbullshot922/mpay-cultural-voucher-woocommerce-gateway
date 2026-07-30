<?php
namespace MPAY_VG\Woo;
if (!defined('ABSPATH')) { exit; }

class Emails {
    public static function meta_fields($fields, $sent_to_admin, $order) {
        $order_number = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order->get_id();
        $order_key = \MPAY_VG\Core\OrderMapper::ensure_order_key($order, $order_number);
        if ($order_key) {
            $fields['mpay_order_key'] = [
                'label' => __('MPay OrderKey', 'mpay-voucher-gateway'),
                'value' => $order_key,
            ];
        }
        $pid = $order->get_meta('_mpay_payment_id'); if ($pid) $fields['mpay_payment_id'] = ['label'=>'MPay PaymentID','value'=>$pid];
        $iid = $order->get_meta('_mpay_invoice_id'); if ($iid) $fields['mpay_invoice_id'] = ['label'=>'MPay InvoiceID','value'=>$iid];
        $paid = $order->get_meta('_mpay_paid_at'); if ($paid) $fields['mpay_paid_at'] = ['label'=>'MPay PaidAt','value'=>$paid];
        return $fields;
    }
}
