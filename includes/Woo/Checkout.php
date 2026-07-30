<?php
namespace MPAY_VG\Woo;

use WC_Order;

if (!defined('ABSPATH')) { exit; }

class Checkout {
    private const LIMIT_AMOUNT = 1000.0;

    public static function init() {
        self::wp('add_filter', 'woocommerce_checkout_fields', [__CLASS__, 'add_identity_fields']);
        self::wp('add_action', 'woocommerce_checkout_process', [__CLASS__, 'validate_selection']);
        self::wp('add_action', 'woocommerce_checkout_create_order', [__CLASS__, 'persist_identity_meta'], 20, 2);
        self::wp('add_filter', 'woocommerce_gateway_description', [__CLASS__, 'enhance_gateway_description'], 10, 2);
    }

    public static function get_limit_amount() {
        $limit = self::wp('apply_filters', 'mpay_vg_mpay_limit', self::LIMIT_AMOUNT);
        if ($limit === null) {
            $limit = self::LIMIT_AMOUNT;
        }
        return max(0, (float) $limit);
    }

    public static function format_amount($amount) {
        $amount = max(0, (float) $amount);
        $decimals = $amount === floor($amount) ? 0 : 2;
        $formatted = self::wp('number_format_i18n', $amount, $decimals);
        if ($formatted === null) {
            $formatted = number_format($amount, $decimals, '.', '');
        }
        return sprintf('%s %s', $formatted, self::get_currency_suffix());
    }

    public static function get_currency_suffix() {
        $currency = 'MDL';
        if (function_exists('get_woocommerce_currency')) {
            $raw = self::wp('get_woocommerce_currency');
            if ($raw) {
                $currency = strtoupper((string) $raw);
            }
        }
        if (in_array($currency, ['MDL', 'RON'], true)) {
            return 'lei';
        }
        return $currency ?: 'lei';
    }

    public static function add_identity_fields($fields) {
        $fields['billing']['billing_idnp'] = [
            'type' => 'text',
            'label' => self::t('IDNP (cod numeric personal de 13 cifre din buletin)'),
            'required' => false,
            'priority' => 130,
            'maxlength' => 13,
            'class' => ['form-row-wide'],
            'autocomplete' => 'off',
            'placeholder' => self::t('Exemplu: 2000000000000'),
        ];
        $fields['billing']['billing_idno'] = [
            'type' => 'text',
            'label' => self::t('IDNO (codul fiscal al companiei / 13 cifre din certificatul de înregistrare)'),
            'required' => false,
            'priority' => 140,
            'maxlength' => 13,
            'class' => ['form-row-wide'],
            'autocomplete' => 'off',
            'description' => self::t('Completează doar dacă achiți ca organizație. IDNO este tipărit pe certificatul de înregistrare și pe facturile fiscale.'),
        ];
        return $fields;
    }

    public static function validate_selection() {
        if (empty($_POST['payment_method']) || $_POST['payment_method'] !== 'mpay_voucher') {
            return;
        }

        $limit = self::get_limit_amount();
        $total = self::resolve_checkout_total();
        if ($limit > 0 && $total > $limit) {
            $diff = $total - $limit;
            self::wp('wc_add_notice',
                sprintf(
                    self::t('Pentru achitare cu voucher cultural trebuie să reduci coșul cu %1$s înainte de a continua plata deoarece maximul pentru voucher cultural este de %2$s.'),
                    self::format_amount($diff),
                    self::format_amount($limit)
                ),
                'error'
            );
        }

        $idnp = isset($_POST['billing_idnp']) ? self::normalize_numeric_id($_POST['billing_idnp']) : '';
        $_POST['billing_idnp'] = $idnp;
        if ($idnp === '') {
            self::wp('wc_add_notice', self::t('IDNP-ul plătitorului este obligatoriu pentru plata cu voucher cultural. Codul se găsește în buletinul de identitate (câmpul „IDNP”).'), 'error');
        } elseif (!preg_match('/^\d{13}$/', $idnp)) {
            self::wp('wc_add_notice', self::t('IDNP-ul trebuie să conțină exact 13 cifre, așa cum apare în buletinul de identitate.'), 'error');
        }

        $idno = isset($_POST['billing_idno']) ? self::normalize_numeric_id($_POST['billing_idno']) : '';
        $_POST['billing_idno'] = $idno;
        $company = isset($_POST['billing_company']) ? trim((string) $_POST['billing_company']) : '';
        if ($company !== '') {
            if ($idno === '') {
                self::wp('wc_add_notice', self::t('IDNO-ul (codul fiscal al companiei) este obligatoriu atunci când plătești în numele unei organizații.'), 'error');
            } elseif (!preg_match('/^\d{13}$/', $idno)) {
                self::wp('wc_add_notice', self::t('IDNO-ul trebuie să conțină exact 13 cifre așa cum apare pe certificatul de înregistrare.'), 'error');
            }
        }
    }

