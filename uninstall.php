<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
delete_option('mpay_vg_settings');
delete_option('mpay_vg_version');
delete_option('mpay_vg_db_ver');
