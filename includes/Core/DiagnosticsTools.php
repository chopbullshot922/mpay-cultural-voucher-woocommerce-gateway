<?php
namespace MPAY_VG\Core;
use MPAY_VG\Core\OrderMapper;
if (!defined('ABSPATH')) { exit; }

class DiagnosticsTools {
    public static function create_test_order(array $args = []) : array {
        if (!class_exists('WC_Order')) {
            return ['success' => false, 'message' => 'WooCommerce nu este disponibil în acest site.'];
        }
        if (!function_exists('wc_create_order')) {
            return ['success' => false, 'message' => 'Funcția wc_create_order() nu poate fi utilizată în acest context.'];
        }

        $amount = isset($args['amount']) ? (float) $args['amount'] : 250.0;
        if ($amount <= 0) {
            $amount = 1.0;
        }
        $currency = strtoupper($args['currency'] ?? (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'MDL'));
        if ($currency === '') {
            $currency = 'MDL';
        }
        $reason = trim((string) ($args['reason'] ?? 'MPay Diagnostics Test'));
        if ($reason === '') {
            $reason = 'MPay Diagnostics Test';
        }
        $email = trim((string) ($args['email'] ?? 'mpay.tester+'.time().'@example.com'));
        if (!is_email($email)) {
            $email = 'mpay.tester+'.time().'@example.com';
        }

        try {
            $order = wc_create_order(['created_via' => 'mpay_diagnostics']);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Nu pot crea comanda: '.$e->getMessage()];
        }
        if (!$order instanceof \WC_Order) {
            return ['success' => false, 'message' => 'Obiectul comenzii nu a putut fi construit.'];
        }

        $order->set_currency($currency);
        $order->set_status('pending');
        $order->set_payment_method('mpay_vg');
        $order->set_payment_method_title('MPay Diagnostics');
        $order->set_billing_first_name('MPay');
        $order->set_billing_last_name('Tester');
        $order->set_billing_email($email);
        $order->set_billing_phone('+37360000000');
        $order->set_customer_note('Generată din portalul MPay Diagnostics.');

        $fee = new \WC_Order_Item_Fee();
        $fee->set_name($reason);
        $fee->set_total($amount);
        $order->add_item($fee);

        $order->calculate_totals(true);
        $order->save();

        $displayNumber = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order->get_id();
        $orderKey = OrderMapper::ensure_order_key($order, $displayNumber);
        $order->update_meta_data('_mpay_diag_generated', 1);
        $order->save();

        $redirectUrl = function_exists('home_url') ? home_url('/mpay/redirect?order='.$order->get_id()) : '';
        $soapEndpoint = function_exists('home_url') ? home_url('/mpay/soap') : '';

        return [
            'success' => true,
            'message' => 'Comandă de test creată.',
            'order' => [
                'id' => $order->get_id(),
                'order_number' => $displayNumber,
                'order_key' => $orderKey,
                'total' => (float) $order->get_total(),
                'currency' => $order->get_currency(),
                'redirect_url' => $redirectUrl,
                'soap_endpoint' => $soapEndpoint,
                'created_at' => $order->get_date_created() ? $order->get_date_created()->date(DATE_ATOM) : gmdate('c'),
            ],
        ];
    }

    public static function ensure_playbook_context(array $args = []) : array {
        $context = [
            'order' => null,
            'error' => null,
            'auto_created' => false,
        ];

        $forceNew = !empty($args['force_create']);
        if (!$forceNew) {
            $order = self::get_reference_order($args);
            if ($order) {
                $context['order'] = $order;
                return $context;
            }
        }

        $shouldCreate = $forceNew || !empty($args['auto_create']);
        if (!$shouldCreate) {
            $context['error'] = 'Nu există încă o comandă mapată pe MPay. Generați una din wp-admin ▸ MPay Diagnostics.';
            return $context;
        }

        $opts = \mpay_vg_get_settings();
        if (!empty($opts['mode_prod'])) {
            $context['error'] = 'Generarea automată a OrderKey-urilor este blocată în modul PROD.';
            return $context;
        }

        $payload = self::create_test_order([
            'amount' => isset($args['amount']) ? (float) $args['amount'] : 250.0,
            'reason' => $args['reason'] ?? 'MPay Public QA',
        ]);

        if (!empty($payload['success']) && !empty($payload['order'])) {
            $context['order'] = $payload['order'];
            $context['auto_created'] = true;
        } else {
            $context['error'] = $payload['message'] ?? 'Nu pot crea comanda de test.';
        }

        return $context;
    }

    public static function get_reference_order(array $args = []) : ?array {
        if (!class_exists('WC_Order')) {
            return null;
        }
        $orderId = isset($args['order_id']) ? (int) $args['order_id'] : 0;
        if ($orderId > 0 && function_exists('wc_get_order')) {
            $order = wc_get_order($orderId);
            if ($order) {
                return self::serialize_order_brief($order);
            }
        }
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_key' => '_mpay_diag_generated',
                'meta_value' => 1,
                'return' => 'objects',
                'status' => function_exists('wc_get_order_statuses') ? array_keys(wc_get_order_statuses()) : 'any',
            ]);
            if (is_array($orders) && !empty($orders[0]) && $orders[0] instanceof \WC_Order) {
                return self::serialize_order_brief($orders[0]);
            }
        }
        return null;
    }

    public static function ensure_playbook_order(array $args = []) : ?array {
        $context = self::ensure_playbook_context($args);
        return $context['order'];
    }

    public static function serialize_order_brief($order) : ?array {
        if (!$order instanceof \WC_Order) {
            return null;
        }
        $displayNumber = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order->get_id();
        $orderKey = OrderMapper::ensure_order_key($order, $displayNumber);
        return [
            'id' => $order->get_id(),
            'order_number' => $displayNumber,
            'order_key' => $orderKey,
            'total' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'created_at' => $order->get_date_created() ? $order->get_date_created()->date(DATE_ATOM) : null,
        ];
    }
}