    public static function persist_identity_meta($order, $data) {
        if (!$order instanceof WC_Order) {
            return;
        }
        $idnp = self::extract_identity($data, 'billing_idnp');
        if ($idnp !== '') {
            $order->update_meta_data('_billing_idnp', $idnp);
        }
        $idno = self::extract_identity($data, 'billing_idno');
        if ($idno !== '') {
            $order->update_meta_data('_billing_idno', $idno);
        }
    }

    public static function enhance_gateway_description($description, $payment_id) {
        if ($payment_id !== 'mpay_voucher') {
            return $description;
        }
        $limit = self::get_limit_amount();
        if ($limit <= 0) {
            return $description;
        }
        $total = self::resolve_checkout_total();
        $diff = max(0, $total - $limit);

        $limit_message = sprintf(self::t('Limită MPay: maximum %s per tranzacție.'), self::format_amount($limit));
        if ($diff > 0) {
            $limit_message = sprintf(
                self::t('Pentru achitare cu voucher cultural trebuie să reduci coșul cu %s înainte de a continua plata deoarece maximul pentru voucher cultural este de %s.'),
                self::format_amount($diff),
                self::format_amount($limit)
            );
        }

        $idnp_message = self::t('IDNP-ul este codul numeric personal de 13 cifre din buletin (câmpul „IDNP”, prima pagină). Pentru organizații, IDNO este codul fiscal cu 13 cifre din certificatul de înregistrare.');

        $style = ' style="color:#e15d3d;"';
        $description .= sprintf('<p class="mpay-vg-limit-note"%s>%s</p>', $style, self::esc($limit_message));
        $description .= sprintf('<p class="mpay-vg-idnp-note"%s>%s</p>', $style, self::esc($idnp_message));

        return $description;
    }

    private static function resolve_checkout_total() {
        if (!empty($_POST['woocommerce_pay']) && !empty($_POST['order_id'])) {
            $abs = self::wp('absint', $_POST['order_id']);
            $order = $abs !== null ? self::wp('wc_get_order', $abs) : null;
            if ($order instanceof WC_Order) {
                return (float) $order->get_total();
            }
        }
        if (function_exists('WC') && WC()->cart) {
            return (float) WC()->cart->get_total('edit');
        }
        return 0.0;
    }

    private static function extract_identity($data, $key) {
        $raw = '';
        if (isset($data[$key])) {
            $raw = $data[$key];
        } elseif (isset($data['billing'][$key])) {
            $raw = $data['billing'][$key];
        }
        return self::normalize_numeric_id($raw);
    }

    private static function normalize_numeric_id($value) {
        $clean = $value;
        $unslashed = self::wp('wp_unslash', $clean);
        if ($unslashed !== null) {
            $clean = $unslashed;
        }
        $sanitized = self::wp('wc_clean', $clean);
        if ($sanitized !== null) {
            $clean = $sanitized;
        }
        $digits = preg_replace('/\D+/', '', (string) $clean);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) > 13) {
            return substr($digits, 0, 13);
        }
        return $digits;
    }

    private static function t($text) {
        $translated = self::wp('__', $text, 'mpay-voucher-gateway');
        return $translated === null ? $text : $translated;
    }

    private static function esc($value) {
        $escaped = self::wp('esc_html', $value);
        return $escaped === null ? $value : $escaped;
    }

    private static function wp(string $name, ...$args) {
        if (function_exists($name)) {
            return $name(...$args);
        }
        return null;
    }
}
