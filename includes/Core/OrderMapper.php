<?php
namespace MPAY_VG\Core;
if (!defined('ABSPATH')) { exit; }

class OrderMapper {
    const CUSTOMER_ID_LENGTH = 13;
    const ORDER_KEY_LENGTH = 36;
    const ORDER_REASON_LENGTH = 50;
    const CUSTOMER_NAME_LENGTH = 60;
    const PROPERTY_NAME_LENGTH = 36;
    const PROPERTY_DISPLAY_NAME_LENGTH = 36;
    const PROPERTY_VALUE_LENGTH = 255;
    const ACCOUNT_CODE_LENGTH = 36;
    const BANK_CODE_LENGTH = 20;
    const BANK_FISCAL_CODE_LENGTH = 20;
    const BANK_ACCOUNT_LENGTH = 24;
    const BENEFICIARY_NAME_LENGTH = 60;
    const TREASURY_ACCOUNT_LENGTH = 24;
    const TREASURY_NAME_LENGTH = 60;

    public static function resolve_order_id($orderKey) {
        $candidate = trim((string) $orderKey);
        if ($candidate === '') {
            return 0;
        }

        if (ctype_digit($candidate)) {
            $order_id = intval($candidate);
            if ($order_id && wc_get_order($order_id)) {
                return $order_id;
            }
        }

        if (function_exists('wc_get_order_id_by_order_key')) {
            $byKey = wc_get_order_id_by_order_key($candidate);
            if ($byKey) {
                return intval($byKey);
            }
        }

        $alternatives = [$candidate];
        $digits = preg_replace('/\D+/', '', $candidate);
        if ($digits !== '' && $digits !== $candidate) {
            $alternatives[] = $digits;
        }

        global $wpdb;
        $metaKeys = ['_mpay_order_key', '_order_number', '_order_number_formatted', '_wc_external_order_id'];
        foreach ($alternatives as $value) {
            foreach ($metaKeys as $metaKey) {
                $order_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                    $metaKey,
                    $value
                ));
                if ($order_id) {
                    $order_id = intval($order_id);
                    if ($order_id && wc_get_order($order_id)) {
                        return $order_id;
                    }
                }
            }
        }

        return 0;
    }

    public static function ensure_order_key($order, $preferred = null) {
        if (!$order instanceof \WC_Order) {
            return '';
        }
        $stored = (string) $order->get_meta('_mpay_order_key', true);
        if ($stored !== '') {
            $limited = self::limit_length($stored, self::ORDER_KEY_LENGTH);
            if ($limited !== $stored) {
                $order->update_meta_data('_mpay_order_key', $limited);
                $order->save();
            }
            return $limited;
        }
        $source = ($preferred !== null && $preferred !== '') ? (string) $preferred : (string) $order->get_id();
        $key = self::limit_length($source, self::ORDER_KEY_LENGTH);
        $order->update_meta_data('_mpay_order_key', $key);
        $order->save();
        return $key;
    }

    public static function build_order_details($order_id, $order_key = null) {
        $order = wc_get_order($order_id); if (!$order) return null;
        $opts = \mpay_vg_get_settings();
        $total = (float) $order->get_total();

        $preferredKey = ($order_key !== null && $order_key !== '')
            ? $order_key
            : (method_exists($order, 'get_order_number') ? $order->get_order_number() : $order_id);
        $orderKey = self::ensure_order_key($order, $preferredKey);

        $customerType = $order->get_billing_company() ? 'Organization' : 'Person';
        $customerName = $order->get_billing_company()
            ?: $order->get_formatted_billing_full_name()
            ?: trim($order->get_billing_first_name().' '.$order->get_billing_last_name());
        if ($customerName === '') {
            $customerName = $order->get_billing_email() ?: 'Client MPay';
        }
        $customerName = self::normalize_whitespace($customerName);
        $customerName = self::limit_length($customerName, self::CUSTOMER_NAME_LENGTH);

        $customerId = (string) $order->get_meta('_billing_idnp', true);
        if ($customerType === 'Organization' && $customerId === '') {
            $customerId = (string) $order->get_meta('_billing_idno', true);
        }
        if ($customerId === '' && $order->get_meta('_billing_fiscal_code', true)) {
            $customerId = (string) $order->get_meta('_billing_fiscal_code', true);
        }
        $customerId = self::normalize_customer_id($customerId);
        if ($customerId === '') {
            $phoneDigits = preg_replace('/\D+/', '', (string) $order->get_billing_phone());
            $customerId = self::normalize_customer_id($phoneDigits);
        }

        $issuedAt = $order->get_date_created();
        $issuedAtTs = $issuedAt ? $issuedAt->getTimestamp() : time();
        $daySeconds = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;
        $dueTs = $issuedAtTs + (14 * $daySeconds);

        $itemNames = [];
        foreach ($order->get_items() as $item) {
            $name = self::normalize_whitespace($item->get_name());
            if ($name !== '') { $itemNames[] = $name; }
        }
        $itemNames = array_values(array_unique($itemNames));
        $itemsReason = self::limit_length(implode('; ', $itemNames), self::ORDER_REASON_LENGTH);

        $reasonTemplate = $opts['reason_template'] ?? 'Comandă #%d';
        $orderNumber = method_exists($order, 'get_order_number') ? $order->get_order_number() : $order_id;
        $reasonValue = $orderNumber;
        if (!is_numeric($reasonValue) && strpos($reasonTemplate, '%d') !== false) {
            $reasonValue = $order_id;
        }
        $templateReason = strpos($reasonTemplate, '%') !== false ? sprintf($reasonTemplate, $reasonValue) : $reasonTemplate;
        $templateReason = self::normalize_whitespace($templateReason);
        $templateReason = self::limit_length($templateReason, self::ORDER_REASON_LENGTH);

        $reason = $itemsReason !== '' ? $itemsReason : $templateReason;

        $allowPartial = !empty($opts['allow_partial']);
        $allowAdvance = !empty($opts['allow_advance']);

        $details = [
            'ServiceID' => $opts['service_id'] ?? '',
            'OrderKey'  => $orderKey,
            'Reason'    => $reason,
            'Status'    => 'Active',
            'Currency'  => 'MDL',
            'TotalAmountDue' => $total,
            'AllowPartialPayments' => $allowPartial,
            'AllowAdvancePayments' => $allowAdvance,
            'IssuedAt'  => gmdate('Y-m-d\TH:i:s\Z', $issuedAtTs),
            'DueDate'   => gmdate('Y-m-d\TH:i:s\Z', $dueTs),
            'CustomerType' => $customerType,
            'CustomerID'   => $customerId,
            'CustomerName' => $customerName,
            'Properties' => [],
            'Lines' => []
        ];

        $contactProps = [];
        $phone = $order->get_billing_phone();
        if ($phone) {
            $contactProps[] = [
                'Name' => self::sanitize_property_name('Telephone'),
                'DisplayName' => self::limit_length('Nr. de telefon de contact', self::PROPERTY_DISPLAY_NAME_LENGTH),
                'Value' => self::sanitize_property_value($phone),
                'Required' => true,
                'Modifiable' => false,
                'Type' => 'number',
            ];
        }
        $email = $order->get_billing_email();
        if ($email) {
            $contactProps[] = [
                'Name' => self::sanitize_property_name('Email'),
                'DisplayName' => self::limit_length('Email', self::PROPERTY_DISPLAY_NAME_LENGTH),
                'Value' => self::sanitize_property_value($email),
                'Required' => false,
                'Modifiable' => false,
                'Type' => 'string',
            ];
        }
        if ($contactProps) {
            $details['Properties'] = $contactProps;
        }

        $strategy = $opts['lines_strategy'] ?? 'single';
        if ($strategy === 'per_item') {
            foreach ($order->get_items() as $item_id => $item) {
                $line_amount = (float) $order->get_line_total($item, true);
                $lineReason = self::normalize_whitespace($item->get_name());
                $lineReason = self::limit_length($lineReason, self::ORDER_REASON_LENGTH);
                $lineId = 'ITEM-'.$item_id;
                $lineId = self::limit_length($lineId, self::ORDER_KEY_LENGTH);
                $details['Lines'][] = [
                    'LineID' => $lineId,
                    'Reason' => $lineReason,
                    'AmountDue' => $line_amount,
                    'DestinationAccount' => self::destination_account($opts),
                    'Properties' => [],
                ];
            }
        } else {
            $lineReason = $itemsReason !== '' ? $itemsReason : $reason;
            $details['Lines'][] = [
                'LineID' => 'DEFAULT',
                'Reason' => $lineReason,
                'AmountDue' => $total,
                'DestinationAccount' => self::destination_account($opts),
                'Properties' => [],
            ];
        }

        $sum = 0.0; foreach ($details['Lines'] as $l) $sum += (float)$l['AmountDue'];
        $details['TotalAmountDue'] = round($sum, 2);
        return $details;
    }

    private static function destination_account($opts) {
        $acc = [
            'ConfigurationCode' => '',
            'BankCode'       => $opts['bank_code'] ?? '',
            'BankFiscalCode' => $opts['bank_fiscal_code'] ?? '',
            'BankAccount'    => $opts['bank_account'] ?? '',
            'BeneficiaryName'=> $opts['beneficiary'] ?? '',
            'TreasuryAccount' => $opts['treasury_account'] ?? '',
            'TreasuryAccountName' => $opts['treasury_account_name'] ?? '',
        ];
        // Autofill defaults in TEST when enabled and any field is missing
        $is_test = empty($opts['mode_prod']);
        $autofill = !empty($opts['autofill_test_bank']);
        if ($is_test && $autofill) {
            $defaults = [
                'ConfigurationCode' => '',
                'BankCode'       => 'TREZMD2X',
                'BankFiscalCode' => '1000000000000',
                'BankAccount'    => 'MD00TREZ0000000000000000',
                'BeneficiaryName'=> 'Prestator Test',
            ];
            foreach ($acc as $k => $v) {
                if (($v === '' || $v === null) && array_key_exists($k, $defaults)) { $acc[$k] = $defaults[$k]; }
            }
        }
        return self::normalize_destination_account($acc);
    }

    public static function apply_payment_confirmation($payload) {
    $order_id = self::resolve_order_id($payload['OrderKey'] ?? '');
        $order = wc_get_order($order_id);
        if (!$order) return ['ok'=>false, 'msg'=>'UnknownOrder'];

    self::ensure_order_key($order, $payload['OrderKey'] ?? null);

        $payment_id = sanitize_text_field($payload['PaymentID'] ?? '');
        $invoice_id = sanitize_text_field($payload['InvoiceID'] ?? '');
        $total      = floatval($payload['TotalAmount'] ?? 0);

        if ($payment_id) {
            $lock_key = 'mpay_conf_lock_'.$payment_id;
            if (get_transient($lock_key)) return ['ok'=>true,'msg'=>'LockedDuplicate'];
            set_transient($lock_key, '1', 10);
        }
        if ($order->get_meta('_mpay_payment_id') === $payment_id && $payment_id !== '') {
            return ['ok'=>true, 'msg'=>'AlreadyConfirmed'];
        }

        $order->update_meta_data('_mpay_payment_id', $payment_id);
        if ($invoice_id) $order->update_meta_data('_mpay_invoice_id', $invoice_id);
        if (!empty($payload['PaidAt'])) $order->update_meta_data('_mpay_paid_at', $payload['PaidAt']);

        $order_total = (float) $order->get_total();
        if ($total + 0.01 < $order_total) {
            $order->update_status('mpay-partial', 'Plată parțială MPay: '.wc_price($total));
        } else {
            $order->payment_complete($payment_id);
        }
        $order->save();

        $order->add_order_note(sprintf('MPay confirm: PaymentID=%s | InvoiceID=%s | Amount=%.2f %s',
            $payment_id ?: '-', $invoice_id ?: '-', $total, $payload['Currency'] ?? 'MDL'
        ));

        return ['ok'=>true, 'msg'=>'Confirmed'];
    }

    private static function limit_length($value, $limit) {
        $value = (string) $value;
        if ($limit <= 0) {
            return $value;
        }
        if (function_exists('mb_substr')) {
            if (mb_strlen($value) > $limit) {
                return mb_substr($value, 0, $limit);
            }
            return $value;
        }
        if (strlen($value) > $limit) {
            return substr($value, 0, $limit);
        }
        return $value;
    }

    private static function normalize_whitespace($value) {
        $value = preg_replace('/\s+/u', ' ', (string) $value);
        return trim($value);
    }

    private static function normalize_customer_id($value) {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/[^A-Z0-9]/', '', $value);
        if ($value === '') {
            return '';
        }
        if (ctype_digit($value)) {
            $len = strlen($value);
            if ($len > self::CUSTOMER_ID_LENGTH) {
                return substr($value, 0, self::CUSTOMER_ID_LENGTH);
            }
            if ($len < self::CUSTOMER_ID_LENGTH) {
                return str_pad($value, self::CUSTOMER_ID_LENGTH, '0', STR_PAD_LEFT);
            }
            return $value;
        }
        return self::limit_length($value, self::CUSTOMER_ID_LENGTH);
    }

    private static function sanitize_property_name($value) {
        $value = preg_replace('/[^A-Za-z0-9 ]/', '', (string) $value);
        return self::limit_length($value, self::PROPERTY_NAME_LENGTH);
    }

    private static function sanitize_property_value($value) {
        $value = trim((string) $value);
        return self::limit_length($value, self::PROPERTY_VALUE_LENGTH);
    }

    private static function normalize_destination_account(array $account) {
        $normalized = $account;
        $normalized['ConfigurationCode'] = self::limit_length(strtoupper(trim((string) ($account['ConfigurationCode'] ?? ''))), self::ACCOUNT_CODE_LENGTH);
        $normalized['BankCode'] = self::limit_length(strtoupper(preg_replace('/\s+/', '', (string) ($account['BankCode'] ?? ''))), self::BANK_CODE_LENGTH);
        $normalized['BankFiscalCode'] = self::limit_length(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($account['BankFiscalCode'] ?? ''))), self::BANK_FISCAL_CODE_LENGTH);
        $normalized['BankAccount'] = self::limit_length(strtoupper(preg_replace('/\s+/', '', (string) ($account['BankAccount'] ?? ''))), self::BANK_ACCOUNT_LENGTH);
        $normalized['BeneficiaryName'] = self::limit_length(self::normalize_whitespace($account['BeneficiaryName'] ?? ''), self::BENEFICIARY_NAME_LENGTH);
        $normalized['TreasuryAccount'] = self::limit_length(strtoupper(preg_replace('/\s+/', '', (string) ($account['TreasuryAccount'] ?? ''))), self::TREASURY_ACCOUNT_LENGTH);
        $normalized['TreasuryAccountName'] = self::limit_length(self::normalize_whitespace($account['TreasuryAccountName'] ?? ''), self::TREASURY_NAME_LENGTH);

        return $normalized;
    }
}
