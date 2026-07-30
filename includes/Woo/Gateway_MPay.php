<?php
namespace MPAY_VG\Woo;
use WC_Payment_Gateway;
use function __;
use function add_action;
use function add_query_arg;
use function apply_filters;
use function get_post_meta;
use function home_url;
use function is_user_logged_in;
use function wc_add_notice;
use function wc_get_order;
if (!defined('ABSPATH')) { exit; }

class Gateway_MPay extends WC_Payment_Gateway {
    public function __construct() {
        $this->id = 'mpay_voucher';
        $this->icon = apply_filters('mpay_vg_icon', '');
    $this->method_title = __('Achitare cu voucher cultural', 'mpay-voucher-gateway');
    $this->method_description = __('Plată prin MPay (inclusiv Voucher Cultural). Clientul este redirecționat către MPay.', 'mpay-voucher-gateway');
        $this->has_fields = false;
        $this->supports = ['products'];

        $this->init_form_fields(); $this->init_settings();
        // Prefer Woo gateway settings (standard Woo behavior), fallback to plugin-level defaults
        $o = \mpay_vg_get_settings();
        $title = $this->get_option('title');
        if ($title === '' || $title === null) { $title = $o['gateway_title'] ?? __('Achitare cu voucher cultural', 'mpay-voucher-gateway'); }
        $desc = $this->get_option('description');
        if ($desc === '' || $desc === null) { $desc = $o['gateway_desc'] ?? __('Veți fi redirecționat către MPay.', 'mpay-voucher-gateway'); }
        $this->title = $title;
        $this->description = $desc;

        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => ['title'=>__('Activează', 'mpay-voucher-gateway'), 'type'=>'checkbox', 'label'=>__('Activează metoda de plată MPay', 'mpay-voucher-gateway'), 'default'=>'yes'],
            'title' => ['title'=>__('Titlu', 'mpay-voucher-gateway'), 'type'=>'text', 'default'=>__('Achitare cu voucher cultural', 'mpay-voucher-gateway')],
            'description' => ['title'=>__('Descriere', 'mpay-voucher-gateway'), 'type'=>'textarea', 'default'=>__('Veți fi redirecționat către MPay pentru finalizarea plății.', 'mpay-voucher-gateway')],
        ];
    }

    public function is_available() {
        if ('yes' !== $this->get_option('enabled')) return false;
        $o = \mpay_vg_get_settings();
        $is_test = empty($o['mode_prod']);
        $relax = $is_test && !empty($o['relax_checkout_test']);
        $trace = [
            ['check'=>'enabled','pass'=>true,'detail'=>'Gateway enabled'],
        ];
        if (empty($o['service_id'])) return false;
        $trace[] = ['check'=>'service_id','pass'=>!empty($o['service_id']),'detail'=>$o['service_id'] ?: ''];
        $has_bank = true; foreach (['bank_code','bank_fiscal_code','bank_account','beneficiary'] as $k) if (empty($o[$k])) { $has_bank = false; break; }
        // În modul TEST permit afişarea gateway‑ului chiar dacă lipsesc detaliile bancare,
        // pentru a valida UX/flow. În producţie sunt obligatorii.
        $trace[] = ['check'=>'bank_details_complete','pass'=>$has_bank,'detail'=>$has_bank?'complete':'incomplete'];
        if (!$has_bank && !empty($o['mode_prod'])) { \mpay_vg_set_runtime('availability',$trace); return false; }
        if (function_exists('get_woocommerce_currency') && strtoupper(get_woocommerce_currency())!=='MDL') { $trace[]=['check'=>'currency_is_mdl','pass'=>false,'detail'=>function_exists('get_woocommerce_currency')?get_woocommerce_currency():'']; \mpay_vg_set_runtime('availability',$trace); return false; }
        $trace[] = ['check'=>'currency_is_mdl','pass'=>true,'detail'=>'MDL'];

        if (function_exists('WC') && WC()->cart) {
            if (!$relax) {
                $total = (float)WC()->cart->get_total('edit');
                if ($o['min_total'] !== '' and $total < (float)$o['min_total']) { $trace[]=['check'=>'min_total','pass'=>false,'detail'=>$total.' < '.$o['min_total']]; \mpay_vg_set_runtime('availability',$trace); return false; }
                if ($o['max_total'] !== '' and $total > (float)$o['max_total']) { $trace[]=['check'=>'max_total','pass'=>false,'detail'=>$total.' > '.$o['max_total']]; \mpay_vg_set_runtime('availability',$trace); return false; }
                if (!empty($o['allow_virtual'])) {
                    $has_physical = false;
                    foreach (WC()->cart->get_cart() as $item) { if (!$item['data']->is_virtual()) { $has_physical = true; break; } }
                    if ($has_physical) { $trace[]=['check'=>'allow_virtual_only','pass'=>false,'detail'=>'cart contains physical']; \mpay_vg_set_runtime('availability',$trace); return false; }
                }
                if (!empty($o['require_cultural_flag']) && !empty($o['mode_prod'])) {
                    foreach (WC()->cart->get_cart() as $item){
                        $ok = get_post_meta($item['product_id'], '_mpay_voucher_eligible', true)==='yes';
                        if (!$ok) { $trace[]=['check'=>'all_items_cultural','pass'=>false,'detail'=>'item '.$item['product_id'].' not eligible']; \mpay_vg_set_runtime('availability',$trace); return false; }
                    }
                }
            }
        }
        if (!$relax) {
            if (empty($o['allow_guest']) && !is_user_logged_in()) { $trace[]=['check'=>'allow_guest','pass'=>false,'detail'=>'guest not allowed']; \mpay_vg_set_runtime('availability',$trace); return false; }
            if (!empty($o['allowed_countries']) && function_exists('WC')) {
                $allowed = array_map('trim', explode(',', $o['allowed_countries']));
                $country = WC()->customer ? WC()->customer->get_billing_country() : '';
                if ($country && !in_array(strtoupper($country), $allowed)) { $trace[]=['check'=>'allowed_countries','pass'=>false,'detail'=>$country.' not allowed']; \mpay_vg_set_runtime('availability',$trace); return false; }
            }
            if (!empty($o['allowed_shipping_methods']) && function_exists('WC')) {
                $allow = array_map('trim', explode(',', $o['allowed_shipping_methods']));
                $chosen = WC()->session ? WC()->session->get('chosen_shipping_methods') : null;
                if (is_array($chosen)) {
                    $ok=false; foreach ($chosen as $c) { if (in_array($c, $allow)) { $ok=true; break; } }
                    if (!$ok) { $trace[]=['check'=>'allowed_shipping_methods','pass'=>false,'detail'=>json_encode($chosen)]; \mpay_vg_set_runtime('availability',$trace); return false; }
                }
            }
        }
        $trace[]=['check'=>'result','pass'=>true,'detail'=>'available'];
        \mpay_vg_set_runtime('availability',$trace);
        return parent::is_available();
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) { wc_add_notice(__('Comanda nu a fost găsită.'), 'error'); return; }
        $limit = Checkout::get_limit_amount();
        if ($limit > 0) {
            $total = (float) $order->get_total();
            if ($total > $limit) {
                $diff = $total - $limit;
                wc_add_notice(
                    sprintf(
                        __('Pentru achitare cu voucher cultural trebuie să reduci coșul cu %1$s înainte de a continua plata deoarece maximul pentru voucher cultural este de %2$s.', 'mpay-voucher-gateway'),
                        Checkout::format_amount($diff),
                        Checkout::format_amount($limit)
                    ),
                    'error'
                );
                return ['result' => 'fail'];
            }
        }
        $redirect = add_query_arg(['order'=>$order_id], home_url('/mpay/redirect'));
        return ['result'=>'success','redirect'=>$redirect];
    }
}
