<?php
namespace MPAY_VG\Woo;
use MPAY_VG\Core\Logger;
if (!defined('ABSPATH')) { exit; }

class Eligibility {
  public static function init(){
    add_action('woocommerce_product_options_general_product_data', [__CLASS__,'field']);
    add_action('woocommerce_process_product_meta', [__CLASS__,'save']);
    add_filter('woocommerce_available_payment_gateways', [__CLASS__,'filter_gateway']);
  }
  public static function field(){
    echo '<div class="options_group">';
    woocommerce_wp_checkbox([
      'id'=>'_mpay_voucher_eligible',
      'label'=>'Eligibil pentru Voucher Cultural (MPay)',
      'description'=>'Dacă e bifat, produsul poate fi achitat cu voucherul cultural.',
      'desc_tip'=>true,
    ]);
    echo '</div>';
  }
  public static function save($post_id){
    update_post_meta($post_id, '_mpay_voucher_eligible', isset($_POST['_mpay_voucher_eligible']) ? 'yes' : 'no');
  }
  public static function filter_gateway($gws){
    if (!isset($gws['mpay_voucher']) || !function_exists('WC') || !WC()->cart) return $gws;
    $o = \mpay_vg_get_settings();
    if (empty($o['require_cultural_flag'])) return $gws;
    $isProd = !empty($o['mode_prod']);
  if (!$isProd) {
    if (!empty($o['relax_checkout_test'])) {
      $last = \mpay_vg_get_runtime('eligibility_relaxed');
      if (!$last || (time() - $last) > 60) {
        Logger::log('Eligibilitate relaxată în TEST.', [
          'component' => 'woo.eligibility',
          'code' => 'relaxed_test_mode',
          'cart_items' => count(WC()->cart->get_cart()),
        ]);
        \mpay_vg_set_runtime('eligibility_relaxed', time(), 300);
      }
    }
    return $gws;
  }
    foreach (WC()->cart->get_cart() as $item){
        $ok = get_post_meta($item['product_id'], '_mpay_voucher_eligible', true)==='yes';
    if (!$ok){
      Logger::log('Metoda MPay ascunsă: produs neeligibil.', [
        'component' => 'woo.eligibility',
        'code' => 'product_ineligible',
        'product_id' => $item['product_id'],
      ]);
      unset($gws['mpay_voucher']);
      break;
    }
    }
    return $gws;
  }
}
