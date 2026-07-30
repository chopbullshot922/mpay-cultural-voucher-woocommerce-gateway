<?php
/**
 * Plugin Name: MPay Voucher Cultural Gateway (WooCommerce)
 * Description: Integrare MPay (Voucher Cultural) pentru WooCommerce: redirect, SOAP WS-Security (semnare/verificare), ConfirmOrderPayment idempotent, PDF notă (8443), statistici, log DB + raw SOAP, POS shortcuts, status "MPay - Parțial plătit", condiții Woo, log viewer, eligibilitate produse, monitor expirare certificate.
 * Version: 14.3.2
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Victor Luncașu – TerabitLab
 * Author URI: https://terabitlab.com
 * License: Source-available, non-commercial
 * Text Domain: mpay-voucher-gateway
 *
 * Dezvoltat de TerabitLab – https://terabitlab.com
 *
 * RO: Implementare conform cerințelor tehnice MPay. Răspunsurile SOAP sunt semnate, iar apelurile primite sunt verificate, când este activat WS-Security și sunt prezente certificatele/cheile.
 * EN: Implementation per MPay guide. SOAP replies are signed and incoming calls verified when WS-Security is enabled and certs/keys are provided.
 */

if (!defined('ABSPATH')) { exit; }

define('MPAY_VG_PLUGIN_FILE', __FILE__);
define('MPAY_VG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MPAY_VG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload.
spl_autoload_register(function($class){
    if (strpos($class, 'MPAY_VG\\') !== 0) return;
    $rel = str_replace('MPAY_VG\\', '', $class);
    $rel = str_replace('\\', DIRECTORY_SEPARATOR, $rel);
    $path = MPAY_VG_PLUGIN_DIR . 'includes/' . $rel . '.php';
    if (file_exists($path)) require_once $path;
});
require_once MPAY_VG_PLUGIN_DIR . 'includes/functions-helpers.php';

register_activation_hook(__FILE__, function() {
    add_option('mpay_vg_version', '14.3.2');
    if (!get_option('mpay_vg_settings')) {
        add_option('mpay_vg_settings', ['config_profile' => 'custom']);
    }
    MPAY_VG\Core\DB::install();
    MPAY_VG\Core\Rewrites::add_rewrites();
    flush_rewrite_rules();
    MPAY_VG\Woo\OrderStatus::register();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

add_action('plugins_loaded', function(){
    MPAY_VG\Core\DB::init();
    MPAY_VG\Woo\OrderStatus::init();

    if (class_exists('WC_Payment_Gateway')) {
        add_filter('woocommerce_payment_gateways', function($g){ $g[]='MPAY_VG\\Woo\\Gateway_MPay'; return $g; });
        add_action('woocommerce_thankyou', ['MPAY_VG\\Woo\\Thankyou','render'], 15);
        add_filter('woocommerce_email_order_meta_fields', ['MPAY_VG\\Woo\\Emails','meta_fields'], 10, 3);
        MPAY_VG\Woo\Eligibility::init();
            MPAY_VG\Woo\Checkout::init();
    }

    if (is_admin()) {
        MPAY_VG\Admin\Settings::init();
        MPAY_VG\Admin\Notices::init();
        MPAY_VG\Admin\Diagnostics::init();
        MPAY_VG\Admin\MetaBox::init();
        MPAY_VG\Admin\Stats::init();
    }

    MPAY_VG\Core\Rewrites::init();
    MPAY_VG\Core\Invoices::init();
    MPAY_VG\Core\CertMonitor::init();

    if (defined('WP_CLI') && WP_CLI) {
        MPAY_VG\CLI\Commands::register();
    }
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links){
    $url = admin_url('admin.php?page=mpay-vg');
    array_unshift($links, '<a href="'.$url.'">'.esc_html__('Settings', 'mpay-voucher-gateway').'</a>');
    return $links;
});
add_filter('plugin_row_meta', function($links, $file){
    if (plugin_basename(__FILE__) === $file) {
        $links[] = '<span>Dezvoltat de <a href="https://terabitlab.com" target="_blank" rel="noopener">TerabitLab</a></span>';
        $links[] = '<span>Victor Luncașu - <a href="https://terabitlab.com" target="_blank" rel="noopener">TerabitLab</a></span>';    }
    return $links;
}, 10, 2);

add_action('mpay_vg_cert_check', ['MPAY_VG\\Core\\CertMonitor','run']);
