<?php
namespace MPAY_VG\Woo;
if (!defined('ABSPATH')) { exit; }

class OrderStatus {
    public static function init() { add_action('init', [__CLASS__,'register']); add_filter('wc_order_statuses', [__CLASS__,'labels']); }
    public static function register() {
        register_post_status('wc-mpay-partial', [
            'label' => 'MPay – Parțial plătit','public'=>true,'exclude_from_search'=>false,'show_in_admin_status_list'=>true,'show_in_admin_all_list'=>true,
            'label_count' => _n_noop('MPay – Parțial plătit <span class="count">(%s)</span>', 'MPay – Parțial plătit <span class="count">(%s)</span>'),
        ]);
    }
    public static function labels($statuses) {
        $new = [];
        foreach ($statuses as $k=>$v) { $new[$k] = $v; if ($k === 'wc-on-hold') $new['wc-mpay-partial'] = 'MPay – Parțial plătit'; }
        if (!isset($new['wc-mpay-partial'])) $new['wc-mpay-partial'] = 'MPay – Parțial plătit';
        return $new;
    }
}
